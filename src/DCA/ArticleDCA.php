<?php

declare(strict_types=1);

namespace Hofff\Contao\LanguageRelations\DCA;

use Contao\ArticleModel;
use Contao\Backend;
use Contao\BackendTemplate;
use Contao\Input;
use Contao\Model\Collection;
use Hofff\Contao\LanguageRelations\Relations;
use Hofff\Contao\LanguageRelations\Util\QueryUtil;

use function array_keys;
use function assert;
use function count;
use function is_int;
use function serialize;
use function usort;

class ArticleDCA
{
    private Relations $relations;

    /** @var array<int|string, array{rootIdSorting: int, rootIdLanguage: string, articles: ArticleModel[]}> */
    public static array $articleCache = [];

    public function __construct()
    {
        $this->relations = new Relations(
            'tl_hofff_language_relations_page',
            'hofff_language_relations_page_item',
            'hofff_language_relations_page_relation'
        );
    }

    /**
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function addArticleTranslationHeaderCss(): void
    {
        if (Input::get('act') === 'edit') {
            return;
        }

        $GLOBALS['TL_CSS']['hofffcontaolanguagerelations_be'] = 'bundles/hofffcontaolanguagerelations/css/backend.css';
    }

    /**
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function addArticleTranslationLinks(): void
    {
        if (Input::get('act') !== 'edit') {
            return;
        }

        $articleModel = ArticleModel::findByPk(Input::get('id'));
        if (! $articleModel || ! $this->relations->getRelations($articleModel->pid)) {
            return;
        }

        $GLOBALS['TL_CSS']['hofffcontaolanguagerelations_be'] = 'bundles/hofffcontaolanguagerelations/css/backend.css';
        foreach (array_keys($GLOBALS['TL_DCA']['tl_article']['palettes']) as $key) {
            /** @psalm-suppress TypeDoesNotContainType */
            if ($key === '__selector__') {
                continue;
            }

            $GLOBALS['TL_DCA']['tl_article']['palettes'][$key] =
                'hofff_language_relations_article_links;' . $GLOBALS['TL_DCA']['tl_article']['palettes'][$key];
        }
    }

    /**
     * Compare current page language against the stored once.
     */
    public function getLinkedArticles(): string
    {
        $objArticle = ArticleModel::findByPk(Input::get('id'));
        if ($objArticle === null) {
            return serialize([]);
        }

        //get the related pages
        /** @psalm-var array<array-key, int|string> $arrPages */
        $arrPages = $this->relations->getRelations($objArticle->pid);
        //add the curent pid
        $arrPages[] = (int) $objArticle->pid;
        //get the articles of the related pages
        $this->collectArticlesFromPages($arrPages);
        //find the position of the current article
        $intArticlePosition = $this->getArticlePosition($objArticle);
        //sort the pages
        usort($arrPages, static function ($pageA, $pageB) {
            return static::$articleCache[$pageA]['rootIdSorting'] < static::$articleCache[$pageB]['rootIdSorting']
                ? -1
                : 1;
        });
        $newValues = [];
        if ($intArticlePosition === null) {
            return serialize($newValues);
        }

        //build return array
        foreach ($arrPages as $value) {
            $newValues[] = [
                'linkedArticles'     => static::$articleCache[$value]['articles'][$intArticlePosition]->id,
                'value'      => '',
            ];
        }

        return serialize($newValues);
    }

    /**
     * @return string returns an empty string.
     */
    public function returnEmptyString(): string
    {
        return '';
    }

    /**
     * @return string[]
     */
    public function getTranslationArticles(): array
    {
        $return       = [];
        $articleModel = ArticleModel::findByPk(Input::get('id'));
        if ($articleModel === null) {
            return $return;
        }

        $articlePosition = $this->getArticlePosition($articleModel);
        if ($articlePosition === null) {
            return $return;
        }

        //get the related pages
        /** @psalm-var list<int|string> $pageIds */
        $pageIds   = $this->relations->getRelations($articleModel->pid);
        $pageIds[] = (int) $articleModel->pid;

        $this->collectArticlesFromPages($pageIds);

        foreach ($pageIds as $pageId) {
            assert(is_int($pageId));

            // skip this page if no matching article is found
            if (! isset(static::$articleCache[$pageId]['articles'][$articlePosition])) {
                continue;
            }

            $template            = new BackendTemplate('be_hofff_language_switcher_article');
            $article             = static::$articleCache[$pageId]['articles'][$articlePosition]->row();
            $article['language'] = static::$articleCache[$pageId]['rootIdLanguage'];
            $article['href']     = Backend::addToUrl('do=article&act=edit&id=' . $article['id']);
            if (Input::get('id') === $article['id']) {
                $article['isActive'] = true;
            }

            $template->article      = $article;
            $return[$article['id']] = $template->parse();
        }

        return $return;
    }

    /**
     * @param mixed[][] $add
     *
     * @return array<array-key, array<array-key, mixed>|string>
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function addArticleTranslations(array $add): array
    {
        if (Input::get('do') !== 'article') {
            return $add;
        }

        $articles = [];
        //find the current article
        if (Input::get('act') === 'paste' && (Input::get('mode') === 'copy' || Input::get('mode') === 'cut')) {
            $intCePid = QueryUtil::query(
                'SELECT pid FROM tl_content WHERE id = ? LIMIT 1',
                null,
                [Input::get('id')]
            )->pid;

            $articleModel = ArticleModel::findByPk($intCePid);
        } else {
            $articleModel = ArticleModel::findByPk(Input::get('id'));
        }

        if ($articleModel === null) {
            return $add;
        }

        //get the related pages
        $pageIds = $this->relations->getRelations($articleModel->pid);
        //return if no related pages are found
        if (empty($pageIds)) {
            return $add;
        }

        /** @psalm-var array<int,int> $pageIds */
        $pageIds[] = $articleModel->pid;
        //get the articles of the related pages
        $this->collectArticlesFromPages($pageIds);
        $intArticlePosition = $this->getArticlePosition($articleModel);
        if ($intArticlePosition === null) {
            return $add;
        }

        //sort the pages
        usort($pageIds, static function ($pageA, $pageB) {
            return static::$articleCache[$pageA]['rootIdSorting'] < static::$articleCache[$pageB]['rootIdSorting']
                ? -1
                : 1;
        });
        foreach ($pageIds as $pageId) {
            //skip this page if no matching article is found
            if (! isset(static::$articleCache[$pageId]['articles'][$intArticlePosition])) {
                continue;
            }

            $articleRow             = static::$articleCache[$pageId]['articles'][$intArticlePosition]->row();
            $articleRow['language'] = static::$articleCache[$pageId]['rootIdLanguage'];
            $articleRow['href']     = Backend::addToUrl('do=article&table=tl_content&id=' . $articleRow['id']);
            $articleRow['isActive'] = (Input::get('id') === $articleRow['id']);

            $articles[] = $articleRow;
        }

        //return if no realted articles could be found
        if (count($articles) <= 1) {
            return $add;
        }

        $objTemplate              = new BackendTemplate('be_hofff_language_switcher_article_header');
        $objTemplate->arrArticles = $articles;

        $add[$GLOBALS['TL_LANG']['tl_content']['belanguage_header']] = $objTemplate->parse();

        return $add;
    }

    /**
     * @param string[]|int[] $pageIds
     */
    private function collectArticlesFromPages(array $pageIds): void
    {
        foreach ($pageIds as $pageId) {
            //update cache if necessary
            if (isset(static::$articleCache[$pageId])) {
                continue;
            }

            $page = QueryUtil::query(
                'SELECT * FROM tl_page WHERE id = (
                 SELECT hofff_root_page_id FROM tl_page WHERE id = ? LIMIT 1) LIMIT 1',
                null,
                [$pageId]
            );

            $articleCollection = ArticleModel::findBy('pid', $pageId, ['order' => 'sorting ASC']);

            /** @psalm-suppress PropertyTypeCoercion */
            static::$articleCache[$pageId] = [
                'rootIdSorting'  => (int) $page->sorting,
                'rootIdLanguage' => $page->language,
                'articles'       => $articleCollection instanceof Collection ? $articleCollection->getModels() : [],
            ];
        }
    }

    private function getArticlePosition(ArticleModel $articleModel): ?int
    {
        $this->collectArticlesFromPages([$articleModel->pid]);

        foreach (static::$articleCache[$articleModel->pid]['articles'] as $key => $article) {
            if ($article->id !== $articleModel->id) {
                continue;
            }

            return $key;
        }

        return null;
    }
}
