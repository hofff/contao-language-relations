<?php

namespace ContaoCommunityAlliance\Contao\LanguageRelations;

/**
 * A relation in tl_cca_lr_relation is valid, if:
 * - pageFrom != pageTo (non reflexive)
 * - pageFrom->id.tl_page.cca_rr_root->id.tl_page.cca_lr_group
 * 		= pageTo->id.tl_page.cca_rr_root->id.tl_page.cca_lr_group
 * (the root pages belong to the same translation group)
 *
 * A relation is primary, if:
 * - it is valid
 * - pageFrom = pageTo->pageFrom.tl_cca_lr_relation.pageTo (there is a link back)
 *
 * @author Oliver Hoff
 */
class LanguageRelations {

	/**
	 * Get the valid relations for each given page ID.
	 *
	 * If $primary is true, only primary relations are returned.
	 *
	 * If a single integer is passed, returns a map of rootPage -> page entries,
	 * where page represents the related page in the respective rootPage.
	 *
	 * If an array of integers is passed, returns a map of page -> array
	 * entries, where array is the same result as this method were called with
	 * the respective page as a single integer argument.
	 *
	 * @param integer|array<integer> $pages
	 * @param boolean $primary
	 * @return array<integer, integer>|array<integer, array<integer, integer>>
	 */
	public static function getRelations($pages, $primary = false) {
		$ids = (array) $pages;
		$relations = array_fill_keys($ids, array());

		$ids = array_filter($ids, function($id) { return $id >= 1; });
		if(!$ids) {
			return is_array($pages) ? $relations : array();
		}

		$wildcards = rtrim(str_repeat('?,', count($ids)), ',');
		$sql = <<<SQL

SELECT		rel.pageFrom,
			rel.pageTo,
			rootPageTo.id			AS rootPageTo,
			refl.pageTo IS NOT NULL	AS isPrimary

FROM 		tl_cca_lr_relation		AS rel

JOIN		tl_page					AS pageFrom			ON pageFrom.id = rel.pageFrom
JOIN		tl_page					AS rootPageFrom		ON rootPageFrom.id = pageFrom.cca_rr_root

JOIN		tl_page					AS pageTo			ON pageTo.id = rel.pageTo
JOIN		tl_page					AS rootPageTo		ON rootPageTo.id = pageTo.cca_rr_root
														AND rootPageTo.id != rootPageFrom.id
														AND rootPageTo.cca_lr_group = rootPageFrom.cca_lr_group

LEFT JOIN	tl_cca_lr_relation		AS refl				ON refl.pageFrom = rel.pageTo
														AND refl.pageTo = rel.pageFrom

WHERE		rel.pageFrom IN ($wildcards)

GROUP BY	rootPageTo.id
HAVING		COUNT(rootPageTo.id) = 1

SQL;
		$result = \Database::getInstance()->prepare($sql)->execute($ids);

		while($result->next()) if(!$primary || $result->isPrimary) {
			$relations[$result->pageFrom][$result->rootPageTo] = $result->pageTo;
		}

		return is_array($pages) ? $relations : $relations[$pages];
	}

	/**
	 * Returns all pages of the root page tree of the given page, that are
	 * missing at least one language relation into another language of their
	 * root's translation group.
	 *
	 * The returned array is an identity map.
	 *
	 * @param integer $page
	 * @return array<integer, integer>
	 */
	public static function getIncompleteRelatedPages($page) {
		if($page < 1) {
			return array();
		}

		$sql = <<<SQL

SELECT		pageFrom.id				AS pageFrom

FROM 		tl_page					AS page

JOIN		tl_page					AS rootPageFrom		ON rootPageFrom.id = page.cca_rr_root
JOIN		tl_page					AS pageFrom			ON pageFrom.cca_rr_root = rootPageFrom.id
														AND pageFrom.id != rootPageFrom.id

LEFT JOIN	tl_page					AS groupRoots		ON groupRoots.cca_lr_group = rootPageFrom.cca_lr_group
														AND groupRoots.id != rootPageFrom.id
LEFT JOIN	(
			tl_cca_lr_relation		AS rel
	JOIN	tl_page					AS pageTo			ON pageTo.id = rel.pageTo
	JOIN	tl_page					AS rootPageTo		ON rootPageTo.id = pageTo.cca_rr_root
)														ON rel.pageFrom = pageFrom.id
														AND rootPageTo.id = groupRoots.id

WHERE		page.id = ?
AND			rel.pageTo IS NULL

GROUP BY	pageFrom.id

SQL;
		$result = \Database::getInstance()->prepare($sql)->execute($page);

		while($result->next()) {
			$incompletenesses[$result->pageFrom] = $result->pageFrom;
		}

		return (array) $incompletenesses;
	}

	/**
	 * Returns all pages of the root page tree of the given page, that contain
	 * multiple relations into the same language of their root's translation
	 * group. These relations are called ambiguous and will be ignored by
	 * ->getRelations.
	 *
	 * The returned array is an identity map.
	 *
	 * @param integer $page
	 * @return array<integer, integer>
	 */
	public static function getAmbiguousRelatedPages($page) {
		if($page < 1) {
			return array();
		}

		$sql = <<<SQL

SELECT		DISTINCT pageFrom.id AS pageFrom

FROM 		tl_page				AS page

JOIN		tl_page				AS rootPageFrom		ON rootPageFrom.id = page.cca_rr_root
JOIN		tl_page				AS pageFrom			ON pageFrom.cca_rr_root = rootPageFrom.id
													AND pageFrom.id != rootPageFrom.id

JOIN		tl_cca_lr_relation	AS rel				ON rel.pageFrom = pageFrom.id

JOIN		tl_page				AS pageTo			ON pageTo.id = rel.pageTo
JOIN		tl_page				AS rootPageTo		ON rootPageTo.id = pageTo.cca_rr_root
													AND rootPageTo.id != rootPageFrom.id
													AND rootPageTo.cca_lr_group = rootPageFrom.cca_lr_group

WHERE		page.id = ?

GROUP BY	pageFrom.id, rootPageTo.id
HAVING		COUNT(pageFrom.id) > 1

SQL;
		$result = \Database::getInstance()->prepare($sql)->execute($page);

		while($result->next()) {
			$ambiguities[$result->pageFrom] = $result->pageFrom;
		}

		return (array) $ambiguities;
	}

	/**
	 * Create relations between the pages that the given page is related to and
	 * the given page itself, if none exists for them in the given page'
	 * language already.
	 *
	 * CARE: Does not check the validity of the origin relations!
	 *
	 * @param integer $page
	 * @return integer
	 */
	public static function createReflectionRelations($page) {
		if($page < 1) {
			return 0;
		}

		$sql = <<<SQL

INSERT INTO	tl_cca_lr_relation
			(pageFrom, pageTo)

SELECT		rel.pageTo, rel.pageFrom

FROM		tl_cca_lr_relation	AS rel
JOIN		tl_page				AS pageFrom			ON pageFrom.id = rel.pageFrom
JOIN		tl_page				AS rootPageFrom		ON rootPageFrom.id = pageFrom.cca_rr_root

LEFT JOIN	(
			tl_cca_lr_relation	AS refl
	JOIN	tl_page				AS reflPageTo		ON reflPageTo.id = refl.pageTo
	JOIN	tl_page				AS reflRootPageTo	ON reflRootPageTo.id = reflPageTo.cca_rr_root
)													ON refl.pageFrom = rel.pageTo
													AND reflRootPageTo.id = rootPageFrom.id

WHERE		rel.pageFrom = ?
AND			refl.pageTo IS NULL

SQL;
		$result = \Database::getInstance()->prepare($sql)->executeUncached($page);

		return $result->affectedRows;
	}

	/**
	 * Create relations between the pages that the given page is related to and
	 * themselfs, if none exists for them in their respective languages already.
	 *
	 * CARE: Does not check the validity of the origin relations!
	 *
	 * @param integer $page
	 * @return integer
	 */
	public static function createIntermediateRelations($page) {
		if($page < 1) {
			return 0;
		}

		$sql = <<<SQL

INSERT INTO	tl_cca_lr_relation
			(pageFrom, pageTo)

SELECT		rel.pageTo, inter.pageTo

FROM		tl_cca_lr_relation	AS rel
JOIN		tl_cca_lr_relation	AS inter			ON inter.pageFrom = rel.pageFrom
													AND inter.pageTo != rel.pageTo

JOIN		tl_page				AS interPageTo		ON interPageTo.id = inter.pageTo
JOIN		tl_page				AS interRootPageTo	ON interRootPageTo.id = interPageTo.cca_rr_root

LEFT JOIN	(
			tl_cca_lr_relation	AS refl
	JOIN	tl_page				AS reflPageTo		ON reflPageTo.id = refl.pageTo
	JOIN	tl_page				AS reflRootPageTo	ON reflRootPageTo.id = reflPageTo.cca_rr_root
)													ON refl.pageFrom = rel.pageTo
													AND reflRootPageTo.id = interRootPageTo.id

WHERE		rel.pageFrom = ?
AND			refl.pageFrom IS NULL

SQL;
		$result = \Database::getInstance()->prepare($sql)->executeUncached($page);

		return $result->affectedRows;
	}

}
