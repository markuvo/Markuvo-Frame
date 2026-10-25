<?php

namespace Core\Database\Schema;

/** Bir tablonun istenen yapısını toplar; SQL'e SchemaGrammar çevirir. */
class Blueprint
{
    /** @var ColumnDefinition[] */
    public array $columns = [];
    /** @var array<int, array{type:string, columns:array, name:?string}> */
    public array $indexes = [];
    /** @var ForeignKeyDefinition[] */
    public array $foreigns = [];
    public array $dropColumns = [];
    public array $renameColumns = [];
    public array $dropIndexes = [];

    public function __construct(public string $table)
    {
    }

    protected function add(string $type, string $name, array $params = []): ColumnDefinition
    {
        return $this->columns[] = new ColumnDefinition($type, $name, $params);
    }

    // ------------------------------------------------------------ sütun türleri

    public function id(string $name = 'id'): ColumnDefinition
    {
        $c = $this->add('bigInteger', $name);
        $c->autoIncrement = true;
        $c->primary = true;
        $c->unsigned = true;

        return $c;
    }

    public function increments(string $name = 'id'): ColumnDefinition
    {
        $c = $this->add('integer', $name);
        $c->autoIncrement = true;
        $c->primary = true;
        $c->unsigned = true;

        return $c;
    }

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        return $this->add('string', $name, ['length' => $length]);
    }

    public function text(string $name): ColumnDefinition { return $this->add('text', $name); }
    public function longText(string $name): ColumnDefinition { return $this->add('longText', $name); }
    public function integer(string $name): ColumnDefinition { return $this->add('integer', $name); }
    public function tinyInteger(string $name): ColumnDefinition { return $this->add('tinyInteger', $name); }
    public function bigInteger(string $name): ColumnDefinition { return $this->add('bigInteger', $name); }
    public function boolean(string $name): ColumnDefinition { return $this->add('boolean', $name); }
    public function float(string $name): ColumnDefinition { return $this->add('float', $name); }
    public function double(string $name): ColumnDefinition { return $this->add('double', $name); }
    public function date(string $name): ColumnDefinition { return $this->add('date', $name); }
    public function dateTime(string $name): ColumnDefinition { return $this->add('dateTime', $name); }
    public function timestamp(string $name): ColumnDefinition { return $this->add('timestamp', $name); }
    public function json(string $name): ColumnDefinition { return $this->add('json', $name); }

    public function decimal(string $name, int $total = 10, int $places = 2): ColumnDefinition
    {
        return $this->add('decimal', $name, ['total' => $total, 'places' => $places]);
    }

    /** Dizi ile sınırlı değerler (mysql ENUM, diğerlerinde CHECK'li metin). */
    public function enum(string $name, array $allowed): ColumnDefinition
    {
        return $this->add('enum', $name, ['allowed' => array_values($allowed)]);
    }

    public function foreignId(string $name): ColumnDefinition
    {
        $c = $this->add('bigInteger', $name);
        $c->unsigned = true;

        return $c;
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    // ------------------------------------------------------------ indeks / ilişki

    public function index(string|array $columns, ?string $name = null): void
    {
        $this->indexes[] = ['type' => 'index', 'columns' => (array) $columns, 'name' => $name];
    }

    public function unique(string|array $columns, ?string $name = null): void
    {
        $this->indexes[] = ['type' => 'unique', 'columns' => (array) $columns, 'name' => $name];
    }

    public function primary(string|array $columns): void
    {
        $this->indexes[] = ['type' => 'primary', 'columns' => (array) $columns, 'name' => null];
    }

    public function foreign(string $column): ForeignKeyDefinition
    {
        return $this->foreigns[] = new ForeignKeyDefinition($column);
    }

    // ------------------------------------------------------------ değiştirme

    public function dropColumn(string|array $columns): void
    {
        $this->dropColumns = array_merge($this->dropColumns, (array) $columns);
    }

    public function renameColumn(string $from, string $to): void
    {
        $this->renameColumns[$from] = $to;
    }

    public function dropIndex(string $name): void
    {
        $this->dropIndexes[] = $name;
    }
}
