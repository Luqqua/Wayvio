<?php

use Illuminate\Support\Str;

$mysqlDumpBinaryPath = trim((string) env('DB_DUMP_BINARY_PATH', ''));
$mysqlDumpBinaryDirectory = $mysqlDumpBinaryPath;
if ($mysqlDumpBinaryPath !== '' && is_file($mysqlDumpBinaryPath)) {
    $mysqlDumpBinaryDirectory = dirname($mysqlDumpBinaryPath);
}

$mysqlTlsOptions = [];
$mysqlSslCa = trim((string) env('DB_SSL_CA', ''));
$mysqlSslCert = trim((string) env('DB_SSL_CERT', ''));
$mysqlSslKey = trim((string) env('DB_SSL_KEY', ''));
$mysqlRequireTls = filter_var(env('DB_REQUIRE_TLS', false), FILTER_VALIDATE_BOOL);
$mysqlVerifyServerCert = filter_var(env('DB_SSL_VERIFY_SERVER_CERT', true), FILTER_VALIDATE_BOOL);

if ($mysqlSslCa !== '') {
    $mysqlTlsOptions[PDO::MYSQL_ATTR_SSL_CA] = $mysqlSslCa;
}
if ($mysqlSslCert !== '') {
    $mysqlTlsOptions[PDO::MYSQL_ATTR_SSL_CERT] = $mysqlSslCert;
}
if ($mysqlSslKey !== '') {
    $mysqlTlsOptions[PDO::MYSQL_ATTR_SSL_KEY] = $mysqlSslKey;
}
if (\defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT') && ($mysqlRequireTls || $mysqlSslCa !== '' || $mysqlSslCert !== '' || $mysqlSslKey !== '')) {
    $mysqlTlsOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $mysqlVerifyServerCert;
}

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DATABASE_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
            'dump' => [
                'dump_binary_path' => $mysqlDumpBinaryDirectory,
                'timeout' => max(30, (int) env('DB_DUMP_TIMEOUT_SECONDS', 300)),
                'use_single_transaction' => filter_var(env('DB_DUMP_USE_SINGLE_TRANSACTION', true), FILTER_VALIDATE_BOOL),
            ],
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ] + $mysqlTlsOptions) : [],
            'security' => [
                'require_tls' => $mysqlRequireTls,
                'ssl_ca' => $mysqlSslCa,
                'ssl_cert' => $mysqlSslCert,
                'ssl_key' => $mysqlSslKey,
                'ssl_verify_server_cert' => $mysqlVerifyServerCert,
            ],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
