<?php

declare(strict_types=1);

namespace Hofff\Contao\LanguageRelations\Util;

use Contao\Environment;

class EnvironmentProxy extends Environment
{
    /**
     * @return mixed[]
     */
    public static function getCache(): array
    {
        return self::$arrCache;
    }

    /**
     * @param mixed[] $cache
     */
    public static function setCache(array $cache): void
    {
        self::$arrCache = $cache;
    }

    /**
     * @return mixed
     */
    public static function getCacheValue(string $key)
    {
        return self::$arrCache[$key];
    }

    /**
     * @param mixed $value
     */
    public static function setCacheValue(string $key, $value): void
    {
        self::$arrCache[$key] = $value;
    }
}
