<?php

use Illuminate\Support\Str;
use Pdo\Mysql;

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

    'default' => env('DB_CONNECTION', 'sqlite'),

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

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            /*
             * The union rather than array_merge: these are integer keys, and
             * array_merge would renumber them into meaningless options.
             */
            'options' => extension_loaded('pdo_mysql')
                ? array_filter([
                    Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                ]) + [
                    /*
                     * For this driver PDO::ATTR_TIMEOUT is the connect
                     * timeout, and it is the only bound on how long a request
                     * waits for a database that is not answering.
                     *
                     * Without one, a host that drops packets rather than
                     * refusing them — a firewall rule, a machine that has
                     * gone away — leaves the request in the TCP retry
                     * sequence until PHP's max_execution_time kills it. That
                     * arrives as a fatal error with no exception to catch, so
                     * the unreachable-database page never gets its chance to
                     * render and the person waiting sees a bare 500 instead.
                     *
                     * Two things are worth knowing when tuning this:
                     *
                     * Even a refused connection is not instant. Windows
                     * retries the SYN before reporting WSAECONNREFUSED, which
                     * costs about two seconds on loopback; this setting caps
                     * that too.
                     *
                     * And whatever is set here, the wait is multiplied by
                     * four. Laravel lists "connection refused" among the
                     * failures it treats as a lost connection, so the
                     * connector retries once and Connection::run reconnects
                     * and retries the whole thing again. That is the right
                     * behaviour for a queue worker whose connection died
                     * mid-shift, so it is left alone — but it means five
                     * seconds here is up to twenty seconds of waiting before
                     * the outage page appears.
                     *
                     * Five is a deliberately safe default: a healthy
                     * connection on this host takes about a millisecond, so
                     * there is enormous headroom, and a database that is
                     * merely slow to accept under load is not mistaken for
                     * one that is down. Lower it if a faster failure matters
                     * more than that margin.
                     *
                     * Floored at one second, because a zero would mean
                     * "connect instantly or fail" and no real network can
                     * honour that.
                     */
                    PDO::ATTR_TIMEOUT => max(1, (int) env('DB_CONNECT_TIMEOUT', 5)),
                ]
                : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            /*
             * The union rather than array_merge: these are integer keys, and
             * array_merge would renumber them into meaningless options.
             */
            'options' => extension_loaded('pdo_mysql')
                ? array_filter([
                    Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                ]) + [
                    /*
                     * For this driver PDO::ATTR_TIMEOUT is the connect
                     * timeout, and it is the only bound on how long a request
                     * waits for a database that is not answering.
                     *
                     * Without one, a host that drops packets rather than
                     * refusing them — a firewall rule, a machine that has
                     * gone away — leaves the request in the TCP retry
                     * sequence until PHP's max_execution_time kills it. That
                     * arrives as a fatal error with no exception to catch, so
                     * the unreachable-database page never gets its chance to
                     * render and the person waiting sees a bare 500 instead.
                     *
                     * Two things are worth knowing when tuning this:
                     *
                     * Even a refused connection is not instant. Windows
                     * retries the SYN before reporting WSAECONNREFUSED, which
                     * costs about two seconds on loopback; this setting caps
                     * that too.
                     *
                     * And whatever is set here, the wait is multiplied by
                     * four. Laravel lists "connection refused" among the
                     * failures it treats as a lost connection, so the
                     * connector retries once and Connection::run reconnects
                     * and retries the whole thing again. That is the right
                     * behaviour for a queue worker whose connection died
                     * mid-shift, so it is left alone — but it means five
                     * seconds here is up to twenty seconds of waiting before
                     * the outage page appears.
                     *
                     * Five is a deliberately safe default: a healthy
                     * connection on this host takes about a millisecond, so
                     * there is enormous headroom, and a database that is
                     * merely slow to accept under load is not mistaken for
                     * one that is down. Lower it if a faster failure matters
                     * more than that margin.
                     *
                     * Floored at one second, because a zero would mean
                     * "connect instantly or fail" and no real network can
                     * honour that.
                     */
                    PDO::ATTR_TIMEOUT => max(1, (int) env('DB_CONNECT_TIMEOUT', 5)),
                ]
                : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
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
