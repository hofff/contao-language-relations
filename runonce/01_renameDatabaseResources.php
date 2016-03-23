<?php

$db = Database::getInstance();

if($db->tableExists('tl_cca_lr_group')) {
	$db->query('ALTER TABLE tl_cca_lr_group RENAME tl_hofff_translation_group');
}

if($db->tableExists('tl_cca_lr_relation')) {
	$db->query('ALTER TABLE tl_cca_lr_relation RENAME tl_hofff_page_translation');
}

if($db->tableExists('tl_page')) {
	if($db->query('SHOW INDEX FROM tl_page WHERE Key_name = \'cca_lr_group\'')->numRows) {
		$db->query('ALTER TABLE tl_page DROP INDEX cca_lr_group');
	}
	if($db->fieldExists('cca_lr_group', 'tl_page')) {
		$db->query('ALTER TABLE tl_page CHANGE cca_lr_group hofff_translation_group_id int(10) unsigned NOT NULL default \'0\'');
	}
}
