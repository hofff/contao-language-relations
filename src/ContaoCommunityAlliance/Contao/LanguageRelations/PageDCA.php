<?php

namespace ContaoCommunityAlliance\Contao\LanguageRelations;

/**
 * @author Oliver Hoff
 */
class PageDCA {

	public function inputFieldPageInfo($dc, $xlabel) {
		$tpl = new \BackendTemplate('cca_lr_pageInfo');
		$tpl->setData($dc->activeRecord->row());
		return $tpl->parse();
	}

	private $relations = array();

	public function onsubmitPage($dc) {
		if(isset($this->relations[$dc->id])) {
			$sql = 'DELETE FROM tl_cca_lr_relation WHERE pageFrom = ?';
			\Database::getInstance()->prepare($sql)->executeUncached($dc->id);

			$relations = deserialize($this->relations[$dc->id], true);
			if($relations) {
				$wildcards = rtrim(str_repeat('(?,?),', count($relations)), ',');
				$sql = 'INSERT INTO tl_cca_lr_relation (pageFrom, pageTo) VALUES ' . $wildcards;
				foreach($relations as $id) {
					$params[] = $dc->id;
					$params[] = $id;
				}
				\Database::getInstance()->prepare($sql)->executeUncached($params);

				LanguageRelations::createReflectionRelations($dc->id);
				LanguageRelations::createIntermediateRelations($dc->id);
			}

			unset($this->relations[$dc->id]);
		}
	}

	public function loadRelations($value, $dc) {
		$sql = 'SELECT pageTo FROM tl_cca_lr_relation WHERE pageFrom = ?';
		$result = \Database::getInstance()->prepare($sql)->executeUncached($dc->id);
		return $result->fetchEach('pageTo');
	}

	public function saveRelations($value, $dc) {
		$this->relations[$dc->id] = $value;
		return null;
	}

}
