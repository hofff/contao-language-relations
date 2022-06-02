<?php

declare(strict_types=1);

namespace Hofff\Contao\LanguageRelations;

use function array_combine;
use function array_keys;

/**
 * A relation in tl_hofff_language_relations_page is valid, if:
 * - page_id != translated_page_id (non identity)
 * - page_id->id.tl_page.hofff_root_page_id->id.tl_page.hofff_language_relations_group_id
 *      = translated_page_id->id.tl_page.hofff_root_page_id->id.tl_page.hofff_language_relations_group_id
 * (the root pages belong to the same translation group)
 * - page_id->id.tl_page.hofff_root_page_id
 *      != translated_page_id->id.tl_page.hofff_root_page_id
 * (the root pages are not the same)
 *
 * A relation is primary, if:
 * - it is valid
 * - page_id = translated_page_id->page_id.tl_hofff_language_relations_page.translated_page_id (there is a link back)
 *
 * @deprecated
 */
class LanguageRelations
{
    private static Relations $relations;

    public static function getRelationsInstance(): Relations
    {
        isset(self::$relations) || self::$relations = new Relations(
            'tl_hofff_language_relations_page',
            'hofff_language_relations_page_item',
            'hofff_language_relations_page_relation'
        );

        return self::$relations;
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
     * @param int|array<int> $pages
     * @param bool           $primary
     *
     * @return int[]|array[]
     */
    public static function getRelations($pages, $primary = false): array
    {
        return self::getRelationsInstance()->getRelations($pages, $primary);
    }

    /**
     * Returns all pages that maintain a valid relation to the given page.
     *
     * The returned array is an identity map.
     *
     * @return int[]
     */
    public static function getPagesRelatedTo(int $page): array
    {
        return self::getRelationsInstance()->getItemsRelatedTo($page);
    }

    /**
     * Returns all pages of the root page tree of the given page, that are
     * missing at least one language relation into another language of their
     * root's translation group.
     *
     * The returned array is an identity map.
     *
     * @return int[]
     */
    public static function getIncompleteRelatedPages(int $page): array
    {
        $incompletenesses = self::getRelationsInstance()->getIncompleteRelatedItems($page);
        $incompletenesses = array_keys($incompletenesses);
        $incompletenesses = array_combine($incompletenesses, $incompletenesses);

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
    public static function getAmbiguousRelatedPages(int $page): array
    {
        return self::getRelationsInstance()->getAmbiguousRelatedItems($page);
    }

    /**
     * Creates relations from the given page to the given pages.
     *
     * CARE: Does not check the validity of the given relations!
     *
     * @param int|array<int> $translatedPages
     *
     * @return int The number of created relations
     */
    public static function createRelations(int $page, $translatedPages): int
    {
        return self::getRelationsInstance()->createRelations($page, $translatedPages);
    }

    /**
     * Create relations between the pages that the given page is related to and
     * the given page itself, if none exists for them in the given page's root
     * page tree already.
     */
    public static function createReflectionRelations(int $page): int
    {
        return self::getRelationsInstance()->createReflectionRelations($page);
    }

    /**
     * Create relations between the pages that the given page is related to and
     * themselfs, if none exists for them in their respective languages already.
     */
    public static function createIntermediateRelations(int $page): int
    {
        return self::getRelationsInstance()->createIntermediateRelations($page);
    }

    /**
     * Deletes all relations that orignate at one of the given pages.
     *
     * @param int|array<int> $pages
     */
    public static function deleteRelationsFrom($pages): int
    {
        return self::getRelationsInstance()->deleteRelationsFrom($pages);
    }

        /**
         * Deletes all relations of the given pages into the root page tree of the
         * given root.
         *
         * @param int|array<int> $pages
         * @param int            $root
         */
    public static function deleteRelationsToRoot($pages, $root): int
    {
        return self::getRelationsInstance()->deleteRelationsToRoot($pages, $root);
    }
}
