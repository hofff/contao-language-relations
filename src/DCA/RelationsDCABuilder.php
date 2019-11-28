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
use const PHP_INT_MAX;
use function array_filter;
use function array_keys;
use function call_user_func;

class RelationsDCABuilder
{
    /** @var RelationsDCABuilderConfig */
    private $config;

    /** @var string[][] */
    protected $submittedRelations;

    /** @var array<string, string> */
    private $groupTitleCache;

    /** @var array<string, array<string>> */
    private $rootsCache;

    /** @var int */
    private $relatedItem;

    /** @var array<integer, integer> */
    private $relatedItemOf;

    public function __construct(RelationsDCABuilderConfig $config)
    {
        $this->config             = clone $config;
        $this->submittedRelations = [];
    }

    /**
     * @param string[][] $dca
     */
    public function build(array &$dca, string $fieldName = 'hofff_language_relations') : void
    {
        System::loadLanguageFile('hofff_language_relations');

        $factory = $this->createFactory();

        $dca['config']['onsubmit_callback'][] = function ($dc) : void {
            $this->onsubmitCallback($dc);
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
            'input_field_callback'  => function ($dc) use (&$dca, $fieldName, $factory) {
                return $this->inputFieldCallback($dc, $dca, $fieldName, $factory);
            },
            'load_callback'         => [
                function ($value, $dc) {
                    return $this->loadRelationsCallback($value, $dc);
                },
            ],
            'save_callback'         => [
                function ($value, $dc) {
                    return $this->saveRelationsCallback($value, $dc);
                },
            ],
        ];
    }

    protected function onsubmitCallback(DataContainer $dc) : void
    {
        $id = (int) $dc->id;
        if (! isset($this->submittedRelations[$id])) {
            return;
        }

        $submittedRelations = $this->submittedRelations[$id];
        unset($this->submittedRelations[$id]);

        $makePrimary = array_keys(array_filter($submittedRelations, static function ($relation) {
            return (bool) $relation['primary'];
        }));

        $relations = $this->config->getRelations();
        $relations->deleteRelationsFrom($id);
        $relations->deleteRelationsToRoot($makePrimary, $id);
        if (! $relations->createRelations($id, array_keys($submittedRelations))) {
            return;
        }
        $relations->createReflectionRelations($id);
        $relations->createIntermediateRelations($id);
    }

    /**
     * @param mixed         $value
     * @param DataContainer $dc
     *
     * @return int[]
     */
    protected function loadRelationsCallback($value, $dc) : array
    {
        $result = QueryUtil::query(
            'SELECT related_item_id FROM %s WHERE item_id = ?',
            [ $this->config->getRelations()->getRelationTable() ],
            [ $dc->id ]
        );
        return $result->fetchEach('related_item_id');
    }

    /**
     * @return null
     *
     * @throws Exception
     */
    protected function saveRelationsCallback(string $value, DataContainer $dc)
    {
        $value = StringUtil::deserialize($value, true);

        $id = (int) $dc->id;
        $this->validateRelationUniquePerRoot($id, array_keys($value));
        $this->validateRelationRoots($id, array_keys($value));

        $this->submittedRelations[$id] = $value;

        return null;
    }

    /**
     * @param int[] $relatedItems
     *
     * @throws Exception
     */
    protected function validateRelationUniquePerRoot(int $item, array $relatedItems) : void
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
     */
    protected function validateRelationRoots(int $item, array $relatedItems) : void
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
     */
    protected function inputFieldCallback(
        DataContainer $dc,
        array &$dca,
        string $fieldName,
        SQLAdjacencyTreeDataFactory $factory
    ) : string {
        $aggregate = $this->extractAggregateKey($dc);

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

        $field['label'] = [ $title, &$field['label'][1] ];

        // set the postback param for speeding up ajax requests of the selectri widgets
        $field['eval']['jsOptions']['qs']['key']                         = 'selectriAJAXCallback';
        $field['eval']['jsOptions']['qs']['hofff_language_relations_id'] = $dc->id;

        // dont call me again, remove the input field callback, avoid infinite recursion
        unset($field['input_field_callback']);

        // call the row method again
        $dcRowMethod = new ReflectionMethod($dc, 'row');
        $dcRowMethod->setAccessible(true);
        $return = $dcRowMethod->invoke($dc, $dc->palette);

        // restore the original dca
        $field = $backup;

        return $return;
    }

    protected function extractAggregateKey(DataContainer $dc) : string
    {
        return $dc->activeRecord->{$this->config->getAggregateFieldName()};
    }

    protected function getGroupTitle(string $aggregate) : string
    {
        if (isset($this->groupTitleCache[$aggregate])) {
            return $this->groupTitleCache[$aggregate];
        }

        $result = QueryUtil::query(
            'SELECT group_title, language FROM %s WHERE aggregate_id = ?',
            [ $this->config->getAggregateView() ],
            [ $aggregate ]
        );

        $tpl           = new BackendTemplate('hofff_language_relations_node_label');
        $tpl->language = $result->language;
        $tpl->title    = $result->group_title;

        return $this->groupTitleCache[$aggregate] = $tpl->parse();
    }

    /**
     * @return string[]
     */
    protected function getRoots(string $aggregate) : array
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
            [ $this->config->getAggregateView(), $this->config->getAggregateView() ],
            [ $aggregate ]
        );
        $roots  = $result->fetchEach('tree_root_id');

        return $this->rootsCache[$aggregate] = $roots;
    }

    protected function createFactory() : SQLAdjacencyTreeDataFactory
    {
        $factory = new SQLAdjacencyTreeDataFactory();
        $factory->getConfig()->setTable($this->config->getTreeView());
        $factory->getConfig()->addColumns([ 'title', 'language' ]);
        $factory->getConfig()->addSearchColumns('title');
        $factory->getConfig()->setLabelCallback([ $this, 'generateNodeLabel' ]);
        $factory->getConfig()->setContentCallback([ $this, 'generateNodeContent' ]);
        $factory->getConfig()->setSelectableExpr('selectable');

        $callback = $this->config->getSelectriDataFactoryConfiguratorCallback();
        if ($callback) {
            call_user_func($callback, $factory);
        }

        return $factory;
    }

    public function generateNodeLabel(Node $node) : string
    {
        $tpl = new BackendTemplate($this->config->getSelectriNodeLabelTemplate());
        $tpl->setData($node->getData());
        return $tpl->parse();
    }

    public function generateNodeContent(Node $node, Data $data) : string
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

    protected function isRelated(int $item, int $relatedItem) : bool
    {
        if ($relatedItem !== $this->relatedItem) {
            $this->relatedItem   = $relatedItem;
            $this->relatedItemOf = $this->config->getRelations()->getItemsRelatedTo($relatedItem);
        }
        return isset($this->relatedItemOf[$item]);
    }
}
