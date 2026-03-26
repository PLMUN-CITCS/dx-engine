<?php

declare(strict_types=1);

namespace DxEngine\Tests\Unit\Core;

use DxEngine\Core\Contracts\AuthenticatableInterface;
use DxEngine\Core\Contracts\GuardInterface;
use DxEngine\Core\DBALWrapper;
use DxEngine\Core\Traits\HasAbacContext;
use DxEngine\Tests\Unit\BaseUnitTestCase;
use Monolog\Handler\NullHandler;
use Monolog\Logger;

final class HasAbacContextTraitTest extends BaseUnitTestCase
{
    public function test_can_in_context_returns_true_when_user_has_scoped_role_with_matching_context_id(): void
    {
        $db = $this->makeDbal();
        $this->seedAbacTables($db, 'user-1', 'business_unit', 'BU-1', 'ROLE_MANAGER', 'case:approve');

        $subject = new HasAbacContextTraitTestSubject($this->makeGuard('user-1'), $db);

        $this->assertTrue($subject->canInContext('case:approve', 'business_unit', 'BU-1'));
    }

    public function test_can_in_context_returns_false_when_user_role_has_different_context_id(): void
    {
        $db = $this->makeDbal();
        $this->seedAbacTables($db, 'user-1', 'business_unit', 'BU-1', 'ROLE_MANAGER', 'case:approve');

        $subject = new HasAbacContextTraitTestSubject($this->makeGuard('user-1'), $db);

        $this->assertFalse($subject->canInContext('case:approve', 'business_unit', 'BU-2'));
    }

    public function test_can_in_context_returns_false_when_user_has_no_assignment_in_context_type(): void
    {
        $db = $this->makeDbal();
        $this->seedAbacTables($db, 'user-1', 'business_unit', 'BU-1', 'ROLE_MANAGER', 'case:approve');

        $subject = new HasAbacContextTraitTestSubject($this->makeGuard('user-1'), $db);

        $this->assertFalse($subject->canInContext('case:approve', 'region', 'R-1'));
    }

    public function test_get_contextual_roles_queries_with_correct_context_type_and_context_id_filters(): void
    {
        $db = $this->makeDbal();
        $this->createAbacTables($db);

        $db->insert('dx_roles', ['id' => 'r1', 'name' => 'ROLE_MANAGER']);
        $db->insert('dx_roles', ['id' => 'r2', 'name' => 'ROLE_OPERATOR']);

        $db->insert('dx_user_roles', [
            'user_id' => 'user-1',
            'role_id' => 'r1',
            'context_type' => 'business_unit',
            'context_id' => 'BU-1',
        ]);
        $db->insert('dx_user_roles', [
            'user_id' => 'user-1',
            'role_id' => 'r2',
            'context_type' => 'business_unit',
            'context_id' => 'BU-1',
        ]);

        $subject = new HasAbacContextTraitTestSubject($this->makeGuard('user-1'), $db);
        $roles = $subject->getContextualRoles('business_unit', 'BU-1');

        $this->assertSame(['ROLE_MANAGER', 'ROLE_OPERATOR'], $roles);
    }

    private function makeGuard(string $userId): GuardInterface
    {
        $user = $this->createMock(AuthenticatableInterface::class);
        $user->method('getAuthId')->willReturn($userId);

        $guard = $this->createMock(GuardInterface::class);
        $guard->method('user')->willReturn($user);

        return $guard;
    }

    private function makeDbal(): DBALWrapper
    {
        $logger = new Logger('test-has-abac');
        $logger->pushHandler(new NullHandler());

        return new DBALWrapper([
            'driver' => 'pdo_sqlite',
            'path' => ':memory:',
            'memory' => true,
            'env' => 'testing',
        ], $logger);
    }

    private function createAbacTables(DBALWrapper $db): void
    {
        $db->executeStatement('CREATE TABLE dx_roles (id TEXT PRIMARY KEY, name TEXT NOT NULL)');
        $db->executeStatement('CREATE TABLE dx_permissions (id TEXT PRIMARY KEY, key TEXT NOT NULL)');
        $db->executeStatement('CREATE TABLE dx_role_permissions (role_id TEXT NOT NULL, permission_id TEXT NOT NULL)');
        $db->executeStatement(
            'CREATE TABLE dx_user_roles (
                user_id TEXT NOT NULL,
                role_id TEXT NOT NULL,
                context_type TEXT NOT NULL,
                context_id TEXT NOT NULL
            )'
        );
    }

    private function seedAbacTables(
        DBALWrapper $db,
        string $userId,
        string $contextType,
        string $contextId,
        string $roleName,
        string $permissionKey
    ): void {
        $this->createAbacTables($db);

        $db->insert('dx_roles', ['id' => 'r1', 'name' => $roleName]);
        $db->insert('dx_permissions', ['id' => 'p1', 'key' => $permissionKey]);
        $db->insert('dx_role_permissions', ['role_id' => 'r1', 'permission_id' => 'p1']);
        $db->insert('dx_user_roles', [
            'user_id' => $userId,
            'role_id' => 'r1',
            'context_type' => $contextType,
            'context_id' => $contextId,
        ]);
    }
}

final class HasAbacContextTraitTestSubject
{
    use HasAbacContext;

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
}
