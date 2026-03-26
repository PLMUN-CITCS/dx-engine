<?php

declare(strict_types=1);

namespace DxEngine\Core;

use DxEngine\Core\Contracts\AuthenticatableInterface;
use DxEngine\Core\Contracts\GuardInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class LayoutService
{
    public function __construct(
        private readonly GuardInterface $guard,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function prunePayload(array $payload): array
    {
        $user = $this->guard->user();
        if ($user === null) {
            $payload['uiResources'] = [];
            return $payload;
        }

        $uiResources = $payload['uiResources'] ?? [];
        if (!is_array($uiResources)) {
            $payload['uiResources'] = [];
            return $payload;
        }

        $payload['uiResources'] = $this->pruneComponentTree($uiResources, $user);
        return $payload;
    }

    /**
     * @param array<string, mixed> $component
     */
    public function isAllowed(array $component, AuthenticatableInterface $user): bool
    {
        $required = $component['required_permission'] ?? null;
        if ($required === null || $required === '' || $required === 'public') {
            return true;
        }

        $permissions = $user->getAuthPermissions();
        return in_array($required, $permissions, true);
    }

    /**
     * @param array<int, mixed> $components
     *
     * @return array<int, mixed>
     */
    public function pruneComponentTree(array $components, AuthenticatableInterface $user): array
    {
        $result = [];
        foreach ($components as $component) {
            if (!is_array($component)) {
                continue;
            }

            if (!$this->isAllowed($component, $user)) {
                $componentKey = (string) ($component['key'] ?? 'unknown_component');
                $required = (string) ($component['required_permission'] ?? 'unknown_permission');
                $this->logPruningEvent($componentKey, $required, $user);
                continue;
            }

            if (isset($component['children']) && is_array($component['children'])) {
                $component['children'] = $this->pruneComponentTree($component['children'], $user);
            }

            $result[] = $component;
        }

        return $result;
    }

    public function logPruningEvent(
        string $componentKey,
        string $requiredPermission,
        AuthenticatableInterface $user
    ): void {
        $this->getLogger()->info('PAYLOAD_PRUNED', [
            'user_id' => (string) $user->getAuthId(),
            'component_key' => $componentKey,
            'required_permission' => $requiredPermission,
        ]);
    }

    private function getLogger(): LoggerInterface
    {
        return $this->logger ?? new NullLogger();
    }
}
