<?php

$GLOBALS['BE_MOD']['design']['hofff_language_relations_group']['tables'][]
	= 'tl_hofff_language_relations_group';
$GLOBALS['BE_MOD']['design']['hofff_language_relations_group']['tables'][]
	= 'tl_page';
$GLOBALS['BE_MOD']['design']['hofff_language_relations_group']['icon']
	= 'system/modules/hofff_language_relations/assets/images/language-relations.png';
$GLOBALS['BE_MOD']['design']['hofff_language_relations_group']['selectriAJAXCallback']
	= [ 'Hofff\\Contao\\LanguageRelations\\DCA\\GroupDCA', 'keySelectriAJAXCallback' ];
$GLOBALS['BE_MOD']['design']['hofff_language_relations_group']['editRelations']
	= [ 'Hofff\\Contao\\LanguageRelations\\DCA\\GroupDCA', 'keyEditRelations' ];
$GLOBALS['BE_MOD']['design']['hofff_language_relations_group']['stylesheet'][]
	= 'system/modules/hofff_language_relations/assets/css/style.css';

$GLOBALS['BE_MOD']['design']['page']['stylesheet'][]
	= 'system/modules/hofff_language_relations/assets/css/style.css';

$GLOBALS['FE_MOD']['navigationMenu']['hofff_language_relations_language_switcher']
	= 'Hofff\\Contao\\LanguageRelations\\Module\\ModuleLanguageSwitcher';

$GLOBALS['TL_HOOKS']['loadDataContainer']['hofff_language_relations']
	= [ 'Hofff\\Contao\\LanguageRelations\\DCA\\PageDCA', 'hookLoadDataContainer' ];
$GLOBALS['TL_HOOKS']['sqlCompileCommands']['hofff_language_relations']
	= [ 'Hofff\\Contao\\LanguageRelations\\Database\\Installer', 'hookSQLCompileCommands' ];
