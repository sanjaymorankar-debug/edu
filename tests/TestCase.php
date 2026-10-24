<?php

namespace Tests;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\ConfigurationUrlParser;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Any test using RefreshDatabase gets roles/permissions seeded
     * automatically — most feature tests assign a role and would otherwise
     * fail against an empty roles table.
     */
    protected $seed = true;

    protected $seeder = RolesAndPermissionsSeeder::class;

    /**
     * RefreshDatabase drops every table, so refuse to start against anything
     * but a local database server, before any test can touch it. phpunit.xml
     * already blanks DB_URL / DB_URL_UNPOOLED from .env; this also catches
     * exported variables and DB_HOST values that name a hosted server.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $config = (new ConfigurationUrlParser)->parseConfiguration(
            $app['config']->get("database.connections.{$connection}", [])
        );

        static::assertLocalDatabase($config, $connection);

        return $app;
    }

    /**
     * Loopback addresses, unix sockets and single-label host names (a
     * container's service name) count as local; anything with a dot in it,
     * e.g. a Neon host, does not.
     */
    public static function assertLocalDatabase(array $config, string $connection = 'default'): void
    {
        $hosts = array_filter([
            'host' => $config['host'] ?? null,
            'direct host' => $config['direct']['host'] ?? null,
        ]);

        foreach ($hosts as $label => $host) {
            foreach ((array) $host as $value) {
                $value = (string) $value;
                $local = $value === 'localhost'
                    || $value === '::1'
                    || str_starts_with($value, '127.')
                    || str_starts_with($value, '/')
                    || ! str_contains($value, '.');

                if (! $local) {
                    throw new RuntimeException("Refusing to run tests: the [{$connection}] connection's {$label} [{$value}] is not a local database server. Tests drop every table; point DB_* at a throwaway local database.");
                }
            }
        }
    }
}
