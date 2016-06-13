<?php

namespace Hofff\Contao\LanguageRelations\Database;

use Hofff\Contao\LanguageRelations\Util\StringUtil;

/**
 * @author Oliver Hoff <oliver@hofff.com>
 */
class Installer {

	/**
	 * @param array $queries
	 * @return void
	 */
	public function hookSQLCompileCommands($queries) {
		$tables = array_flip(\Database::getInstance()->listTables(null, true));

		if(!isset($tables['hofff_language_relations_page_item'])) {
			$queries['ALTER_CHANGE'][] = StringUtil::tabsToSpaces($this->getItemView());
		}
		if(!isset($tables['hofff_language_relations_page_relation'])) {
			$queries['ALTER_CHANGE'][] = StringUtil::tabsToSpaces($this->getRelationView());
		}
		if(!isset($tables['hofff_language_relations_page_aggregate'])) {
			$queries['ALTER_CHANGE'][] = StringUtil::tabsToSpaces($this->getAggregateView());
		}
		if(!isset($tables['hofff_language_relations_page_tree'])) {
			$queries['ALTER_CHANGE'][] = StringUtil::tabsToSpaces($this->getTreeView());
		}

		return $queries;
	}

	/**
	 * @return string
	 */
	protected function getItemView() {
		return <<<SQL
CREATE OR REPLACE VIEW hofff_language_relations_page_item AS

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
SQL;
	}

	/**
	 * @return string
	 */
	protected function getRelationView() {
		return <<<SQL
CREATE OR REPLACE VIEW hofff_language_relations_page_relation AS

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
	}

	/**
	 * @return string
	 */
	protected function getAggregateView() {
		return <<<SQL
CREATE OR REPLACE VIEW hofff_language_relations_page_aggregate AS

SELECT
	root_page.id				AS aggregate_id,
	root_page.id				AS tree_root_id,
	root_page.id				AS root_page_id,
	grp.id						AS group_id,
	grp.title					AS group_title
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
	}
	/**
	 * @return string
	 */
	protected function getTreeView() {
		return <<<SQL
CREATE OR REPLACE VIEW hofff_language_relations_page_tree AS

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
	}

}
