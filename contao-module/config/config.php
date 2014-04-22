<?php

$GLOBALS['BE_MOD']['design']['cca_lr_group']['tables'][]             = 'tl_cca_lr_group';
$GLOBALS['BE_MOD']['design']['cca_lr_group']['tables'][]             = 'tl_page';
$GLOBALS['BE_MOD']['design']['cca_lr_group']['icon']                 = 'system/modules/cca-language-relations/assets/images/language-relations.png';
$GLOBALS['BE_MOD']['design']['cca_lr_group']['selectriAJAXCallback'] = array('ContaoCommunityAlliance\\Contao\\LanguageRelations\\GroupDCA', 'keySelectriAJAXCallback');
$GLOBALS['BE_MOD']['design']['cca_lr_group']['editRelations']        = array('ContaoCommunityAlliance\\Contao\\LanguageRelations\\GroupDCA', 'keyEditRelations');
$GLOBALS['BE_MOD']['design']['cca_lr_group']['stylesheet'][]         = 'system/modules/cca-language-relations/assets/css/style.css';

$GLOBALS['BE_MOD']['design']['page']['stylesheet'][]                 = 'system/modules/cca-language-relations/assets/css/style.css';

$GLOBALS['TL_HOOKS']['loadDataContainer']['cca_lr']                  = array('ContaoCommunityAlliance\\Contao\\LanguageRelations\\PageDCA', 'hookLoadDataContainer');
