<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tolak menjalankan trait (RefreshDatabase dkk.) jika aplikasi tidak
     * berjalan di environment testing atau database aktif bukan database
     * khusus test. Config cache (bootstrap/cache/config.php) mengabaikan env
     * dari phpunit.xml dan pernah membuat migrate:fresh mengenai database
     * development.
     */
    protected function setUpTraits()
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (! $this->app->environment('testing') || ! str_ends_with($database, '_testing')) {
            throw new RuntimeException(sprintf(
                'Test dihentikan: environment [%s], database [%s] bukan database testing. Pastikan config cache tidak dipakai (APP_CONFIG_CACHE) dan DB_DATABASE berakhiran _testing.',
                $this->app->environment(),
                $database,
            ));
        }

        return parent::setUpTraits();
    }
}
