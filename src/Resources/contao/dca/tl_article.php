<?php

declare(strict_types=1);

use Hofff\Contao\LanguageRelations\DCA\ArticleDCA;

$GLOBALS['TL_DCA']['tl_article']['config']['onload_callback'][] = [ArticleDCA::class, 'addArticleTranslationLinks'];

/**
 * Fields
 */
$GLOBALS['TL_DCA']['tl_article']['fields']['hofff_language_relations_article_links'] = [
    'label'                          => &$GLOBALS['TL_LANG']['tl_article']['hofff_language_relations_article_links'],
    'inputType'                      => 'multiColumnWizard',
    'load_callback'                  => [[ArticleDCA::class, 'getLinkedArticles']],
    'save_callback'                  => [[ArticleDCA::class, 'returnEmptyString']],
    'eval' => [
        'style'                      => 'width:100%;',
        'columnFields' => [
            'linkedArticles' => [
                'label'              => null,
                'exclude'            => true,
                'inputType'          => 'justtextoption',
                'options_callback'   => [ArticleDCA::class, 'getTranslationArticles'],
                'eval' => [
                    'hideHead'       => true,
                    'hideBody'       => false,
                    'doNotSaveEmpty' => true,
                ],
            ],
        ],
        'hideButtons'                => true,
        'doNotSaveEmpty'             => true,
        'tl_class'                   => 'be-language-switch',
    ],
];
