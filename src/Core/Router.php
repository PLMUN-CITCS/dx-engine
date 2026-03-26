<?php

declare(strict_types=1);

namespace DxEngine\Core;

class Router
{
    /**
     * @var array<string, array<int, array{path: string, handler: callable|string}>>
     */
    private array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
    ];

    public function get(string $path, callable|string $handler): void
    {
        $this->register('GET', $path, $handler);
    }

    public function post(string $path, callable|string $handler): void
    {
        $this->register('POST', $path, $handler);
    }

    public function put(string $path, callable|string $handler): void
    {
        $this->register('PUT', $path, $handler);
    }

    public function delete(string $path, callable|string $handler): void
    {
        $this->register('DELETE', $path, $handler);
    }

    /**
     * @param array<string, mixed> $serverVars
     */
    public function dispatch(array $serverVars): void
    {
        $method = strtoupper((string) ($serverVars['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($serverVars['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        $methodRoutes = $this->routes[$method] ?? [];
        foreach ($methodRoutes as $route) {
            $params = $this->extractUriParams($route['path'], $path);
            if ($params === [] && $route['path'] !== $path && !str_contains($route['path'], '{')) {
                continue;
            }

            if ($params !== [] || $route['path'] === $path) {
                $handler = $route['handler'];
                $requestBody = $this->decodeJsonBody();

                if (is_callable($handler)) {
                    $handler([
                        'method' => $method,
                        'uri' => $path,
                        'params' => $params,
                        'body' => $requestBody,
                        'query' => $_GET,
                        'server' => $serverVars,
                    ]);
                    return;
                }

                if (is_string($handler) && is_file($handler)) {
                    require $handler;
                    return;
                }

                break;
            }
        }

        if ($this->hasPathWithDifferentMethod($path, $method)) {
            $this->methodNotAllowed();
            return;
        }

        $this->notFound();
    }

    public function notFound(): void
    {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Not Found',
            'code' => 404,
        ], JSON_THROW_ON_ERROR);
    }

    public function methodNotAllowed(): void
    {
        $allowedMethods = $this->getAllowedMethodsForCurrentPath();
        if ($allowedMethods !== []) {
            header('Allow: ' . implode(', ', $allowedMethods));
        }

        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Method Not Allowed',
            'code' => 405,
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, string>
     */
    public function extractUriParams(string $pattern, string $uri): array
    {
        $patternParts = explode('/', trim($pattern, '/'));
        $uriParts = explode('/', trim($uri, '/'));

        if (count($patternParts) !== count($uriParts)) {
            return [];
        }

        $params = [];
        foreach ($patternParts as $index => $part) {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $part, $matches) === 1) {
                $params[$matches[1]] = $uriParts[$index];
                continue;
            }

            if ($part !== $uriParts[$index]) {
                return [];
            }
        }

        return $params;
    }

    /**
     * @param array<string, mixed> $requestBody
     */
    public function resolveDxId(array $requestBody): ?string
    {
        $dxId = $requestBody['dx_id'] ?? null;
        if (!is_string($dxId) || trim($dxId) === '') {
            return null;
        }

        return $dxId;
    }

    private function register(string $method, string $path, callable|string $handler): void
    {
        $normalizedPath = '/' . trim($path, '/');
        $this->routes[$method][] = [
            'path' => $normalizedPath === '//' ? '/' : $normalizedPath,
            'handler' => $handler,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonBody(): array
    {
        $raw = (string) file_get_contents('php://input');
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function hasPathWithDifferentMethod(string $path, string $currentMethod): bool
    {
        foreach ($this->routes as $method => $methodRoutes) {
            if ($method === $currentMethod) {
                continue;
            }

            foreach ($methodRoutes as $route) {
                $params = $this->extractUriParams($route['path'], $path);
                if ($params !== [] || $route['path'] === $path) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function getAllowedMethodsForCurrentPath(): array
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        $allowed = [];

        foreach ($this->routes as $method => $methodRoutes) {
            foreach ($methodRoutes as $route) {
                $params = $this->extractUriParams($route['path'], $path);
                if ($params !== [] || $route['path'] === $path) {
                    $allowed[] = $method;
                    break;
                }
            }
        }

        return $allowed;
    }
}
