<?php

declare(strict_types=1);

namespace Hofff\Contao\LanguageRelations\DCA;

use Contao\Backend;
use Contao\BackendTemplate;
use Contao\Database\Result;
use Contao\DataContainer;
use Contao\Input;
use Contao\PageModel;
use Hofff\Contao\LanguageRelations\Relations;
use Hofff\Contao\LanguageRelations\Util\QueryUtil;
use function array_keys;
use function serialize;
use function time;
use function usort;

class PageDCA
{
    /** @var Relations */
    private $relations;

    /** @var mixed[][] */
    public static $pageCache = [];

    /**
     * Create a new instance.
     */
    public function __construct()
    {
        $this->relations = new Relations(
            'tl_hofff_language_relations_page',
            'hofff_language_relations_page_item',
            'hofff_language_relations_page_relation'
        );
    }

    public function hookLoadDataContainer(string $table) : void
    {
        if ($table !== 'tl_page') {
            return;
        }

        $palettes = &$GLOBALS['TL_DCA']['tl_page']['palettes'];
        foreach ($palettes as $key => &$palette) {
            if ($key === '__selector__' || $key === 'root') {
                continue;
            }

            $palette                                                     .= ';{hofff_language_relations_legend}';
            $_GET['do'] === 'hofff_language_relations_group' && $palette .= ',hofff_language_relations_info';
            $palette                                                     .= ',hofff_language_relations';
        }
        unset($palette, $palettes);
    }

    public function inputFieldCallbackPageInfo(DataContainer $dc, string $xlabel) : string
    {
        $tpl = new BackendTemplate('hofff_language_relations_page_info');
        $tpl->setData($dc->activeRecord->row());
        return $tpl->parse();
    }

    /**
     * @param string|int $insertID
     */
    public function oncopyCallback($insertID, DataContainer $dc) : void
    {
        $this->copyRelations((int) $dc->id, (int) $insertID, (int) $insertID);
    }

    private function copyRelations(int $original, int $copy, int $copyStart) : void
    {
        $original = $this->getPageInfo($original);
        $copy     = $this->getPageInfo($copy);

        if ($original->type === 'root') {
            if (! $original->group_id) {
                $result = QueryUtil::query(
                    'SELECT dns, title FROM tl_page WHERE id = ?',
                    null,
                    [ $original->id ]
                );

                $result             = QueryUtil::query(
                    'INSERT INTO tl_hofff_language_relations_group(tstamp, title) VALUES(?, ?)',
                    null,
                    [ time(), $result->dns ?: $result->title ]
                );
                $original->group_id = $result->insertId;

                $result = QueryUtil::query(
                    'UPDATE tl_page SET hofff_language_relations_group_id = ? WHERE id = ?',
                    null,
                    [ $original->group_id, $original->id ]
                );
            }

            QueryUtil::query(
                'UPDATE tl_page SET hofff_language_relations_group_id = ? WHERE id = ?',
                null,
                [ $original->group_id, $copy->id ]
            );
        } elseif ($original->root_page_id !== $copy->root_page_id && $original->group_id === $copy->group_id) {
            $relatedItems   = $this->relations->getRelations($original->id);
            $relatedItems[] = $original->id;
            $this->relations->createRelations((int) $copy->id, $relatedItems);
            $this->relations->createReflectionRelations((int) $copy->id);
        }

        $copyChildren = QueryUtil::query(
            'SELECT id FROM tl_page WHERE pid = ? ORDER BY sorting',
            null,
            [ $copy->id ]
        );
        if (! $copyChildren->numRows) {
            return;
        }

        $originalChildren = QueryUtil::query(
            'SELECT id FROM tl_page WHERE pid = ? AND id != ? ORDER BY sorting',
            null,
            [ $original->id, $copyStart ]
        );
        if ($originalChildren->numRows !== $copyChildren->numRows) {
            return;
        }

        while ($originalChildren->next() && $copyChildren->next()) {
            $this->copyRelations((int) $originalChildren->id, (int) $copyChildren->id, $copyStart);
        }
    }

    public function addPageTranslationLinks() : void
    {
        if (Input::get('act') !== 'edit' || ! $this->relations->getRelations(Input::get('id'))) {
            return;
        }

        $GLOBALS['TL_CSS']['hofffcontaolanguagerelations_be'] = 'bundles/hofffcontaolanguagerelations/css/backend.css';
        foreach (array_keys($GLOBALS['TL_DCA']['tl_page']['palettes']) as $key) {
            //skip '__selector__
            if ($key === '__selector__') {
                continue;
            }
            $GLOBALS['TL_DCA']['tl_page']['palettes'][$key] = 'hofff_language_relations_page_links;' .
                $GLOBALS['TL_DCA']['tl_page']['palettes'][$key];
        }
    }

    /**
     * Compare current page language against the stored once.
     */
    public function getLinkedPages() : string
    {
        //get the related languaged
        $relations = $this->relations->getRelations(Input::get('id'));
        //add the curent id
        $relations[] = Input::get('id');
        //get page details and sorting info
        $this->collectPageDetails($relations);
        usort($relations, static function ($a, $b) {
            return static::$pageCache[$a]['rootIdSorting'] < static::$pageCache[$b]['rootIdSorting'] ? -1 : 1;
        });
        //build return array
        $newValues = [];
        foreach ($relations as $value) {
            $newValues[] = [
                'linkedPages'    => $value,
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
    public function getTranslationPages() : array
    {
        $return = [];
        $ids    = $this->relations->getRelations(Input::get('id'));
        $ids[]  = Input::get('id');
        foreach ($ids as $value) {
            $template     = new BackendTemplate('be_hofff_language_switcher_page');
            $page         = PageModel::findWithDetails($value)->row();
            $page['href'] = Backend::addToUrl('do=page&act=edit&id=' . $value);
            if (Input::get('id') === $value) {
                $page['isActive'] = true;
            }
            $template->page = $page;
            $return[$value] = $template->parse();
        }

        return $return;
    }

    private function getPageInfo(int $id) : Result
    {
        $sql = <<<SQL
SELECT
	page.id														AS id,
	page.type													AS type,
	page.hofff_root_page_id										AS root_page_id,
	COALESCE(root_page.hofff_language_relations_group_id, 0)	AS group_id
FROM
	tl_page
	AS page
LEFT JOIN
	tl_page
	AS root_page
	ON root_page.id = page.hofff_root_page_id
WHERE
	page.id = ?
SQL;
        return QueryUtil::query($sql, null, [ $id ]);
    }

    /**
     * @param int[] $pageIds
     */
    private function collectPageDetails(array $pageIds) : void
    {
        foreach ($pageIds as $value) {
            if (static::$pageCache[$value]) {
                continue;
            }

            static::$pageCache[$value]                  = PageModel::findWithDetails($value)->row();
            static::$pageCache[$value]['rootIdSorting'] = QueryUtil::query(
                'SELECT sorting FROM tl_page WHERE id = ?',
                [static::$pageCache[$value]['rootId']]
            )->sorting;
        }
    }
}
