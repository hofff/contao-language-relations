<?php

declare(strict_types=1);

namespace Hofff\Contao\LanguageRelations\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Hofff\Contao\LanguageRelations\HofffContaoLanguageRelationsBundle;
use Hofff\Contao\RootRelations\HofffContaoRootRelationsBundle;

final class Plugin implements BundlePluginInterface
{
    /** {@inheritDoc} */
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(HofffContaoLanguageRelationsBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class, HofffContaoRootRelationsBundle::class]),
        ];
    }
}
