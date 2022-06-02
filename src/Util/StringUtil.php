<?php

declare(strict_types=1);

namespace Hofff\Contao\LanguageRelations\Util;

use function preg_replace_callback;
use function str_repeat;
use function strlen;

class StringUtil
{
    public static function tabsToSpaces(string $string, int $width = 4): string
    {
        return preg_replace_callback('/((?>[^\t\n\r]*))((?>\t+))/m', static function ($matches) use ($width) {
            $align  = strlen($matches[1]) % $width;
            $spaces = strlen($matches[2]) * $width;

            return $matches[1] . str_repeat(' ', $spaces - $align);
        }, $string);
    }
}
