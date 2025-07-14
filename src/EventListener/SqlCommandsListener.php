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
     * @param array<string,array<string,string>> $queries
     *
     * @return array<string,array<string,string>>
     */
    public function __invoke(array $queries): array
    {
        $existingViews = $this->connection->getSchemaManager()->listViews();
        $plattform     = $this->connection->getDatabasePlatform();

        foreach ($this->schema->views() as $view) {
            if (isset($existingViews[$view->getName()])) {
                continue;
            }

            $queries['hofff-contao-language-relations'][$view->getName()] = $plattform->getCreateViewSQL(
                $view->getName(),
                $view->getSql(),
            );
        }

        return $queries;
    }
}
