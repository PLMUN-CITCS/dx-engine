<?php

declare(strict_types=1);

return [
    'name' => $_ENV['APP_NAME'] ?? 'DX Engine Framework',
    'env' => $_ENV['APP_ENV'] ?? 'development',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOL),
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'key' => $_ENV['APP_KEY'] ?? '',
    'session' => [
        'driver' => $_ENV['SESSION_DRIVER'] ?? 'database',
        'lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 120),
    ],
    'queue' => [
        'driver' => $_ENV['QUEUE_DRIVER'] ?? 'database',
        'worker_id' => $_ENV['QUEUE_WORKER_ID'] ?? gethostname(),
    ],
    'logging' => [
        'channel' => $_ENV['LOG_CHANNEL'] ?? 'file',
        'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
    ],
    'security' => [
        'etag_algo' => $_ENV['SECURITY_ETAG_ALGO'] ?? 'sha256',
        'bcrypt_cost' => (int) ($_ENV['SECURITY_BCRYPT_COST'] ?? 12),
        'session_regenerate_on_login' => filter_var($_ENV['SECURITY_SESSION_REGENERATE_ON_LOGIN'] ?? 'true', FILTER_VALIDATE_BOOL),
        'max_failed_login_attempts' => (int) ($_ENV['SECURITY_MAX_FAILED_LOGIN_ATTEMPTS'] ?? 5),
    ],
];
