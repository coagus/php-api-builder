<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\ORM;

use InvalidArgumentException;

/**
 * Parses libpq/MySQL URIs into the field map Connection::configure() consumes.
 *
 * Managed Postgres/MySQL hosts (Supabase, RDS, Heroku, Render, Cloud SQL, …)
 * universally publish connection strings in URI form:
 *
 *     postgresql://user:pass@host:port/dbname?sslmode=require
 *     postgres://user:pass@host/dbname
 *     mysql://user:pass@host:port/dbname
 *     mariadb://user:pass@host/dbname
 *
 * PDO does not consume those URIs directly — its DSN scheme names the driver
 * (e.g. "pgsql"), not the protocol ("postgresql"), so passing the URI verbatim
 * to `new PDO('postgresql://…')` fails with "could not find driver" even when
 * pdo_pgsql is loaded. This parser converts a URI into the array of fields
 * (driver, host, port, database, username, password) the rest of the ORM layer
 * already understands; drivers continue to work unchanged.
 *
 * Query-string options are intentionally dropped here. libpq/MySQL negotiate
 * TLS automatically against any TLS-enforcing server, and propagating
 * `sslmode=require` into a local Docker postgres without TLS would break
 * development. Specific options can be re-introduced once a real consumer
 * needs them.
 *
 * IPv6 literal hosts (`postgres://user:pass@[::1]:5432/db`) are not yet
 * supported; pass the address without brackets via the legacy `host` field
 * if you need IPv6.
 */
final class DsnParser
{
    /** @var array<string,string> URI scheme → PDO driver name */
    private const SCHEME_TO_DRIVER = [
        'postgres'    => 'pgsql',
        'postgresql'  => 'pgsql',
        'mysql'       => 'mysql',
        'mariadb'     => 'mysql',
    ];

    /** @var array<string,int> driver → default port */
    private const DEFAULT_PORT = [
        'pgsql' => 5432,
        'mysql' => 3306,
    ];

    /**
     * Convert a URI into the field map Connection::configure() consumes.
     *
     * @return array{
     *     driver: string,
     *     host: string,
     *     port: int,
     *     database: string,
     *     username: ?string,
     *     password: ?string,
     * }
     *
     * @throws InvalidArgumentException when the URI cannot be parsed or the
     *                                  scheme is not supported.
     */
    public static function parse(string $uri): array
    {
        if (!preg_match(
            '#^(?<scheme>postgres(?:ql)?|mysql|mariadb)://'
            . '(?:(?<user>[^:@/]+)(?::(?<pass>[^@]*))?@)?'
            . '(?<host>[^:/?]+)(?::(?<port>\d+))?'
            . '(?:/(?<db>[^?]*))?'
            . '(?:\?.*)?$#',
            $uri,
            $m,
        )) {
            throw new InvalidArgumentException(
                'Cannot parse DSN URI: scheme must be one of postgres/postgresql/mysql/mariadb '
                . 'with the form scheme://[user[:pass]@]host[:port][/database][?opts].'
            );
        }

        $driver = self::SCHEME_TO_DRIVER[$m['scheme']]
            ?? throw new InvalidArgumentException("Unsupported DSN scheme: {$m['scheme']}");

        $port = isset($m['port']) && $m['port'] !== ''
            ? (int) $m['port']
            : (self::DEFAULT_PORT[$driver] ?? 0);

        return [
            'driver'   => $driver,
            'host'     => $m['host'],
            'port'     => $port,
            'database' => $m['db'] ?? '',
            'username' => isset($m['user']) && $m['user'] !== '' ? urldecode($m['user']) : null,
            'password' => isset($m['pass']) && $m['pass'] !== '' ? urldecode($m['pass']) : null,
        ];
    }

    /**
     * Heuristic: does this string look like a URI we can parse?
     * Used by Connection::configure() to decide whether to normalize.
     */
    public static function looksLikeUri(string $value): bool
    {
        return (bool) preg_match('#^(postgres(?:ql)?|mysql|mariadb)://#', $value);
    }
}
