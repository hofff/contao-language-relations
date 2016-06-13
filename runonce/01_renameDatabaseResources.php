<?php

$db = Database::getInstance();

if($db->tableExists('tl_cca_lr_group', null, true)) {
	$db->query('ALTER TABLE tl_cca_lr_group RENAME tl_hofff_language_relations_group');
}

if($db->tableExists('tl_cca_lr_relation', null, true)) {
	$db->query('ALTER TABLE tl_cca_lr_relation RENAME tl_hofff_language_relations_page');
}

if($db->tableExists('tl_page', null, true)) {
	if($db->query('SHOW INDEX FROM tl_page WHERE Key_name = \'cca_lr_group\'')->numRows) {
		$db->query('ALTER TABLE tl_page DROP INDEX cca_lr_group');
	}
	if($db->fieldExists('cca_lr_group', 'tl_page', true)) {
		$db->query('ALTER TABLE tl_page CHANGE cca_lr_group hofff_language_relations_group_id int(10) unsigned NOT NULL default \'0\'');
	}
}

if($db->tableExists('tl_hofff_language_relations_page', null, true)) {
	$pageFromExists = $db->fieldExists('pageFrom', 'tl_hofff_language_relations_page', true);
	$pageToExists = $db->fieldExists('pageTo', 'tl_hofff_language_relations_page', true);

	if($pageFromExists || $pageToExists) {
		if($db->query('SHOW INDEX FROM tl_hofff_language_relations_page WHERE Key_name = \'pageTo_ix\'')->numRows) {
			$db->query('ALTER TABLE tl_hofff_language_relations_page DROP INDEX pageTo_ix');
		}
		if($db->query('SHOW INDEX FROM tl_hofff_language_relations_page WHERE Key_name = \'PRIMARY\'')->numRows) {
			$db->query('ALTER TABLE tl_hofff_language_relations_page DROP PRIMARY KEY');
		}
	}

	if($pageFromExists) {
		$db->query('ALTER TABLE tl_hofff_language_relations_page CHANGE pageFrom item_id int(10) unsigned NOT NULL');
	}
	if($pageToExists) {
		$db->query('ALTER TABLE tl_hofff_language_relations_page CHANGE pageTo related_item_id int(10) unsigned NOT NULL');
	}
}
