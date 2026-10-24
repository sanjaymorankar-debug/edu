<?php

namespace App\Database;

use Illuminate\Database\Connectors\PostgresConnector as BasePostgresConnector;
use InvalidArgumentException;

/**
 * Laravel hands sslmode, sslcert, sslkey and sslrootcert from the connection
 * config (including DB_URL's query string) to libpq, but not channel_binding.
 * Neon's connection strings end in ?sslmode=require&channel_binding=require;
 * without this the second parameter is dropped and libpq falls back to its
 * default (prefer), so a server that stopped offering channel binding would
 * go unnoticed. Registered as db.connector.pgsql in AppServiceProvider.
 */
class PostgresConnector extends BasePostgresConnector
{
    protected function addSslOptions($dsn, array $config)
    {
        $dsn = parent::addSslOptions($dsn, $config);

        if (isset($config['channel_binding'])) {
            if (! in_array($config['channel_binding'], ['disable', 'prefer', 'require'], true)) {
                throw new InvalidArgumentException('channel_binding must be disable, prefer or require.');
            }

            $dsn .= ";channel_binding={$config['channel_binding']}";
        }

        return $dsn;
    }
}
