<?php

namespace ContaoCommunityAlliance\Contao\LanguageRelations;

class SelectriDataFactoryCallbacks extends \DataContainer {

	/** @var \SelectriContaoTableDataFactory */
	private $factory;

	private $rootLanguages;

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

		$return = $dc->row($dc->strPalette);

		// readd the callback
		$field['input_field_callback'] = array(__CLASS__, __FUNCTION__);

		return $return;
	}

	public function getFactory() {
		return $this->factory;
	}

	public function formatPageNodeLabel($node) {
		$root = $node['cca_rr_root'];
		if(!isset($this->rootLanguages[$root])) {
			$sql = 'SELECT language FROM tl_page WHERE id = ?';
			$result = \Database::getInstance()->prepare($sql)->executeUncached($root);
			$this->rootLanguages[$root] = $result->language;
		}
		return sprintf('<span class="cca-lr-greyed">[%s]</span> %s (ID %s)', $this->rootLanguages[$root], $node['title'], $node['id']);
	}

	protected function __construct() {
		parent::__construct();
		$this->factory = new \SelectriContaoTableDataFactory;
		$this->factory->setTreeTable('tl_page');
		$this->factory->getConfig()->addTreeColumns(array('title', 'cca_rr_root'));
		$this->factory->getConfig()->addTreeSearchColumns('title');
		$this->factory->getConfig()->setTreeLabelCallback(array($this, 'formatPageNodeLabel'));
		$this->factory->getConfig()->setSelectableExpr('type != \'root\'');
	}

	private function __clone() {
	}

	private static $instance;

	public static function getInstance() {
		isset(self::$instance) || self::$instance = new self;
		return self::$instance;
	}

}
