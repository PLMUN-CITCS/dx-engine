<?php

declare(strict_types=1);

namespace DxEngine\Tests\Unit;

use DxEngine\App\Models\JobModel;
use DxEngine\Core\Contracts\AuthenticatableInterface;
use DxEngine\Core\Contracts\GuardInterface;
use DxEngine\Core\DBALWrapper;
use PHPUnit\Framework\TestCase;

abstract class BaseUnitTestCase extends TestCase
{
    protected function makeDbalMock(): DBALWrapper
    {
        /** @var DBALWrapper $mock */
        $mock = $this->createMock(DBALWrapper::class);
        return $mock;
    }

    protected function makeGuardMock(): GuardInterface
    {
        /** @var GuardInterface $mock */
        $mock = $this->createMock(GuardInterface::class);
        return $mock;
    }

    protected function makeAuthenticatableMock(): AuthenticatableInterface
    {
        /** @var AuthenticatableInterface $mock */
        $mock = $this->createMock(AuthenticatableInterface::class);
        return $mock;
    }

    protected function makeJobModelMock(): JobModel
    {
        /** @var JobModel $mock */
        $mock = $this->createMock(JobModel::class);
        return $mock;
    }

    protected function makeUser(array $overrides = []): AuthenticatableInterface
    {
        $defaults = [
            'id' => 'test-user-1',
            'email' => 'test.user@example.org',
            'roles' => ['ROLE_VIEWER'],
            'permissions' => ['case:read'],
            'active' => true,
        ];

        $data = array_merge($defaults, $overrides);

        $user = $this->makeAuthenticatableMock();
        $user->method('getAuthId')->willReturn($data['id']);
        $user->method('getAuthEmail')->willReturn($data['email']);
        $user->method('getAuthRoles')->willReturn($data['roles']);
        $user->method('getAuthPermissions')->willReturn($data['permissions']);
        $user->method('isActive')->willReturn((bool) $data['active']);

        return $user;
    }
}
