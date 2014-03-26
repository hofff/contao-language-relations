<?php

$GLOBALS['TL_DCA']['tl_page']['config']['onsubmit_callback'][]
	= array('ContaoCommunityAlliance\\Contao\\LanguageRelations\\PageDCA', 'onsubmitPage');

$palettes = &$GLOBALS['TL_DCA']['tl_page']['palettes'];
foreach($palettes as $key => &$palette) if($key != '__selector__' && $key != 'root') {
	$palette .= ';{cca_lr_legend},cca_lr_pageInfo,cca_lr_relations';
}
unset($palettes);

$GLOBALS['TL_DCA']['tl_page']['fields']['cca_lr_pageInfo'] = array(
	'label'		=> &$GLOBALS['TL_LANG']['tl_page']['cca_lr_pageInfo'],
	'exclude'	=> true,
	'input_field_callback'=> array('ContaoCommunityAlliance\\Contao\\LanguageRelations\\PageDCA', 'inputFieldPageInfo'),
);

$GLOBALS['TL_DCA']['tl_page']['fields']['cca_lr_relations'] = array(
	'label'		=> &$GLOBALS['TL_LANG']['tl_page']['cca_lr_relations'],
	'exclude'	=> true,
	'inputType'	=> 'selectri',
	'foreignKey'=> 'tl_page.title',
	'eval'	=> array(
		'doNotSaveEmpty'=> true,
		'min' => 0,
		'max' => PHP_INT_MAX,
		'data' => array(\ContaoCommunityAlliance\Contao\LanguageRelations\SelectriDataFactoryHack::getInstance(), 'getFactory'),
	),
	'input_field_callback' => array('ContaoCommunityAlliance\\Contao\\LanguageRelations\\SelectriDataFactoryHack', 'inputFieldCallback'),
	'load_callback' => array(
		array('ContaoCommunityAlliance\\Contao\\LanguageRelations\\PageDCA', 'loadRelations'),
	),
	'save_callback' => array(
		array('ContaoCommunityAlliance\\Contao\\LanguageRelations\\PageDCA', 'saveRelations'),
	),
);
