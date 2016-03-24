<?php

$GLOBALS['TL_LANG']['tl_page']['hofff_language_relations_legend']
	= 'Übersetzungen';

$GLOBALS['TL_LANG']['tl_page']['hofff_language_relations_info'][0]
	= 'Seiteninformationen';
$GLOBALS['TL_LANG']['tl_page']['hofff_page_translations'][0]
	= 'Übersetzungen';
$GLOBALS['TL_LANG']['tl_page']['hofff_page_translations'][1]
	= 'Legt fest, welche Seiten anderer Startpunkte Übersetzungen dieser Seite sind.';

$GLOBALS['TL_LANG']['tl_page']['hofff_makePrimary']
	= 'Diese Seite als primäre Beziehung setzen';
$GLOBALS['TL_LANG']['tl_page']['hofff_isPrimary']
	= 'Dies ist die primäre Beziehung';

$GLOBALS['TL_LANG']['tl_page']['hofff_language_relations_no_translation_group'] = <<<EOT
Diese Seite wird nicht übersetzt, da der Seitenbaum dieser Seite keiner
Übersetzungsgruppe zugeordnet ist.
EOT;
$GLOBALS['TL_LANG']['tl_page']['hofff_language_relations_no_translated_root_pages'] = <<<EOT
Diese Seite wird nicht übersetzt, da es keine Seitenbäume anderer Sprachen in
der Übersetzungsgruppe dieses Seitenbaums gibt.
EOT;
$GLOBALS['TL_LANG']['tl_page']['hofff_language_relations_manage_translation_groups']
	= 'Übersetzungsgruppen verwalten';

$GLOBALS['TL_LANG']['tl_page']['hofff_errMultipleRelationsPerRoot']
	= 'Es darf je Startpunkt nur eine Referenz ausgewählt werden.';
$GLOBALS['TL_LANG']['tl_page']['hofff_errUngroupedRelations']
	= 'Die Startpunkte aller referenzierten Seiten müssen der gleichen Übersetzungsgruppe angehören.';
$GLOBALS['TL_LANG']['tl_page']['hofff_errOwnRootRelations']
	= 'Es darf sich keine referenzierte Seite im gleichen Startpunkt wie diese Seite befinden.';
