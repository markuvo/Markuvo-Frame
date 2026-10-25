<?php

namespace Core\Database;

/**
 * Seeder tabanı. DatabaseSeeder diğerlerini çağırır:
 *   public function run(): void { $this->call(UserSeeder::class); }
 */
abstract class Seeder
{
    abstract public function run();

    public function call(string|array $classes): void
    {
        foreach ((array) $classes as $class) {
            (new $class())->run();
        }
    }
}
