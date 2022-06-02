<?php

declare(strict_types=1);

namespace Hofff\Contao\LanguageRelations\DCA;

use Contao\BackendTemplate;
use Contao\DataContainer;
use Contao\StringUtil;
use Contao\System;
use Exception;
use Hofff\Contao\LanguageRelations\Util\QueryUtil;
use Hofff\Contao\Selectri\Model\Data;
use Hofff\Contao\Selectri\Model\Node;
use Hofff\Contao\Selectri\Model\Tree\SQLAdjacencyTreeDataFactory;
use ReflectionMethod;

use function array_filter;
use function array_keys;
use function call_user_func;

use const PHP_INT_MAX;

class RelationsDCABuilder
{
    private RelationsDCABuilderConfig $config;

    /** @var string[][] */
    protected array $submittedRelations;

    /** @var array<string, string> */
    private array $groupTitleCache;

    /** @var array<string, array<string>> */
    private array $rootsCache;

    private int $relatedItem;

    /** @var array<int, int> */
    private array $relatedItemOf;

    public function __construct(RelationsDCABuilderConfig $config)
    {
        $this->config             = clone $config;
        $this->submittedRelations = [];
    }

    /**
     * @param string[][] $dca
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function build(array &$dca, string $fieldName = 'hofff_language_relations'): void
    {
        System::loadLanguageFile('hofff_language_relations');

        $factory = $this->createFactory();

        $dca['config']['onsubmit_callback'][] = function ($dataContainer): void {
            $this->onsubmitCallback($dataContainer);
        };

        $dca['fields'][$fieldName] = [
            'label'                 => &$GLOBALS['TL_LANG']['hofff_language_relations']['field'],
            'exclude'               => true,
            'inputType'             => 'selectri',
            'eval'                  => [
                'doNotSaveEmpty'        => true,
                'min'                   => 0,
                'max'                   => PHP_INT_MAX,
                'sort'                  => false,
                'canonical'             => true,
                'class'                 => 'hofff-relations',
                'data'                  => $factory,
            ],
            'input_field_callback'  => function ($dataContainer) use (&$dca, $fieldName, $factory) {
                return $this->inputFieldCallback($dataContainer, $dca, $fieldName, $factory);
            },
            'load_callback'         => [
                function ($value, $dataContainer) {
                    return $this->loadRelationsCallback($value, $dataContainer);
                },
            ],
            'save_callback'         => [
                function ($value, $dataContainer) {
                    return $this->saveRelationsCallback($value, $dataContainer);
                },
            ],
        ];
    }

    protected function onsubmitCallback(DataContainer $dataContainer): void
    {
        $rowId = (int) $dataContainer->id;
        if (! isset($this->submittedRelations[$rowId])) {
            return;
        }

        $submittedRelations = $this->submittedRelations[$rowId];
        unset($this->submittedRelations[$rowId]);

        $makePrimary = array_keys(array_filter($submittedRelations, static function ($relation) {
            return (bool) $relation['primary'];
        }));

        $relations = $this->config->getRelations();
        $relations->deleteRelationsFrom($rowId);
        $relations->deleteRelationsToRoot($makePrimary, $rowId);
        if (! $relations->createRelations($rowId, array_keys($submittedRelations))) {
            return;
        }

        $relations->createReflectionRelations($rowId);
        $relations->createIntermediateRelations($rowId);
    }

    /**
     * @param mixed $value
     *
     * @return int[]
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function loadRelationsCallback($value, DataContainer $dataContainer): array
    {
        $result = QueryUtil::query(
            'SELECT related_item_id FROM %s WHERE item_id = ?',
            [$this->config->getRelations()->getRelationTable()],
            [$dataContainer->id]
        );

        return $result->fetchEach('related_item_id');
    }

    /**
     * @return null
     *
     * @throws Exception
     */
    protected function saveRelationsCallback(string $value, DataContainer $dataContainer)
    {
        $value = StringUtil::deserialize($value, true);

        $rowId = (int) $dataContainer->id;
        $this->validateRelationUniquePerRoot($rowId, array_keys($value));
        $this->validateRelationRoots($rowId, array_keys($value));

        $this->submittedRelations[$rowId] = $value;

        return null;
    }

    /**
     * @param int[] $relatedItems
     *
     * @throws Exception
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function validateRelationUniquePerRoot(int $item, array $relatedItems): void
    {
        if (! $relatedItems) {
            return;
        }

        $sql    = <<<SQL
SELECT
	root_page_id
FROM
	%s
WHERE
	item_id IN (%s)
GROUP BY
	root_page_id
HAVING
	COUNT(item_id) > 1
LIMIT
	1
SQL;
        $result = QueryUtil::query(
            $sql,
            [
                $this->config->getRelations()->getItemView(),
                QueryUtil::wildcards($relatedItems),
            ],
            $relatedItems
        );

        if ($result->numRows) {
            throw new Exception($GLOBALS['TL_LANG']['hofff_language_relations']['multipleRelationsPerRoot']);
        }
    }

    /**
     * @param int[] $relatedItems
     *
     * @throws Exception
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    protected function validateRelationRoots(int $item, array $relatedItems): void
    {
        if (! $relatedItems) {
            return;
        }

        $sql      = <<<SQL
SELECT
	SUM(item.group_id != current_item.group_id)			AS ungrouped_relations,
	SUM(item.root_page_id = current_item.root_page_id)	AS own_root_relations
FROM
	%s AS item,
	%s AS current_item
WHERE
	item.item_id IN (%s)
	AND current_item.item_id = ?
SQL;
        $params   = $relatedItems;
        $params[] = $item;
        $result   = QueryUtil::query(
            $sql,
            [
                $this->config->getRelations()->getItemView(),
                $this->config->getRelations()->getItemView(),
                QueryUtil::wildcards($relatedItems),
            ],
            $params
        );

        if ($result->ungrouped_relations) {
            throw new Exception($GLOBALS['TL_LANG']['hofff_language_relations']['ungroupedRelations']);
        }

        if ($result->own_root_relations) {
            throw new Exception($GLOBALS['TL_LANG']['hofff_language_relations']['ownRootRelations']);
        }
    }

    /**
     * @param string[][][] $dca
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    protected function inputFieldCallback(
        DataContainer $dataContainer,
        array &$dca,
        string $fieldName,
        SQLAdjacencyTreeDataFactory $factory
    ): string {
        $aggregate = $this->extractAggregateKey($dataContainer);

        $title = $this->getGroupTitle($aggregate);
        if (! $title) {
            $tpl         = new BackendTemplate('hofff_language_relations_not_translated');
            $tpl->reason = $GLOBALS['TL_LANG']['hofff_language_relations']['noRelationGroup'];

            return $tpl->parse();
        }

        $roots = $this->getRoots($aggregate);
        if (! $roots) {
            $tpl         = new BackendTemplate('hofff_language_relations_not_translated');
            $tpl->reason = $GLOBALS['TL_LANG']['hofff_language_relations']['noRelatedContent'];

            return $tpl->parse();
        }

        $factory->getConfig()->setRoots($roots);

        // grab a ref to the dca field config and a backup copy
        $field  = &$dca['fields'][$fieldName];
        $backup = $field;

        $field['label'] = [$title, &$field['label'][1]];

        // set the postback param for speeding up ajax requests of the selectri widgets
        $field['eval']['jsOptions']['qs']['key']                         = 'selectriAJAXCallback';
        $field['eval']['jsOptions']['qs']['hofff_language_relations_id'] = $dataContainer->id;

        // dont call me again, remove the input field callback, avoid infinite recursion
        unset($field['input_field_callback']);

        // call the row method again
        $dcRowMethod = new ReflectionMethod($dataContainer, 'row');
        $dcRowMethod->setAccessible(true);
        $return = $dcRowMethod->invoke($dataContainer, $dataContainer->palette);

        // restore the original dca
        $field = $backup;

        return $return;
    }

    protected function extractAggregateKey(DataContainer $dataContainer): string
    {
        return $dataContainer->activeRecord->{$this->config->getAggregateFieldName()};
    }

    protected function getGroupTitle(string $aggregate): string
    {
        if (isset($this->groupTitleCache[$aggregate])) {
            return $this->groupTitleCache[$aggregate];
        }

        $result = QueryUtil::query(
            'SELECT group_title, language FROM %s WHERE aggregate_id = ?',
            [$this->config->getAggregateView()],
            [$aggregate]
        );

        $tpl           = new BackendTemplate('hofff_language_relations_node_label');
        $tpl->language = $result->language;
        $tpl->title    = $result->group_title;

        return $this->groupTitleCache[$aggregate] = $tpl->parse();
    }

    /**
     * @return string[]
     */
    protected function getRoots(string $aggregate): array
    {
        if (isset($this->rootsCache[$aggregate])) {
            return $this->rootsCache[$aggregate];
        }

        $sql    = <<<SQL
SELECT
	related_aggregate.tree_root_id
FROM
	%s
	AS aggregate
JOIN
	%s
	AS related_aggregate
	ON related_aggregate.group_id = aggregate.group_id
	AND related_aggregate.root_page_id != aggregate.root_page_id
WHERE
	aggregate.aggregate_id = ?
SQL;
        $result = QueryUtil::query(
            $sql,
            [$this->config->getAggregateView(), $this->config->getAggregateView()],
            [$aggregate]
        );
        $roots  = $result->fetchEach('tree_root_id');

        return $this->rootsCache[$aggregate] = $roots;
    }

    protected function createFactory(): SQLAdjacencyTreeDataFactory
    {
        $factory = new SQLAdjacencyTreeDataFactory();
        $factory->getConfig()->setTable($this->config->getTreeView());
        $factory->getConfig()->addColumns(['title', 'language']);
        $factory->getConfig()->addSearchColumns('title');
        $factory->getConfig()->setLabelCallback([$this, 'generateNodeLabel']);
        $factory->getConfig()->setContentCallback([$this, 'generateNodeContent']);
        $factory->getConfig()->setSelectableExpr('selectable');

        $callback = $this->config->getSelectriDataFactoryConfiguratorCallback();
        if ($callback) {
            call_user_func($callback, $factory);
        }

        return $factory;
    }

    public function generateNodeLabel(Node $node): string
    {
        $tpl = new BackendTemplate($this->config->getSelectriNodeLabelTemplate());
        $tpl->setData($node->getData());

        return $tpl->parse();
    }

    public function generateNodeContent(Node $node, Data $data): string
    {
        if (! $node->isSelectable()) {
            return '';
        }

        $jsOptions = $data->getWidget()->getJSOptions();

        $tpl = new BackendTemplate($this->config->getSelectriNodeContentTemplate());

        $tpl->name      = $node->getAdditionalInputName('primary');
        $tpl->id        = $data->getWidget()->name . '_hofff_language_relations_primary_' . $node->getKey();
        $tpl->isPrimary = $this->isRelated(
            (int) $node->getKey(),
            (int) $jsOptions['qs']['hofff_language_relations_id']
        );

        return $tpl->parse();
    }

    protected function isRelated(int $item, int $relatedItem): bool
    {
        if ($relatedItem !== $this->relatedItem) {
            $this->relatedItem   = $relatedItem;
            $this->relatedItemOf = $this->config->getRelations()->getItemsRelatedTo($relatedItem);
        }

        return isset($this->relatedItemOf[$item]);
    }
}
