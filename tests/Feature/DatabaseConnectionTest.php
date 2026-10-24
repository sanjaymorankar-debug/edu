<?php

namespace Tests\Feature;

use App\Database\PostgresConnector;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PDO;
use Pdo\Pgsql;
use RuntimeException;
use Tests\TestCase;

/**
 * How the pgsql connection is built from the Neon variables (DB_URL pooled,
 * DB_URL_UNPOOLED direct). Nothing here opens a connection: the hosts are
 * made up, and building a Laravel connection does not connect.
 */
class DatabaseConnectionTest extends TestCase
{
    private const POOLED = 'postgresql://edu_standalone_app:secret@ep-example-123456-pooler.eu-central-1.aws.neon.tech/edu_standalone?sslmode=require&channel_binding=require';

    private const DIRECT = 'postgresql://edu_standalone_app:secret@ep-example-123456.eu-central-1.aws.neon.tech/edu_standalone?sslmode=require&channel_binding=require';

    private static function disablePrepares(): int
    {
        return PHP_VERSION_ID >= 80500 ? Pgsql::ATTR_DISABLE_PREPARES : PDO::PGSQL_ATTR_DISABLE_PREPARES;
    }

    /**
     * Evaluate config/database.php with the given variables set, the way it
     * is evaluated at boot, then put the variables back.
     */
    private function pgsqlConfigWith(array $env): array
    {
        $saved = [];
        foreach ($env as $key => $value) {
            $saved[$key] = [$_SERVER[$key] ?? null, $_ENV[$key] ?? null, getenv($key)];
            $_SERVER[$key] = $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }

        try {
            return (require config_path('database.php'))['connections']['pgsql'];
        } finally {
            foreach ($saved as $key => [$server, $envValue, $putenv]) {
                if ($server === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $server;
                }
                if ($envValue === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $envValue;
                }
                putenv($putenv === false ? $key : "{$key}={$putenv}");
            }
        }
    }

    public function test_unpooled_url_becomes_the_direct_connection_used_by_migrations(): void
    {
        $pgsql = $this->pgsqlConfigWith(['DB_URL' => self::POOLED, 'DB_URL_UNPOOLED' => self::DIRECT]);

        $this->assertSame('ep-example-123456.eu-central-1.aws.neon.tech', $pgsql['direct']['host']);
        $this->assertSame('require', $pgsql['direct']['sslmode']);
        $this->assertSame('require', $pgsql['direct']['channel_binding']);

        config(['database.connections.pgsql' => $pgsql]);
        DB::purge('pgsql');
        $connection = DB::connection('pgsql');

        // Runtime: the pooled host, sending unnamed statements with
        // server-side parameters (named prepared statements break inside
        // transactions on a transaction pooler) and no client-side emulation.
        $this->assertSame('ep-example-123456-pooler.eu-central-1.aws.neon.tech', $connection->getConfig('host'));
        $this->assertTrue($connection->getConfig('options')[self::disablePrepares()]);
        $this->assertFalse($connection->getConfig('options')[PDO::ATTR_EMULATE_PREPARES]);

        // Migrations: the direct host, same statement mode.
        $this->assertTrue($connection->hasDirectConnection());
        $direct = $connection->getDirectPdoConfig();
        $this->assertSame('ep-example-123456.eu-central-1.aws.neon.tech', $direct['host']);
        $this->assertTrue($direct['options'][self::disablePrepares()]);
        $this->assertFalse($direct['options'][PDO::ATTR_EMULATE_PREPARES]);

        $migrator = $this->app['migrator'];
        $this->assertSame('pgsql::direct', $migrator->usingConnection('pgsql', fn () => $migrator->getConnection()));

        DB::purge('pgsql');
    }

    public function test_without_an_unpooled_url_migrations_use_db_url(): void
    {
        $pgsql = $this->pgsqlConfigWith(['DB_URL' => self::DIRECT, 'DB_URL_UNPOOLED' => '']);

        $this->assertSame([], $pgsql['direct']);

        config(['database.connections.pgsql' => $pgsql]);
        DB::purge('pgsql');

        $this->assertFalse(DB::connection('pgsql')->hasDirectConnection());
        $migrator = $this->app['migrator'];
        $this->assertSame('pgsql', $migrator->usingConnection('pgsql', fn () => $migrator->getConnection()));

        DB::purge('pgsql');
    }

    public function test_no_session_settings_are_sent_on_connect(): void
    {
        // Each would be a session-level SET, which a transaction pooler
        // applies to one server connection only.
        $pgsql = $this->pgsqlConfigWith(['DB_URL' => self::POOLED, 'DB_URL_UNPOOLED' => self::DIRECT]);

        foreach (['search_path', 'schema', 'timezone', 'isolation_level', 'synchronous_commit'] as $key) {
            $this->assertArrayNotHasKey($key, $pgsql);
        }
    }

    public function test_channel_binding_from_the_url_reaches_libpq(): void
    {
        $config = (new ConfigurationUrlParser)->parseConfiguration(['url' => self::POOLED]);

        $dsn = (fn (array $config) => $this->getDsn($config))->call(new PostgresConnector, $config);

        $this->assertStringContainsString(';sslmode=require', $dsn);
        $this->assertStringContainsString(';channel_binding=require', $dsn);
        $this->assertInstanceOf(PostgresConnector::class, $this->app->make('db.connector.pgsql'));
    }

    public function test_an_unknown_channel_binding_value_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (fn (array $config) => $this->getDsn($config))->call(new PostgresConnector, [
            'host' => '127.0.0.1', 'database' => 'x', 'channel_binding' => 'require;host=elsewhere',
        ]);
    }

    public function test_tests_refuse_a_hosted_database(): void
    {
        foreach (['127.0.0.1', 'localhost', '::1', '/tmp', 'postgres'] as $host) {
            TestCase::assertLocalDatabase(['host' => $host]);
        }

        $this->expectException(RuntimeException::class);
        TestCase::assertLocalDatabase((new ConfigurationUrlParser)->parseConfiguration(['url' => self::POOLED]));
    }

    public function test_tests_refuse_a_hosted_direct_endpoint(): void
    {
        $this->expectException(RuntimeException::class);
        TestCase::assertLocalDatabase(['host' => '127.0.0.1', 'direct' => ['host' => 'ep-example-123456.eu-central-1.aws.neon.tech']]);
    }
}
