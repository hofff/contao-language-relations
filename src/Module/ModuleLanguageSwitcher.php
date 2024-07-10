<?php

declare(strict_types=1);

namespace Hofff\Contao\LanguageRelations\Module;

use Contao\BackendTemplate;
use Contao\CoreBundle\Exception\RouteParametersException;
use Contao\Environment;
use Contao\FrontendTemplate;
use Contao\Module;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Hofff\Contao\LanguageRelations\LanguageRelations;
use Hofff\Contao\LanguageRelations\Util\ContaoUtil;
use Locale;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

use function array_filter;
use function array_flip;
use function array_keys;
use function array_map;
use function assert;
use function call_user_func;
use function class_exists;
use function defined;
use function explode;
use function is_array;
use function sprintf;
use function strip_tags;
use function strlen;
use function strnatcasecmp;
use function strpos;
use function strtolower;
use function strtoupper;
use function substr;
use function uasort;

use const PHP_INT_MAX;

/**
 * @property string|bool                       $hofff_language_relations_keep_request_params
 * @property string|bool                       $hofff_language_relations_hide_current
 * @property string|bool                       $hofff_language_relations_keep_qs
 * @property string|list<array<string,string>> $hofff_language_relations_labels
 * @psalm-suppress PropertyNotSetInConstructor
 */
class ModuleLanguageSwitcher extends Module
{
    protected bool $intlSupported;

    /** @var array<string, string> */
    protected array $labels = [];

    public function __construct(ModuleModel $module, string $column = 'main')
    {
        parent::__construct($module, $column);
        $this->intlSupported = class_exists('Locale');
        $this->strTemplate   = 'mod_hofff_language_relations_language_switcher';

        foreach (StringUtil::deserialize($this->hofff_language_relations_labels, true) as $row) {
            if (! strlen($row['language'])) {
                continue;
            }

            $this->labels[$row['language']] = $row['label'];
        }
    }

    /**
     * @see \Contao\Module::generate()
     */
    public function generate(): string
    {
        if (defined('TL_MODE') && TL_MODE === 'BE') {
            $tpl           = new BackendTemplate('be_wildcard');
            $tpl->wildcard = '### LANGUAGE SWITCHER ###';
            $tpl->title    = $this->headline;
            $tpl->id       = $this->id;
            $tpl->link     = $this->name;
            $tpl->href     = 'contao?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $tpl->parse();
        }

        strlen($this->navigationTpl) || $this->navigationTpl = 'nav_default';

        return parent::generate();
    }

    /**
     * {@inheritDoc}
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function compile(): void
    {
        $relations    = LanguageRelations::getRelationsInstance();
        $currentPage  = $GLOBALS['objPage'];
        $relatedPages = $relations->getRelations($currentPage->id, false, true);

        foreach ($relatedPages as $rootPageID => &$page) {
            /** @psalm-suppress RiskyCast */
            $page = PageModel::findWithDetails((int) ($page ?: $rootPageID));
        }

        unset($page);
        /** @psalm-var array<numeric-string|int|PageModel|null> $relatedPages */

        if (! defined('BE_USER_LOGGED_IN') || ! BE_USER_LOGGED_IN) {
            $relatedPages = array_filter($relatedPages, static function ($page): bool {
                if (! $page instanceof PageModel) {
                    return false;
                }

                return (bool) $page->rootIsPublic;
            });
            $relatedPages = array_map(static function ($page): ?PageModel {
                if (! $page instanceof PageModel) {
                    return null;
                }

                return ContaoUtil::isPublished($page)
                    ? $page
                    : PageModel::findWithDetails((int) $page->hofff_root_page_id);
            }, $relatedPages);
        }

        $relatedPages[$currentPage->hofff_root_page_id] = $currentPage;

        $params = $this->getRequestParams($currentPage);

        $items = [];
        foreach ($relatedPages as $rootPageID => $page) {
            assert($page instanceof PageModel);

            $language = strtolower($page->rootLanguage);

            try {
                $url = $page->getFrontendUrl($params, $language);
            } catch (RouteParametersException $exception) {
                $url = '';
            } catch (ResourceNotFoundException $exception) {
                $url = '';
            }

            $item              = [];
            $item['isActive']  = $page->id === $currentPage->id;
            $item['href']      = $url;
            $item['class']     = 'lang-' . $language;
            $item['link']      = $this->getLabel($language);
            $item['pageTitle'] = strip_tags(strlen($page->pageTitle) ? $page->pageTitle : $page->title);
            $item['language']  = $language;
//          $item['target']     = $target . ' hreflang="' . $language . '"';
            $item['subitems']  = '';
            $item['accesskey'] = '';
            $item['tabindex']  = '';
            $item['nofollow']  = false;
            $item['model']     = $page;

            if ($item['isActive']) {
                $item['href']   = Environment::get('request');
                $item['class'] .= ' active';
            }

            $items[$rootPageID] = $item;
        }

        $items = $this->executeHook($items);

        if ($this->hofff_language_relations_keep_qs) {
            foreach ($items as &$item) {
                $queryString = Environment::get('queryString');
                if (! $queryString) {
                    continue;
                }

                $item['href'] .= strpos($item['href'], '?') === false ? '?' : '&';
                $item['href'] .= $queryString;
            }

            unset($item);
        }

        $items = $this->sortItems($items);

        $this->injectAlternateLinks($items);

        if ($this->hofff_language_relations_hide_current) {
            unset($items[$currentPage->hofff_root_page_id]);
        }

        $tpl = new FrontendTemplate($this->navigationTpl);
        $tpl->setData($this->arrData);
        $tpl->level            = 'level_1';
        $tpl->items            = $items;
        $this->Template->items = $tpl->parse();
    }

    protected function getRequestParams(PageModel $currentPage): string
    {
        if (! $this->hofff_language_relations_keep_request_params) {
            return '';
        }

        [$params] = explode('?', Environment::get('request'), 2);

        return substr($params, strlen($currentPage->alias) + 1);
    }

    protected function getLabel(string $language): string
    {
        $labels = $this->getLabels();

        if (isset($labels[$language]) && strlen($labels[$language])) {
            return $labels[$language];
        }

        if ($this->intlSupported) {
            return Locale::getDisplayLanguage($language, $language);
        }

        return strtoupper($language);
    }

    /**
     * @param mixed[][] $items
     *
     * @return mixed[][]
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    protected function executeHook(array $items): array
    {
        $hooks = &$GLOBALS['TL_HOOKS']['hofff_language_relations_language_switcher'];

        if (! isset($hooks) || ! is_array($hooks)) {
            return $items;
        }

        foreach ($hooks as $callback) {
            $items = call_user_func(
                [System::importStatic($callback[0]), $callback[1]],
                $items,
                $this
            );
        }

        return $items;
    }

    /**
     * @param mixed[][] $items
     *
     * @return mixed[][]
     */
    protected function sortItems(array $items): array
    {
        uasort($items, static function ($itemA, $itemB) {
            return strnatcasecmp($itemA['language'], $itemB['language']);
        });

        $labels = $this->getLabels();
        if ($labels) {
            $labels = array_flip(array_keys($labels));
            uasort($items, static function ($itemA, $itemB) use ($labels) {
                $itemA = $labels[$itemA['language']] ?? PHP_INT_MAX;
                $itemB = $labels[$itemB['language']] ?? PHP_INT_MAX;

                return $itemA - $itemB;
            });
        }

        return $items;
    }

    /**
     * @param string[][] $items
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    protected function injectAlternateLinks(array $items): void
    {
        foreach ($items as $item) {
            $GLOBALS['TL_HEAD'][] = sprintf(
                '<link rel="alternate" hreflang="%s" href="%s" />',
                $item['language'],
                $item['href']
            );
        }
    }

    /**
     * @return string[]
     */
    protected function getLabels(): array
    {
        return $this->labels;
    }
}
