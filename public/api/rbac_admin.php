<?php

declare(strict_types=1);

use DxEngine\App\Models\UserModel;
use DxEngine\Core\Autoloader;
use DxEngine\Core\Contracts\AuthenticatableInterface;
use DxEngine\Core\DBALWrapper;
use DxEngine\Core\Middleware\AuthMiddleware;
use DxEngine\Core\Middleware\RateLimitMiddleware;
use DxEngine\Core\Middleware\SessionGuard;
use Dotenv\Dotenv;
use Psr\Log\NullLogger;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Core/Autoloader.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
}

(new Autoloader())->register();
Dotenv::createImmutable(APP_ROOT)->safeLoad();

header('Content-Type: application/json');

/** @var array<string, mixed> $dbConfig */
$dbConfig = require APP_ROOT . '/config/database.php';
$driver = (string) ($_ENV['DB_DRIVER'] ?? 'mysql');
$activeDbConfig = $dbConfig['drivers'][$driver] ?? $dbConfig['drivers']['mysql'] ?? null;

if (!is_array($activeDbConfig)) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Invalid database configuration.',
        'code' => 500,
        'errors' => [],
    ], JSON_THROW_ON_ERROR);
    exit;
}

$dbal = new DBALWrapper($activeDbConfig, new NullLogger());
$userModel = new UserModel($dbal);
$guard = new SessionGuard($userModel);

$request = [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'uri' => $_SERVER['REQUEST_URI'] ?? '/api/rbac_admin.php',
    'headers' => function_exists('getallheaders') ? (getallheaders() ?: []) : [],
    'query' => $_GET,
    'body' => json_decode((string) file_get_contents('php://input'), true) ?? [],
];

$authMiddleware = new AuthMiddleware($guard);
$rateLimitMiddleware = new RateLimitMiddleware();

$sendJson = static function (array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR);
};

$permissionCheck = static function (AuthenticatableInterface $user) use ($sendJson): bool {
    $permissions = $user->getAuthPermissions();
    if (!in_array('rbac:manage', $permissions, true)) {
        $sendJson(['error' => 'Forbidden', 'code' => 403], 403);
        return false;
    }

    return true;
};

$handler = static function (array $req) use ($sendJson, $permissionCheck): void {
    if (!isset($_SESSION)) {
        session_start();
    }

    $sessionUser = $_SESSION['auth_user'] ?? null;
    if (!is_array($sessionUser)) {
        $sendJson(['error' => 'Unauthenticated', 'code' => 401], 401);
        return;
    }

    $user = new class ($sessionUser) implements AuthenticatableInterface {
        public function __construct(private readonly array $userData)
        {
        }

        public function getAuthId(): string|int
        {
            return (string) ($this->userData['id'] ?? '');
        }

        public function getAuthEmail(): string
        {
            return (string) ($this->userData['email'] ?? '');
        }

        public function getAuthRoles(): array
        {
            return $this->userData['roles'] ?? [];
        }

        public function getAuthPermissions(): array
        {
            return $this->userData['permissions'] ?? [];
        }

        public function isActive(): bool
        {
            return (bool) ($this->userData['active'] ?? false);
        }
    };

    if (!$permissionCheck($user)) {
        return;
    }

    $method = strtoupper((string) ($req['method'] ?? 'GET'));
    $path = parse_url((string) ($req['uri'] ?? '/'), PHP_URL_PATH) ?? '/';
    $base = '/api/rbac_admin.php';
    $route = str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    $route = $route === '' ? '/' : $route;

    if ($method === 'GET' && $route === '/roles') {
        $sendJson(['data' => ['roles' => []], 'message' => 'Roles listed']);
        return;
    }

    if ($method === 'POST' && $route === '/roles') {
        $sendJson(['data' => ['created' => true], 'message' => 'Role created'], 201);
        return;
    }

    if ($method === 'PUT' && preg_match('#^/roles/([^/]+)$#', $route, $m) === 1) {
        $sendJson(['data' => ['role_id' => $m[1], 'updated' => true], 'message' => 'Role updated']);
        return;
    }

    if ($method === 'DELETE' && preg_match('#^/roles/([^/]+)$#', $route, $m) === 1) {
        $sendJson(['data' => ['role_id' => $m[1], 'deleted' => true], 'message' => 'Role deleted']);
        return;
    }

    if ($method === 'GET' && $route === '/permissions') {
        $sendJson(['data' => ['permissions' => []], 'message' => 'Permissions listed']);
        return;
    }

    if ($method === 'POST' && preg_match('#^/roles/([^/]+)/permissions$#', $route, $m) === 1) {
        $sendJson(['data' => ['role_id' => $m[1], 'assigned' => true], 'message' => 'Permissions assigned']);
        return;
    }

    if ($method === 'DELETE' && preg_match('#^/roles/([^/]+)/permissions/([^/]+)$#', $route, $m) === 1) {
        $sendJson([
            'data' => ['role_id' => $m[1], 'permission_key' => $m[2], 'revoked' => true],
            'message' => 'Permission revoked',
        ]);
        return;
    }

    if ($method === 'GET' && preg_match('#^/users/([^/]+)/roles$#', $route, $m) === 1) {
        $sendJson(['data' => ['user_id' => $m[1], 'roles' => []], 'message' => 'User roles listed']);
        return;
    }

    if ($method === 'POST' && preg_match('#^/users/([^/]+)/roles$#', $route, $m) === 1) {
        $sendJson(['data' => ['user_id' => $m[1], 'assigned' => true], 'message' => 'Role assigned'], 201);
        return;
    }

    if ($method === 'DELETE' && preg_match('#^/users/([^/]+)/roles/([^/]+)$#', $route, $m) === 1) {
        $sendJson([
            'data' => ['user_id' => $m[1], 'role_id' => $m[2], 'revoked' => true],
            'message' => 'Role revoked',
        ]);
        return;
    }

    $sendJson(['error' => 'Not Found', 'code' => 404], 404);
};

$pipeline = static function (array $req) use ($authMiddleware, $rateLimitMiddleware, $handler): mixed {
    return $authMiddleware->handle(
        $req,
        static fn (array $authReq) => $rateLimitMiddleware->handle(
            $authReq,
            static fn (array $rateReq) => $handler($rateReq)
        )
    );
};

$pipeline($request);
