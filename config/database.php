<?php

declare(strict_types=1);

/**
 * Doctrine DBAL driver configuration.
 *
 * MariaDB uses Doctrine's `pdo_mysql` driver alias.
 * Core migrations must remain database-agnostic and must not use
 * dialect-specific SQL features.
 */
return [
    'default' => $_ENV['DB_DRIVER'] ?? 'mysql',
    'drivers' => [
        'mysql' => [
            'driver' => 'pdo_mysql',
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
            'dbname' => $_ENV['DB_NAME'] ?? 'dx_engine',
            'user' => $_ENV['DB_USER'] ?? 'root',
            'password' => $_ENV['DB_PASS'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            'driverOptions' => [],
        ],
        'pgsql' => [
            'driver' => 'pdo_pgsql',
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port' => (int) ($_ENV['DB_PORT'] ?? 5432),
            'dbname' => $_ENV['DB_NAME'] ?? 'dx_engine',
            'user' => $_ENV['DB_USER'] ?? 'postgres',
            'password' => $_ENV['DB_PASS'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8',
            'driverOptions' => [],
        ],
        'sqlite' => [
            'driver' => 'pdo_sqlite',
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port' => (int) ($_ENV['DB_PORT'] ?? 0),
            'dbname' => $_ENV['DB_NAME'] ?? 'storage/database.sqlite',
            'user' => $_ENV['DB_USER'] ?? '',
            'password' => $_ENV['DB_PASS'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8',
            'driverOptions' => [],
        ],
        'sqlsrv' => [
            'driver' => 'pdo_sqlsrv',
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port' => (int) ($_ENV['DB_PORT'] ?? 1433),
            'dbname' => $_ENV['DB_NAME'] ?? 'dx_engine',
            'user' => $_ENV['DB_USER'] ?? 'sa',
            'password' => $_ENV['DB_PASS'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8',
            'driverOptions' => [],
        ],
    ],
];
