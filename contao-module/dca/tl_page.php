<?php

$GLOBALS['TL_DCA']['tl_page']['config']['onsubmit_callback'][]
	= array('ContaoCommunityAlliance\\Contao\\LanguageRelations\\PageDCA', 'onsubmitPage');
$GLOBALS['TL_DCA']['tl_page']['config']['oncopy_callback'][]
	= array('ContaoCommunityAlliance\\Contao\\LanguageRelations\\PageDCA', 'oncopyPage');

/*
 * FIXME OH: this is a temp workaround to speed up saving of edit all in translation group be module
 * https://github.com/contao-community-alliance/language-relations/issues/2
 */
if($_GET['do'] == 'cca_lr_group')
{
    $onsubmit = &$GLOBALS['TL_DCA']['tl_page']['config']['onsubmit_callback'];
    foreach($onsubmit as $i => $callback) if($callback[0] == 'tl_page' && $callback[1] == 'updateSitemap')
    {
        unset($onsubmit[$i]);
        break;
    }
    unset($onsubmit);
}

$palettes = &$GLOBALS['TL_DCA']['tl_page']['palettes'];
foreach($palettes as $key => &$palette) if($key != '__selector__' && $key != 'root')
{
    $palette .= ';{cca_lr_legend}';
    $_GET['do'] == 'cca_lr_group' && $palette .= ',cca_lr_pageInfo';
    $palette .= ',cca_lr_relations';
}
unset($palettes);

$GLOBALS['TL_DCA']['tl_page']['fields']['cca_lr_pageInfo'] = array
(
    'label'     => &$GLOBALS['TL_LANG']['tl_page']['cca_lr_pageInfo'],
    'exclude'   => true,
    'input_field_callback'=> array('ContaoCommunityAlliance\\Contao\\LanguageRelations\\PageDCA', 'inputFieldPageInfo'),
);

$GLOBALS['TL_DCA']['tl_page']['fields']['cca_lr_relations'] = array
(
    'label'     => &$GLOBALS['TL_LANG']['tl_page']['cca_lr_relations'],
    'exclude'   => true,
    'inputType' => 'selectri',
    'eval'  => array
    (
        'doNotSaveEmpty'=> true,
        'min' => 0,
        'max' => PHP_INT_MAX,
        'sort' => false,
        'data' => array(\ContaoCommunityAlliance\Contao\LanguageRelations\SelectriDataFactoryCallbacks::getInstance(), 'getFactory'),
    ),
    'input_field_callback' => array('ContaoCommunityAlliance\\Contao\\LanguageRelations\\SelectriDataFactoryCallbacks', 'inputFieldCallback'),
    'load_callback' => array
    (
        array('ContaoCommunityAlliance\\Contao\\LanguageRelations\\PageDCA', 'loadRelations'),
    ),
    'save_callback' => array
    (
        array('ContaoCommunityAlliance\\Contao\\LanguageRelations\\PageDCA', 'saveRelations'),
    ),
);