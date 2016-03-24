<?php

$sql = <<<SQL

CREATE OR REPLACE VIEW hofff_page_translation_valid AS

SELECT		root_page.hofff_translation_group_id					AS translation_group_id,
			root_page.id											AS root_page_id,
			translation.page_id										AS page_id,
			translation.translated_page_id							AS translated_page_id,
			translated_root_page.hofff_root_page_id					AS translated_root_page_id,
			reflected_translation.translated_page_id IS NOT NULL	AS is_primary

FROM		tl_hofff_page_translation
			AS translation

JOIN		tl_page
			AS page
			ON page.id = translation.page_id

JOIN		tl_page
			AS root_page
			ON root_page.id = page.hofff_root_page_id

JOIN		tl_page
			AS translated_page
			ON translated_page.id = translation.translated_page_id

JOIN		tl_page
			AS translated_root_page
			ON translated_root_page.id = translated_page.hofff_root_page_id
			AND translated_root_page.id != root_page.id
			AND translated_root_page.hofff_translation_group_id = root_page.hofff_translation_group_id

LEFT JOIN	tl_hofff_page_translation
			AS reflected_translation
			ON reflected_translation.page_id = translation.translated_page_id
			AND reflected_translation.translated_page_id = translation.page_id

SQL;
Database::getInstance()->query($sql);

/*
 * this view resolves the translations with fallbacks and returns everything
 * to generate URLs for each page
 *
 * this view should only be used for development purposes
 */
$sql = <<<SQL

CREATE OR REPLACE VIEW hofff_page_translation_resolved AS

SELECT		root_page.hofff_translation_group_id				AS translation_group_id,
			translation_group.title								AS translation_group_title,
			root_page.id										AS root_page_id,
			root_page.alias										AS root_page_alias,
			root_page.language									AS root_page_language,
			root_page.title										AS root_page_title,
			root_page.dns										AS root_page_dns,
			translated_root_page.id								AS translated_root_page_id,
			translated_root_page.alias							AS translated_root_page_alias,
			translated_root_page.language						AS translated_root_page_language,
			translated_root_page.title							AS translated_root_page_title,
			translated_root_page.dns							AS translated_root_page_dns,
			page.id												AS page_id,
			page.type											AS page_type,
			page.alias											AS page_alias,
			page.title											AS page_title,
			translated_page.id									AS translated_page_id,
			translated_page.type								AS translated_page_type,
			translated_page.alias								AS translated_page_alias,
			translated_page.title								AS translated_page_title,
			reflected_translated_page.id						AS reflected_translated_page_id,
			reflected_translated_page.type						AS reflected_translated_page_type,
			reflected_translated_page.alias						AS reflected_translated_page_alias,
			reflected_translated_page.title						AS reflected_translated_page_title,
			reflected_translated_page.id <=> page.id			AS is_primary,
			resolved_translated_page.id							AS resolved_translated_page_id,
			resolved_translated_page.type						AS resolved_translated_page_type,
			resolved_translated_page.alias						AS resolved_translated_page_alias,
			resolved_translated_page.title						AS resolved_translated_page_title,
			resolved_reflected_translated_page.id				AS resolved_reflected_translated_page_id,
			resolved_reflected_translated_page.type				AS resolved_reflected_translated_page_type,
			resolved_reflected_translated_page.alias			AS resolved_reflected_translated_page_alias,
			resolved_reflected_translated_page.title			AS resolved_reflected_translated_page_title,
			resolved_reflected_translated_page.id = page.id		AS resolved_is_primary

FROM		tl_page
			AS page

JOIN		tl_page
			AS root_page
			ON root_page.id = page.hofff_root_page_id

JOIN		tl_page
			AS translated_root_page
			ON translated_root_page.hofff_translation_group_id = root_page.hofff_translation_group_id
			AND translated_root_page.id != root_page.id
			AND translated_root_page.type = 'root'

LEFT JOIN	hofff_page_translation_valid
			AS translation
			ON translation.page_id = page.id
			AND translation.page_id != translation.root_page_id
			AND translation.translated_root_page_id = translated_root_page.id

LEFT JOIN	tl_page
			AS translated_page
			ON translated_page.id = translation.translated_page_id

LEFT JOIN	hofff_page_translation_valid
			AS reflected_translation
			ON reflected_translation.page_id = translation.translated_page_id
			AND reflected_translation.page_id != reflected_translation.root_page_id
			AND reflected_translation.translated_root_page_id = root_page.id

LEFT JOIN	tl_page
			AS reflected_translated_page
			ON reflected_translated_page.id = reflected_translation.translated_page_id

LEFT JOIN	tl_page
			AS resolved_translated_page
			ON resolved_translated_page.id = COALESCE(translated_page.id, translated_root_page.id)

LEFT JOIN	tl_page
			AS resolved_reflected_translated_page
			ON resolved_reflected_translated_page.id = COALESCE(reflected_translated_page.id, root_page.id)

LEFT JOIN	tl_hofff_translation_group
			AS translation_group
			ON translation_group.id = root_page.hofff_translation_group_id

ORDER BY	translation_group.title,
			root_page.hofff_translation_group_id,
			root_page.language,
			root_page.id,
			page.id,
			translated_root_page.language,
			translated_root_page.id

SQL;
Database::getInstance()->query($sql);
