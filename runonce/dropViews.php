<?php

Database::getInstance()->query('DROP VIEW IF EXISTS hofff_language_relations_page_tree');
Database::getInstance()->query('DROP VIEW IF EXISTS hofff_language_relations_page_aggregate');
Database::getInstance()->query('DROP VIEW IF EXISTS hofff_language_relations_page_relation');
Database::getInstance()->query('DROP VIEW IF EXISTS hofff_language_relations_page_item');
