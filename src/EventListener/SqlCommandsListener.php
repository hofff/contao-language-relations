<?php

declare(strict_types=1);

namespace Hofff\Contao\LanguageRelations\EventListener;

use Contao\CoreBundle\ServiceAnnotation\Hook;
use Doctrine\DBAL\Connection;
use Hofff\Contao\LanguageRelations\Database\Schema;

/** @Hook("sqlCompileCommands") */
final class SqlCommandsListener
{
    private Connection $connection;

    private Schema $schema;

    public function __construct(Connection $connection, Schema $schema)
    {
        $this->connection = $connection;
        $this->schema     = $schema;
    }

    /**
     * @param array<string,string> $queries
     *
     * @return array<string,string>
     */
    public function __invoke(array $queries): array
    {
        $existingViews = $this->connection->getSchemaManager()->listViews();

        foreach ($this->schema->views() as $view) {
            if (isset($existingViews[$view->getName()])) {
                continue;
            }

            $queries[$view->getName()] = $this->connection->getDatabasePlatform()->getCreateViewSQL(
                $view->getName(),
                $view->getSql()
            );
        }

        return $queries;
    }
}
