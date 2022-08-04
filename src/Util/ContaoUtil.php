<?php

declare(strict_types=1);

namespace Hofff\Contao\LanguageRelations\Util;

use Contao\Model;

use function defined;
use function time;

class ContaoUtil
{
    public static function isPublished(Model $model): bool
    {
        if (defined('BE_USER_LOGGED_IN') && BE_USER_LOGGED_IN) {
            return true;
        }

        $time = time();

        return $model->published
            && (! $model->start || $model->start <= $time)
            && (! $model->stop || $model->stop >= $time);
    }
}
