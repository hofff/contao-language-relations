<?php

declare(strict_types=1);

use Contao\Database;

$db = Database::getInstance();

if ($db->tableExists('tl_hofff_language_relations_page', null, true)) {
    $pageFromExists = $db->fieldExists('pageFrom', 'tl_hofff_language_relations_page', true);
    $pageToExists   = $db->fieldExists('pageTo', 'tl_hofff_language_relations_page', true);

    if ($pageFromExists || $pageToExists) {
        if ($db->query('SHOW INDEX FROM tl_hofff_language_relations_page WHERE Key_name = \'pageTo_ix\'')->numRows) {
            $db->query('ALTER TABLE tl_hofff_language_relations_page DROP INDEX pageTo_ix');
        }
        if ($db->query('SHOW INDEX FROM tl_hofff_language_relations_page WHERE Key_name = \'PRIMARY\'')->numRows) {
            $db->query('ALTER TABLE tl_hofff_language_relations_page DROP PRIMARY KEY');
        }
    }

    if ($pageFromExists) {
        $db->query('ALTER TABLE tl_hofff_language_relations_page CHANGE pageFrom item_id INT(10) UNSIGNED NOT NULL');
    }
    if ($pageToExists) {
        $db->query(
            'ALTER TABLE tl_hofff_language_relations_page CHANGE pageTo related_item_id INT(10) UNSIGNED NOT NULL'
        );
    }
}

$db->query('DROP VIEW IF EXISTS hofff_language_relations_page_tree');
$db->query('DROP VIEW IF EXISTS hofff_language_relations_page_aggregate');
$db->query('DROP VIEW IF EXISTS hofff_language_relations_page_relation');
$db->query('DROP VIEW IF EXISTS hofff_language_relations_page_item');
