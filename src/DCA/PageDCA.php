<?php

declare(strict_types=1);

namespace Hofff\Contao\LanguageRelations\DCA;

use Contao\BackendTemplate;
use Contao\Database\Result;
use Contao\DataContainer;
use Hofff\Contao\LanguageRelations\Relations;
use Hofff\Contao\LanguageRelations\Util\QueryUtil;
use function time;

class PageDCA
{
    /** @var Relations */
    private $relations;

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

    public function oncopyCallback(int $insertID, DataContainer $dc) : void
    {
        $this->copyRelations((int) $dc->id, $insertID, $insertID);
    }

    protected function copyRelations(int $original, int $copy, int $copyStart) : void
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

    protected function getPageInfo(int $id) : Result
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
}
