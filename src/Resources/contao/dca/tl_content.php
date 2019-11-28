<?php

declare(strict_types=1);

use Hofff\Contao\LanguageRelations\DCA\ArticleDCA;

$GLOBALS['TL_DCA']['tl_content']['list']['sorting']['header_callback'] = [ArticleDCA::class, 'addArticleTranslations'];

$GLOBALS['TL_DCA']['tl_content']['config']['onload_callback'][]
    = [ArticleDCA::class, 'addArticleTranslationHeaderCss'];
