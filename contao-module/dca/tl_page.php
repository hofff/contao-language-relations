<?php

use Hofff\Contao\LanguageRelations\PageDCA;
use Hofff\Contao\LanguageRelations\SelectriDataFactoryCallbacks;

$GLOBALS['TL_DCA']['tl_page']['config']['onsubmit_callback'][]
	= [ PageDCA::class, 'onsubmitPage' ];
$GLOBALS['TL_DCA']['tl_page']['config']['oncopy_callback'][]
	= [ PageDCA::class, 'oncopyPage' ];

/*
 * FIXME OH: this is a temp workaround to speed up saving of edit all in translation group be module
 * https://github.com/hofff/contao-language-relations/issues/2
 */
if($_GET['do'] == 'hofff_translation_group') {
	$onsubmit = &$GLOBALS['TL_DCA']['tl_page']['config']['onsubmit_callback'];
	foreach($onsubmit as $i => list($class, $method)) {
		if($class == 'tl_page' && $method == 'updateSitemap') {
			unset($onsubmit[$i]);
			break;
		}
	}
	unset($onsubmit);
}

$GLOBALS['TL_DCA']['tl_page']['config']['sql']['keys']['hofff_translation_group_id']
	= 'index';
$GLOBALS['TL_DCA']['tl_page']['fields']['hofff_translation_group_id']['sql']
	= 'int(10) unsigned NOT NULL default \'0\'';

$GLOBALS['TL_DCA']['tl_page']['fields']['hofff_language_relations_info'] = [
	'label'					=> &$GLOBALS['TL_LANG']['tl_page']['hofff_language_relations_info'],
	'exclude'				=> true,
	'input_field_callback'	=> [ PageDCA::class, 'inputFieldPageInfo' ],
];

$GLOBALS['TL_DCA']['tl_page']['fields']['hofff_page_translations'] = [
	'label'					=> &$GLOBALS['TL_LANG']['tl_page']['hofff_page_translations'],
	'exclude'				=> true,
	'inputType'				=> 'selectri',
	'eval'					=> [
		'doNotSaveEmpty'		=> true,
		'min'					=> 0,
		'max'					=> PHP_INT_MAX,
		'sort'					=> false,
		'canonical'				=> true,
		'class'					=> 'hofff-relations',
		'data'					=> [ SelectriDataFactoryCallbacks::getInstance(), 'getFactory' ],
	],
	'input_field_callback'	=> [ SelectriDataFactoryCallbacks::class, 'inputFieldCallback' ],
	'load_callback'			=> [
		[ PageDCA::class, 'loadRelations' ],
	],
	'save_callback'			=> [
		[ PageDCA::class, 'saveRelations' ],
	],
];
