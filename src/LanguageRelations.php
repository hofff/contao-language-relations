<?php

namespace Hofff\Contao\LanguageRelations;

/**
 * A relation in tl_hofff_page_translation is valid, if:
 * - pageFrom != pageTo (non identity)
 * - pageFrom->id.tl_page.hofff_root_page_id->id.tl_page.cca_lr_group
 * 		= pageTo->id.tl_page.hofff_root_page_id->id.tl_page.cca_lr_group
 * (the root pages belong to the same translation group)
 * - pageFrom->id.tl_page.hofff_root_page_id
 * 		!= pageTo->id.tl_page.hofff_root_page_id
 * (the root pages are not the same)
 *
 * A relation is primary, if:
 * - it is valid
 * - pageFrom = pageTo->pageFrom.tl_hofff_page_translation.pageTo (there is a link back)
 *
 * @author Oliver Hoff <oliver@hofff.com>
 */
class LanguageRelations {

	/**
	 * Get the valid and non-ambiguous relations for each given page ID.
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
		if(!$ids = self::ids($pages)) {
			return is_array($pages) ? array_fill_keys($pages, []) : [];
		}

		$wildcards = self::wildcards($ids);
		$sql = <<<SQL

SELECT		rel.pageFrom,
			rel.pageTo,
			rootPageTo.id			AS rootPageTo,
			refl.pageTo IS NOT NULL	AS isPrimary

FROM 		tl_hofff_page_translation		AS rel

JOIN		tl_page					AS pageFrom			ON pageFrom.id = rel.pageFrom
JOIN		tl_page					AS rootPageFrom		ON rootPageFrom.id = pageFrom.hofff_root_page_id

JOIN		tl_page					AS pageTo			ON pageTo.id = rel.pageTo
JOIN		tl_page					AS rootPageTo		ON rootPageTo.id = pageTo.hofff_root_page_id
														AND rootPageTo.id != rootPageFrom.id
														AND rootPageTo.cca_lr_group = rootPageFrom.cca_lr_group

LEFT JOIN	tl_hofff_page_translation		AS refl				ON refl.pageFrom = rel.pageTo
														AND refl.pageTo = rel.pageFrom

WHERE		rel.pageFrom IN ($wildcards)

GROUP BY	rel.pageFrom, rootPageTo.id
HAVING		COUNT(rel.pageTo) = 1

SQL;
		$result = self::query($sql, $ids);

		$relations = array_fill_keys((array) $pages, []);

		while($result->next()) if(!$primary || $result->isPrimary) {
			$relations[$result->pageFrom][$result->rootPageTo] = $result->pageTo;
		}

		return is_array($pages) ? $relations : $relations[$pages];
	}

	/**
	 * Returns all pages that maintain a valid relation to the given page.
	 *
	 * The returned array is an identity map.
	 *
	 * @param integer $page
	 * @return array<integer, integer>
	 */
	public static function getPagesRelatedTo($page) {
		if($page < 1) {
			return [];
		}

		$sql = <<<SQL

SELECT		rel.pageFrom

FROM 		tl_hofff_page_translation		AS rel

JOIN		tl_page					AS pageFrom			ON pageFrom.id = rel.pageFrom
JOIN		tl_page					AS rootPageFrom		ON rootPageFrom.id = pageFrom.hofff_root_page_id

JOIN		tl_page					AS pageTo			ON pageTo.id = rel.pageTo
JOIN		tl_page					AS rootPageTo		ON rootPageTo.id = pageTo.hofff_root_page_id
														AND rootPageTo.id != rootPageFrom.id
														AND rootPageTo.cca_lr_group = rootPageFrom.cca_lr_group

WHERE		rel.pageTo = ?

SQL;
		$result = self::query($sql, [ $page ]);

		$related = [];
		while($result->next()) {
			$related[$result->pageFrom] = $result->pageFrom;
		}

		return $related;
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
			return [];
		}

		$sql = <<<SQL

SELECT		pageFrom.id				AS pageFrom

FROM 		tl_page					AS page

JOIN		tl_page					AS rootPageFrom		ON rootPageFrom.id = page.hofff_root_page_id
JOIN		tl_page					AS pageFrom			ON pageFrom.hofff_root_page_id = rootPageFrom.id
														AND pageFrom.id != rootPageFrom.id

LEFT JOIN	tl_page					AS groupRoots		ON groupRoots.cca_lr_group = rootPageFrom.cca_lr_group
														AND groupRoots.id != rootPageFrom.id
LEFT JOIN	(
			tl_hofff_page_translation		AS rel
	JOIN	tl_page					AS pageTo			ON pageTo.id = rel.pageTo
	JOIN	tl_page					AS rootPageTo		ON rootPageTo.id = pageTo.hofff_root_page_id
)														ON rel.pageFrom = pageFrom.id
														AND rootPageTo.id = groupRoots.id

WHERE		page.id = ?
AND			rel.pageTo IS NULL

GROUP BY	pageFrom.id

SQL;
		$result = self::query($sql, [ $page ]);

		$incompletenesses = [];
		while($result->next()) {
			$incompletenesses[$result->pageFrom] = $result->pageFrom;
		}

		return $incompletenesses;
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
			return [];
		}

		$sql = <<<SQL

SELECT		DISTINCT pageFrom.id AS pageFrom

FROM 		tl_page				AS page

JOIN		tl_page				AS rootPageFrom		ON rootPageFrom.id = page.hofff_root_page_id
JOIN		tl_page				AS pageFrom			ON pageFrom.hofff_root_page_id = rootPageFrom.id
													AND pageFrom.id != rootPageFrom.id

JOIN		tl_hofff_page_translation	AS rel				ON rel.pageFrom = pageFrom.id

JOIN		tl_page				AS pageTo			ON pageTo.id = rel.pageTo
JOIN		tl_page				AS rootPageTo		ON rootPageTo.id = pageTo.hofff_root_page_id
													AND rootPageTo.id != rootPageFrom.id
													AND rootPageTo.cca_lr_group = rootPageFrom.cca_lr_group

WHERE		page.id = ?

GROUP BY	pageFrom.id, rootPageTo.id
HAVING		COUNT(pageFrom.id) > 1

SQL;
		$result = self::query($sql, [ $page ]);

		$ambiguities = [];
		while($result->next()) {
			$ambiguities[$result->pageFrom] = $result->pageFrom;
		}

		return $ambiguities;
	}

	/**
	 * Creates relations from the given page to the given pages.
	 *
	 * CARE: Does not check the validity of the origin relations!
	 *
	 * @param integer $pageFrom
	 * @param integer|array<integer> $pagesTo
	 * @return integer The number of created relations
	 */
	public static function createRelations($pageFrom, $pagesTo) {
		if($pageFrom < 1 || !$pagesTo = self::ids($pagesTo)) {
			return 0;
		}

		$sql = 'INSERT INTO tl_hofff_page_translation (pageFrom, pageTo) VALUES ' . self::wildcards($pagesTo, '(?,?)');
		$params = [];
		foreach($pagesTo as $pageTo) {
			$params[] = $pageFrom;
			$params[] = $pageTo;
		}
		$result = self::query($sql, $params);

		return $result->affectedRows;
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

INSERT INTO	tl_hofff_page_translation
			(pageFrom, pageTo)

SELECT		rel.pageTo, rel.pageFrom

FROM		tl_hofff_page_translation	AS rel
JOIN		tl_page				AS pageFrom			ON pageFrom.id = rel.pageFrom
JOIN		tl_page				AS rootPageFrom		ON rootPageFrom.id = pageFrom.hofff_root_page_id

LEFT JOIN	(
			tl_hofff_page_translation	AS refl
	JOIN	tl_page				AS reflPageTo		ON reflPageTo.id = refl.pageTo
	JOIN	tl_page				AS reflRootPageTo	ON reflRootPageTo.id = reflPageTo.hofff_root_page_id
)													ON refl.pageFrom = rel.pageTo
													AND reflRootPageTo.id = rootPageFrom.id

WHERE		rel.pageFrom = ?
AND			refl.pageTo IS NULL

SQL;
		$result = self::query($sql, [ $page ]);

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

INSERT INTO	tl_hofff_page_translation
			(pageFrom, pageTo)

SELECT		rel.pageTo, inter.pageTo

FROM		tl_hofff_page_translation	AS rel
JOIN		tl_hofff_page_translation	AS inter			ON inter.pageFrom = rel.pageFrom
													AND inter.pageTo != rel.pageTo

JOIN		tl_page				AS interPageTo		ON interPageTo.id = inter.pageTo
JOIN		tl_page				AS interRootPageTo	ON interRootPageTo.id = interPageTo.hofff_root_page_id

LEFT JOIN	(
			tl_hofff_page_translation	AS refl
	JOIN	tl_page				AS reflPageTo		ON reflPageTo.id = refl.pageTo
	JOIN	tl_page				AS reflRootPageTo	ON reflRootPageTo.id = reflPageTo.hofff_root_page_id
)													ON refl.pageFrom = rel.pageTo
													AND reflRootPageTo.id = interRootPageTo.id

WHERE		rel.pageFrom = ?
AND			refl.pageFrom IS NULL

SQL;
		$result = self::query($sql, [ $page ]);

		return $result->affectedRows;
	}

	/**
	 * Deletes all relations that orignate at one of the given pages.
	 *
	 * @param integer|array<integer> $pages
	 * @return integer
	 */
	public static function deleteRelationsFrom($pages) {
		if(!$pages = self::ids($pages)) {
			return 0;
		}

		$sql = 'DELETE FROM tl_hofff_page_translation WHERE pageFrom IN (' . self::wildcards($pages) . ')';
		$result = self::query($sql, $pages);

		return $result->affectedRows;
	}

	/**
	 * Deletes all relations of the given pages into the root page tree of the
	 * given root.
	 *
	 * @param integer|array<integer> $pages
	 * @param integer $root
	 * @return integer
	 */
	public static function deleteRelationsToRoot($pages, $root) {
		if($root < 1 || !$pages = self::ids($pages)) {
			return 0;
		}

		$wildcards = self::wildcards($pages);
		$sql = <<<SQL

DELETE		rel

FROM		tl_hofff_page_translation	AS rel
JOIN		tl_page				AS relPageTo		ON relPageTo.id = rel.pageTo
JOIN		tl_page				AS page				ON page.hofff_root_page_id = relPageTo.hofff_root_page_id

WHERE		rel.pageFrom IN ($wildcards)
AND			page.id = ?

SQL;
		$params = $pages;
		$params[] = $root;
		$result = self::query($sql, $params);

		return $result->affectedRows;
	}

	/**
	 * @param string $sql
	 * @param array $params
	 * @return Result
	 */
	private static function query($sql, array $params = null) {
		return \Database::getInstance()->prepare($sql)->executeUncached($params);
	}

	/**
	 * @param mixed $params
	 * @param string $wildcard
	 * @return string
	 */
	private static function wildcards($params, $wildcard = '?') {
		return rtrim(str_repeat($wildcard . ',', count((array) $params)), ',');
	}

	/**
	 * @param integer|array<integer> $ids
	 * @return array<integer>
	 */
	private static function ids($ids) {
		return array_filter((array) $ids, function($id) { return $id >= 1; });
	}

}
