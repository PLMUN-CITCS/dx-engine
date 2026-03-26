<?php

declare(strict_types=1);

namespace DxEngine\Tests\Unit\Core;

use DxEngine\Core\Contracts\AuthenticatableInterface;
use DxEngine\Core\Contracts\GuardInterface;
use DxEngine\Core\DBALWrapper;
use DxEngine\Core\Traits\HasPermissions;
use DxEngine\Tests\Unit\BaseUnitTestCase;
use Monolog\Handler\NullHandler;
use Monolog\Logger;

final class HasPermissionsTraitTest extends BaseUnitTestCase
{
    public function test_can_returns_true_when_user_role_grants_permission(): void
    {
        $subject = $this->makeSubject(
            ['ROLE_MANAGER'],
            [['key' => 'case:update']]
        );

        $this->assertTrue($subject->can('case:update'));
    }

    public function test_can_returns_false_when_no_user_role_grants_permission(): void
    {
        $subject = $this->makeSubject(
            ['ROLE_VIEWER'],
            [['key' => 'case:read']]
        );

        $this->assertFalse($subject->can('case:update'));
    }

    public function test_cannot_is_the_strict_inverse_of_can(): void
    {
        $subject = $this->makeSubject(['ROLE_MANAGER'], [['key' => 'case:update']]);

        $this->assertTrue($subject->can('case:update'));
        $this->assertFalse($subject->cannot('case:update'));
    }

    public function test_can_any_returns_true_when_at_least_one_permission_key_matches(): void
    {
        $subject = $this->makeSubject(['ROLE_MANAGER'], [['key' => 'case:update']]);

        $this->assertTrue($subject->canAny(['case:approve', 'case:update']));
    }

    public function test_can_all_returns_false_when_any_one_permission_is_missing(): void
    {
        $subject = $this->makeSubject(['ROLE_MANAGER'], [['key' => 'case:update']]);

        $this->assertFalse($subject->canAll(['case:update', 'case:approve']));
    }

    public function test_get_permissions_returns_deduplicated_flattened_permission_set(): void
    {
        $subject = $this->makeSubject(
            ['ROLE_MANAGER'],
            [
                ['key' => 'case:update'],
                ['key' => 'case:update'],
                ['key' => 'case:read'],
                ['key' => ''],
            ]
        );

        $permissions = $subject->getPermissions();

        $this->assertSame(['case:update', 'case:read'], $permissions);
    }

    public function test_permissions_are_cached_in_instance_property_and_db_is_not_queried_twice(): void
    {
        $db = $this->makeDbal();
        $db->executeStatement('CREATE TABLE dx_roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $db->executeStatement('CREATE TABLE dx_permissions (id INTEGER PRIMARY KEY AUTOINCREMENT, key TEXT NOT NULL)');
        $db->executeStatement('CREATE TABLE dx_role_permissions (role_id INTEGER NOT NULL, permission_id INTEGER NOT NULL)');

        $db->insert('dx_roles', ['name' => 'ROLE_MANAGER']);
        $db->insert('dx_permissions', ['key' => 'case:update']);

        $roleId = (int) $db->selectOne('SELECT id FROM dx_roles WHERE name = ?', ['ROLE_MANAGER'])['id'];
        $permId = (int) $db->selectOne('SELECT id FROM dx_permissions WHERE key = ?', ['case:update'])['id'];
        $db->insert('dx_role_permissions', ['role_id' => $roleId, 'permission_id' => $permId]);

        $guard = $this->makeGuardWithRoles(['ROLE_MANAGER']);
        $subject = new HasPermissionsTraitTestSubject($guard, $db);

        $first = $subject->getPermissions();
        $second = $subject->getPermissions();

        $this->assertSame($first, $second);
        $this->assertSame(['case:update'], $first);
    }

    /**
     * @param array<int, string> $roles
     * @param array<int, array{key:string}> $dbRows
     */
    private function makeSubject(array $roles, array $dbRows): HasPermissionsTraitTestSubject
    {
        $db = $this->makeDbal();
        $db->executeStatement('CREATE TABLE dx_roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $db->executeStatement('CREATE TABLE dx_permissions (id INTEGER PRIMARY KEY AUTOINCREMENT, key TEXT NOT NULL)');
        $db->executeStatement('CREATE TABLE dx_role_permissions (role_id INTEGER NOT NULL, permission_id INTEGER NOT NULL)');

        $roleIds = [];
        foreach ($roles as $roleName) {
            $db->insert('dx_roles', ['name' => $roleName]);
            $roleIds[$roleName] = (int) $db->selectOne('SELECT id FROM dx_roles WHERE name = ?', [$roleName])['id'];
        }

        foreach ($dbRows as $row) {
            $db->insert('dx_permissions', ['key' => $row['key']]);
            $permId = (int) $db->selectOne('SELECT id FROM dx_permissions WHERE key = ?', [$row['key']])['id'];

            $roleName = $roles[0] ?? 'ROLE_VIEWER';
            $db->insert('dx_role_permissions', [
                'role_id' => $roleIds[$roleName],
                'permission_id' => $permId,
            ]);
        }

        $guard = $this->makeGuardWithRoles($roles);

        return new HasPermissionsTraitTestSubject($guard, $db);
    }

    /**
     * @param array<int, string> $roles
     */
    private function makeGuardWithRoles(array $roles): GuardInterface
    {
        $user = $this->createMock(AuthenticatableInterface::class);
        $user->method('getAuthRoles')->willReturn($roles);

        $guard = $this->createMock(GuardInterface::class);
        $guard->method('user')->willReturn($user);

        return $guard;
    }

    private function makeDbal(): DBALWrapper
    {
        $logger = new Logger('test-has-permissions');
        $logger->pushHandler(new NullHandler());

        return new DBALWrapper([
            'driver' => 'pdo_sqlite',
            'path' => ':memory:',
            'memory' => true,
            'env' => 'testing',
        ], $logger);
    }
}

final class HasPermissionsTraitTestSubject
{
    use HasPermissions;

    public function __construct(
        private readonly GuardInterface $guard,
        private readonly DBALWrapper $dbal
    ) {
    }

    protected function getGuard(): GuardInterface
    {
        return $this->guard;
    }

    protected function getDbal(): DBALWrapper
    {
        return $this->dbal;
    }

    /**
     * @return array<int, string>
     */
    public function getRoles(): array
    {
        return $this->guard->user()?->getAuthRoles() ?? [];
    }
}
