<?php

namespace ContaoCommunityAlliance\Contao\LanguageRelations;

class SelectriDataFactoryCallbacks {

	public function inputFieldCallback($dc, $xlabel) {
		$sql = <<<SQL
SELECT		grpRoots.id
FROM		tl_page			AS page
JOIN		tl_page			AS root		ON root.id = page.cca_rr_root
JOIN		tl_cca_lr_group	AS grp		ON grp.id = root.cca_lr_group
JOIN		tl_page			AS grpRoots	ON grpRoots.cca_lr_group = root.cca_lr_group AND grpRoots.id != root.id
WHERE		page.id = ?
ORDER BY	grpRoots.sorting
SQL;
		$result = \Database::getInstance()->prepare($sql)->executeUncached($dc->id);

		$this->factory->getConfig()->setRoots($result->numRows ? $result->fetchEach('id') : array(-1));

		// remove the input field callback, to avoid endless recursion
		$field = &$GLOBALS['TL_DCA']['tl_page']['fields']['cca_lr_relations'];
		unset($field['input_field_callback']);

		// set the postback param for speeding up ajax requests of the selectri widgets
		$field['eval']['jsOptions']['qs']['key'] = 'selectriAJAXCallback';
		$field['eval']['jsOptions']['qs']['cca_lr_id'] = $dc->id;

		$dcRowMethod = new \ReflectionMethod($dc, 'row');
		$dcRowMethod->setAccessible(true);
		$return = $dcRowMethod->invoke($dc, $dc->palette);

		// restore the original dca
		$field['input_field_callback'] = array(__CLASS__, __FUNCTION__);
		unset($field['eval']['jsOptions']);

		return $return;
	}

	public function getFactory() {
		return $this->factory;
	}

	public function generatePageNodeLabel(\SelectriNode $node) {
		$nodeData = $node->getData();

		$tpl = 'cca_lr_pageNodeLabel';
		$tpl = TL_MODE == 'FE' ? new \FrontendTemplate($tpl) : new \BackendTemplate($tpl);

		$tpl->language	= $this->getLanguage($nodeData['cca_rr_root']);
		$tpl->title		= $nodeData['title'];
		$tpl->id		= $nodeData['id'];

		return $tpl->parse();
	}

	public function generatePageNodeContent(\SelectriNode $node, \SelectriData $data) {
		$jsOptions = $data->getWidget()->getJSOptions();

		$tpl = 'cca_lr_pageNodeContent';
		$tpl = TL_MODE == 'FE' ? new \FrontendTemplate($tpl) : new \BackendTemplate($tpl);

		$tpl->name		= $node->getAdditionalInputName('primary');
		$tpl->id		= $data->getWidget()->name . '_cca_lr_primary_' . $node->getKey();
		$tpl->isPrimary	= $this->isRelated($node->getKey(), $jsOptions['qs']['cca_lr_id']);

		return $tpl->parse();
	}

	/** @var \SelectriContaoTableDataFactory */
	private $factory;

	protected function __construct() {
		$this->factory = new \SelectriContaoTableDataFactory;
		$this->factory->setTreeTable('tl_page');
		$this->factory->getConfig()->addTreeColumns(array('title', 'cca_rr_root'));
		$this->factory->getConfig()->addTreeSearchColumns('title');
		$this->factory->getConfig()->setTreeLabelCallback(array($this, 'generatePageNodeLabel'));
		$this->factory->getConfig()->setTreeContentCallback(array($this, 'generatePageNodeContent'));
		$this->factory->getConfig()->setSelectableExpr('type != \'root\'');
	}

	private $rootLanguages;

	protected function getLanguage($root) {
		if(!isset($this->rootLanguages[$root])) {
			$sql = 'SELECT language FROM tl_page WHERE id = ?';
			$result = \Database::getInstance()->prepare($sql)->executeUncached($root);
			$this->rootLanguages[$root] = $result->language;
		}
		return $this->rootLanguages[$root];
	}

	private $pageTo;

	private $relatedToPageTo;

	protected function isRelated($pageFrom, $pageTo) {
		if($pageTo != $this->pageTo) {
			$this->pageTo = $pageTo;
			$this->relatedToPageTo = LanguageRelations::getPagesRelatedTo($pageTo);
		}
		return isset($this->relatedToPageTo[$pageFrom]);
	}

	private function __clone() {
	}

	private static $instance;

	public static function getInstance() {
		isset(self::$instance) || self::$instance = new self;
		return self::$instance;
	}

}
