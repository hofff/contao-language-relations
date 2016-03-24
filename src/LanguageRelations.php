<?php

namespace Hofff\Contao\LanguageRelations;

/**
 * A relation in tl_hofff_page_translation is valid, if:
 * - page_id != translated_page_id (non identity)
 * - page_id->id.tl_page.hofff_root_page_id->id.tl_page.hofff_translation_group_id
 * 		= translated_page_id->id.tl_page.hofff_root_page_id->id.tl_page.hofff_translation_group_id
 * (the root pages belong to the same translation group)
 * - page_id->id.tl_page.hofff_root_page_id
 * 		!= translated_page_id->id.tl_page.hofff_root_page_id
 * (the root pages are not the same)
 *
 * A relation is primary, if:
 * - it is valid
 * - page_id = translated_page_id->page_id.tl_hofff_page_translation.translated_page_id (there is a link back)
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
SELECT
	page_id						AS page_id,
	MAX(translated_page_id)		AS translated_page_id,
	translated_root_page_id		AS translated_root_page_id,
	MAX(is_primary)				AS is_primary
FROM
	hofff_page_translation_valid
WHERE
	page_id IN ($wildcards)
GROUP BY
	page_id,
	translated_root_page_id
HAVING
	COUNT(translated_page_id) = 1
SQL;
		$result = self::query($sql, $ids);

		$relations = array_fill_keys((array) $pages, []);

		while($result->next()) if(!$primary || $result->is_primary) {
			$relations[$result->page_id][$result->translated_root_page_id] = $result->translated_page_id;
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
SELECT
	page_id
FROM
	hofff_page_translation_valid
WHERE
	translated_page_id = ?
SQL;
		$result = self::query($sql, [ $page ]);

		$related = [];
		while($result->next()) {
			$related[$result->page_id] = $result->page_id;
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
SELECT
	page.id						AS page_id,
	group_root_page.id			AS missing_root_page_id,
	group_root_page.language	AS missing_root_page_language
FROM
	tl_page
	AS root_page
JOIN
	tl_page
	AS page
	ON page.hofff_root_page_id = root_page.id
	AND page.id != root_page.id
LEFT JOIN
	tl_page
	AS group_root_page
	ON group_root_page.hofff_translation_group_id = root_page.hofff_translation_group_id
	AND group_root_page.id != root_page.id
	AND group_root_page.type = 'root'
LEFT JOIN
	hofff_page_translation_valid
	AS translation
	ON translation.page_id = page.id
	AND translation.translated_root_page_id = group_root_page.id
WHERE
	root_page.id IN (
		SELECT
			page_1.hofff_root_page_id
		FROM
			tl_page
			AS page_1
		WHERE
			page_1.id = ?
	)
	AND translation.page_id IS NULL
SQL;
		$result = self::query($sql, [ $page ]);

		$incompletenesses = [];
		while($result->next()) {
			$incompletenesses[$result->page_id] = $result->page_id;
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
SELECT DISTINCT
	translation.page_id		AS page_id
FROM
	hofff_page_translation_valid
	AS translation
WHERE
	translation.root_page_id IN (
		SELECT
			page_1.hofff_root_page_id
		FROM
			tl_page
			AS page_1
		WHERE
			page_1.id = ?
	)
	AND translation.page_id != translation.root_page_id
GROUP BY
	translation.page_id,
	translation.translated_root_page_id
HAVING
	COUNT(translation.page_id) > 1
SQL;
		$result = self::query($sql, [ $page ]);

		$ambiguities = [];
		while($result->next()) {
			$ambiguities[$result->page_id] = $result->page_id;
		}

		return $ambiguities;
	}

	/**
	 * Creates relations from the given page to the given pages.
	 *
	 * CARE: Does not check the validity of the given relations!
	 *
	 * @param integer $page
	 * @param integer|array<integer> $translatedPages
	 * @return integer The number of created relations
	 */
	public static function createRelations($page, $translatedPages) {
		if($page < 1 || !$translatedPages = self::ids($translatedPages)) {
			return 0;
		}

		$sql = 'INSERT INTO tl_hofff_page_translation (page_id, translated_page_id) VALUES ' . self::wildcards($translatedPages, '(?,?)');
		$params = [];
		foreach($translatedPages as $translatedPage) {
			$params[] = $page;
			$params[] = $translatedPage;
		}
		$result = self::query($sql, $params);

		return $result->affectedRows;
	}

	/**
	 * Create relations between the pages that the given page is related to and
	 * the given page itself, if none exists for them in the given page's root
	 * page tree already.
	 *
	 * @param integer $page
	 * @return integer
	 */
	public static function createReflectionRelations($page) {
		if($page < 1) {
			return 0;
		}

		$sql = <<<SQL
INSERT INTO
	tl_hofff_page_translation
	(page_id, translated_page_id)
SELECT
	translation.translated_page_id,
	translation.page_id
FROM
	hofff_page_translation_valid
	AS translation
LEFT JOIN
	hofff_page_translation_valid
	AS reflected_translation
	ON reflected_translation.page_id = translation.translated_page_id
	AND reflected_translation.translated_root_page_id = translation.root_page_id
WHERE
	translation.page_id = ?
	AND reflected_translation.page_id IS NULL
SQL;
		$result = self::query($sql, [ $page ]);

		return $result->affectedRows;
	}

	/**
	 * Create relations between the pages that the given page is related to and
	 * themselfs, if none exists for them in their respective languages already.
	 *
	 * @param integer $page
	 * @return integer
	 */
	public static function createIntermediateRelations($page) {
		if($page < 1) {
			return 0;
		}

		$sql = <<<SQL
INSERT INTO
	tl_hofff_page_translation
	(page_id, translated_page_id)
SELECT
	left_translation.translated_page_id,
	right_translation.translated_page_id
FROM
	hofff_page_translation_valid
	AS left_translation
JOIN
	hofff_page_translation_valid
	AS right_translation
	ON right_translation.page_id = left_translation.page_id
	AND right_translation.translated_page_id != left_translation.translated_page_id
LEFT JOIN
	hofff_page_translation_valid
	AS reflected_translation
	ON reflected_translation.page_id = left_translation.translated_page_id
	AND reflected_translation.translated_root_page_id = right_translation.translated_root_page_id
WHERE
	reflected_translation.page_id IS NULL
	AND left_translation.page_id = ?
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

		$sql = 'DELETE FROM tl_hofff_page_translation WHERE page_id IN (' . self::wildcards($pages) . ')';
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
DELETE
	translation
FROM
	tl_hofff_page_translation
	AS translation
JOIN
	tl_page
	AS translated_page
	ON translated_page.id = translation.translated_page_id
WHERE
	translation.page_id IN ($wildcards)
	AND translated_page.hofff_root_page_id IN (
		SELECT
			page_1.hofff_root_page_id
		FROM
			tl_page
			AS page_1
		WHERE
			page_1.id = ?
	)
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
