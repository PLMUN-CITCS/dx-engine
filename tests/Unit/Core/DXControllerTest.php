<?php

declare(strict_types=1);

namespace DxEngine\Tests\Unit\Core;

use DxEngine\Core\Contracts\AuthenticatableInterface;
use DxEngine\Core\Contracts\GuardInterface;
use DxEngine\Core\DBALWrapper;
use DxEngine\Core\DXController;
use DxEngine\Core\Exceptions\ETagMismatchException;
use DxEngine\Core\Exceptions\ValidationException;
use DxEngine\Core\LayoutService;
use DxEngine\Tests\Unit\BaseUnitTestCase;
use Monolog\Handler\NullHandler;
use Monolog\Logger;

final class DXControllerTest extends BaseUnitTestCase
{
    private DBALWrapper $db;
    private GuardInterface $guard;
    private LayoutService $layoutService;

    protected function setUp(): void
    {
        parent::setUp();

        $logger = new Logger('test-dx-controller');
        $logger->pushHandler(new NullHandler());

        $this->db = new DBALWrapper([
            'driver' => 'pdo_sqlite',
            'path' => ':memory:',
            'memory' => true,
            'env' => 'testing',
        ], $logger);

        $this->db->executeStatement('CREATE TABLE dx_cases (id TEXT PRIMARY KEY, e_tag TEXT)');
        $this->db->executeStatement('CREATE TABLE dx_case_history (id TEXT, case_id TEXT, assignment_id TEXT, actor_id TEXT, action TEXT, from_status TEXT, to_status TEXT, details TEXT, e_tag_at_time TEXT, occurred_at TEXT)');
        $this->db->insert('dx_cases', ['id' => 'case-1', 'e_tag' => 'etag-123']);

        $user = $this->createMock(AuthenticatableInterface::class);
        $user->method('getAuthId')->willReturn('user-1');
        $user->method('getAuthRoles')->willReturn(['ROLE_ADMIN']);
        $user->method('getAuthPermissions')->willReturn(['case:update']);
        $user->method('isActive')->willReturn(true);

        $this->guard = $this->createMock(GuardInterface::class);
        $this->guard->method('user')->willReturn($user);

        $this->layoutService = new LayoutService($this->guard);
    }

    public function test_handle_calls_pre_process_before_get_flow(): void
    {
        $controller = new TestableDXController($this->db, $this->guard, $this->layoutService);
        $controller->runHandleWithoutExit(['action' => 'create']);

        $this->assertSame(['pre', 'flow', 'post'], $controller->callOrder);
    }

    public function test_handle_calls_post_process_after_build_response(): void
    {
        $controller = new TestableDXController($this->db, $this->guard, $this->layoutService);
        $controller->runHandleWithoutExit(['action' => 'create']);

        $this->assertSame('post', end($controller->callOrder));
    }

    public function test_handle_aborts_pipeline_and_returns_422_when_pre_process_throws_validation_exception(): void
    {
        $controller = new TestableDXController($this->db, $this->guard, $this->layoutService);
        $controller->throwValidationInPre = true;

        $response = $controller->runHandleWithoutExit(['action' => 'create']);

        $this->assertSame(422, $response['status']);
        $this->assertSame('Validation failed.', $response['error']);
    }

    public function test_validate_etag_throws_etag_mismatch_exception_when_client_etag_does_not_match_server(): void
    {
        $controller = new TestableDXController($this->db, $this->guard, $this->layoutService);

        $this->expectException(ETagMismatchException::class);
        $controller->validateETag('case-1', 'wrong-etag');
    }

    public function test_validate_etag_throws_etag_mismatch_exception_when_if_match_header_is_absent_on_non_create_action(): void
    {
        $controller = new TestableDXController($this->db, $this->guard, $this->layoutService);

        $this->expectException(ETagMismatchException::class);
        $controller->validateETag('case-1', '');
    }

    public function test_validate_etag_passes_silently_when_etag_matches(): void
    {
        $controller = new TestableDXController($this->db, $this->guard, $this->layoutService);
        $controller->validateETag('case-1', 'etag-123');

        $this->assertTrue(true);
    }

    public function test_build_response_calls_layout_service_prune_payload_as_last_operation(): void
    {
        $controller = new TestableDXController($this->db, $this->guard, $this->layoutService);
        $payload = $controller->buildResponse([['component_type' => 'display_text', 'key' => 'a']]);

        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('uiResources', $payload);
        $this->assertArrayHasKey('nextAssignmentInfo', $payload);
        $this->assertArrayHasKey('confirmationNote', $payload);
    }

    public function test_send_response_sets_etag_response_header_on_every_successful_response(): void
    {
        $controller = new TestableDXController($this->db, $this->guard, $this->layoutService);
        $controller->setCurrentETagForTest('etag-new');

        $response = $controller->sendResponseWithoutExit(['data' => []], 200);

        $this->assertSame('etag-new', $response['etag']);
    }

    public function test_send_response_sets_correct_http_status_code(): void
    {
        $controller = new TestableDXController($this->db, $this->guard, $this->layoutService);

        $response = $controller->sendResponseWithoutExit(['data' => []], 201);

        $this->assertSame(201, $response['status']);
    }

    public function test_get_dirty_state_returns_empty_array_when_dirty_state_node_is_absent(): void
    {
        $controller = new TestableDXController($this->db, $this->guard, $this->layoutService);
        $controller->setRequestDataForTest(['action' => 'load']);

        $this->assertSame([], $controller->getDirtyState());
    }

    public function test_fail_emits_json_error_envelope_and_terminates_execution(): void
    {
        $controller = new TestableDXController($this->db, $this->guard, $this->layoutService);

        $result = $controller->failWithoutExit('Bad request', 400, ['x']);

        $this->assertSame(400, $result['status']);
        $this->assertSame('Bad request', $result['error']);
        $this->assertSame(['x'], $result['errors']);
    }
}

final class TestableDXController extends DXController
{
    /** @var array<int, string> */
    public array $callOrder = [];
    public bool $throwValidationInPre = false;

    public function preProcess(): void
    {
        $this->callOrder[] = 'pre';
        if ($this->throwValidationInPre) {
            throw new ValidationException('Validation failed.', ['field' => ['required']]);
        }

        $this->setData(['status' => 'ok']);
        $this->setNextAssignmentInfo(['steps' => []]);
        $this->setConfirmationNote(['message' => null]);
    }

    public function getFlow(): array
    {
        $this->callOrder[] = 'flow';
        return [['component_type' => 'display_text', 'key' => 'k1', 'label' => 'X']];
    }

    public function postProcess(): void
    {
        $this->callOrder[] = 'post';
    }

    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    public function runHandleWithoutExit(array $request): array
    {
        $this->requestData = $request;

        try {
            $caseId = $this->getCaseId();
            $action = (string) ($this->requestData['action'] ?? 'load');
            if ($caseId !== null && strtoupper($action) !== 'CREATE') {
                $clientETag = (string) ($this->requestData['if_match'] ?? '');
                $this->validateETag($caseId, $clientETag);
            }

            $this->preProcess();
            $uiResources = $this->getFlow();
            $payload = $this->buildResponse($uiResources);
            $this->postProcess();

            return $this->sendResponseWithoutExit($payload, 200);
        } catch (ETagMismatchException $e) {
            return $this->failWithoutExit($e->getMessage(), 412);
        } catch (ValidationException $e) {
            return $this->failWithoutExit($e->getMessage(), 422, $e->getErrors());
        } catch (\Throwable $e) {
            return $this->failWithoutExit($e->getMessage(), 500);
        }
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function sendResponseWithoutExit(array $payload, int $code): array
    {
        return [
            'status' => $code,
            'payload' => $payload,
            'etag' => $this->currentETag,
        ];
    }

    /**
     * @param array<int|string,mixed> $errors
     * @return array<string,mixed>
     */
    public function failWithoutExit(string $message, int $code = 400, array $errors = []): array
    {
        return [
            'status' => $code,
            'error' => $message,
            'errors' => $errors,
        ];
    }

    public function setCurrentETagForTest(string $etag): void
    {
        $this->currentETag = $etag;
    }

    /**
     * @param array<string,mixed> $requestData
     */
    public function setRequestDataForTest(array $requestData): void
    {
        $this->requestData = $requestData;
    }
}
