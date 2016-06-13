<?php

namespace Hofff\Contao\LanguageRelations\Util;

/**
 * @author Oliver Hoff <oliver@hofff.com>
 */
class StringUtil {

	/**
	 * @param string $string
	 * @param integer $width
	 * @return string
	 */
	public static function tabsToSpaces($string, $width = 4) {
		return preg_replace_callback('/((?>[^\t\n\r]*))((?>\t+))/m', function($matches) use($width) {
			$align = strlen($matches[1]) % $width;
			$spaces = strlen($matches[2]) * $width;
			return $matches[1] . str_repeat(' ', $spaces - $align);
		}, $string);
	}

}
