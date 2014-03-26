<?php

namespace ContaoCommunityAlliance\Contao\LanguageRelations;

/**
 * @author Oliver Hoff
 */
class GroupDCA {

	public function getRootsOptions() {
		$sql = <<<SQL
SELECT		page.id, page.title, page.language,
			grp.id AS grpID, grp.title AS grpTitle
FROM		tl_page			AS page
LEFT JOIN	tl_cca_lr_group	AS grp	ON grp.id = page.cca_lr_group
WHERE		page.type = ?
ORDER BY	grp.title IS NOT NULL, grp.title, page.title
SQL;
		$result = \Database::getInstance()->prepare($sql)->executeUncached('root');

		$options = array();
		while($result->next()) {
			$groupTitle = $result->grpID
				? $result->grpTitle . ' (ID ' . $result->grpID . ')'
				: $GLOBALS['TL_LANG']['tl_cca_lr_group']['notGrouped'];
			$options[$groupTitle][$result->id] = $result->title . ' [' . $result->language . ']';
		}

		return $options;
	}

	private $roots = array();

	public function onsubmitGroup($dc) {
		if(isset($this->roots[$dc->id])) {
			$sql = 'UPDATE tl_page SET cca_lr_group = NULL WHERE cca_lr_group = ?';
			\Database::getInstance()->prepare($sql)->executeUncached($dc->id);

			$roots = deserialize($this->roots[$dc->id], true);
			if($roots) {
				$wildcards = rtrim(str_repeat('?,', count($roots)), ',');
				$sql = 'UPDATE tl_page SET cca_lr_group = ? WHERE id IN (' . $wildcards . ')';
				array_unshift($roots, $dc->id);
				\Database::getInstance()->prepare($sql)->executeUncached($roots);
			}
		}
	}

	public function loadRoots($value, $dc) {
		$sql = 'SELECT id FROM tl_page WHERE cca_lr_group = ? AND type = ? ORDER BY title';
		$result = \Database::getInstance()->prepare($sql)->executeUncached($dc->id, 'root');
		return $result->fetchEach('id');
	}

	public function saveRoots($value, $dc) {
		$this->roots[$dc->id] = $value;
		return null;
	}

}
