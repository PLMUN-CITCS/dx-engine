<?php

declare(strict_types=1);

use DxEngine\App\DX\AnonymousIntakeDX;
use DxEngine\Core\Autoloader;
use DxEngine\Core\DBALWrapper;
use DxEngine\Core\Exceptions\AuthenticationException;
use DxEngine\Core\Exceptions\ETagMismatchException;
use DxEngine\Core\Exceptions\ValidationException;
use DxEngine\Core\LayoutService;
use DxEngine\App\Models\UserModel;
use DxEngine\Core\Middleware\AuthMiddleware;
use DxEngine\Core\Middleware\CsrfMiddleware;
use DxEngine\Core\Middleware\RateLimitMiddleware;
use DxEngine\Core\Middleware\SessionGuard;
use DxEngine\Core\Router;
use Dotenv\Dotenv;
use Psr\Log\NullLogger;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/src/Core/Autoloader.php';

define('APP_ROOT', dirname(__DIR__, 2));
(new Autoloader())->register();
Dotenv::createImmutable(APP_ROOT)->safeLoad();

/** @var array<string, mixed> $dbConfig */
$dbConfig = require APP_ROOT . '/config/database.php';
$driver = (string) ($_ENV['DB_DRIVER'] ?? 'mysql');
$activeDbConfig = $dbConfig['drivers'][$driver] ?? $dbConfig['drivers']['mysql'] ?? null;

if (!is_array($activeDbConfig)) {
    http_response_code(500);
    header('Content-Type: application/json');
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
$layoutService = new LayoutService($guard);
$router = new Router();

$rateLimit = new RateLimitMiddleware();
$auth = new AuthMiddleware($guard);
$csrf = new CsrfMiddleware();

$requestBodyRaw = (string) file_get_contents('php://input');
$requestBody = json_decode($requestBodyRaw, true);
$requestBody = is_array($requestBody) ? $requestBody : [];
$dxId = $router->resolveDxId($requestBody);

$request = [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'POST',
    'uri' => $_SERVER['REQUEST_URI'] ?? '/api/dx.php',
    'headers' => function_exists('getallheaders') ? (getallheaders() ?: []) : [],
    'body' => $requestBody,
    'server' => $_SERVER,
];

$sendError = static function (string $message, int $code, array $errors = []): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => $message,
        'code' => $code,
        'errors' => $errors,
    ], JSON_THROW_ON_ERROR);
    exit;
};

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'POST')) !== 'POST') {
    $sendError('Method Not Allowed', 405);
}

try {
    $rateLimitResult = $rateLimit->handle($request, static fn (array $req): mixed => $req);
    if ($rateLimitResult === null) {
        exit;
    }

    if ($dxId === 'AnonymousIntakeDX') {
        $controllerClass = 'DxEngine\\App\\DX\\AnonymousIntakeDX';
        if (!class_exists($controllerClass)) {
            $sendError('DX controller not found.', 404);
        }

        /** @var AnonymousIntakeDX $controller */
        $controller = new $controllerClass($dbal, $guard, $layoutService);
        $controller->handle($requestBody);
        exit;
    }

    $authResult = $auth->handle($request, static fn (array $req): mixed => $req);
    if ($authResult === null) {
        exit;
    }

    $csrfResult = $csrf->handle($request, static fn (array $req): mixed => $req);
    if ($csrfResult === null) {
        exit;
    }

    if ($dxId === null) {
        $sendError('dx_id is required.', 422, ['dx_id' => ['dx_id is required.']]);
    }

    $controllerClass = 'DxEngine\\App\\DX\\' . $dxId;
    if (!class_exists($controllerClass)) {
        $sendError('DX controller not found.', 404);
    }

    $controller = new $controllerClass($dbal, $guard, $layoutService);
    $controller->handle($requestBody);
} catch (ETagMismatchException $e) {
    $sendError($e->getMessage(), 412);
} catch (ValidationException $e) {
    $sendError($e->getMessage(), 422, $e->getErrors());
} catch (AuthenticationException $e) {
    $sendError($e->getMessage(), 401);
} catch (\Throwable $e) {
    $debug = ((string) ($_ENV['APP_DEBUG'] ?? 'false')) === 'true';
    $sendError($debug ? $e->getMessage() : 'Internal Server Error', 500);
}
