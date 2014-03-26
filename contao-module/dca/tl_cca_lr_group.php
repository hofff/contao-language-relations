<?php

$GLOBALS['TL_DCA']['tl_cca_lr_group'] = array
(
    'config' => array
    (
        'dataContainer'     => 'Table',
//      'enableVersioning'  => true,
        'onsubmit_callback' => array
        (
            array('ContaoCommunityAlliance\\Contao\\LanguageRelations\\GroupDCA', 'onsubmitGroup'),
        ),
    ),

    'list' => array
    (
        'sorting' => array
        (
            'mode'          => 1,
            'fields'        => array('title'),
            'flag'          => 12,
            'panelLayout'   => 'filter;search,limit',
            'disableGrouping'=> true,
        ),
        'label' => array
        (
            'fields'        => array('title'),
            'format'        => '%s',
            'label_callback'=> array('ContaoCommunityAlliance\\Contao\\LanguageRelations\\GroupDCA', 'labelGroup')
        ),
        'global_operations' => array
        (
        ),
        'operations' => array
        (
            'edit' => array
            (
                'label'     => &$GLOBALS['TL_LANG']['tl_cca_lr_group']['edit'],
                'href'      => 'act=edit',
                'icon'      => 'edit.gif'
            ),
            'delete' => array
            (
                'label'     => &$GLOBALS['TL_LANG']['tl_cca_lr_group']['delete'],
                'href'      => 'act=delete',
                'icon'      => 'delete.gif',
                'attributes'=> 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"'
            ),
        )
    ),

    'palettes' => array
    (
        'default'       => '{general_legend},title,roots',
    ),

    'subpalettes' => array
    (
    ),

    'fields' => array
    (
        'title' => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_cca_lr_group']['title'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => array
            (
                'mandatory' => true,
                'maxlength' => 255,
                'tl_class'  => 'clr long',
            ),
        ),
        'roots' => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_cca_lr_group']['roots'],
            'exclude'   => true,
            'inputType' => 'select',
            'options_callback'  => array('ContaoCommunityAlliance\\Contao\\LanguageRelations\\GroupDCA', 'getRootsOptions'),
            'eval'      => array
            (
                'includeBlankOption'=> true,
                'doNotSaveEmpty'=> true,
                'multiple'  => true,
                'chosen'    => true,
                'style'     => 'width:100%;',
                'tl_class'  => '',
            ),
            'load_callback' => array
            (
                array('ContaoCommunityAlliance\\Contao\\LanguageRelations\\GroupDCA', 'loadRoots'),
            ),
            'save_callback' => array
            (
                array('ContaoCommunityAlliance\\Contao\\LanguageRelations\\GroupDCA', 'saveRoots'),
            ),
        ),
    ),
);