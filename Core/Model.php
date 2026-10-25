<?php

namespace Core;

use ArrayAccess;
use Core\Database\DB;
use Core\Database\MassAssignmentException;
use Core\Database\ModelNotFoundException;
use Core\Database\QueryBuilder;
use Core\Support\Collection;
use Core\Support\Str;
use JsonSerializable;

/**
 * ORM tabanı. Her tablo için bir sınıf:
 *
 *   class BlogModel extends Model {
 *       protected $table = 'bloglar';                 // yoksa sınıf adından türetilir (BlogModel -> blogs)
 *       protected $fillable = ['baslik', 'icerik'];   // toplu atamaya (create/fill) açık alanlar
 *       protected $hidden = ['gizli_alan'];           // toArray()/JSON'da gizlenir
 *   }
 *
 *   BlogModel::where('durum', 'aktif')->orderBy('id', 'DESC')->get();
 *   $blog = BlogModel::find(5);   $blog->baslik = 'Yeni';   $blog->save();
 *   BlogModel::create(['baslik' => 'Merhaba']);
 *
 * @method static QueryBuilder where(mixed $column, mixed $operator = null, mixed $value = null)
 * @method static QueryBuilder orderBy(string $column, string $direction = 'asc')
 * @method static QueryBuilder limit(int $value)
 */
abstract class Model implements ArrayAccess, JsonSerializable
{
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $hidden = [];
    protected $timestamps = true;

    protected array $attributes = [];
    protected array $original = [];

    /** Kayıt veritabanında var mı? */
    public bool $exists = false;

    public function __construct(array $attributes = [])
    {
        if ($attributes !== []) {
            $this->fill($attributes);
        }
    }

    // ---------------------------------------------------------------------
    // Tablo / sorgu
    // ---------------------------------------------------------------------

    public function getTable(): string
    {
        if ($this->table !== null) {
            return $this->table;
        }

        $name = (new \ReflectionClass($this))->getShortName();

        // BlogModel -> Blog (yalnızca "Model" son ekiyse ve geriye ad kalıyorsa)
        if (str_ends_with($name, 'Model') && strlen($name) > 5) {
            $name = substr($name, 0, -5);
        }

        return Str::plural(Str::snake($name));
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function getKey(): mixed
    {
        return $this->attributes[$this->primaryKey] ?? null;
    }

    public function newQuery(): QueryBuilder
    {
        return new QueryBuilder($this->getTable(), $this);
    }

    public static function query(): QueryBuilder
    {
        return (new static())->newQuery();
    }

    /** Veritabanı satırından model üretir (kayıt "mevcut" sayılır). */
    public function newFromBuilder(object|array $row): static
    {
        $model = new static();
        $model->attributes = (array) $row;
        $model->original = $model->attributes;
        $model->exists = true;

        return $model;
    }

    /** @return Collection<int, static> */
    public static function hydrate(array $rows): Collection
    {
        $model = new static();

        return new Collection(array_map(fn($row) => $model->newFromBuilder($row), $rows));
    }

    // ---------------------------------------------------------------------
    // Statik kapılar
    // ---------------------------------------------------------------------

    public static function all(): Collection
    {
        return static::query()->get();
    }

    public static function find(mixed $id): ?static
    {
        return static::query()->find($id);
    }

    public static function findOrFail(mixed $id): static
    {
        $model = static::query()->find($id);

        if ($model === null) {
            throw new ModelNotFoundException(static::class, $id);
        }

        return $model;
    }

    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();

        return $model;
    }

    /** Model::where(...) / orderBy / limit ... çağrılarını yeni bir sorgu nesnesine iletir. */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return (new static())->newQuery()->{static::guardMethod($method)}(...$arguments);
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->newQuery()->{static::guardMethod($method)}(...$arguments);
    }

    protected static function guardMethod(string $method): string
    {
        if (!method_exists(QueryBuilder::class, $method)) {
            throw new \BadMethodCallException(static::class . '::' . $method . '() metodu yok (Model ya da sorgu oluşturucuda bulunamadı).');
        }

        return $method;
    }

    // ---------------------------------------------------------------------
    // Nitelikler
    // ---------------------------------------------------------------------

    /** Toplu atama: yalnızca $fillable içindeki alanlar yazılabilir. */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if (!in_array($key, $this->fillable, true)) {
                throw new MassAssignmentException(static::class, (string) $key);
            }

            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /** $fillable denetimi olmadan atama (yalnızca güvendiğiniz veriyle). */
    public function forceFill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    public function getAttribute(string $key): mixed
    {
        $accessor = 'get' . Str::studly($key) . 'Attribute';

        if (method_exists($this, $accessor)) {
            return $this->{$accessor}($this->attributes[$key] ?? null);
        }

        return $this->attributes[$key] ?? null;
    }

    public function setAttribute(string $key, mixed $value): static
    {
        $mutator = 'set' . Str::studly($key) . 'Attribute';

        if (method_exists($this, $mutator)) {
            $value = $this->{$mutator}($value);
        }

        $this->attributes[$key] = $value;

        return $this;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getOriginal(?string $key = null): mixed
    {
        return $key === null ? $this->original : ($this->original[$key] ?? null);
    }

    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    public function isDirty(?string $key = null): bool
    {
        return $key === null ? $this->getDirty() !== [] : array_key_exists($key, $this->getDirty());
    }

    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }

    // ---------------------------------------------------------------------
    // Kaydet / sil
    // ---------------------------------------------------------------------

    public function save(): bool
    {
        $now = date('Y-m-d H:i:s');

        if ($this->exists) {
            $dirty = $this->getDirty();

            if ($dirty === []) {
                return true;
            }

            if ($this->timestamps && !array_key_exists('updated_at', $dirty)) {
                $dirty['updated_at'] = $this->attributes['updated_at'] = $now;
            }

            $this->newQuery()->where($this->primaryKey, '=', $this->getKey())->update($dirty);
        } else {
            if ($this->timestamps) {
                $this->attributes['created_at'] ??= $now;
                $this->attributes['updated_at'] ??= $now;
            }

            $query = $this->newQuery();

            if (array_key_exists($this->primaryKey, $this->attributes)) {
                $query->insert($this->attributes);
            } else {
                $this->attributes[$this->primaryKey] = $query->insertGetId($this->attributes, $this->primaryKey);
            }

            $this->exists = true;
        }

        $this->original = $this->attributes;

        return true;
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $this->newQuery()->where($this->primaryKey, '=', $this->getKey())->delete();
        $this->exists = false;

        return true;
    }

    /** Kaydı veritabanından yeniden okur. */
    public function refresh(): static
    {
        if ($this->exists) {
            $fresh = $this->newQuery()->where($this->primaryKey, '=', $this->getKey())->first();

            if ($fresh !== null) {
                $this->attributes = $fresh->attributes;
                $this->original = $fresh->attributes;
            }
        }

        return $this;
    }

    // ---------------------------------------------------------------------
    // Dönüşüm
    // ---------------------------------------------------------------------

    public function toArray(): array
    {
        return array_diff_key($this->attributes, array_flip($this->hidden));
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->getAttribute((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->setAttribute((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->__unset((string) $offset);
    }
}
