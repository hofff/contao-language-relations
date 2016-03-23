<?php

$GLOBALS['BE_MOD']['design']['cca_lr_group']['tables'][]
	= 'tl_hofff_translation_group';
$GLOBALS['BE_MOD']['design']['cca_lr_group']['tables'][]
	= 'tl_page';
$GLOBALS['BE_MOD']['design']['cca_lr_group']['icon']
	= 'system/modules/hofff_language_relations/assets/images/language-relations.png';
$GLOBALS['BE_MOD']['design']['cca_lr_group']['selectriAJAXCallback']
	= [ 'Hofff\\Contao\\LanguageRelations\\GroupDCA', 'keySelectriAJAXCallback' ];
$GLOBALS['BE_MOD']['design']['cca_lr_group']['editRelations']
	= [ 'Hofff\\Contao\\LanguageRelations\\GroupDCA', 'keyEditRelations' ];
$GLOBALS['BE_MOD']['design']['cca_lr_group']['stylesheet'][]
	= 'system/modules/hofff_language_relations/assets/css/style.css';

$GLOBALS['BE_MOD']['design']['page']['stylesheet'][]
	= 'system/modules/hofff_language_relations/assets/css/style.css';

$GLOBALS['TL_HOOKS']['loadDataContainer']['cca_lr']
	= [ 'Hofff\\Contao\\LanguageRelations\\PageDCA', 'hookLoadDataContainer' ];
