<?php

declare(strict_types=1);

namespace Hofff\Contao\LanguageRelations\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Hofff\Contao\LanguageRelations\Database\Schema as DatabaseSchema;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

use function time;

final class ViewsMigration extends AbstractMigration
{
    public const INSTALLED_FILE = '.installed/views';

    private Connection $connection;

    private DatabaseSchema $schema;

    private Filesystem $filesystem;

    private FileLocatorInterface $fileLocator;

    public function __construct(
        Connection $connection,
        DatabaseSchema $schema,
        Filesystem $filesystem,
        FileLocatorInterface $fileLocator
    ) {
        $this->connection  = $connection;
        $this->schema      = $schema;
        $this->filesystem  = $filesystem;
        $this->fileLocator = $fileLocator;
    }

    public function shouldRun(): bool
    {
        $path = $this->fileLocator->locate('@HofffContaoLanguageRelationsBundle');

        return ! $this->filesystem->exists($path . self::INSTALLED_FILE);
    }

    public function run(): MigrationResult
    {
        foreach ($this->schema->views() as $view) {
            $this->connection->getSchemaManager()->dropAndCreateView($view);
        }

        $path = $this->fileLocator->locate('@HofffContaoLanguageRelationsBundle');

        try {
            $this->filesystem->dumpFile($path . self::INSTALLED_FILE, time());
        } catch (IOExceptionInterface $exception) {
            // We cannot mark view as successful on a readonly filesystem
        }

        return $this->createResult(true);
    }
}
