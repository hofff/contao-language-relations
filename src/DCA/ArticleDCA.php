<?php

declare(strict_types=1);

namespace Hofff\Contao\LanguageRelations\DCA;

use Contao\ArticleModel;
use Contao\Backend;
use Contao\BackendTemplate;
use Contao\Input;
use Hofff\Contao\LanguageRelations\Relations;
use Hofff\Contao\LanguageRelations\Util\QueryUtil;
use function array_keys;
use function count;
use function serialize;
use function usort;

class ArticleDCA
{
    /** @var Relations */
    private $relations;

    /** @var array<int, ArticleModel[]> */
    public static $articleCache = [];

    public function __construct()
    {
        $this->relations = new Relations(
            'tl_hofff_language_relations_page',
            'hofff_language_relations_page_item',
            'hofff_language_relations_page_relation'
        );
    }

    public function addArticleTranslationHeaderCss() : void
    {
        if (Input::get('act') === 'edit') {
            return;
        }

        $GLOBALS['TL_CSS']['hofffcontaolanguagerelations_be'] = 'bundles/hofffcontaolanguagerelations/css/backend.css';
    }

    public function addArticleTranslationLinks() : void
    {
        if (Input::get('act') !== 'edit') {
            return;
        }

        $articleModel = ArticleModel::findByPk(Input::get('id'));
        if (! $this->relations->getRelations($articleModel->pid)) {
            return;
        }
        $GLOBALS['TL_CSS']['hofffcontaolanguagerelations_be'] = 'bundles/hofffcontaolanguagerelations/css/backend.css';
        foreach (array_keys($GLOBALS['TL_DCA']['tl_article']['palettes']) as $key) {
            //skip '__selector__
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
    public function getLinkedArticles() : string
    {
        $objArticle = ArticleModel::findByPk(Input::get('id'));
        //get the related pages
        $arrPages = $this->relations->getRelations($objArticle->pid);
        //add the curent pid
        $arrPages[] = $objArticle->pid;
        //get the articles of the related pages
        $this->collectArticlesFromPages($arrPages);
        //find the position of the current article
        $intArticlePosition = $this->getArticlePosition($objArticle);
        //sort the pages
        usort($arrPages, static function ($a, $b) {
            return static::$articleCache[$a]['rootIdSorting'] < static::$articleCache[$b]['rootIdSorting'] ? -1 : 1;
        });
        $newValues = [];
        //build return array
        foreach ($arrPages as $value) {
            $newValues[] = [
                'linkedArticles'     => static::$articleCache[$value][$intArticlePosition]->id,
                'value'      => '',
            ];
        }

        return serialize($newValues);
    }

    /**
     * @return string returns an empty string.
     */
    public function returnEmptyString() : string
    {
        return '';
    }

    /**
     * @return string[]
     */
    public function getTranslationArticles() : array
    {
        $return          = [];
        $articleModel    = ArticleModel::findByPk(Input::get('id'));
        $articlePosition = $this->getArticlePosition($articleModel);
        if ($articlePosition === null) {
            return $return;
        }
        //get the related pages
        $ids   = $this->relations->getRelations($articleModel->pid);
        $ids[] = $articleModel->pid;
        foreach ($ids as $value) {
            //try to load article if not in cache
            if (! static::$articleCache[$value]) {
                $this->collectArticlesFromPages([$value]);
            }
            //skip this page if no matching article is found
            if (! static::$articleCache[$value][$articlePosition]) {
                continue;
            }
            $template            = new BackendTemplate('be_hofff_language_switcher_article');
            $article             = static::$articleCache[$value][$articlePosition]->row();
            $article['language'] = static::$articleCache[$value]['rootIdLanguage'];
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
     * @return mixed[][]
     */
    public function addArticleTranslations(array $add) : array
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
        //get the related pages
        $ids = $this->relations->getRelations($articleModel->pid);
        //return if no related pages are found
        if (empty($ids)) {
            return $add;
        }
        $ids[] = $articleModel->pid;
        //get the articles of the related pages
        $this->collectArticlesFromPages($ids);
        $intArticlePosition = $this->getArticlePosition($articleModel);
        if ($intArticlePosition === null) {
            return $add;
        }
        //sort the pages
        usort($ids, static function ($a, $b) {
            return static::$articleCache[$a]['rootIdSorting'] < static::$articleCache[$b]['rootIdSorting'] ? -1 : 1;
        });
        foreach ($ids as $value) {
            if (! static::$articleCache[$value]) {
                $this->collectArticlesFromPages([$value]);
            }
            //skip this page if no matching article is found
            if (! static::$articleCache[$value][$intArticlePosition]) {
                continue;
            }
            $articleRow             = static::$articleCache[$value][$intArticlePosition]->row();
            $articleRow['language'] = static::$articleCache[$value]['rootIdLanguage'];
            $articleRow['href']     = Backend::addToUrl('do=article&table=tl_content&id=' . $articleRow['id']);
            if (Input::get('id') === $articleRow['id']) {
                $articleRow['isActive'] = true;
            }
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
     * @param int[] $pageIds
     */
    private function collectArticlesFromPages(array $pageIds) : void
    {
        foreach ($pageIds as $pageId) {
            //update cache if necessary
            if (static::$articleCache[$pageId]) {
                continue;
            }

            $articleCollection = ArticleModel::findBy('pid', $pageId, ['order' => 'sorting ASC']);
            if ($articleCollection === null) {
                continue;
            }
            static::$articleCache[$pageId] = $articleCollection->getModels();
            $page                          = QueryUtil::query(
                'SELECT * FROM tl_page WHERE id = (
                 SELECT hofff_root_page_id FROM tl_page WHERE id = ? LIMIT 1) LIMIT 1',
                null,
                [$pageId]
            );

            static::$articleCache[$pageId]['rootIdSorting']  = $page->sorting;
            static::$articleCache[$pageId]['rootIdLanguage'] = $page->language;
        }
        return;
    }

    private function getArticlePosition(ArticleModel $articleModel) : ?int
    {
        if (! static::$articleCache[$articleModel->pid]) {
            $this->collectArticlesFromPages([$articleModel->pid]);
        }
        $articlePosition = null;
        foreach (static::$articleCache[$articleModel->pid] as $key => $article) {
            if ($article->id !== $articleModel->id) {
                continue;
            }

            $articlePosition = $key;
            break;
        }
        return $articlePosition;
    }
}
