<?php

$GLOBALS['TL_DCA']['tl_module']['palettes']['hofff_language_relations_language_switcher']
	= '{title_legend},name,headline,type'
	. ';{config_legend},hofff_language_relations_hide_current'
	. ',hofff_language_relations_keep_request_params,hofff_language_relations_keep_qs'
	. ',hofff_language_relations_labels'
	. ';{template_legend:hide},navigationTpl,customTpl'
	. ';{protected_legend:hide},protected'
	. ';{expert_legend:hide},guests,cssID,space';

$GLOBALS['TL_DCA']['tl_module']['fields']['hofff_language_relations_hide_current'] = [
	'label'			=> &$GLOBALS['TL_LANG']['tl_module']['hofff_language_relations_hide_current'],
	'exclude'		=> true,
	'inputType'		=> 'checkbox',
	'eval'			=> [
		'tl_class'		=> 'clr w50 cbx',
	],
	'sql'			=> 'char(1) NOT NULL default \'\'',
];
$GLOBALS['TL_DCA']['tl_module']['fields']['hofff_language_relations_keep_request_params'] = [
	'label'			=> &$GLOBALS['TL_LANG']['tl_module']['hofff_language_relations_keep_request_params'],
	'exclude'		=> true,
	'inputType'		=> 'checkbox',
	'eval'			=> [
		'tl_class'		=> 'clr w50 cbx',
	],
	'sql'			=> 'char(1) NOT NULL default \'\'',
];
$GLOBALS['TL_DCA']['tl_module']['fields']['hofff_language_relations_keep_qs'] = [
	'label'			=> &$GLOBALS['TL_LANG']['tl_module']['hofff_language_relations_keep_qs'],
	'exclude'		=> true,
	'default'		=> '1',
	'inputType'		=> 'checkbox',
	'eval'			=> [
		'tl_class'		=> 'w50 cbx',
	],
	'sql'			=> 'char(1) NOT NULL default \'\'',
];
$GLOBALS['TL_DCA']['tl_module']['fields']['hofff_language_relations_labels'] = [
	'label'			=> &$GLOBALS['TL_LANG']['tl_module']['hofff_language_relations_labels'],
	'exclude'		=> true,
	'inputType'		=> 'multiColumnWizard',
	'eval'			=> [
		'columnFields'	=> [
			'language'		=> [
				'label'			=> &$GLOBALS['TL_LANG']['tl_module']['hofff_language_relations_language'],
				'inputType'		=> 'text',
				'eval'			=> [
				],
			],
			'label'			=> [
				'label'			=> &$GLOBALS['TL_LANG']['tl_module']['hofff_language_relations_label'],
				'inputType'		=> 'text',
				'eval'			=> [
				],
			],
		],
		'tl_class'		=> '',
	],
	'sql'			=> 'blob NULL',
];
