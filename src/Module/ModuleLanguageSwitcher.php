<?php

namespace Hofff\Contao\LanguageRelations\Module;

use Hofff\Contao\LanguageRelations\LanguageRelations;
use Hofff\Contao\LanguageRelations\Util\ContaoUtil;

class ModuleLanguageSwitcher extends \Module {

	/**
	 * @var boolean
	 */
	protected $intlSupported;

	/**
	 * @var array<string, string>
	 */
	protected $labels;

	/**
	 * @param \ModuleModel $module
	 * @param string $column
	 */
	public function __construct($module, $column = 'main') {
		parent::__construct($module, $column);
		$this->intlSupported = class_exists('Locale');
		$this->strTemplate = 'mod_hofff_language_relations_language_switcher';
	}

	/**
	 * @see \Contao\Module::generate()
	 */
	public function generate() {
		if(TL_MODE == 'BE') {
			$tpl = new \BackendTemplate('be_wildcard');
			$tpl->wildcard	= '### LANGUAGE SWITCHER ###';
			$tpl->title		= $this->headline;
			$tpl->id		= $this->id;
			$tpl->link		= $this->name;
			$tpl->href		= 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;
			return $tpl->parse();
		}

		strlen($this->navigationTpl) || $this->navigationTpl = 'nav_default';

		return parent::generate();
	}

	/**
	 * @see \Contao\Module::compile()
	 */
	protected function compile() {
		$relations = LanguageRelations::getRelationsInstance();
		$currentPage = $GLOBALS['objPage'];
		$relatedPages = $relations->getRelations($currentPage->id, false, true);

		foreach($relatedPages as $rootPageID => &$page) {
			$page = \PageModel::findWithDetails($page === null ? $rootPageID : $page);
		}
		unset($page);

		if(!BE_USER_LOGGED_IN) {
			$relatedPages = array_filter($relatedPages, function($page) {
				return $page->rootIsPublic;
			});
			$relatedPages = array_map(function($page) {
				return ContaoUtil::isPublished($page)
					? $page
					: \PageModel::findWithDetails($page->hofff_root_page_id);
			}, $relatedPages);
		}

		if(!$this->hofff_language_relations_hide_current) {
			$relatedPages[$currentPage->hofff_root_page_id] = $currentPage;
		}

		$params = $this->getRequestParams($currentPage);

		$items = [];
		foreach($relatedPages as $rootPageID => $page) {
			/* @var $page \PageModel */

			$language = strtolower($page->rootLanguage);

			$item = [];
			$item['isActive']	= $page->id === $currentPage->id;
			$item['href']		= $page->getFrontendUrl($params, $language);
			$item['class']		= 'lang-' . $language;
			$item['link']		= $this->getLabel($language);
			$item['pageTitle']	= strip_tags(strlen($page->pageTitle) ? $page->pageTitle : $page->title);
			$item['language']	= $language;
// 			$item['target']		= $target . ' hreflang="' . $language . '"';
			$item['subitems']	= '';
			$item['accesskey']	= '';
			$item['tabindex']	= '';
			$item['nofollow']	= false;

			if($item['isActive']) {
				$item['href']	= \Environment::get('request');
				$item['class']	.= ' active';
			}

			$items[$rootPageID] = $item;
		}

		$items = $this->executeHook($items);

		if($this->hofff_language_relations_keep_qs) {
			foreach($items as &$item) {
				$item['href'] .= strpos($item['href'], '?') === false ? '?' : '&';
				$item['href'] .= \Environment::get('queryString');
			}
			unset($item);
		}

		$items = $this->sortItems($items);

		$tpl = new \FrontendTemplate($this->navigationTpl);
		$tpl->setData($this->arrData);
		$tpl->level = 'level_1';
		$tpl->items = $items;
		$this->Template->items = $tpl->parse();

		$this->injectAlternateLinks($items);
	}

	/**
	 * @param \PageModel $currentPage
	 * @return string|null
	 */
	protected function getRequestParams($currentPage) {
		if(!$this->hofff_language_relations_keep_request_params) {
			return null;
		}

		list($params) = explode('?', \Environment::get('request'), 2);
		$params = strval(substr($params, strlen($currentPage->alias) + 1));

		return $params;
	}

	/**
	 * @param string
	 * @return string
	 */
	protected function getLabel($language) {
		$labels = $this->getLabels();

		if(isset($labels[$language]) && strlen($labels[$language])) {
			return $labels[$language];
		}

		if($this->intlSupported) {
			return \Locale::getDisplayLanguage($language, $language);
		}

		return strtoupper($language);
	}

	/**
	 * @param array
	 * @return array
	 */
	protected function executeHook(array $items) {
		$hooks = &$GLOBALS['TL_HOOKS']['hofff_language_relations_language_switcher'];

		if(!isset($hooks) || !is_array($hooks)) {
			return $items;
		}

		foreach($hooks as $callback) {
			$items = call_user_func(
				[ \System::importStatic($callback[0]), $callback[1] ],
				$items,
				$this
			);
		}

		return $items;
	}

	/**
	 * @param array
	 * @return array
	 */
	protected function sortItems(array $items) {
		uasort($items, function($a, $b) {
			return strnatcasecmp($a['language'], $b['language']);
		});

		if($labels = $this->getLabels()) {
			$labels = array_flip(array_keys($labels));
			uasort($items, function($a, $b) use($labels) {
				$a = isset($labels[$a['language']]) ? $labels[$a['language']] : PHP_INT_MAX;
				$b = isset($labels[$b['language']]) ? $labels[$b['language']] : PHP_INT_MAX;
				return $a - $b;
			});
		}

		return $items;
	}

	/**
	 * @param array $items
	 * @return void
	 */
	protected function injectAlternateLinks(array $items) {
		foreach($items as $item) {
			if($item['isActive']) {
				continue;
			}

			$GLOBALS['TL_HEAD'][] = sprintf(
				'<link rel="alternate" hreflang="%s" lang="%s" href="%s" title="%s" />',
				$item['language'],
				$item['language'],
				$item['href'],
				specialchars($item['pageTitle'])
			);
		}
	}

	protected function getLabels() {
		if(isset($this->labels)) {
			return $this->labels;
		}

		$labels = [];

		foreach(deserialize($this->hofff_language_relations_labels, true) as $row) {
			if(!strlen($row['language'])) {
				continue;
			}

			$labels[$row['language']] = $row['label'];
		}

		return $this->labels = $labels;
	}

}
