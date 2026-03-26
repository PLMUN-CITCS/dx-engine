<?php

declare(strict_types=1);

namespace DxEngine\Core;

use DxEngine\Core\Contracts\AuthenticatableInterface;
use DxEngine\Core\Contracts\GuardInterface;
use DxEngine\Core\Exceptions\AuthenticationException;
use DxEngine\Core\Exceptions\ETagMismatchException;
use DxEngine\Core\Exceptions\ValidationException;
use DxEngine\Core\Traits\HasAbacContext;
use DxEngine\Core\Traits\HasPermissions;
use DxEngine\Core\Traits\HasRoles;
use Throwable;

abstract class DXController
{
    use HasRoles;
    use HasPermissions;
    use HasAbacContext;

    /**
     * @var array<string, mixed>
     */
    protected array $requestData = [];

    /**
     * @var array<string, mixed>
     */
    protected array $responseData = [];

    /**
     * @var array<string, mixed>
     */
    protected array $nextAssignmentInfo = [];

    /**
     * @var array<string, mixed>
     */
    protected array $confirmationNote = [];

    protected ?string $currentETag = null;

    public function __construct(
        protected readonly DBALWrapper $dbal,
        protected readonly GuardInterface $guard,
        protected readonly LayoutService $layoutService
    ) {
    }

    abstract public function preProcess(): void;

    /**
     * @return array<int, array<string, mixed>>
     */
    abstract public function getFlow(): array;

    abstract public function postProcess(): void;

    /**
     * @param array<string, mixed> $requestData
     */
    public function handle(array $requestData): void
    {
        $this->requestData = $requestData;

        try {
            $caseId = $this->getCaseId();
            $action = (string) ($this->requestData['action'] ?? 'load');
            if ($caseId !== null && strtoupper($action) !== 'CREATE') {
                $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
                $clientETag = (string) ($headers['If-Match'] ?? $headers['if-match'] ?? '');
                $this->validateETag($caseId, $clientETag);
            }

            $this->preProcess();
            $uiResources = $this->getFlow();
            $payload = $this->buildResponse($uiResources);
            $this->postProcess();

            $httpCode = 200;
            if ($caseId !== null) {
                $this->currentETag = $this->refreshETag($caseId);
            }

            $this->sendResponse($payload, $httpCode);
        } catch (ETagMismatchException $e) {
            $this->logCaseHistoryEvent($this->getCaseId(), 'ETAG_CONFLICT', ['message' => $e->getMessage()]);
            $this->fail($e->getMessage(), 412);
        } catch (ValidationException $e) {
            $this->fail($e->getMessage(), 422, $e->getErrors());
        } catch (AuthenticationException $e) {
            $this->fail($e->getMessage(), 401);
        } catch (Throwable $e) {
            $debug = ((string) ($_ENV['APP_DEBUG'] ?? 'false')) === 'true';
            $message = $debug ? $e->getMessage() : 'Internal Server Error';
            $this->fail($message, 500);
        }
    }

    public function validateETag(string $caseId, string $clientETag): void
    {
        if ($clientETag === '') {
            throw new ETagMismatchException('If-Match header is required for updates.');
        }

        $row = $this->dbal->selectOne(
            'SELECT e_tag FROM dx_cases WHERE id = ?',
            [$caseId]
        );

        $serverETag = (string) ($row['e_tag'] ?? '');
        if ($serverETag === '' || !hash_equals($serverETag, $clientETag)) {
            throw new ETagMismatchException('ETag mismatch. Case has been modified by another process.');
        }
    }

    public function refreshETag(string $caseId): string
    {
        $updatedAt = (string) microtime(true);
        $appKey = (string) ($_ENV['APP_KEY'] ?? 'dx-engine-default-key');
        $newETag = hash_hmac('sha256', $caseId . microtime() . $updatedAt, $appKey);

        $this->dbal->transactional(function () use ($caseId, $newETag): void {
            $this->dbal->update('dx_cases', ['e_tag' => $newETag], ['id' => $caseId]);
        });

        return $newETag;
    }

    /**
     * @param array<int, array<string, mixed>> $uiResources
     *
     * @return array<string, mixed>
     */
    public function buildResponse(array $uiResources): array
    {
        $payload = [
            'data' => $this->responseData,
            'uiResources' => $uiResources,
            'nextAssignmentInfo' => $this->nextAssignmentInfo,
            'confirmationNote' => $this->confirmationNote,
        ];

        return $this->layoutService->prunePayload($payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function sendResponse(array $payload, int $httpStatusCode = 200): void
    {
        http_response_code($httpStatusCode);
        header('Content-Type: application/json');

        if ($this->currentETag !== null && $this->currentETag !== '') {
            header('ETag: ' . $this->currentETag);
        }

        echo json_encode($payload, JSON_THROW_ON_ERROR);
        exit;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDirtyState(): array
    {
        $dirty = $this->requestData['dirty_state'] ?? [];
        return is_array($dirty) ? $dirty : [];
    }

    public function getCaseId(): ?string
    {
        $caseId = $this->requestData['case_id'] ?? null;
        if (!is_string($caseId) || trim($caseId) === '') {
            return null;
        }

        return $caseId;
    }

    public function getCurrentUser(): AuthenticatableInterface
    {
        $user = $this->guard->user();
        if ($user === null) {
            throw new AuthenticationException('Unauthenticated.');
        }

        return $user;
    }

    /**
     * @param array<int|string, mixed> $errors
     */
    public function fail(string $message, int $httpCode = 400, array $errors = []): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json');

        echo json_encode([
            'error' => $message,
            'code' => $httpCode,
            'errors' => $errors,
        ], JSON_THROW_ON_ERROR);

        exit;
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
     * @param array<string, mixed> $data
     */
    protected function setData(array $data): void
    {
        $this->responseData = $data;
    }

    /**
     * @param array<string, mixed> $info
     */
    protected function setNextAssignmentInfo(array $info): void
    {
        $this->nextAssignmentInfo = $info;
    }

    /**
     * @param array<string, mixed> $note
     */
    protected function setConfirmationNote(array $note): void
    {
        $this->confirmationNote = $note;
    }

    /**
     * @param array<string, mixed> $details
     */
    protected function logCaseHistoryEvent(?string $caseId, string $action, array $details = []): void
    {
        if ($caseId === null) {
            return;
        }

        $this->dbal->insert('dx_case_history', [
            'id' => uniqid('hist_', true),
            'case_id' => $caseId,
            'assignment_id' => null,
            'actor_id' => null,
            'action' => $action,
            'from_status' => null,
            'to_status' => null,
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
            'e_tag_at_time' => $this->currentETag ?? '',
            'occurred_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
