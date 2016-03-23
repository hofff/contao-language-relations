<?php

$GLOBALS['BE_MOD']['design']['hofff_translation_group']['tables'][]
	= 'tl_hofff_translation_group';
$GLOBALS['BE_MOD']['design']['hofff_translation_group']['tables'][]
	= 'tl_page';
$GLOBALS['BE_MOD']['design']['hofff_translation_group']['icon']
	= 'system/modules/hofff_language_relations/assets/images/language-relations.png';
$GLOBALS['BE_MOD']['design']['hofff_translation_group']['selectriAJAXCallback']
	= [ 'Hofff\\Contao\\LanguageRelations\\GroupDCA', 'keySelectriAJAXCallback' ];
$GLOBALS['BE_MOD']['design']['hofff_translation_group']['editRelations']
	= [ 'Hofff\\Contao\\LanguageRelations\\GroupDCA', 'keyEditRelations' ];
$GLOBALS['BE_MOD']['design']['hofff_translation_group']['stylesheet'][]
	= 'system/modules/hofff_language_relations/assets/css/style.css';

$GLOBALS['BE_MOD']['design']['page']['stylesheet'][]
	= 'system/modules/hofff_language_relations/assets/css/style.css';

$GLOBALS['TL_HOOKS']['loadDataContainer']['hofff_language_relations']
	= [ 'Hofff\\Contao\\LanguageRelations\\PageDCA', 'hookLoadDataContainer' ];
