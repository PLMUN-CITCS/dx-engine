<?php

declare(strict_types=1);

namespace DxEngine\App\Models;

use DxEngine\Core\Contracts\AuthenticatableInterface;
use DxEngine\Core\DataModel;
use DxEngine\Core\DBALWrapper;

final class UserModel extends DataModel implements AuthenticatableInterface
{
    private static ?DBALWrapper $factoryDb = null;
    public function __construct(DBALWrapper $db)
    {
        self::$factoryDb = $db;
        parent::__construct($db);
    }

    protected function table(): string
    {
        return 'dx_users';
    }

    protected function fieldMap(): array
    {
        return [
            'id' => ['column' => 'id', 'type' => 'string'],
            'username' => ['column' => 'username', 'type' => 'string'],
            'email' => ['column' => 'email', 'type' => 'string'],
            'passwordHash' => ['column' => 'password_hash', 'type' => 'string'],
            'displayName' => ['column' => 'display_name', 'type' => 'string'],
            'status' => ['column' => 'status', 'type' => 'string'],
            'lastLoginAt' => ['column' => 'last_login_at', 'type' => 'datetime'],
            'passwordChangedAt' => ['column' => 'password_changed_at', 'type' => 'datetime'],
            'failedLoginCount' => ['column' => 'failed_login_count', 'type' => 'integer'],
            'createdAt' => ['column' => 'created_at', 'type' => 'datetime'],
            'updatedAt' => ['column' => 'updated_at', 'type' => 'datetime'],
        ];
    }

    public function findByEmail(string $email): ?static
    {
        return static::findOneBy(['email' => $email]);
    }

    public function findByUsername(string $username): ?static
    {
        return static::findOneBy(['username' => $username]);
    }

    public function verifyPassword(string $plaintext): bool
    {
        $hash = (string) ($this->attributes['passwordHash'] ?? '');
        if ($hash === '') {
            return false;
        }

        return password_verify($plaintext, $hash);
    }

    public function setPassword(string $plaintext): void
    {
        $this->attributes['passwordHash'] = password_hash($plaintext, PASSWORD_BCRYPT);
        $this->attributes['passwordChangedAt'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    public function incrementFailedLogin(): void
    {
        $current = (int) ($this->attributes['failedLoginCount'] ?? 0);
        $this->attributes['failedLoginCount'] = $current + 1;
    }

    public function resetFailedLogin(): void
    {
        $this->attributes['failedLoginCount'] = 0;
    }

    public function isLocked(): bool
    {
        $maxAttempts = (int) ($_ENV['SECURITY_MAX_FAILED_LOGIN_ATTEMPTS'] ?? 5);
        return (int) ($this->attributes['failedLoginCount'] ?? 0) >= $maxAttempts;
    }

    public function isActive(): bool
    {
        return ($this->attributes['status'] ?? 'inactive') === 'active' && !$this->isLocked();
    }

    public function getAuthId(): string|int
    {
        return (string) ($this->attributes['id'] ?? '');
    }

    public function getAuthEmail(): string
    {
        return (string) ($this->attributes['email'] ?? '');
    }

    /**
     * @return array<int, string>
     */
    public function getAuthRoles(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    public function getAuthPermissions(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = parent::toArray();
        unset($data['passwordHash']);

        return $data;
    }

    protected static function newInstance(): static
    {
        if (!self::$factoryDb instanceof DBALWrapper) {
            throw new \RuntimeException('UserModel::newInstance() requires a DBALWrapper-backed factory binding.');
        }

        return new self(self::$factoryDb);
    }
}
