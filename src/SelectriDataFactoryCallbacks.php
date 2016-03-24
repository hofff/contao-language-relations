<?php

namespace Hofff\Contao\LanguageRelations;

use Hofff\Contao\Selectri\Model\Data;
use Hofff\Contao\Selectri\Model\Node;
use Hofff\Contao\Selectri\Model\Tree\SQLAdjacencyTreeDataFactory;

/**
 * @author Oliver Hoff <oliver@hofff.com>
 */
class SelectriDataFactoryCallbacks {

	/**
	 * @var SelectriDataFactoryCallbacks
	 */
	private static $instance;

	/**
	 * @return SelectriDataFactoryCallbacks
	 */
	public static function getInstance() {
		isset(self::$instance) || self::$instance = new self;
		return self::$instance;
	}

	/**
	 * @var SQLAdjacencyTreeDataFactory
	 */
	private $factory;

	/**
	 * @var array<array<string, mixed>>
	 */
	private $translationGroups;

	/**
	 * @var array<integer, string>
	 */
	private $rootLanguages;

	/**
	 * @var integer
	 */
	private $translatedPage;

	/**
	 * @var array<integer, integer>
	 */
	private $translatedPageOf;

	/**
	 */
	protected function __construct() {
	}

	/**
	 */
	private function __clone() {
	}

	/**
	 * @return SQLAdjacencyTreeDataFactory
	 */
	public function getFactory() {
		if(!isset($this->factory)) {
			$this->factory = new SQLAdjacencyTreeDataFactory;
			$this->factory->getConfig()->setTable('tl_page');
			$this->factory->getConfig()->addColumns([ 'title', 'hofff_root_page_id' ]);
			$this->factory->getConfig()->addSearchColumns('title');
			$this->factory->getConfig()->setLabelCallback([ $this, 'generatePageNodeLabel' ]);
			$this->factory->getConfig()->setContentCallback([ $this, 'generatePageNodeContent' ]);
			$this->factory->getConfig()->setSelectableExpr('type != \'root\'');
		}

		return $this->factory;
	}

	/**
	 * @param \DataContainer $dc
	 * @param string $xlabel
	 * @return string
	 */
	public function inputFieldCallback($dc, $xlabel) {
		$group = $this->getTranslationGroup($dc->activeRecord->hofff_root_page_id);

		if(!$group) {
			$tpl = new \BackendTemplate('hofff_language_relations_not_translated');
			$tpl->reason = $GLOBALS['TL_LANG']['tl_page']['hofff_language_relations_no_translation_group'];
			return $tpl->parse();
		}

		if(!$group['translatedRootPages']) {
			$tpl = new \BackendTemplate('hofff_language_relations_not_translated');
			$tpl->reason = $GLOBALS['TL_LANG']['tl_page']['hofff_language_relations_no_translated_root_pages'];
			return $tpl->parse();
		}

		$this->getFactory()->getConfig()->setRoots($group['translatedRootPages']);

		// grab a ref to the dca field config and a backup copy
		$field = &$GLOBALS['TL_DCA']['tl_page']['fields']['hofff_page_translations'];
		$backup = $field;

		$field['label'] = [ $group['title'], &$field['label'][1] ];

		// set the postback param for speeding up ajax requests of the selectri widgets
		$field['eval']['jsOptions']['qs']['key'] = 'selectriAJAXCallback';
		$field['eval']['jsOptions']['qs']['hofff_page_id'] = $dc->id;

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
	 * @param Node $node
	 * @return string
	 */
	public function generatePageNodeLabel(Node $node) {
		$nodeData = $node->getData();

		$tpl = 'hofff_pageNodeLabel';
		$tpl = TL_MODE == 'FE' ? new \FrontendTemplate($tpl) : new \BackendTemplate($tpl);

		$tpl->language	= $this->getLanguage($nodeData['hofff_root_page_id']);
		$tpl->title		= $nodeData['title'];
		$tpl->id		= $nodeData['id'];

		return $tpl->parse();
	}

	/**
	 * @param Node $node
	 * @param Data $data
	 * @return string
	 */
	public function generatePageNodeContent(Node $node, Data $data) {
		$jsOptions = $data->getWidget()->getJSOptions();

		$tpl = 'hofff_pageNodeContent';
		$tpl = TL_MODE == 'FE' ? new \FrontendTemplate($tpl) : new \BackendTemplate($tpl);

		$tpl->name		= $node->getAdditionalInputName('primary');
		$tpl->id		= $data->getWidget()->name . '_hofff_language_relations_primary_' . $node->getKey();
		$tpl->isPrimary	= $this->isRelated($node->getKey(), $jsOptions['qs']['hofff_page_id']);

		return $tpl->parse();
	}

	/**
	 * @param integer $rootPage
	 * @return array<string, mixed>
	 */
	protected function getTranslationGroup($rootPage) {
		if(isset($this->translationGroups[$rootPage])) {
			return $this->translationGroups[$rootPage] ?: null;
		}

		$sql = <<<SQL
SELECT
	translated_root_page.id		AS root_page_id,
	translation_group.title		AS title
FROM
	tl_page
	AS root_page
JOIN
	tl_hofff_translation_group
	AS translation_group
	ON translation_group.id = root_page.hofff_translation_group_id
JOIN
	tl_page
	AS translated_root_page
	ON translated_root_page.hofff_translation_group_id = root_page.hofff_translation_group_id
WHERE
	root_page.id = ?
ORDER BY
	translated_root_page.sorting
SQL;
		$result = \Database::getInstance()->prepare($sql)->executeUncached($rootPage);

		if(!$result->numRows) {
			$this->translationGroups[$rootPage] = false;
			return null;
		}

		$group = array();
		$group['title'] = $result->title;
		$rootPages = $result->fetchEach('root_page_id');
		$rootPages = array_combine($rootPages, $rootPages);
		unset($rootPages[$rootPage]);
		$group['translatedRootPages'] = $rootPages;

		$this->translationGroups[$rootPage] = $group;

		return $group;
	}

	/**
	 * @param integer $root
	 * @return string
	 */
	protected function getLanguage($root) {
		if(!isset($this->rootLanguages[$root])) {
			$sql = 'SELECT language FROM tl_page WHERE id = ?';
			$result = \Database::getInstance()->prepare($sql)->executeUncached($root);
			$this->rootLanguages[$root] = $result->language;
		}
		return $this->rootLanguages[$root];
	}

	/**
	 * @param integer $page
	 * @param integer $translatedPage
	 * @return boolean
	 */
	protected function isRelated($page, $translatedPage) {
		if($translatedPage != $this->translatedPage) {
			$this->translatedPage = $translatedPage;
			$this->translatedPageOf = LanguageRelations::getPagesRelatedTo($translatedPage);
		}
		return isset($this->translatedPageOf[$page]);
	}

}
