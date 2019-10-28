<?php

declare(strict_types=1);

use Hofff\Contao\LanguageRelations\DCA\GroupDCA;

$GLOBALS['TL_DCA']['tl_hofff_language_relations_group'] = [

    'config' => [
        'dataContainer'     => 'Table',
        //      'enableVersioning'  => true,
        'onsubmit_callback' => [
            [
                GroupDCA::class,
                'onsubmitGroup',
            ],
        ],
        'sql'               => [
            'keys' => ['id' => 'primary'],
        ],
    ],

    'list' => [
        'sorting'           => [
            'mode'        => 1,
            'fields'      => [
                'title',
                'id',
            ],
            'flag'        => 12,
            'panelLayout' => 'filter;search,limit',
        ],
        'label'             => [
            'fields'         => ['title'],
            'format'         => '%s',
            'label_callback' => [
                GroupDCA::class,
                'labelGroup',
            ],
            'group_callback' => [
                GroupDCA::class,
                'groupGroup',
            ],
        ],
        'global_operations' => [],
        'operations'        => [
            'edit'   => [
                'label' => &$GLOBALS['TL_LANG']['tl_hofff_language_relations_group']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.gif',
            ],
            'delete' => [
                'label'      => &$GLOBALS['TL_LANG']['tl_hofff_language_relations_group']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"',
            ],
        ],
    ],

    'palettes' => ['default' => '{general_legend},title,roots'],

    'subpalettes' => [],

    'fields' => [
        'id'     => [
            'label'  => ['ID'],
            'search' => true,
            'sql'    => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => ['sql' => 'int(10) unsigned NOT NULL default \'0\''],
        'title'  => [
            'label'     => &$GLOBALS['TL_LANG']['tl_hofff_language_relations_group']['title'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => [
                'mandatory' => true,
                'maxlength' => 255,
                'tl_class'  => 'clr long',
            ],
            'sql'       => 'varchar(255) NOT NULL default \'\'',
        ],
        'roots'  => [
            'label'            => &$GLOBALS['TL_LANG']['tl_hofff_language_relations_group']['roots'],
            'exclude'          => true,
            'inputType'        => 'select',
            'options_callback' => [
                GroupDCA::class,
                'getRootsOptions',
            ],
            'eval'             => [
                'includeBlankOption' => true,
                'doNotSaveEmpty'     => true,
                'multiple'           => true,
                'chosen'             => true,
                'style'              => 'width:100%;',
                'tl_class'           => '',
            ],
            'load_callback'    => [
                [
                    GroupDCA::class,
                    'loadRoots',
                ],
            ],
            'save_callback'    => [
                [
                    GroupDCA::class,
                    'saveRoots',
                ],
            ],
        ],
    ],

];
