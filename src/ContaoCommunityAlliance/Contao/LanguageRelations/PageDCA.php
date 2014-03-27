<?php

namespace ContaoCommunityAlliance\Contao\LanguageRelations;

/**
 * @author Oliver Hoff
 */
class PageDCA {

	public function inputFieldPageInfo($dc, $xlabel) {
		$tpl = new \BackendTemplate('cca_lr_pageInfo');
		$tpl->setData($dc->activeRecord->row());
		return $tpl->parse();
	}

	private $relations = array();

	public function onsubmitPage($dc) {
		if(isset($this->relations[$dc->id])) {
			$sql = 'DELETE FROM tl_cca_lr_relation WHERE pageFrom = ?';
			\Database::getInstance()->prepare($sql)->executeUncached($dc->id);

			$relations = $this->relations[$dc->id];
			if($relations) {
				$wildcards = rtrim(str_repeat('(?,?),', count($relations)), ',');
				$sql = 'INSERT INTO tl_cca_lr_relation (pageFrom, pageTo) VALUES ' . $wildcards;
				foreach($relations as $id) {
					$params[] = $dc->id;
					$params[] = $id;
				}
				\Database::getInstance()->prepare($sql)->executeUncached($params);

				LanguageRelations::createReflectionRelations($dc->id);
				LanguageRelations::createIntermediateRelations($dc->id);
			}

			unset($this->relations[$dc->id]);
		}
	}

	public function loadRelations($value, $dc) {
		$sql = 'SELECT pageTo FROM tl_cca_lr_relation WHERE pageFrom = ?';
		$result = \Database::getInstance()->prepare($sql)->executeUncached($dc->id);
		return $result->fetchEach('pageTo');
	}

	public function saveRelations($value, $dc) {
		$value = deserialize($value, true);

		if($value) {
			$wildcards = rtrim(str_repeat('?,', count($value)), ',');

			$sql = <<<SQL
SELECT		cca_rr_root
FROM		tl_page
WHERE		id IN ($wildcards)
GROUP BY	cca_rr_root
HAVING		COUNT(id) > 1
LIMIT		1
SQL;
			$params = $value;
			$result = \Database::getInstance()->prepare($sql)->executeUncached($params);
			if($result->numRows) {
				throw new \Exception($GLOBALS['TL_LANG']['tl_page']['cca_lr_errMultipleRelationsPerRoot']);
			}

			$sql = <<<SQL
SELECT		SUM(rootPage.cca_lr_group != curRootPage.cca_lr_group) AS ungroupedRelations,
			SUM(rootPage.id = curRootPage.id) AS ownRootRelations

FROM		tl_page		AS page
JOIN		tl_page		AS rootPage			ON rootPage.id = page.cca_rr_root
JOIN		(
	SELECT	curRootPage1.cca_lr_group, curRootPage1.id
	FROM	tl_page		AS curPage1
	JOIN	tl_page		AS curRootPage1		ON curRootPage1.id = curPage1.cca_rr_root
	WHERE	curPage1.id = ?
)						AS curRootPage

WHERE		page.id IN ($wildcards)
SQL;
			array_unshift($params, $dc->id);
			$result = \Database::getInstance()->prepare($sql)->executeUncached($params);
			if($result->ungroupedRelations) {
				throw new \Exception($GLOBALS['TL_LANG']['tl_page']['cca_lr_errUngroupedRelations']);
			}
			if($result->ownRootRelations) {
				throw new \Exception($GLOBALS['TL_LANG']['tl_page']['cca_lr_errOwnRootRelations']);
			}
		}

		$this->relations[$dc->id] = $value;
		return null;
	}

}
