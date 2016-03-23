<?php

if(Database::getInstance()->tableExists('tl_cca_lr_group')) {
	Database::getInstance()->query('ALTER TABLE tl_cca_lr_group RENAME tl_hofff_translation_group');
}

if(Database::getInstance()->tableExists('tl_cca_lr_relation')) {
	Database::getInstance()->query('ALTER TABLE tl_cca_lr_relation RENAME tl_hofff_page_translation');
}

if(Database::getInstance()->tableExists('tl_page')) {
	if(Database::getInstance()->fieldExists('cca_lr_group', 'tl_page')) {
		Database::getInstance()->query('ALTER TABLE tl_page DROP INDEX cca_lr_group');
		Database::getInstance()->query('ALTER TABLE tl_page CHANGE cca_lr_group hofff_translation_group_id int(10) unsigned NOT NULL default \'0\'');
	}
}
