<?php

namespace Hofff\Contao\LanguageRelations\Util;

/**
 * @author Oliver Hoff <oliver@hofff.com>
 */
class ContaoUtil {

	/**
	 * @param \Model $model
	 * @return boolean
	 */
	public static function isPublished(\Model $model) {
		if(BE_USER_LOGGED_IN) {
			return true;
		}

		$time = time();
		return $model->published
			&& (!$model->start || $model->start <= $time)
			&& (!$model->stop || $model->stop >= $time);
	}

}
