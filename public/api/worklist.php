<?php

declare(strict_types=1);

use DxEngine\App\Models\UserModel;
use DxEngine\Core\Autoloader;
use DxEngine\Core\DBALWrapper;
use DxEngine\Core\DxWorklistService;
use DxEngine\Core\Middleware\AuthMiddleware;
use DxEngine\Core\Middleware\RateLimitMiddleware;
use DxEngine\Core\Middleware\SessionGuard;
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
$worklistService = new DxWorklistService($dbal, $guard);

$auth = new AuthMiddleware($guard);
$rateLimit = new RateLimitMiddleware();

$request = [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'uri' => $_SERVER['REQUEST_URI'] ?? '/api/worklist.php',
    'headers' => function_exists('getallheaders') ? (getallheaders() ?: []) : [],
    'query' => $_GET,
    'server' => $_SERVER,
    'body' => json_decode((string) file_get_contents('php://input'), true),
];

$sendJson = static function (array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit;
};

$sendError = static function (string $message, int $code, array $errors = []) use ($sendJson): void {
    $sendJson([
        'error' => $message,
        'code' => $code,
        'errors' => $errors,
    ], $code);
};

try {
    // Middleware pipeline: AuthMiddleware -> RateLimitMiddleware
    $authResult = $auth->handle($request, static fn (array $req): mixed => $req);
    if ($authResult === null) {
        exit;
    }

    $rateLimitResult = $rateLimit->handle($request, static fn (array $req): mixed => $req);
    if ($rateLimitResult === null) {
        exit;
    }

    $user = $guard->user();
    if ($user === null) {
        $sendError('Unauthenticated', 401);
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/';
    $segments = array_values(array_filter(explode('/', trim($path, '/'))));

    // Normalize expected path after /public/api/worklist.php
    $tail = [];
    $worklistIndex = array_search('worklist.php', $segments, true);
    if ($worklistIndex !== false) {
        $tail = array_slice($segments, $worklistIndex + 1);
    }

    // GET /
    if ($method === 'GET' && $tail === []) {
        $filters = [
            'status' => $_GET['status'] ?? null,
            'deadline_before' => $_GET['deadline_before'] ?? null,
            'priority' => $_GET['priority'] ?? null,
        ];

        $rows = $worklistService->getPersonalWorklist((string) $user->getAuthId(), $filters);

        $sendJson([
            'data' => [
                'items' => $rows,
                'summary_label' => 'Personal Worklist Results',
            ],
            'message' => 'Personal worklist loaded successfully.',
        ]);
    }

    // GET /queue/{roleName}
    if ($method === 'GET' && count($tail) === 2 && $tail[0] === 'queue') {
        $roleName = (string) $tail[1];
        $permissions = $user->getAuthPermissions();

        if (!in_array('worklist:claim', $permissions, true)) {
            $sendError('Forbidden: Missing permission worklist:claim', 403);
        }

        $filters = [
            'case_status' => $_GET['case_status'] ?? null,
            'deadline_before' => $_GET['deadline_before'] ?? null,
            'priority' => $_GET['priority'] ?? null,
        ];

        $rows = $worklistService->getGroupQueue($roleName, $filters);

        $sendJson([
            'data' => [
                'items' => $rows,
                'queue_label' => 'Group Queue: ' . $roleName,
            ],
            'message' => 'Group queue loaded successfully.',
        ]);
    }

    // POST /claim/{assignmentId}
    if ($method === 'POST' && count($tail) === 2 && $tail[0] === 'claim') {
        $assignmentId = (string) $tail[1];
        $ok = $worklistService->claimAssignment($assignmentId, (string) $user->getAuthId());

        if (!$ok) {
            $sendError('Unable to claim assignment.', 409);
        }

        $sendJson([
            'data' => [
                'assignment_id' => $assignmentId,
                'status_label' => 'Assignment Status: Active',
            ],
            'message' => 'Assignment claimed successfully.',
        ]);
    }

    // POST /release/{assignmentId}
    if ($method === 'POST' && count($tail) === 2 && $tail[0] === 'release') {
        $assignmentId = (string) $tail[1];
        $ok = $worklistService->releaseAssignment($assignmentId, (string) $user->getAuthId());

        if (!$ok) {
            $sendError('Unable to release assignment.', 409);
        }

        $sendJson([
            'data' => [
                'assignment_id' => $assignmentId,
                'status_label' => 'Assignment Status: Pending',
            ],
            'message' => 'Assignment released successfully.',
        ]);
    }

    // GET /case/{caseId}/history
    if ($method === 'GET' && count($tail) === 3 && $tail[0] === 'case' && $tail[2] === 'history') {
        $caseId = (string) $tail[1];
        $permissions = $user->getAuthPermissions();

        if (!in_array('case:read', $permissions, true)) {
            $sendError('Forbidden: Missing permission case:read', 403);
        }

        $history = $worklistService->getCaseHistory($caseId);

        $sendJson([
            'data' => [
                'case_id' => $caseId,
                'history' => $history,
            ],
            'message' => 'Case history loaded successfully.',
        ]);
    }

    $sendError('Not Found', 404);
} catch (\Throwable $e) {
    $debug = ((string) ($_ENV['APP_DEBUG'] ?? 'false')) === 'true';
    $sendError($debug ? $e->getMessage() : 'Internal Server Error', 500);
}
