<?php

declare(strict_types=1);

namespace Hofff\Contao\LanguageRelations\Util;

use Contao\Database;
use Contao\Database\Result;
use Contao\Database\Statement;
use function array_filter;
use function count;
use function rtrim;
use function str_repeat;
use function vsprintf;

class QueryUtil
{
    /**
     * @param mixed[]|null $placeholders
     * @param mixed[]|null $params
     */
    public static function query(string $sql, ?array $placeholders = null, ?array $params = null) : Result
    {
        $placeholders === null || $sql = vsprintf($sql, $placeholders);
        return Database::getInstance()->prepare($sql)->execute($params);
    }

    /**
     * @param mixed[]|null $placeholders
     * @param mixed[]|null $params
     */
    public static function exec(string $sql, ?array $placeholders = null, ?array $params = null) : Statement
    {
        $placeholders === null || $sql = vsprintf($sql, $placeholders);
        return Database::getInstance()->prepare($sql)->execute($params);
    }

    /**
     * @param mixed  $params
     * @param string $wildcard
     */
    public static function wildcards($params, $wildcard = '?') : string
    {
        return rtrim(str_repeat($wildcard . ',', count((array) $params)), ',');
    }

    /**
     * @param int|int[] $ids
     *
     * @return int[]
     */
    public static function ids($ids) : array
    {
        return array_filter((array) $ids, static function ($id) {
            return $id >= 1;
        });
    }
}
