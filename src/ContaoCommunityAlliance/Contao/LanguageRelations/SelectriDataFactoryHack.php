<?php

namespace ContaoCommunityAlliance\Contao\LanguageRelations;

class SelectriDataFactoryHack extends \DataContainer {

	/** @var \SelectriContaoTableDataFactory */
	private $factory;

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
		$result = \Database::getInstance()->prepare($sql)->execute($dc->id);

		$this->factory->getConfig()->setRoots($result->numRows ? $result->fetchEach('id') : array(-1));

		// remove the input field callback, to avoid endless recursion
		$field = &$GLOBALS['TL_DCA']['tl_page']['fields']['cca_lr_relations'];
		unset($field['input_field_callback']);

		$return = $dc->row($dc->strPalette);

		// readd the callback
		$field['input_field_callback'] = array(__CLASS__, __METHOD__);

		return $return;
	}

	public function getFactory() {
		return $this->factory;
	}

	protected function __construct() {
		parent::__construct();
		$this->factory = new \SelectriContaoTableDataFactory;
		$this->factory->setTreeTable('tl_page');
		$this->factory->getConfig()->addTreeSearchColumns('title');
	}

	private function __clone() {
	}

	private static $instance;

	public static function getInstance() {
		isset(self::$instance) || self::$instance = new self;
		return self::$instance;
	}

}
