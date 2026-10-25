<?php

namespace Core\Database\Schema;

/**
 * Sütun tanımı (zincirleme):
 *   $table->string('email', 150)->unique();
 *   $table->integer('stok')->default(0)->unsigned();
 *   $table->text('not')->nullable();
 */
class ColumnDefinition
{
    public bool $nullable = false;
    public bool $hasDefault = false;
    public mixed $default = null;
    public bool $useCurrent = false;
    public bool $unsigned = false;
    public bool $unique = false;
    public bool $index = false;
    public bool $primary = false;
    public bool $autoIncrement = false;
    public ?ForeignKeyDefinition $foreign = null;

    public function __construct(public string $type, public string $name, public array $params = [])
    {
    }

    public function nullable(bool $value = true): static
    {
        $this->nullable = $value;

        return $this;
    }

    public function default(mixed $value): static
    {
        $this->hasDefault = true;
        $this->default = $value;

        return $this;
    }

    public function useCurrent(): static
    {
        $this->useCurrent = true;

        return $this;
    }

    public function unsigned(): static
    {
        $this->unsigned = true;

        return $this;
    }

    public function unique(): static
    {
        $this->unique = true;

        return $this;
    }

    public function index(): static
    {
        $this->index = true;

        return $this;
    }

    public function primary(): static
    {
        $this->primary = true;

        return $this;
    }

    /** foreignId('user_id')->constrained() / ->constrained('users') */
    public function constrained(?string $table = null, string $column = 'id'): ForeignKeyDefinition
    {
        $table ??= \Core\Support\Str::plural(preg_replace('/_id$/', '', $this->name));
        $this->foreign = new ForeignKeyDefinition($this->name);
        $this->foreign->on($table)->references($column);

        return $this->foreign;
    }
}
