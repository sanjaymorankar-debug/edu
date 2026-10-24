<?php

use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Str;
use Pdo\Pgsql;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'pgsql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        // PostgreSQL is the only supported database for development, staging
        // and production. SQLite is kept solely so the test suite can still
        // run against an in-memory database with zero setup (see phpunit.xml);
        // CI runs the same suite against PostgreSQL.
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            // Sent as the client_encoding startup parameter, which a pooler
            // keeps per client, not as a SET.
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // Deliberately no 'search_path', 'timezone', 'isolation_level' or
            // 'synchronous_commit': Laravel applies each with a session-level
            // SET after connecting, and behind Neon's transaction-mode pooler
            // that SET lands on whichever server connection ran it, not on the
            // ones later queries get. The server default search_path
            // ("$user", public) resolves to public for this app's role.
            'sslmode' => env('DB_SSLMODE', 'prefer'),
            // Each query goes out as one unnamed statement, its parameters
            // still sent separately. PHP 8.3's pdo_pgsql otherwise uses named
            // prepared statements and frees them with an SQL DEALLOCATE that a
            // pooler cannot track; inside a transaction that error aborts the
            // transaction. Explicit EMULATE_PREPARES => false keeps Laravel's
            // pooled mode (below) from switching to client-side emulation.
            'options' => extension_loaded('pdo_pgsql') ? [
                (PHP_VERSION_ID >= 80500 ? Pgsql::ATTR_DISABLE_PREPARES : PDO::PGSQL_ATTR_DISABLE_PREPARES) => true,
                PDO::ATTR_EMULATE_PREPARES => false,
            ] : [],

            // On Neon, DB_URL is the pooled connection string and
            // DB_URL_UNPOOLED the direct one. When DB_URL_UNPOOLED is set,
            // Laravel's pooler support sends migrate, migrate:fresh and
            // db:wipe over it; everything else uses DB_URL. Unset, everything
            // uses DB_URL (or the DB_* values above).
            'direct' => (new ConfigurationUrlParser)->parseConfiguration([
                'url' => env('DB_URL_UNPOOLED'),
            ]),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
