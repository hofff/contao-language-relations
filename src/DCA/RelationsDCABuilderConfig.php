<?php

namespace Hofff\Contao\LanguageRelations\DCA;

use Hofff\Contao\LanguageRelations\Relations;

/**
 * @author Oliver Hoff <oliver@hofff.com>
 */
class RelationsDCABuilderConfig {

	/**
	 * @var Relations
	 */
	protected $relations;

	/**
	 * @var string
	 */
	protected $aggregateFieldName;

	/**
	 * @var string
	 */
	protected $aggregateView;

	/**
	 * @var string
	 */
	protected $treeView;

	/**
	 * @var callable
	 */
	protected $selectriDataFactoryConfiguratorCallback;

	/**
	 * @var string
	 */
	protected $selectriNodeLabelTemplate;

	/**
	 * @var string
	 */
	protected $selectriNodeContentTemplate;

	/**
	 */
	public function __construct() {
		$this->selectriNodeLabelTemplate = 'hofff_language_relations_node_label';
		$this->selectriNodeContentTemplate = 'hofff_language_relations_node_content';
	}

	/**
	 * @return Relations
	 */
	public function getRelations() {
		return $this->relations;
	}

	/**
	 * @param Relations $relations
	 * @return void
	 */
	public function setRelations(Relations $relations) {
		$this->relations = $relations;
	}

	/**
	 * @return string
	 */
	public function getAggregateFieldName() {
		return $this->aggregateFieldName;
	}

	/**
	 * @param string $aggregateFieldName
	 * @return void
	 */
	public function setAggregateFieldName($aggregateFieldName) {
		$this->aggregateFieldName = $aggregateFieldName;
	}

	/**
	 * @return string
	 */
	public function getAggregateView() {
		return $this->aggregateView;
	}

	/**
	 * @param string $aggregateView
	 * @return void
	 */
	public function setAggregateView($aggregateView) {
		$this->aggregateView = $aggregateView;
	}

	/**
	 * @return string
	 */
	public function getTreeView() {
		return $this->treeView;
	}

	/**
	 * @param string $treeView
	 * @return void
	 */
	public function setTreeView($treeView) {
		$this->treeView = $treeView;
	}

	/**
	 * @return callable
	 */
	public function getSelectriDataFactoryConfiguratorCallback() {
		return $this->selectriDataFactoryConfiguratorCallback;
	}

	/**
	 * @param callable $callback
	 * @return void
	 */
	public function setSelectriDataFactoryConfiguratorCallback($callback) {
		$this->selectriDataFactoryConfiguratorCallback = $callback;
	}

	/**
	 * @return string
	 */
	public function getSelectriNodeLabelTemplate() {
		return $this->selectriNodeLabelTemplate;
	}

	/**
	 * @param string $template
	 * @return void
	 */
	public function setSelectriNodeLabelTemplate($template) {
		$this->selectriNodeLabelTemplate = $template;
	}

	/**
	 * @return string
	 */
	public function getSelectriNodeContentTemplate() {
		return $this->selectriNodeContentTemplate;
	}

	/**
	 * @param string $template
	 * @return void
	 */
	public function setSelectriNodeContentTemplate($template) {
		$this->selectriNodeContentTemplate = $template;
	}

}
