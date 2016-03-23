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

$GLOBALS['TL_DCA']['tl_page']['config']['sql']['keys']['cca_lr_group']
	= 'index';
$GLOBALS['TL_DCA']['tl_page']['fields']['cca_lr_group']['sql']
	= 'int(10) unsigned NOT NULL default \'0\'';

$GLOBALS['TL_DCA']['tl_page']['fields']['cca_lr_pageInfo'] = [
	'label'					=> &$GLOBALS['TL_LANG']['tl_page']['cca_lr_pageInfo'],
	'exclude'				=> true,
	'input_field_callback'	=> [ PageDCA::class, 'inputFieldPageInfo' ],
];

$GLOBALS['TL_DCA']['tl_page']['fields']['cca_lr_relations'] = [
	'label'					=> &$GLOBALS['TL_LANG']['tl_page']['cca_lr_relations'],
	'exclude'				=> true,
	'inputType'				=> 'selectri',
	'eval'					=> [
		'doNotSaveEmpty'		=> true,
		'min'					=> 0,
		'max'					=> PHP_INT_MAX,
		'sort'					=> false,
		'canonical'				=> true,
		'class'					=> 'cca-lr-relations',
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
