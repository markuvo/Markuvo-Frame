<?php

namespace Core\Database\Schema;

class ForeignKeyDefinition
{
    public ?string $table = null;
    public string $column = 'id';
    public ?string $onDelete = null;
    public ?string $onUpdate = null;

    public function __construct(public string $local)
    {
    }

    public function references(string $column): static
    {
        $this->column = $column;

        return $this;
    }

    public function on(string $table): static
    {
        $this->table = $table;

        return $this;
    }

    public function onDelete(string $action): static
    {
        $this->onDelete = $action;

        return $this;
    }

    public function onUpdate(string $action): static
    {
        $this->onUpdate = $action;

        return $this;
    }

    public function cascadeOnDelete(): static
    {
        return $this->onDelete('cascade');
    }
}
