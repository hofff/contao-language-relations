<?php

namespace Hofff\Contao\LanguageRelations;

/**
 * @author Oliver Hoff <oliver@hofff.com>
 */
class GroupDCA {

	/**
	 * @var array
	 */
	private $roots;

	/**
	 */
	public function __construct() {
		$this->roots = [];
	}

	/**
	 * @param \DataContainer $dc
	 * @return string
	 */
	public function keySelectriAJAXCallback($dc) {
		$key = 'isAjaxRequest';

		// the X-Requested-With gets deleted on ajax requests by selectri widget,
		// to enable regular contao DC process, but we need this behavior for the
		// editAll call respecting the passed id
		$$key = EnvironmentProxy::getCacheValue($key);
		EnvironmentProxy::setCacheValue($key, true);

		$return = $dc->editAll(\Input::get('hofff_page_id'));

		// this would never be reached, but we clean up the env
		EnvironmentProxy::setCacheValue($key, $$key);

		return $return;
	}

	/**
	 * @return void
	 */
	public function keyEditRelations() {
		$fields = [ 'hofff_language_relations_info', 'hofff_page_translations' ];
		$roots = (array) $_GET['roots'];
		$roots = array_map('intval', $roots);
		$roots = array_filter($roots, function($root) { return $root >= 1; });
		$roots = array_unique($roots);

		switch($_GET['filter']) {
			case 'incomplete':
				$ids = LanguageRelations::getIncompleteRelatedPages($roots[0]);
				$ids || $msg = $GLOBALS['TL_LANG']['tl_hofff_translation_group']['noIncompleteRelations'];
				break;

			case 'ambiguous':
				$ids = LanguageRelations::getAmbiguousRelatedPages($roots[0]);
				$ids || $msg = $GLOBALS['TL_LANG']['tl_hofff_translation_group']['noAmbiguousRelations'];
				break;

			default:
				if($roots) {
					$wildcards = rtrim(str_repeat('?,', count($roots)), ',');
					$sql = 'SELECT id FROM tl_page WHERE hofff_root_page_id IN (' . $wildcards . ') AND type != \'root\'';
					$result = \Database::getInstance()->prepare($sql)->executeUncached($roots);
					$ids = $result->fetchEach('id');
				}
				break;
		}

		if(!$ids) {
			\Message::addConfirmation($msg ?: $GLOBALS['TL_LANG']['tl_hofff_translation_group']['noPagesToEdit']);
			\Controller::redirect(\System::getReferer());
			return;
		}

		$session = \Session::getInstance()->getData();
		$session['CURRENT']['IDS'] = $ids;
		$session['CURRENT']['tl_page'] = $fields;
		\Session::getInstance()->setData($session);

		\Controller::redirect('contao/main.php?do=hofff_translation_group&table=tl_page&act=editAll&fields=1&rt=' . REQUEST_TOKEN);
	}

	/**
	 * @param string $group
	 * @param string $mode
	 * @param string $field
	 * @param array $row
	 * @param \DataContainer $dc
	 * @return string
	 */
	public function groupGroup($group, $mode, $field, $row, $dc) {
		return $row['title'];
	}

	/**
	 * @param array $row
	 * @param string $label
	 * @return string
	 */
	public function labelGroup($row, $label) {
		$sql = 'SELECT * FROM tl_page WHERE hofff_translation_group_id = ? ORDER BY title';
		$result = \Database::getInstance()->prepare($sql)->executeUncached($row['id']);

		$groupRoots = [];
		while($result->next()) {
			$row = $result->row();
			$row['incomplete'] = LanguageRelations::getIncompleteRelatedPages($row['id']);
			$row['ambiguous'] = LanguageRelations::getAmbiguousRelatedPages($row['id']);
			$groupRoots[] = $row;
		}

		$tpl = new \BackendTemplate('hofff_groupRoots');
		$tpl->groupRoots = $groupRoots;

		return $tpl->parse();
	}

	/**
	 * @return array<string, array<integer, string>>
	 */
	public function getRootsOptions() {
		$sql = <<<SQL
SELECT		page.id,
			page.title,
			page.language,
			grp.id				AS grpID,
			grp.title			AS grpTitle

FROM		tl_page				AS page
LEFT JOIN	tl_hofff_translation_group		AS grp			ON grp.id = page.hofff_translation_group_id

WHERE		page.type = ?

ORDER BY	grp.title IS NOT NULL,
			grp.title,
			page.title
SQL;
		$result = \Database::getInstance()->prepare($sql)->executeUncached('root');

		$options = [];
		while($result->next()) {
			$groupTitle = $result->grpID
				? $result->grpTitle . ' (ID ' . $result->grpID . ')'
				: $GLOBALS['TL_LANG']['tl_hofff_translation_group']['notGrouped'];
			$options[$groupTitle][$result->id] = $result->title . ' [' . $result->language . ']';
		}

		return $options;
	}

	/**
	 * @param \DataContainer $dc
	 * @return void
	 */
	public function onsubmitGroup($dc) {
		if(isset($this->roots[$dc->id])) {
			$sql = 'UPDATE tl_page SET hofff_translation_group_id = NULL WHERE hofff_translation_group_id = ?';
			\Database::getInstance()->prepare($sql)->executeUncached($dc->id);

			$roots = deserialize($this->roots[$dc->id], true);
			if($roots) {
				$wildcards = rtrim(str_repeat('?,', count($roots)), ',');
				$sql = 'UPDATE tl_page SET hofff_translation_group_id = ? WHERE id IN (' . $wildcards . ')';
				array_unshift($roots, $dc->id);
				\Database::getInstance()->prepare($sql)->executeUncached($roots);
			}
		}
	}

	/**
	 * @param mixed $value
	 * @param \DataContainer $dc
	 * @return array<integer>
	 */
	public function loadRoots($value, $dc) {
		$sql = 'SELECT id FROM tl_page WHERE hofff_translation_group_id = ? AND type = ? ORDER BY title';
		$result = \Database::getInstance()->prepare($sql)->executeUncached($dc->id, 'root');
		return $result->fetchEach('id');
	}

	/**
	 * @param integer $value
	 * @param \DataContainer $dc
	 * @return null
	 */
	public function saveRoots($value, $dc) {
		$this->roots[$dc->id] = $value;
		return null;
	}

}
