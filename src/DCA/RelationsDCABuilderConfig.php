<?php

declare(strict_types=1);

namespace Hofff\Contao\LanguageRelations\DCA;

use Hofff\Contao\LanguageRelations\Relations;

/** @SuppressWarnings(PHPMD.LongVariable) */
class RelationsDCABuilderConfig
{
    protected Relations $relations;

    protected string $aggregateFieldName;

    protected string $aggregateView;

    protected string $treeView;

    /** @var callable|null */
    protected $selectriDataFactoryConfiguratorCallback;

    protected string $selectriNodeLabelTemplate;

    protected string $selectriNodeContentTemplate;

    public function __construct()
    {
        $this->selectriNodeLabelTemplate   = 'hofff_language_relations_node_label';
        $this->selectriNodeContentTemplate = 'hofff_language_relations_node_content';
    }

    public function getRelations(): Relations
    {
        return $this->relations;
    }

    public function setRelations(Relations $relations): void
    {
        $this->relations = $relations;
    }

    public function getAggregateFieldName(): string
    {
        return $this->aggregateFieldName;
    }

    public function setAggregateFieldName(string $aggregateFieldName): void
    {
        $this->aggregateFieldName = $aggregateFieldName;
    }

    public function getAggregateView(): string
    {
        return $this->aggregateView;
    }

    public function setAggregateView(string $aggregateView): void
    {
        $this->aggregateView = $aggregateView;
    }

    public function getTreeView(): string
    {
        return $this->treeView;
    }

    public function setTreeView(string $treeView): void
    {
        $this->treeView = $treeView;
    }

    public function getSelectriDataFactoryConfiguratorCallback(): ?callable
    {
        return $this->selectriDataFactoryConfiguratorCallback;
    }

    public function setSelectriDataFactoryConfiguratorCallback(?callable $callback): void
    {
        $this->selectriDataFactoryConfiguratorCallback = $callback;
    }

    public function getSelectriNodeLabelTemplate(): string
    {
        return $this->selectriNodeLabelTemplate;
    }

    public function setSelectriNodeLabelTemplate(string $template): void
    {
        $this->selectriNodeLabelTemplate = $template;
    }

    public function getSelectriNodeContentTemplate(): string
    {
        return $this->selectriNodeContentTemplate;
    }

    public function setSelectriNodeContentTemplate(string $template): void
    {
        $this->selectriNodeContentTemplate = $template;
    }
}
