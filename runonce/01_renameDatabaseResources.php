<?php

if(Database::getInstance()->tableExists('tl_cca_lr_group')) {
	Database::getInstance()->query('ALTER TABLE tl_cca_lr_group RENAME tl_hofff_translation_group');
}

if(Database::getInstance()->tableExists('tl_cca_lr_relation')) {
	Database::getInstance()->query('ALTER TABLE tl_cca_lr_relation RENAME tl_hofff_page_translation');
}
