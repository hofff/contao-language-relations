<?php

declare(strict_types=1);

namespace Hofff\Contao\LanguageRelations;

use Hofff\Contao\LanguageRelations\Util\QueryUtil;

use function array_fill_keys;
use function array_filter;
use function array_map;
use function is_array;

class Relations
{
    protected string $relationTable;

    protected string $itemView;

    protected string $relationView;

    public function __construct(string $relationTable, string $itemView, string $relationView)
    {
        $this->relationTable = $relationTable;
        $this->itemView      = $itemView;
        $this->relationView  = $relationView;
    }

    public function getRelationTable(): string
    {
        return $this->relationTable;
    }

    public function getItemView(): string
    {
        return $this->itemView;
    }

    public function getRelationView(): string
    {
        return $this->relationView;
    }

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
     * @param string|int|array<string|int> $items
     * @param bool                         $primary
     * @param bool                         $complete
     *
     * @return array<int,int>|array<array<int,int>>
     */
    public function getRelations($items, $primary = false, $complete = false): array
    {
        $ids = QueryUtil::ids($items);
        if (! $ids) {
            return is_array($items) ? array_fill_keys($items, []) : [];
        }

        $sql    = <<<SQL
SELECT
	item.item_id					AS item_id,
	MAX(relation.related_item_id)	AS related_item_id,
	root_page.id					AS related_root_page_id,
	MAX(relation.is_primary)		AS is_primary
FROM
	%s
	AS item
JOIN
	tl_page
	AS root_page
	ON root_page.hofff_language_relations_group_id = item.group_id
	AND root_page.id != item.root_page_id
	AND root_page.type = 'root'
LEFT JOIN
	%s
	AS relation
	ON relation.item_id = item.item_id
	AND relation.related_root_page_id = root_page.id
	AND relation.is_valid
WHERE
	item.item_id IN (%s)
GROUP BY
	item.item_id,
	root_page.id
HAVING
	COUNT(relation.related_item_id) = 1
SQL;
        $result = QueryUtil::query(
            $sql,
            [
                $this->itemView,
                $this->relationView,
                QueryUtil::wildcards($ids),
            ],
            $ids
        );

        $relations = array_fill_keys(array_map('intval', (array) $items), []);

        /** @psalm-suppress PossiblyUndefinedMethod */
        while ($result->next()) {
            if ($primary && ! $result->is_primary) {
                continue;
            }

            $relations[(int) $result->item_id][(int) $result->related_root_page_id] = (int) $result->related_item_id;
        }

        if (! $complete) {
            foreach ($relations as &$map) {
                $map = array_filter($map);
            }

            unset($map);
        }

        return is_array($items) ? $relations : ($relations[$items] ?? []);
    }

    /**
     * Returns all pages that maintain a valid relation to the given page.
     *
     * The returned array is an identity map.
     *
     * @return int[]
     */
    public function getItemsRelatedTo(int $item): array
    {
        if ($item < 1) {
            return [];
        }

        $sql    = 'SELECT item_id FROM %s WHERE related_item_id = ?';
        $result = QueryUtil::query(
            $sql,
            [$this->relationView],
            [$item]
        );

        $related = [];
        /** @psalm-suppress PossiblyUndefinedMethod */
        while ($result->next()) {
            $related[$result->item_id] = $result->item_id;
        }

        return $related;
    }

    /**
     * Returns all items of the root page tree of the given page, that are
     * missing at least one relation into another root of their relation group.
     *
     * The returned array is a map of item IDs to a map of missing root IDs to
     * the roots language.
     *
     * @return int[][]
     */
    public function getIncompleteRelatedItems(int $page): array
    {
        if ($page < 1) {
            return [];
        }

        $sql    = <<<SQL
SELECT
	item.item_id				AS item_id,
	group_root_page.id			AS missing_root_page_id,
	group_root_page.language	AS missing_root_page_language
FROM
	%s
	AS item
JOIN
	tl_page
	AS group_root_page
	ON group_root_page.hofff_language_relations_group_id = item.group_id
	AND group_root_page.id != item.root_page_id
	AND group_root_page.type = 'root'
LEFT JOIN
	%s
	AS relation
	ON relation.item_id = item.item_id
	AND relation.is_valid
	AND relation.related_root_page_id = group_root_page.id
WHERE
	item.root_page_id IN (
		SELECT
			page_1.hofff_root_page_id
		FROM
			tl_page
			AS page_1
		WHERE
			page_1.id = ?
	)
	AND relation.item_id IS NULL
SQL;
        $result = QueryUtil::query(
            $sql,
            [
                $this->itemView,
                $this->relationView,
            ],
            [$page]
        );

        $incompletenesses = [];
        /** @psalm-suppress PossiblyUndefinedMethod */
        while ($result->next()) {
            $incompletenesses[$result->item_id][$result->missing_root_page_id] = $result->missing_root_page_language;
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
     * @return int[]
     */
    public function getAmbiguousRelatedItems(int $page): array
    {
        if ($page < 1) {
            return [];
        }

        $sql    = <<<SQL
SELECT DISTINCT
	item_id
FROM
	%s
WHERE
	root_page_id IN (
		SELECT
			page_1.hofff_root_page_id
		FROM
			tl_page
			AS page_1
		WHERE
			page_1.id = ?
	)
	AND is_valid
	AND item_id != root_page_id
GROUP BY
	item_id,
	related_root_page_id
HAVING
	COUNT(related_item_id) > 1
SQL;
        $result = QueryUtil::query(
            $sql,
            [$this->relationView],
            [$page]
        );

        $ambiguities = [];
        /** @psalm-suppress PossiblyUndefinedMethod */
        while ($result->next()) {
            $ambiguities[$result->item_id] = $result->item_id;
        }

        return $ambiguities;
    }

    /**
     * Creates relations from the given item to the given related items.
     *
     * CARE: Does not check the validity of the given relations!
     *
     * @param array<array-key,int|numeric-string> $relatedItems
     *
     * @return int The number of created relations
     */
    public function createRelations(int $item, $relatedItems): int
    {
        if ($item < 1) {
            return 0;
        }

        $relatedItems = QueryUtil::ids($relatedItems);
        if (! $relatedItems) {
            return 0;
        }

        $sql = 'INSERT INTO %s (item_id, related_item_id) VALUES %s';

        $params = [];
        foreach ($relatedItems as $relatedItem) {
            $params[] = $item;
            $params[] = $relatedItem;
        }

        $result = QueryUtil::exec(
            $sql,
            [
                $this->relationTable,
                QueryUtil::wildcards($relatedItems, '(?,?)'),
            ],
            $params
        );

        return $result->affectedRows;
    }

    /**
     * Create relations between the items that the given item is related to and
     * the given item itself, if none exists for them in the given item's
     * relation root already.
     */
    public function createReflectionRelations(int $item): int
    {
        if ($item < 1) {
            return 0;
        }

        $sql    = <<<SQL
INSERT INTO
	%s
	(item_id, related_item_id)
SELECT
	relation.related_item_id,
	relation.item_id
FROM
	%s
	AS relation
LEFT JOIN
	%s
	AS reflected_relation
	ON reflected_relation.item_id = relation.related_item_id
	AND reflected_relation.related_root_page_id = relation.root_page_id
WHERE
	relation.item_id = ?
	AND relation.is_valid
	AND reflected_relation.item_id IS NULL
SQL;
        $result = QueryUtil::exec(
            $sql,
            [
                $this->relationTable,
                $this->relationView,
                $this->relationView,
            ],
            [$item]
        );

        return $result->affectedRows;
    }

    /**
     * Create relations between the items that the given item is related to and
     * themselfs, if none exists for them in their respective relation roots
     * already.
     */
    public function createIntermediateRelations(int $item): int
    {
        if ($item < 1) {
            return 0;
        }

        $sql    = <<<SQL
INSERT INTO
	%s
	(item_id, related_item_id)
SELECT
	left_relation.related_item_id,
	right_relation.related_item_id
FROM
	%s
	AS left_relation
JOIN
	%s
	AS right_relation
	ON right_relation.item_id = left_relation.item_id
	AND right_relation.is_valid
	AND right_relation.related_item_id != left_relation.related_item_id
LEFT JOIN
	%s
	AS intermediate_relation
	ON intermediate_relation.item_id = left_relation.related_item_id
	AND intermediate_relation.is_valid
	AND intermediate_relation.related_root_page_id = right_relation.related_root_page_id
WHERE
	left_relation.item_id = ?
	AND left_relation.is_valid
	AND intermediate_relation.item_id IS NULL
SQL;
        $result = QueryUtil::exec(
            $sql,
            [
                $this->relationTable,
                $this->relationView,
                $this->relationView,
                $this->relationView,
            ],
            [$item]
        );

        return $result->affectedRows;
    }

    /**
     * Deletes all relations that orignate at one of the given items.
     *
     * @param int|int[] $items
     */
    public function deleteRelationsFrom($items): int
    {
        $items = QueryUtil::ids($items);
        if (! $items) {
            return 0;
        }

        $sql    = 'DELETE FROM %s WHERE item_id IN (%s)';
        $result = QueryUtil::exec(
            $sql,
            [
                $this->relationTable,
                QueryUtil::wildcards($items),
            ],
            $items
        );

        return $result->affectedRows;
    }

    /**
     * Deletes all relations of the given items into the relation root of the
     * given page.
     *
     * @param int|numeric-string|list<int|numeric-string> $items
     * @param int                                         $page
     */
    public function deleteRelationsToRoot($items, $page): int
    {
        if ($page < 1) {
            return 0;
        }

        $items = QueryUtil::ids($items);
        if (! $items) {
            return 0;
        }

        $sql      = <<<SQL
DELETE
	relation
FROM
	%s
	AS relation
JOIN
	%s
	AS related_item
	ON related_item.item_id = relation.related_item_id
WHERE
	relation.item_id IN (%s)
	AND related_item.root_page_id IN (
		SELECT
			page_1.hofff_root_page_id
		FROM
			tl_page
			AS page_1
		WHERE
			page_1.id = ?
	)
SQL;
        $params   = $items;
        $params[] = $page;
        $result   = QueryUtil::exec(
            $sql,
            [
                $this->relationTable,
                $this->itemView,
                QueryUtil::wildcards($items),
            ],
            $params
        );

        return $result->affectedRows;
    }
}
