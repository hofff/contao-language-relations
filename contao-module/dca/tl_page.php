<?php

call_user_func(function() {
	list($callback, $columns) = \Hofff\Contao\Selectri\Util\Icons::getTableIconCallback('tl_page');
	\Hofff\Contao\Selectri\Util\Icons::setTableIconCallback(
		'hofff_language_relations_page_tree',
		$callback,
		$columns
	);

	$relations = new \Hofff\Contao\LanguageRelations\Relations(
		'tl_hofff_language_relations_page',
		'hofff_language_relations_page_item',
		'hofff_language_relations_page_relation'
	);

	$config = new \Hofff\Contao\LanguageRelations\DCA\RelationsDCABuilderConfig;
	$config->setRelations($relations);
	$config->setAggregateFieldName('hofff_root_page_id');
	$config->setAggregateView('hofff_language_relations_page_aggregate');
	$config->setTreeView('hofff_language_relations_page_tree');

	$builder = new \Hofff\Contao\LanguageRelations\DCA\RelationsDCABuilder($config);
	$builder->build($GLOBALS['TL_DCA']['tl_page']);
});

$GLOBALS['TL_DCA']['tl_page']['config']['oncopy_callback'][]
	= [ \Hofff\Contao\LanguageRelations\DCA\PageDCA::class, 'oncopyCallback' ];

/*
 * FIXME OH: this is a temp workaround to speed up saving of edit all in translation group be module
 * https://github.com/hofff/contao-language-relations/issues/2
 */
if($_GET['do'] == 'hofff_language_relations_group') {
	$onsubmit = &$GLOBALS['TL_DCA']['tl_page']['config']['onsubmit_callback'];
	foreach($onsubmit as $i => $callback) {
		if(!is_array($callback)) {
			continue;
		}
		list($class, $method) = $callback;
		if($class == 'tl_page' && $method == 'updateSitemap') {
			unset($onsubmit[$i]);
			break;
		}
	}
	unset($onsubmit);
}

$GLOBALS['TL_DCA']['tl_page']['config']['sql']['keys']['hofff_language_relations_group_id']
	= 'index';
$GLOBALS['TL_DCA']['tl_page']['fields']['hofff_language_relations_group_id']['sql']
	= 'int(10) unsigned NOT NULL default \'0\'';

$GLOBALS['TL_DCA']['tl_page']['fields']['hofff_language_relations_info'] = [
	'label'					=> &$GLOBALS['TL_LANG']['tl_page']['hofff_language_relations_info'],
	'exclude'				=> true,
	'input_field_callback'	=> [
		\Hofff\Contao\LanguageRelations\DCA\PageDCA::class,
		'inputFieldCallbackPageInfo'
	],
];
