<?php

namespace Core\Database;

use Closure;
use Core\Model;
use Core\Support\Collection;
use Core\Support\Paginator;
use InvalidArgumentException;

/**
 * Akıcı (fluent) sorgu oluşturucu. Her sorgu kendi nesnesinde yaşar: iki sorgu birbirini ezmez.
 *
 *   DB::table('blog')->where('durum', 'aktif')->orderBy('id', 'DESC')->limit(10)->get();
 *   BlogModel::where('durum', 'aktif')->orderBy('id', 'DESC')->get();   // Model üzerinden aynı zincir
 *
 * Güvenlik: değerler her zaman bağlama (?) ile gider; kolon/tablo adı ve operatörler doğrulanır.
 * Ham SQL gerekirse selectRaw()/whereRaw() açıkça kullanılır.
 */
class QueryBuilder
{
    protected array $columns = ['*'];
    protected bool $distinct = false;
    protected array $joins = [];
    protected array $wheres = [];
    protected array $groups = [];
    protected array $orders = [];
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected ?array $aggregate = null;

    public function __construct(
        protected string $table,
        protected ?Model $model = null,
        protected ?string $driver = null
    ) {
    }

    public function __clone()
    {
        foreach ($this->wheres as $i => $where) {
            if (($where['type'] ?? '') === 'nested') {
                $this->wheres[$i]['query'] = clone $where['query'];
            }
        }
    }

    // ---------------------------------------------------------------------
    // SELECT / JOIN
    // ---------------------------------------------------------------------

    public function select(array|string|Expression ...$columns): static
    {
        $this->columns = $this->flattenColumns($columns);

        return $this;
    }

    public function addSelect(array|string|Expression ...$columns): static
    {
        $current = $this->columns === ['*'] ? [] : $this->columns;
        $this->columns = array_merge($current, $this->flattenColumns($columns));

        return $this;
    }

    /** Ham ifade: sabit SQL için (ör. 'COUNT(*) AS toplam'). Kullanıcı girdisi eklemeyin. */
    public function selectRaw(string $expression): static
    {
        $this->columns[] = new Expression($expression);
        if ($this->columns[0] === '*') {
            array_shift($this->columns);
        }

        return $this;
    }

    public function distinct(): static
    {
        $this->distinct = true;

        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): static
    {
        $type = strtoupper($type);
        if (!in_array($type, ['INNER', 'LEFT', 'RIGHT'], true)) {
            throw new InvalidArgumentException("Geçersiz JOIN türü: {$type}");
        }

        $this->joins[] = compact('table', 'first', 'operator', 'second', 'type');

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): static
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    // ---------------------------------------------------------------------
    // WHERE
    // ---------------------------------------------------------------------

    /**
     * where('durum', 'aktif')            -> durum = 'aktif'
     * where('yas', '>', 18)              -> yas > 18
     * where(fn($q) => $q->where(...)->orWhere(...))   -> parantezli grup
     * where(['a' => 1, 'b' => 2])        -> a = 1 AND b = 2
     */
    public function where(mixed $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static
    {
        if ($column instanceof Closure) {
            $nested = new static($this->table, $this->model, $this->driver);
            $column($nested);
            $this->wheres[] = ['type' => 'nested', 'query' => $nested, 'boolean' => $boolean];

            return $this;
        }

        if (is_array($column)) {
            return $this->where(function (self $query) use ($column) {
                foreach ($column as $key => $val) {
                    $query->where($key, '=', $val);
                }
            }, null, null, $boolean);
        }

        if (func_num_args() === 2) {
            [$value, $operator] = [$operator, '='];
        }

        if (!is_string($operator)) {
            throw new InvalidArgumentException('where(): operatör metin olmalı.');
        }

        $operator = Grammar::normalizeOperator($operator);

        if ($value === null) {
            if ($operator === '=') {
                return $this->whereNull($column, $boolean);
            }
            if ($operator === '!=' || $operator === '<>') {
                return $this->whereNotNull($column, $boolean);
            }
        }

        if (is_array($value) || is_object($value)) {
            throw new InvalidArgumentException("where('{$column}'): değer dizi/nesne olamaz (dizi için whereIn kullanın).");
        }

        $this->wheres[] = ['type' => 'basic', 'column' => $column, 'operator' => $operator, 'value' => $value, 'boolean' => $boolean];

        return $this;
    }

    public function orWhere(mixed $column, mixed $operator = null, mixed $value = null): static
    {
        return func_num_args() === 2
            ? $this->where($column, '=', $operator, 'or')
            : $this->where($column, $operator, $value, 'or');
    }

    public function whereIn(string $column, array $values, string $boolean = 'and', bool $not = false): static
    {
        $this->wheres[] = ['type' => 'in', 'column' => $column, 'values' => array_values($values), 'boolean' => $boolean, 'not' => $not];

        return $this;
    }

    public function whereNotIn(string $column, array $values, string $boolean = 'and'): static
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    public function orWhereIn(string $column, array $values): static
    {
        return $this->whereIn($column, $values, 'or');
    }

    public function whereNull(string $column, string $boolean = 'and', bool $not = false): static
    {
        $this->wheres[] = ['type' => 'null', 'column' => $column, 'boolean' => $boolean, 'not' => $not];

        return $this;
    }

    public function whereNotNull(string $column, string $boolean = 'and'): static
    {
        return $this->whereNull($column, $boolean, true);
    }

    public function orWhereNull(string $column): static
    {
        return $this->whereNull($column, 'or');
    }

    public function whereBetween(string $column, array $range, string $boolean = 'and', bool $not = false): static
    {
        if (count($range) !== 2) {
            throw new InvalidArgumentException('whereBetween: iki değer (alt, üst) gerekir.');
        }

        $this->wheres[] = ['type' => 'between', 'column' => $column, 'values' => array_values($range), 'boolean' => $boolean, 'not' => $not];

        return $this;
    }

    /** Ham koşul. SQL'i siz yazarsınız; değerleri MUTLAKA $bindings ile verin, birleştirmeyin. */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'and'): static
    {
        $this->wheres[] = ['type' => 'raw', 'sql' => $sql, 'bindings' => array_values($bindings), 'boolean' => $boolean];

        return $this;
    }

    public function when(mixed $condition, callable $callback): static
    {
        if ($condition) {
            $callback($this, $condition);
        }

        return $this;
    }

    // ---------------------------------------------------------------------
    // GROUP / ORDER / LIMIT
    // ---------------------------------------------------------------------

    public function groupBy(string ...$columns): static
    {
        $this->groups = array_merge($this->groups, $columns);

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->orders[] = [$column, Grammar::normalizeDirection($direction)];

        return $this;
    }

    public function orderByDesc(string $column): static
    {
        return $this->orderBy($column, 'desc');
    }

    public function latest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'desc');
    }

    public function oldest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'asc');
    }

    public function limit(int $value): static
    {
        $this->limit = max(0, $value);

        return $this;
    }

    public function offset(int $value): static
    {
        $this->offset = max(0, $value);

        return $this;
    }

    public function take(int $value): static
    {
        return $this->limit($value);
    }

    public function skip(int $value): static
    {
        return $this->offset($value);
    }

    public function forPage(int $page, int $perPage = 10): static
    {
        $page = max(1, $page);

        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    // ---------------------------------------------------------------------
    // Okuma
    // ---------------------------------------------------------------------

    public function get(): Collection
    {
        $rows = DB::select($this->toSql(), $this->getBindings());

        if ($this->model !== null) {
            $rows = array_map(fn($row) => $this->model->newFromBuilder($row), $rows);
        }

        return new Collection($rows);
    }

    public function first(): mixed
    {
        return (clone $this)->limit(1)->get()->first();
    }

    public function find(mixed $id, ?string $key = null): mixed
    {
        $key ??= $this->model?->getKeyName() ?? 'id';

        return $this->where($key, '=', $id)->first();
    }

    public function findOrFail(mixed $id, ?string $key = null): mixed
    {
        $result = $this->find($id, $key);

        if ($result === null) {
            throw new ModelNotFoundException($this->model ? $this->model::class : $this->table, $id);
        }

        return $result;
    }

    public function pluck(string $column, ?string $key = null): Collection
    {
        $clone = clone $this;
        $clone->model = null;
        $clone->columns = $key === null ? [$column] : [$column, $key];

        $valueName = $this->resultName($column);
        $keyName = $key === null ? null : $this->resultName($key);
        $result = [];

        foreach ($clone->get() as $row) {
            if ($keyName === null) {
                $result[] = $row->{$valueName};
            } else {
                $result[$row->{$keyName}] = $row->{$valueName};
            }
        }

        return new Collection($result);
    }

    public function value(string $column): mixed
    {
        return $this->pluck($column)->first();
    }

    public function count(string $column = '*'): int
    {
        return (int) $this->aggregate('COUNT', $column);
    }

    public function max(string $column): mixed
    {
        return $this->aggregate('MAX', $column);
    }

    public function min(string $column): mixed
    {
        return $this->aggregate('MIN', $column);
    }

    public function sum(string $column): int|float
    {
        $v = $this->aggregate('SUM', $column);

        return $v === null ? 0 : $v + 0;
    }

    public function avg(string $column): int|float|null
    {
        $v = $this->aggregate('AVG', $column);

        return $v === null ? null : $v + 0;
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    protected function aggregate(string $function, string $column): mixed
    {
        $clone = clone $this;
        $clone->model = null;
        $clone->orders = [];
        $clone->limit = $clone->offset = null;
        $clone->aggregate = [$function, $column];

        $row = DB::select($clone->toSql(), $clone->getBindings())[0] ?? null;

        return $row?->aggregate;
    }

    /**
     * Sayfalanmış sonuç. Geçerli sayfa, istekteki ?page= değerinden okunur.
     *   $bloglar = BlogModel::where('durum', 'aktif')->paginate(10);
     *   foreach ($bloglar as $blog) ...;   {!! $bloglar->links() !!}
     */
    public function paginate(int $perPage = 10, ?int $page = null, string $pageName = 'page'): Paginator
    {
        $perPage = max(1, $perPage);
        $request = function_exists('app') && \Core\Application::getInstance() ? request() : null;

        $page ??= max(1, (int) ($request?->query($pageName, 1) ?? 1));

        $total = (clone $this)->count();
        $items = $total > 0 ? (clone $this)->forPage($page, $perPage)->get() : new Collection();

        $query = $request ? $request->query() : [];
        unset($query[$pageName]);

        return new Paginator($items, $total, $perPage, $page, [
            'path' => $request ? $request->getBasePath() . $request->getPath() : '/',
            'query' => $query,
            'pageName' => $pageName,
        ]);
    }

    // ---------------------------------------------------------------------
    // Yazma
    // ---------------------------------------------------------------------

    /** Tek satır ya da satır listesi ekler. */
    public function insert(array $values): bool
    {
        if ($values === []) {
            $this->insertGetId([]);

            return true;
        }

        $rows = is_array(reset($values)) ? $values : [$values];
        $columns = array_keys(reset($rows));
        $driver = $this->driver();

        $group = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $this->wrapTable(),
            implode(', ', array_map(fn($c) => Grammar::wrap($c, $driver), $columns)),
            implode(', ', array_fill(0, count($rows), $group))
        );

        $bindings = [];
        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $bindings[] = $row[$column] ?? null;
            }
        }

        return DB::statement($sql, $bindings);
    }

    /** Satırı ekler ve otomatik artan id'yi döner. */
    public function insertGetId(array $values, string $sequence = 'id'): int|string
    {
        $driver = $this->driver();
        $columns = array_keys($values);

        if ($columns === []) {
            // Yalnızca varsayılan değerlerle satır: MySQL "() VALUES ()", diğerleri DEFAULT VALUES
            $sql = 'INSERT INTO ' . $this->wrapTable() . ($driver === 'mysql' ? ' () VALUES ()' : ' DEFAULT VALUES');
        } else {
            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $this->wrapTable(),
                implode(', ', array_map(fn($c) => Grammar::wrap($c, $driver), $columns)),
                implode(', ', array_fill(0, count($columns), '?'))
            );
        }

        $bindings = array_values($values);

        if ($driver === 'pgsql') {
            $id = DB::run($sql . ' RETURNING ' . Grammar::wrap($sequence, $driver), $bindings)->fetchColumn();
        } else {
            DB::run($sql, $bindings);
            $id = DB::pdo()->lastInsertId();
        }

        return is_string($id) && ctype_digit($id) ? (int) $id : $id;
    }

    /** Koşula uyan satırları günceller; etkilenen satır sayısını döner. */
    public function update(array $values): int
    {
        if ($values === []) {
            return 0;
        }

        $driver = $this->driver();
        $sets = [];
        $bindings = [];

        foreach ($values as $column => $value) {
            $sets[] = Grammar::wrap($column, $driver) . ' = ?';
            $bindings[] = $value;
        }

        [$where, $whereBindings] = $this->compileWheres($this->wheres);

        $sql = 'UPDATE ' . $this->wrapTable() . ' SET ' . implode(', ', $sets)
            . ($where !== '' ? ' WHERE ' . $where : '');

        return DB::affectingStatement($sql, array_merge($bindings, $whereBindings));
    }

    /** Koşula uyan satırları siler; etkilenen satır sayısını döner. Koşulsuz çağrı tabloyu boşaltır. */
    public function delete(): int
    {
        [$where, $bindings] = $this->compileWheres($this->wheres);

        $sql = 'DELETE FROM ' . $this->wrapTable() . ($where !== '' ? ' WHERE ' . $where : '');

        return DB::affectingStatement($sql, $bindings);
    }

    // ---------------------------------------------------------------------
    // SQL üretimi
    // ---------------------------------------------------------------------

    public function toSql(): string
    {
        $driver = $this->driver();

        if ($this->aggregate !== null) {
            [$function, $column] = $this->aggregate;
            $columns = $function . '(' . ($column === '*' ? '*' : Grammar::wrap($column, $driver)) . ') AS aggregate';
        } else {
            $columns = implode(', ', array_map(
                fn($c) => $c instanceof Expression ? $c->getValue() : Grammar::wrap($c, $driver),
                $this->columns
            ));
        }

        $sql = 'SELECT ' . ($this->distinct && $this->aggregate === null ? 'DISTINCT ' : '') . $columns . ' FROM ' . $this->wrapTable();

        foreach ($this->joins as $join) {
            $sql .= sprintf(
                ' %s JOIN %s ON %s %s %s',
                $join['type'],
                Grammar::wrap($join['table'], $driver),
                Grammar::wrap($join['first'], $driver),
                Grammar::normalizeOperator($join['operator']),
                Grammar::wrap($join['second'], $driver)
            );
        }

        [$where] = $this->compileWheres($this->wheres);
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }

        if ($this->groups !== []) {
            $sql .= ' GROUP BY ' . implode(', ', array_map(fn($g) => Grammar::wrap($g, $driver), $this->groups));
        }

        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', array_map(
                fn($o) => Grammar::wrap($o[0], $driver) . ' ' . $o[1],
                $this->orders
            ));
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . (int) $this->limit;
        }

        if ($this->offset !== null) {
            if ($this->limit === null) {
                $sql .= match ($driver) {
                    'mysql'  => ' LIMIT 18446744073709551615',
                    'sqlite' => ' LIMIT -1',
                    default  => '',
                };
            }
            $sql .= ' OFFSET ' . (int) $this->offset;
        }

        return $sql;
    }

    public function getBindings(): array
    {
        return $this->compileWheres($this->wheres)[1];
    }

    // ---------------------------------------------------------------------
    // İç yardımcılar
    // ---------------------------------------------------------------------

    protected function driver(): string
    {
        return $this->driver ?? DB::driver();
    }

    protected function wrapTable(): string
    {
        return Grammar::wrap($this->table, $this->driver());
    }

    protected function flattenColumns(array $columns): array
    {
        $flat = [];

        foreach ($columns as $column) {
            foreach ((array) $column as $c) {
                $flat[] = $c;
            }
        }

        return $flat === [] ? ['*'] : $flat;
    }

    protected function resultName(string $column): string
    {
        if (preg_match('/\s+as\s+(\w+)$/i', $column, $m)) {
            return $m[1];
        }

        $parts = explode('.', $column);

        return end($parts);
    }

    /** @return array{0: string, 1: array} */
    protected function compileWheres(array $wheres): array
    {
        $driver = $this->driver();
        $parts = [];
        $bindings = [];

        foreach ($wheres as $i => $where) {
            switch ($where['type']) {
                case 'basic':
                    $sql = Grammar::wrap($where['column'], $driver) . ' ' . $where['operator'] . ' ?';
                    $b = [$where['value']];
                    break;

                case 'in':
                    $col = Grammar::wrap($where['column'], $driver);
                    if ($where['values'] === []) {
                        $sql = $where['not'] ? '1 = 1' : '0 = 1';
                        $b = [];
                    } else {
                        $sql = $col . ($where['not'] ? ' NOT IN ' : ' IN ')
                            . '(' . implode(', ', array_fill(0, count($where['values']), '?')) . ')';
                        $b = $where['values'];
                    }
                    break;

                case 'null':
                    $sql = Grammar::wrap($where['column'], $driver) . ($where['not'] ? ' IS NOT NULL' : ' IS NULL');
                    $b = [];
                    break;

                case 'between':
                    $sql = Grammar::wrap($where['column'], $driver) . ($where['not'] ? ' NOT BETWEEN ? AND ?' : ' BETWEEN ? AND ?');
                    $b = $where['values'];
                    break;

                case 'nested':
                    [$inner, $b] = $where['query']->compileWheres($where['query']->wheres);
                    $sql = '(' . $inner . ')';
                    if ($inner === '') {
                        continue 2;
                    }
                    break;

                case 'raw':
                    $sql = '(' . $where['sql'] . ')';
                    $b = $where['bindings'];
                    break;

                default:
                    throw new \LogicException('Bilinmeyen where türü: ' . $where['type']);
            }

            $parts[] = ($parts === [] ? '' : strtoupper($where['boolean']) . ' ') . $sql;
            array_push($bindings, ...$b);
        }

        return [implode(' ', $parts), $bindings];
    }
}
