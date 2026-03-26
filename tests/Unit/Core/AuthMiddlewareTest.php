<?php

declare(strict_types=1);

namespace DxEngine\Tests\Unit\Core;

use DxEngine\Core\Contracts\AuthenticatableInterface;
use DxEngine\Core\Contracts\GuardInterface;
use DxEngine\Core\Middleware\AuthMiddleware;
use DxEngine\Tests\Unit\BaseUnitTestCase;

final class AuthMiddlewareTest extends BaseUnitTestCase
{
    public function test_handle_calls_next_closure_when_user_is_authenticated_and_active(): void
    {
        $user = $this->createMock(AuthenticatableInterface::class);
        $user->method('isActive')->willReturn(true);

        $guard = $this->createMock(GuardInterface::class);
        $guard->method('check')->willReturn(true);
        $guard->method('user')->willReturn($user);

        $middleware = new AuthMiddleware($guard);

        $called = false;
        $result = $middleware->handle(['headers' => []], function (array $request) use (&$called): string {
            $called = true;
            return 'next-called';
        });

        $this->assertTrue($called);
        $this->assertSame('next-called', $result);
    }

    public function test_handle_returns_401_json_when_request_is_api_type_and_user_is_unauthenticated(): void
    {
        $guard = $this->createMock(GuardInterface::class);
        $guard->method('check')->willReturn(false);
        $guard->method('user')->willReturn(null);

        $middleware = new AuthMiddleware($guard);

        $result = $middleware->handle(
            ['headers' => ['Accept' => 'application/json']],
            static fn (): string => 'should-not-run'
        );

        $this->assertNull($result);
    }

    public function test_handle_redirects_to_login_when_request_is_browser_type_and_user_is_unauthenticated(): void
    {
        $guard = $this->createMock(GuardInterface::class);
        $guard->method('check')->willReturn(false);
        $guard->method('user')->willReturn(null);

        $middleware = new AuthMiddleware($guard);

        $result = $middleware->handle(
            ['headers' => ['Accept' => 'text/html']],
            static fn (): string => 'should-not-run'
        );

        $this->assertNull($result);
    }

    public function test_handle_returns_401_when_authenticated_user_is_inactive(): void
    {
        $user = $this->createMock(AuthenticatableInterface::class);
        $user->method('isActive')->willReturn(false);

        $guard = $this->createMock(GuardInterface::class);
        $guard->method('check')->willReturn(true);
        $guard->method('user')->willReturn($user);

        $middleware = new AuthMiddleware($guard);

        $result = $middleware->handle(
            ['headers' => ['Accept' => 'application/json']],
            static fn (): string => 'should-not-run'
        );

        $this->assertNull($result);
    }

    public function test_is_api_request_correctly_detects_application_json_accept_header(): void
    {
        $guard = $this->createMock(GuardInterface::class);
        $middleware = new AuthMiddleware($guard);

        $this->assertTrue($middleware->isApiRequest(['headers' => ['Accept' => 'application/json']]));
    }

    public function test_is_api_request_correctly_detects_x_requested_with_header(): void
    {
        $guard = $this->createMock(GuardInterface::class);
        $middleware = new AuthMiddleware($guard);

        $this->assertTrue($middleware->isApiRequest(['headers' => ['X-Requested-With' => 'XMLHttpRequest']]));
    }
}
