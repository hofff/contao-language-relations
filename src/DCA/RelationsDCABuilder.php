<?php

namespace Hofff\Contao\LanguageRelations\DCA;

use Hofff\Contao\LanguageRelations\Relations;
use Hofff\Contao\LanguageRelations\Util\QueryUtil;
use Hofff\Contao\Selectri\Model\Data;
use Hofff\Contao\Selectri\Model\Node;
use Hofff\Contao\Selectri\Model\Tree\SQLAdjacencyTreeDataFactory;

/**
 * @author Oliver Hoff <oliver@hofff.com>
 */
 class RelationsDCABuilder {

	/**
	 * @var RelationsDCABuilderConfig
	 */
	private $config;

	/**
	 * @var array
	 */
	protected $submittedRelations;

	/**
	 * @var array<string, string>
	 */
	private $groupTitleCache;

	/**
	 * @var array<string, array<string>>
	 */
	private $rootsCache;

	/**
	 * @var integer
	 */
	private $relatedItem;

	/**
	 * @var array<integer, integer>
	 */
	private $relatedItemOf;

	/**
	 * @param RelationsDCABuilderConfig $config
	 */
	public function __construct(RelationsDCABuilderConfig $config) {
		$this->config = clone $config;
		$this->submittedRelations = [];
	}

	/**
	 * @param array $dca
	 * @param string $fieldName
	 * @return void
	 */
	public function build(array &$dca, $fieldName = 'hofff_language_relations') {
		\System::loadLanguageFile('hofff_language_relations');

		$factory = $this->createFactory();

		$dca['config']['onsubmit_callback'][] = function($dc) {
			$this->onsubmitCallback($dc);
		};

		$dca['fields'][$fieldName] = [
			'label'					=> &$GLOBALS['TL_LANG']['hofff_language_relations']['field'],
			'exclude'				=> true,
			'inputType'				=> 'selectri',
			'eval'					=> [
				'doNotSaveEmpty'		=> true,
				'min'					=> 0,
				'max'					=> PHP_INT_MAX,
				'sort'					=> false,
				'canonical'				=> true,
				'class'					=> 'hofff-relations',
				'data'					=> $factory,
			],
			'input_field_callback'	=> function($dc) use(&$dca, $fieldName, $factory) {
				return $this->inputFieldCallback($dc, $dca, $fieldName, $factory);
			},
			'load_callback'			=> [
				function($value, $dc) {
					return $this->loadRelationsCallback($value, $dc);
				},
			],
			'save_callback'			=> [
				function($value, $dc) {
					return $this->saveRelationsCallback($value, $dc);
				},
			],
		];
	}

	/**
	 * @param \DataContainer $dc
	 * @return void
	 */
	protected function onsubmitCallback($dc) {
		if(!isset($this->submittedRelations[$dc->id])) {
			return;
		}

		$submittedRelations = $this->submittedRelations[$dc->id];
		unset($this->submittedRelations[$dc->id]);

		$makePrimary = array_keys(array_filter($submittedRelations, function($relation) {
			return (bool) $relation['primary'];
		}));

		$relations = $this->config->getRelations();
		$relations->deleteRelationsFrom($dc->id);
		$relations->deleteRelationsToRoot($makePrimary, $dc->id);
		if(!$relations->createRelations($dc->id, array_keys($submittedRelations))) {
			return;
		}
		$relations->createReflectionRelations($dc->id);
		$relations->createIntermediateRelations($dc->id);
	}

	/**
	 * @param mixed $value
	 * @param \DataContainer $dc
	 * @return array<integer>
	 */
	protected function loadRelationsCallback($value, $dc) {
		$result = QueryUtil::query(
			'SELECT related_item_id FROM %s WHERE item_id = ?',
			[ $this->config->getRelations()->getRelationTable() ],
			[ $dc->id ]
		);
		return $result->fetchEach('related_item_id');
	}

	/**
	 * @param array<integer> $value
	 * @param \DataContainer $dc
	 * @throws \Exception
	 * @return null
	 */
	protected function saveRelationsCallback($value, $dc) {
		$value = deserialize($value, true);

		$this->validateRelationUniquePerRoot($dc->id, array_keys($value));
		$this->validateRelationRoots($dc->id, array_keys($value));

		$this->submittedRelations[$dc->id] = $value;

		return null;
	}

	/**
	 * @param integer $item
	 * @param array<integer> $relatedItems
	 * @throws \Exception
	 */
	protected function validateRelationUniquePerRoot($item, $relatedItems) {
		if(!$relatedItems) {
			return;
		}

		$sql = <<<SQL
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
				QueryUtil::wildcards($relatedItems)
			],
			$relatedItems
		);

		if($result->numRows) {
			throw new \Exception($GLOBALS['TL_LANG']['hofff_language_relations']['multipleRelationsPerRoot']);
		}
	}

	/**
	 * @param integer $item
	 * @param array<integer> $relatedItems
	 * @throws \Exception
	 */
	protected function validateRelationRoots($item, $relatedItems) {
		if(!$relatedItems) {
			return;
		}

		$sql = <<<SQL
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
		$params = $relatedItems;
		$params[] = $item;
		$result = QueryUtil::query(
			$sql,
			[
				$this->config->getRelations()->getItemView(),
				$this->config->getRelations()->getItemView(),
				QueryUtil::wildcards($relatedItems)
			],
			$params
		);

		if($result->ungrouped_relations) {
			throw new \Exception($GLOBALS['TL_LANG']['hofff_language_relations']['ungroupedRelations']);
		}

		if($result->own_root_relations) {
			throw new \Exception($GLOBALS['TL_LANG']['hofff_language_relations']['ownRootRelations']);
		}
	}

	/**
	 * @param \DataContainer $dc
	 * @param array $dca
	 * @param string $fieldName
	 * @param SQLAdjacencyTreeDataFactory $factory
	 * @return string
	 */
	protected function inputFieldCallback($dc, array &$dca, $fieldName, SQLAdjacencyTreeDataFactory $factory) {
		$aggregate = $this->extractAggregateKey($dc);

		$title = $this->getGroupTitle($aggregate);
		if(!$title) {
			$tpl = new \BackendTemplate('hofff_language_relations_not_translated');
			$tpl->reason = $GLOBALS['TL_LANG']['hofff_language_relations']['noRelationGroup'];
			return $tpl->parse();
		}

		$roots = $this->getRoots($aggregate);
		if(!$roots) {
			$tpl = new \BackendTemplate('hofff_language_relations_not_translated');
			$tpl->reason = $GLOBALS['TL_LANG']['hofff_language_relations']['noRelatedContent'];
			return $tpl->parse();
		}

		$factory->getConfig()->setRoots($roots);

		// grab a ref to the dca field config and a backup copy
		$field = &$dca['fields'][$fieldName];
		$backup = $field;

		$field['label'] = [ $title, &$field['label'][1] ];

		// set the postback param for speeding up ajax requests of the selectri widgets
		$field['eval']['jsOptions']['qs']['key'] = 'selectriAJAXCallback';
		$field['eval']['jsOptions']['qs']['hofff_language_relations_id'] = $dc->id;

		// dont call me again, remove the input field callback, avoid infinite recursion
		unset($field['input_field_callback']);

		// call the row method again
		$dcRowMethod = new \ReflectionMethod($dc, 'row');
		$dcRowMethod->setAccessible(true);
		$return = $dcRowMethod->invoke($dc, $dc->palette);

		// restore the original dca
		$field = $backup;

		return $return;
	}

	/**
	 * @param \DataContainer $dc
	 * @return string
	 */
	protected function extractAggregateKey($dc) {
		return $dc->activeRecord->{$this->config->getAggregateFieldName()};
	}

	/**
	 * @param string $aggregate
	 * @return string
	 */
	protected function getGroupTitle($aggregate) {
		if(isset($this->groupTitleCache[$aggregate])) {
			return $this->groupTitleCache[$aggregate];
		}

		$result = QueryUtil::query(
			'SELECT group_title FROM %s WHERE aggregate_id = ?',
			[ $this->config->getAggregateView() ],
			[ $aggregate ]
		);

		return $this->groupTitleCache[$aggregate] = $result->group_title;
	}

	/**
	 * @param string $aggregate
	 * @return array<string>
	 */
	protected function getRoots($aggregate) {
		if(isset($this->rootsCache[$aggregate])) {
			return $this->rootsCache[$aggregate];
		}

		$sql = <<<SQL
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
		$roots = $result->fetchEach('tree_root_id');

		return $this->rootsCache[$aggregate] = $roots;
	}

	/**
	 * @return SQLAdjacencyTreeDataFactory
	 */
	protected function createFactory() {
		$factory = new SQLAdjacencyTreeDataFactory;
		$factory->getConfig()->setTable($this->config->getTreeView());
		$factory->getConfig()->addColumns([ 'title', 'language' ]);
		$factory->getConfig()->addSearchColumns('title');
		$factory->getConfig()->setLabelCallback([ $this, 'generateNodeLabel' ]);
		$factory->getConfig()->setContentCallback([ $this, 'generateNodeContent' ]);
		$factory->getConfig()->setSelectableExpr('selectable');

		if($callback = $this->config->getSelectriDataFactoryConfiguratorCallback()) {
			call_user_func($callback, $factory);
		}

		return $factory;
	}

	/**
	 * @param Node $node
	 * @return string
	 */
	public function generateNodeLabel(Node $node) {
		$tpl = new \BackendTemplate($this->config->getSelectriNodeLabelTemplate());
		$tpl->setData($node->getData());
		return $tpl->parse();
	}

	/**
	 * @param Node $node
	 * @param Data $data
	 * @return string
	 */
	public function generateNodeContent(Node $node, Data $data) {
		if(!$node->isSelectable()) {
			return '';
		}

		$jsOptions = $data->getWidget()->getJSOptions();

		$tpl = new \BackendTemplate($this->config->getSelectriNodeContentTemplate());

		$tpl->name		= $node->getAdditionalInputName('primary');
		$tpl->id		= $data->getWidget()->name . '_hofff_language_relations_primary_' . $node->getKey();
		$tpl->isPrimary	= $this->isRelated($node->getKey(), $jsOptions['qs']['hofff_language_relations_id']);

		return $tpl->parse();
	}

	/**
	 * @param integer $item
	 * @param integer $relatedItem
	 * @return boolean
	 */
	protected function isRelated($item, $relatedItem) {
		if($relatedItem != $this->relatedItem) {
			$this->relatedItem = $relatedItem;
			$this->relatedItemOf = $this->config->getRelations()->getItemsRelatedTo($relatedItem);
		}
		return isset($this->relatedItemOf[$item]);
	}

}
