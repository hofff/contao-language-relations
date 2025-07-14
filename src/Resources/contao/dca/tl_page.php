<?php

declare(strict_types=1);

use Hofff\Contao\LanguageRelations\DCA\PageDCA;
use Hofff\Contao\LanguageRelations\DCA\RelationsDCABuilder;
use Hofff\Contao\LanguageRelations\DCA\RelationsDCABuilderConfig;
use Hofff\Contao\LanguageRelations\Relations;
use Hofff\Contao\Selectri\Util\Icons;

call_user_func(
    static function (): void {
        /**
         * @psalm-suppress InvalidArrayOffset
         * @psalm-suppress InvalidArrayAccess
         */
        [$callback, $columns] = Icons::getTableIconCallback('tl_page');
        Icons::setTableIconCallback(
            'hofff_language_relations_page_tree',
            $callback,
            $columns
        );

        $relations = new Relations(
            'tl_hofff_language_relations_page',
            'hofff_language_relations_page_item',
            'hofff_language_relations_page_relation'
        );

        $config = new RelationsDCABuilderConfig();
        $config->setRelations($relations);
        $config->setAggregateFieldName('hofff_root_page_id');
        $config->setAggregateView('hofff_language_relations_page_aggregate');
        $config->setTreeView('hofff_language_relations_page_tree');

        $builder = new RelationsDCABuilder($config);
        $builder->build($GLOBALS['TL_DCA']['tl_page']);
    }
);

$GLOBALS['TL_DCA']['tl_page']['config']['oncopy_callback'][] = [PageDCA::class, 'oncopyCallback'];
$GLOBALS['TL_DCA']['tl_page']['config']['onload_callback'][] = [PageDCA::class, 'addPageTranslationLinks'];

/*
 * FIXME OH: this is a temp workaround to speed up saving of edit all in translation group be module
 * https://github.com/hofff/contao-language-relations/issues/2
 */
if (isset($_GET['do']) && $_GET['do'] === 'hofff_language_relations_group') {
    $onsubmit = &$GLOBALS['TL_DCA']['tl_page']['config']['onsubmit_callback'];
    foreach ($onsubmit as $i => $callback) {
        if (! is_array($callback)) {
            continue;
        }

        [$class, $method] = $callback;
        if ($class === 'tl_page' && $method === 'updateSitemap') {
            unset($onsubmit[$i]);
            break;
        }
    }

    unset($onsubmit);
}

$GLOBALS['TL_DCA']['tl_page']['config']['sql']['keys']['hofff_language_relations_group_id'] =
    'index';

$GLOBALS['TL_DCA']['tl_page']['fields']['hofff_language_relations_group_id']['sql'] =
    'int(10) unsigned default \'0\'';

$GLOBALS['TL_DCA']['tl_page']['fields']['hofff_language_relations_info'] = [
    'label'                => &$GLOBALS['TL_LANG']['tl_page']['hofff_language_relations_info'],
    'exclude'              => true,
    'input_field_callback' => [PageDCA::class,'inputFieldCallbackPageInfo'],
];

$GLOBALS['TL_DCA']['tl_page']['fields']['hofff_language_relations_page_links'] = [
    'label'                          => &$GLOBALS['TL_LANG']['tl_page']['hofff_language_relations_page_links'],
    'inputType'                      => 'multiColumnWizard',
    'load_callback'                  => [[PageDCA::class, 'getLinkedPages']],
    'save_callback'                  => [[PageDCA::class, 'returnEmptyString']],
    'eval' => [
        'style'                      => 'width:100%;',
        'columnFields' => [
            'linkedPages' => [
                'label'              => null,
                'exclude'            => true,
                'inputType'          => 'languagerelatation_textoptions',
                'options_callback'   => [PageDCA::class, 'getTranslationPages'],
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
