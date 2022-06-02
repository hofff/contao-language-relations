<?php

declare(strict_types=1);

namespace Hofff\Contao\LanguageRelations\Database;

use Doctrine\DBAL\Schema\View;

final class Schema
{
    private const ITEM_VIEW = <<<SQL
SELECT
	root_page.hofff_language_relations_group_id		AS group_id,
	root_page.id									AS root_page_id,
	page.id											AS page_id,
	page.id											AS item_id
FROM
	tl_page
	AS page
JOIN
	tl_page
	AS root_page
	ON root_page.id = page.hofff_root_page_id
	AND root_page.id != page.id
	AND root_page.type = 'root'
SQL;

    private const RELATION_VIEW = <<<SQL
SELECT
	item.group_id											AS group_id,
	item.root_page_id										AS root_page_id,
	item.page_id											AS page_id,
	item.item_id											AS item_id,
	related_item.item_id									AS related_item_id,
	related_item.page_id									AS related_page_id,
	related_item.root_page_id								AS related_root_page_id,
	related_item.group_id									AS related_group_id,
	item.root_page_id != related_item.root_page_id
		AND item.group_id = related_item.group_id			AS is_valid,
	reflected_relation.item_id IS NOT NULL					AS is_primary

FROM
	tl_hofff_language_relations_page
	AS relation
JOIN
	hofff_language_relations_page_item
	AS item
	ON item.item_id = relation.item_id
JOIN
	hofff_language_relations_page_item
	AS related_item
	ON related_item.item_id = relation.related_item_id

LEFT JOIN
	tl_hofff_language_relations_page
	AS reflected_relation
	ON reflected_relation.item_id = relation.related_item_id
	AND reflected_relation.related_item_id = relation.item_id
SQL;

    private const AGGREGATE_VIEW = <<<SQL
SELECT
	root_page.id				AS aggregate_id,
	root_page.id				AS tree_root_id,
	root_page.id				AS root_page_id,
	grp.id						AS group_id,
	grp.title					AS group_title,
	root_page.language			AS language
FROM
	tl_page
	AS root_page
JOIN
	tl_hofff_language_relations_group
	AS grp
	ON grp.id = root_page.hofff_language_relations_group_id
WHERE
	root_page.type = 'root'
SQL;

    private const TREE_VIEW = <<<SQL
SELECT
	page.pid										AS pid,
	page.id											AS id,
	page.title										AS title,
	page.type != 'root'								AS selectable,
	root_page.hofff_language_relations_group_id		AS group_id,
	root_page.language								AS language,
	page.sorting									AS sorting,
	page.type										AS type,
	page.published									AS published,
	page.start										AS start,
	page.stop										AS stop,
	page.hide										AS hide,
	page.protected									AS protected
FROM
	tl_page
	AS page
JOIN
	tl_page
	AS root_page
	ON root_page.id = page.hofff_root_page_id
SQL;

    /** @var list<View> */
    private array $views;

    public function __construct()
    {
        $this->views = [
            new View('hofff_language_relations_page_item', self::ITEM_VIEW),
            new View('hofff_language_relations_page_relation', self::RELATION_VIEW),
            new View('hofff_language_relations_page_aggregate', self::AGGREGATE_VIEW),
            new VIEW('hofff_language_relations_page_tree', self::TREE_VIEW),
        ];
    }

    /** @return iterable<View> */
    public function views(): iterable
    {
        return $this->views;
    }
}
