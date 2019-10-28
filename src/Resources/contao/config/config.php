<?php

declare(strict_types=1);

use Hofff\Contao\LanguageRelations\Database\Installer;
use Hofff\Contao\LanguageRelations\DCA\GroupDCA;
use Hofff\Contao\LanguageRelations\DCA\PageDCA;
use Hofff\Contao\LanguageRelations\Module\ModuleLanguageSwitcher;

$GLOBALS['BE_MOD']['design']['hofff_language_relations_group']['tables'][]
             = 'tl_hofff_language_relations_group';
$GLOBALS['BE_MOD']['design']['hofff_language_relations_group']['tables'][]
             = 'tl_page';
$GLOBALS['BE_MOD']['design']['hofff_language_relations_group']['selectriAJAXCallback']
    = [ GroupDCA::class, 'keySelectriAJAXCallback' ];
$GLOBALS['BE_MOD']['design']['hofff_language_relations_group']['editRelations']
        = [ GroupDCA::class, 'keyEditRelations' ];
$GLOBALS['BE_MOD']['design']['hofff_language_relations_group']['stylesheet'][]
         = 'bundles/hofffcontaolanguagerelations/css/style.css';

$GLOBALS['BE_MOD']['design']['page']['stylesheet'][]
    = 'bundles/hofffcontaolanguagerelations/css/style.css';

$GLOBALS['FE_MOD']['navigationMenu']['hofff_language_relations_language_switcher']
    = ModuleLanguageSwitcher::class;

$GLOBALS['TL_HOOKS']['loadDataContainer']['hofff_language_relations']
  = [ PageDCA::class, 'hookLoadDataContainer' ];
$GLOBALS['TL_HOOKS']['sqlCompileCommands']['hofff_language_relations']
    = [ Installer::class, 'hookSQLCompileCommands' ];
