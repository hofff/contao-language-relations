<?php

namespace ContaoCommunityAlliance\Contao\LanguageRelations;

/**
 * @author Oliver Hoff
 */
class EnvironmentProxy extends \Environment {

	public static function getCache() {
		return self::getInstance()->arrCache;
	}

	public static function setCache(array $cache) {
		self::getInstance()->arrCache = $cache;
	}

	public static function getCacheValue($key) {
		return self::getInstance()->arrCache[$key];
	}

	public static function setCacheValue($key, $value) {
		self::getInstance()->arrCache[$key] = $value;
	}

}
