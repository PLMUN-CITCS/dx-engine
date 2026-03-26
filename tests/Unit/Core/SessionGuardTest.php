<?php

declare(strict_types=1);

namespace DxEngine\Tests\Unit\Core;

use DxEngine\App\Models\UserModel;
use DxEngine\Core\DBALWrapper;
use DxEngine\Core\Middleware\SessionGuard;
use DxEngine\Tests\Unit\BaseUnitTestCase;
use Monolog\Handler\NullHandler;
use Monolog\Logger;

final class SessionGuardTest extends BaseUnitTestCase
{
    private DBALWrapper $db;
    private UserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = [];

        $logger = new Logger('test-session-guard');
        $logger->pushHandler(new NullHandler());

        $this->db = new DBALWrapper([
            'driver' => 'pdo_sqlite',
            'path' => ':memory:',
            'memory' => true,
            'env' => 'testing',
        ], $logger);

        $this->db->executeStatement(
            'CREATE TABLE dx_users (
                id TEXT PRIMARY KEY,
                username TEXT,
                email TEXT,
                password_hash TEXT,
                display_name TEXT,
                status TEXT,
                last_login_at TEXT,
                password_changed_at TEXT,
                failed_login_count INTEGER,
                created_at TEXT,
                updated_at TEXT
            )'
        );

        $this->userModel = new UserModel($this->db);
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_write_close();
        }

        parent::tearDown();
    }

    public function test_attempt_returns_true_and_starts_session_on_correct_credentials(): void
    {
        $this->makeSeededUser('u1', 'valid@example.com', 'correct-password', 'active');

        $guard = new SessionGuard($this->userModel);

        $result = $guard->attempt('valid@example.com', 'correct-password');

        $this->assertTrue($result);
        $this->assertTrue($guard->check());
        $this->assertSame('u1', (string) $guard->id());
    }

    public function test_attempt_returns_false_when_password_does_not_match(): void
    {
        $this->makeSeededUser('u2', 'invalid@example.com', 'correct-password', 'active');

        $guard = new SessionGuard($this->userModel);

        $result = $guard->attempt('invalid@example.com', 'wrong-password');

        $this->assertFalse($result);
    }

    public function test_attempt_increments_failed_login_count_on_incorrect_password(): void
    {
        $this->makeSeededUser('u3', 'failed@example.com', 'correct-password', 'active');

        $guard = new SessionGuard($this->userModel);
        $guard->attempt('failed@example.com', 'wrong-password');

        $row = $this->db->selectOne('SELECT failed_login_count FROM dx_users WHERE id = ?', ['u3']);
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row['failed_login_count']);
    }

    public function test_attempt_returns_false_when_user_account_is_locked(): void
    {
        $this->makeSeededUser('u4', 'locked@example.com', 'correct-password', 'locked');

        $guard = new SessionGuard($this->userModel);

        $this->assertFalse($guard->attempt('locked@example.com', 'correct-password'));
        $this->assertFalse($guard->check());
    }

    public function test_check_returns_true_when_valid_user_id_is_in_session(): void
    {
        $user = $this->makeSeededUser('u5', 'check@example.com', 'correct-password', 'active');

        $guard = new SessionGuard($this->userModel);
        $guard->login($user);

        $this->assertTrue($guard->check());
    }

    public function test_check_returns_false_when_session_is_empty(): void
    {
        $guard = new SessionGuard($this->userModel);

        $_SESSION = [];
        $this->assertFalse($guard->check());
    }

    public function test_logout_destroys_session_data_and_sets_check_to_false(): void
    {
        $user = $this->makeSeededUser('u6', 'logout@example.com', 'correct-password', 'active');

        $guard = new SessionGuard($this->userModel);
        $guard->login($user);

        $this->assertTrue($guard->check());

        $guard->logout();

        $this->assertFalse($guard->check());
        $this->assertNull($guard->id());
    }

    private function makeSeededUser(string $id, string $email, string $plainPassword, string $status): UserModel
    {
        $hash = password_hash($plainPassword, PASSWORD_BCRYPT);

        $this->db->insert('dx_users', [
            'id' => $id,
            'username' => $email,
            'email' => $email,
            'password_hash' => $hash,
            'display_name' => 'Test User',
            'status' => $status,
            'failed_login_count' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $persisted = $this->db->selectOne('SELECT * FROM dx_users WHERE id = ?', [$id]);
        $this->assertNotNull($persisted, 'Expected seeded user row to exist.');

        $user = new UserModel($this->db);
        $user->fill([
            'id' => $id,
            'username' => $email,
            'email' => $email,
            'passwordHash' => $hash,
            'displayName' => 'Test User',
            'status' => $status,
            'failedLoginCount' => 0,
            'createdAt' => date('Y-m-d H:i:s'),
            'updatedAt' => date('Y-m-d H:i:s'),
        ]);

        return $user;
    }
}
