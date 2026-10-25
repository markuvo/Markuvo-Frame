<?php

namespace Core\Database\Schema;

use Core\Database\Grammar;

/** Blueprint'i sürücüye (mysql / pgsql / sqlite) uygun DDL cümlelerine çevirir. */
class SchemaGrammar
{
    /** MySQL/MariaDB için: sütun adı -> mevcut tanım ("varchar(255) NOT NULL ..."); renameColumn için gerekir. */
    public array $columnDefinitions = [];

    public function __construct(protected string $driver)
    {
    }

    protected function w(string $name): string
    {
        return Grammar::wrap($name, $this->driver);
    }

    protected function wrapColumns(array $columns): string
    {
        return implode(', ', array_map(fn($c) => $this->w($c), $columns));
    }

    /** @return string[] */
    public function compileCreate(Blueprint $t, bool $ifNotExists = false): array
    {
        $defs = [];
        $primaryInline = false;

        foreach ($t->columns as $col) {
            $defs[] = $this->columnSql($col);
            $primaryInline = $primaryInline || ($col->primary && $col->autoIncrement);
        }

        // Bileşik / elle belirtilen birincil anahtar
        foreach ($t->indexes as $idx) {
            if ($idx['type'] === 'primary') {
                $defs[] = 'PRIMARY KEY (' . $this->wrapColumns($idx['columns']) . ')';
            }
        }
        foreach ($t->columns as $col) {
            if ($col->primary && !$col->autoIncrement) {
                $defs[] = 'PRIMARY KEY (' . $this->w($col->name) . ')';
            }
        }

        foreach ($this->allForeigns($t) as $fk) {
            $defs[] = $this->foreignSql($fk);
        }

        $sql = 'CREATE TABLE ' . ($ifNotExists ? 'IF NOT EXISTS ' : '') . $this->w($t->table)
            . ' (' . implode(', ', $defs) . ')';

        if ($this->driver === 'mysql') {
            $sql .= ' DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        return array_merge([$sql], $this->indexStatements($t));
    }

    /** @return string[] */
    public function compileAlter(Blueprint $t): array
    {
        $sql = [];
        $table = $this->w($t->table);

        foreach ($t->columns as $col) {
            $sql[] = "ALTER TABLE {$table} ADD COLUMN " . $this->columnSql($col);
        }
        foreach ($t->renameColumns as $from => $to) {
            if ($this->driver === 'mysql') {
                // RENAME COLUMN yalnızca MySQL 8+ / MariaDB 10.5.2+ ile gelir; CHANGE her sürümde çalışır
                // ama sütunun tanımını yeniden ister. Tanım mevcut tablodan okunup aynen korunur.
                if (!isset($this->columnDefinitions[$from])) {
                    throw new \LogicException("'{$from}' sütununun tanımı okunamadı (renameColumn).");
                }
                $sql[] = "ALTER TABLE {$table} CHANGE " . $this->w($from) . ' ' . $this->w($to) . ' ' . $this->columnDefinitions[$from];
                continue;
            }
            $sql[] = "ALTER TABLE {$table} RENAME COLUMN " . $this->w($from) . ' TO ' . $this->w($to);
        }
        foreach ($t->dropIndexes as $name) {
            $sql[] = $this->driver === 'mysql'
                ? "DROP INDEX " . $this->w($name) . " ON {$table}"
                : 'DROP INDEX ' . $this->w($name);
        }
        foreach ($t->dropColumns as $column) {
            $sql[] = "ALTER TABLE {$table} DROP COLUMN " . $this->w($column);
        }

        if ($t->foreigns !== [] || array_filter($t->columns, fn($c) => $c->foreign)) {
            if ($this->driver === 'sqlite') {
                throw new \LogicException('SQLite, mevcut tabloya yabancı anahtar eklemeyi desteklemez; Schema::create içinde tanımlayın.');
            }
            foreach ($this->allForeigns($t) as $fk) {
                $sql[] = "ALTER TABLE {$table} ADD " . $this->foreignSql($fk, $t->table);
            }
        }

        return array_merge($sql, $this->indexStatements($t));
    }

    public function compileDrop(string $table, bool $ifExists = false): string
    {
        return 'DROP TABLE ' . ($ifExists ? 'IF EXISTS ' : '') . $this->w($table);
    }

    public function compileRename(string $from, string $to): string
    {
        return 'ALTER TABLE ' . $this->w($from) . ' RENAME TO ' . $this->w($to);
    }

    // ------------------------------------------------------------------ iç yardımcılar

    /** @return ForeignKeyDefinition[] */
    protected function allForeigns(Blueprint $t): array
    {
        $list = $t->foreigns;
        foreach ($t->columns as $col) {
            if ($col->foreign) {
                $list[] = $col->foreign;
            }
        }

        return $list;
    }

    protected function foreignSql(ForeignKeyDefinition $fk, ?string $table = null): string
    {
        if ($fk->table === null) {
            throw new \LogicException("'{$fk->local}' yabancı anahtarı için on('tablo') belirtilmeli.");
        }

        $name = $this->w(($table ?? 'fk') . '_' . $fk->local . '_foreign');
        $sql = 'CONSTRAINT ' . $name . ' FOREIGN KEY (' . $this->w($fk->local) . ') REFERENCES '
            . $this->w($fk->table) . ' (' . $this->w($fk->column) . ')';

        foreach (['onDelete' => 'ON DELETE', 'onUpdate' => 'ON UPDATE'] as $prop => $label) {
            if ($fk->{$prop} !== null) {
                $sql .= ' ' . $label . ' ' . $this->action($fk->{$prop});
            }
        }

        return $sql;
    }

    protected function action(string $action): string
    {
        $action = strtoupper(trim($action));
        if (!in_array($action, ['CASCADE', 'SET NULL', 'RESTRICT', 'NO ACTION', 'SET DEFAULT'], true)) {
            throw new \InvalidArgumentException("Geçersiz yabancı anahtar eylemi: {$action}");
        }

        return $action;
    }

    /** @return string[] */
    protected function indexStatements(Blueprint $t): array
    {
        $out = [];

        $all = array_filter($t->indexes, fn($i) => $i['type'] !== 'primary');
        foreach ($t->columns as $col) {
            if ($col->unique) {
                $all[] = ['type' => 'unique', 'columns' => [$col->name], 'name' => null];
            }
            if ($col->index) {
                $all[] = ['type' => 'index', 'columns' => [$col->name], 'name' => null];
            }
        }

        foreach ($all as $idx) {
            $name = $idx['name'] ?? $this->indexName($t->table, $idx['columns'], $idx['type']);
            $out[] = 'CREATE ' . ($idx['type'] === 'unique' ? 'UNIQUE ' : '') . 'INDEX ' . $this->w($name)
                . ' ON ' . $this->w($t->table) . ' (' . $this->wrapColumns($idx['columns']) . ')';
        }

        return $out;
    }

    protected function indexName(string $table, array $columns, string $type): string
    {
        $name = $table . '_' . implode('_', $columns) . '_' . $type;

        // MySQL 64 karakter sınırı
        return strlen($name) > 60 ? substr($name, 0, 48) . '_' . substr(md5($name), 0, 10) : $name;
    }

    protected function columnSql(ColumnDefinition $c): string
    {
        $sql = $this->w($c->name) . ' ';

        // Otomatik artan birincil anahtar: sürücüye özel
        if ($c->autoIncrement) {
            return $sql . match ($this->driver) {
                'mysql' => ($c->type === 'integer' ? 'INT' : 'BIGINT') . ($c->unsigned ? ' UNSIGNED' : '') . ' NOT NULL AUTO_INCREMENT PRIMARY KEY',
                'pgsql' => ($c->type === 'integer' ? 'SERIAL' : 'BIGSERIAL') . ' PRIMARY KEY',
                default => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            };
        }

        $sql .= $this->typeSql($c);
        $sql .= $c->nullable ? ' NULL' : ' NOT NULL';

        if ($c->useCurrent) {
            $sql .= ' DEFAULT CURRENT_TIMESTAMP';
        } elseif ($c->hasDefault) {
            $sql .= ' DEFAULT ' . $this->defaultSql($c->default);
        }

        if ($c->type === 'enum' && $this->driver !== 'mysql') {
            $sql .= ' CHECK (' . $this->w($c->name) . ' IN (' . $this->quoteList($c->params['allowed']) . '))';
        }

        return $sql;
    }

    protected function typeSql(ColumnDefinition $c): string
    {
        $d = $this->driver;
        $unsigned = $c->unsigned && $d === 'mysql' ? ' UNSIGNED' : '';

        return match ($c->type) {
            'string' => 'VARCHAR(' . (int) $c->params['length'] . ')',
            'text' => 'TEXT',
            'longText' => $d === 'mysql' ? 'LONGTEXT' : 'TEXT',
            'integer' => ($d === 'sqlite' ? 'INTEGER' : ($d === 'pgsql' ? 'INTEGER' : 'INT')) . $unsigned,
            'tinyInteger' => ($d === 'mysql' ? 'TINYINT' : ($d === 'pgsql' ? 'SMALLINT' : 'INTEGER')) . $unsigned,
            'bigInteger' => ($d === 'sqlite' ? 'INTEGER' : 'BIGINT') . $unsigned,
            'boolean' => $d === 'mysql' ? 'TINYINT(1)' : ($d === 'pgsql' ? 'BOOLEAN' : 'INTEGER'),
            'decimal' => 'DECIMAL(' . (int) $c->params['total'] . ',' . (int) $c->params['places'] . ')',
            'float' => $d === 'pgsql' ? 'REAL' : 'FLOAT',
            'double' => $d === 'pgsql' ? 'DOUBLE PRECISION' : ($d === 'sqlite' ? 'REAL' : 'DOUBLE'),
            'date' => 'DATE',
            'dateTime' => $d === 'pgsql' ? 'TIMESTAMP(0)' : 'DATETIME',
            'timestamp' => $d === 'pgsql' ? 'TIMESTAMP(0)' : ($d === 'sqlite' ? 'DATETIME' : 'TIMESTAMP'),
            'json' => $d === 'pgsql' ? 'JSONB' : ($d === 'sqlite' ? 'TEXT' : 'JSON'),
            'enum' => $d === 'mysql' ? 'ENUM(' . $this->quoteList($c->params['allowed']) . ')' : 'VARCHAR(255)',
            default => throw new \LogicException("Bilinmeyen sütun türü: {$c->type}"),
        };
    }

    protected function defaultSql(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $this->driver === 'pgsql' ? ($value ? 'TRUE' : 'FALSE') : ($value ? '1' : '0');
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $this->quote((string) $value);
    }

    protected function quoteList(array $values): string
    {
        return implode(', ', array_map(fn($v) => $this->quote((string) $v), $values));
    }

    protected function quote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
