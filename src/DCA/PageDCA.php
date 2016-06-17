<?php

namespace Hofff\Contao\LanguageRelations\DCA;

use Hofff\Contao\LanguageRelations\Relations;
use Hofff\Contao\LanguageRelations\Util\QueryUtil;

/**
 * @author Oliver Hoff <oliver@hofff.com>
 */
class PageDCA {

	/**
	 * @var Relations
	 */
	private $relations;

	/**
	 */
	public function __construct() {
		$this->relations = new Relations(
			'tl_hofff_language_relations_page',
			'hofff_language_relations_page_item',
			'hofff_language_relations_page_relation'
		);
	}

	/**
	 * @param string $table
	 * @return void
	 */
	public function hookLoadDataContainer($table) {
		if($table != 'tl_page') {
			return;
		}

		$palettes = &$GLOBALS['TL_DCA']['tl_page']['palettes'];
		foreach($palettes as $key => &$palette) {
			if($key != '__selector__' && $key != 'root') {
				$palette .= ';{hofff_language_relations_legend}';
				$_GET['do'] == 'hofff_language_relations_group' && $palette .= ',hofff_language_relations_info';
				$palette .= ',hofff_language_relations';
			}
		}
		unset($palette, $palettes);
	}

	/**
	 * @param \DataContainer $dc
	 * @param string $xlabel
	 * @return string
	 */
	public function inputFieldCallbackPageInfo($dc, $xlabel) {
		$tpl = new \BackendTemplate('hofff_language_relations_page_info');
		$tpl->setData($dc->activeRecord->row());
		return $tpl->parse();
	}

	/**
	 * @param integer $insertID
	 * @param \DataContainer $dc
	 * @return void
	 */
	public function oncopyCallback($insertID, $dc) {
		$this->copyRelations($dc->id, $insertID, $insertID);
	}

	/**
	 * @param integer $original
	 * @param integer $copy
	 * @param integer $copyStart
	 * @return void
	 */
	protected function copyRelations($original, $copy, $copyStart) {
		$original = $this->getPageInfo($original);
		$copy = $this->getPageInfo($copy);

		if($original->type == 'root') {
			if(!$original->group_id) {
				$result = QueryUtil::query(
					'SELECT dns, title FROM tl_page WHERE id = ?',
					null,
					[ $original->id ]
				);

				$result = QueryUtil::query(
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

		} elseif($original->root_page_id != $copy->root_page_id && $original->group_id == $copy->group_id) {
			$relatedItems = $this->relations->getRelations($original->id);
			$relatedItems[] = $original->id;
			$this->relations->createRelations($copy->id, $relatedItems);
			$this->relations->createReflectionRelations($copy->id);
		}

		$copyChildren = QueryUtil::query(
			'SELECT id FROM tl_page WHERE pid = ? ORDER BY sorting',
			null,
			[ $copy->id ]
		);
		if(!$copyChildren->numRows) {
			return;
		}

		$originalChildren = QueryUtil::query(
			'SELECT id FROM tl_page WHERE pid = ? AND id != ? ORDER BY sorting',
			null,
			[ $original->id, $copyStart ]
		);
		if($originalChildren->numRows != $copyChildren->numRows) {
			return;
		}

		while($originalChildren->next() && $copyChildren->next()) {
			$this->copyRelations($originalChildren->id, $copyChildren->id, $copyStart);
		}
	}

	/**
	 * @param integer $id
	 * @return array
	 */
	protected function getPageInfo($id) {
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
