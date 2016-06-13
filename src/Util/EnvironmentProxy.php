<?php

namespace Hofff\Contao\LanguageRelations\Util;

/**
 * @author Oliver Hoff <oliver@hofff.com>
 */
class EnvironmentProxy extends \Environment {

	/**
	 * @return array
	 */
	public static function getCache() {
		return self::$arrCache;
	}

	/**
	 * @param array $cache
	 * @return void
	 */
	public static function setCache(array $cache) {
		self::$arrCache = $cache;
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public static function getCacheValue($key) {
		return self::$arrCache[$key];
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	public static function setCacheValue($key, $value) {
		self::$arrCache[$key] = $value;
	}

}
