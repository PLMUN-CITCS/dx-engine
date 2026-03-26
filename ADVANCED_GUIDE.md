# DX Engine Framework - Advanced Implementation Guide

## Table of Contents

1. [Framework Deep Dive](#framework-deep-dive)
2. [Complete Implementation Examples](#complete-implementation-examples)
3. [Advanced Patterns](#advanced-patterns)
4. [Performance Optimization](#performance-optimization)
5. [Security Best Practices](#security-best-practices)
6. [Troubleshooting](#troubleshooting)

---

## Framework Deep Dive

### How DX Engine Works: Request-Response Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                        CLIENT BROWSER                            │
│                                                                  │
│  1. User clicks "Submit"                                        │
│  2. DXInterpreter.js collects dirty state                       │
│  3. POST to /api/dx.php with:                                   │
│     {                                                            │
│       "dx_id": "order_processing",                              │
│       "case_id": "case-123",                                    │
│       "action": "approve",                                      │
│       "dirty_state": { "approval_notes": "Looks good!" }        │
│     }                                                            │
│  4. If-Match: <etag> header included                            │
└─────────────────────┬───────────────────────────────────────────┘
                      │
                      │ HTTP POST
                      ▼
┌─────────────────────────────────────────────────────────────────┐
│                    SERVER (public/api/dx.php)                    │
│                                                                  │
│  5. Router instantiates OrderProcessingDX                       │
│  6. DXController.handle() called                                │
│  7. Validate eTag against database                              │
│     ├─ Match? Continue                                          │
│     └─ Mismatch? Return 412 Precondition Failed                 │
│  8. preProcess() - Load order, validate permissions             │
│  9. getFlow() - Build UI components array                       │
│ 10. LayoutService.prunePayload() - Remove unauthorized components│
│ 11. postProcess() - Dispatch jobs, trigger webhooks             │
│ 12. Refresh eTag, save to database                              │
│ 13. Send 4-node JSON response                                   │
│     {                                                            │
│       "data": { "order_id": "ORD-001", "status": "Approved" },  │
│       "uiResources": [...],                                     │
│       "nextAssignmentInfo": { "steps": [...] },                 │
│       "confirmationNote": { "message": "Approved!" }            │
│     }                                                            │
│ 14. ETag: <new-etag> header included                            │
└─────────────────────┬───────────────────────────────────────────┘
                      │
                      │ JSON Response
                      ▼
┌─────────────────────────────────────────────────────────────────┐
│                        CLIENT BROWSER                            │
│                                                                  │
│ 15. DXInterpreter.js receives response                          │
│ 16. Stores new eTag for next request                            │
│ 17. StateManager updates client state                           │
│ 18. ComponentRegistry renders UI components                     │
│ 19. VisibilityEngine applies visibility rules                   │
│ 20. Stepper shows workflow progress                             │
│ 21. Validator attaches validation logic                         │
│ 22. User sees updated UI                                        │
└─────────────────────────────────────────────────────────────────┘
```

### Data Flow Diagram

```
┌──────────────────────────────────────────────────────────────────┐
│                    DATABASE (Source of Truth)                     │
│                                                                   │
│  dx_cases                dx_assignments           dx_case_history │
│  ├─ id                   ├─ case_id               ├─ case_id     │
│  ├─ case_status          ├─ assigned_to_user_id  ├─ action      │
│  ├─ payload (JSON)       ├─ step_name             ├─ from_status │
│  └─ e_tag                └─ e_tag                 └─ to_status   │
└──────────────────┬───────────────────────────────────────────────┘
                   │
                   │ DBALWrapper (Database Abstraction)
                   ▼
┌──────────────────────────────────────────────────────────────────┐
│                     CORE FRAMEWORK LAYER                          │
│                                                                   │
│  DXController               DataModel              LayoutService  │
│  ├─ preProcess()            ├─ find()              ├─ prunePayload()│
│  ├─ getFlow()               ├─ findAll()           └─ checkPermission()│
│  ├─ postProcess()           ├─ save()                             │
│  └─ buildResponse()         └─ delete()                           │
│                                                                   │
│  JobDispatcher              Middleware             Router         │
│  ├─ dispatch()              ├─ AuthMiddleware      ├─ dispatch()  │
│  └─ process()               ├─ CsrfMiddleware      └─ match()     │
│                             └─ RateLimitMiddleware                │
└──────────────────┬───────────────────────────────────────────────┘
                   │
                   │ 4-Node JSON Contract
                   ▼
┌──────────────────────────────────────────────────────────────────┐
│                   APPLICATION LAYER (Your Code)                   │
│                                                                   │
│  OrderProcessingDX          EmployeeOnboardingDX                 │
│  ├─ preProcess()            ├─ preProcess()                      │
│  │  └─ Load order           │  └─ Load employee                  │
│  ├─ getFlow()               ├─ getFlow()                         │
│  │  └─ Build approval UI    │  └─ Build onboarding UI            │
│  └─ postProcess()           └─ postProcess()                     │
│     └─ Dispatch job            └─ Send welcome email             │
└──────────────────┬───────────────────────────────────────────────┘
                   │
                   │ HTTP Response (JSON + eTag)
                   ▼
┌──────────────────────────────────────────────────────────────────┐
│                    FRONTEND (Vanilla JS)                          │
│                                                                   │
│  DXInterpreter.js           ComponentRegistry.js                 │
│  ├─ fetch(action)           ├─ render(component)                 │
│  ├─ render(payload)         ├─ renderAll(components)             │
│  └─ handleError()           └─ Built-in Bootstrap components     │
│                                                                   │
│  StateManager.js            VisibilityEngine.js                  │
│  ├─ set(key, value)         ├─ applyAll(container)               │
│  ├─ get(key)                └─ subscribeToStateChanges()         │
│  └─ getAll()                                                     │
│                                                                   │
│  Validator.js               Stepper.js                           │
│  ├─ validate(form)          ├─ render(steps)                     │
│  └─ applyErrorStyles()      └─ updateProgress()                  │
└──────────────────────────────────────────────────────────────────┘
```

---

## Complete Implementation Examples

### Example 1: Loan Application Workflow

A complete loan application system with multi-stage approval.

**Database Model:**

```php
namespace DxEngine\App\Models;

use DxEngine\Core\DataModel;

class LoanApplicationModel extends DataModel
{
    protected function table(): string
    {
        return 'loan_applications';
    }
    
    protected function fieldMap(): array
    {
        return [
            'id' => ['column' => 'id', 'type' => 'string'],
            'applicantName' => ['column' => 'applicant_name', 'type' => 'string'],
            'applicantEmail' => ['column' => 'applicant_email', 'type' => 'string'],
            'loanAmount' => ['column' => 'loan_amount', 'type' => 'float'],
            'loanPurpose' => ['column' => 'loan_purpose', 'type' => 'string'],
            'annualIncome' => ['column' => 'annual_income', 'type' => 'float'],
            'creditScore' => ['column' => 'credit_score', 'type' => 'integer'],
            'status' => ['column' => 'status', 'type' => 'string'],
            'stage' => ['column' => 'stage', 'type' => 'string'],
            'reviewerNotes' => ['column' => 'reviewer_notes', 'type' => 'string'],
            'approvalDecision' => ['column' => 'approval_decision', 'type' => 'string'],
            'createdAt' => ['column' => 'created_at', 'type' => 'datetime'],
            'updatedAt' => ['column' => 'updated_at', 'type' => 'datetime'],
            'eTag' => ['column' => 'e_tag', 'type' => 'string']
        ];
    }
    
    protected static function newInstance(): static
    {
        global $db;
        return new static($db);
    }
}
```

**Migration:**

```php
namespace DxEngine\Database\Migrations;

use DxEngine\Core\Migrations\MigrationInterface;
use DxEngine\Core\DBALWrapper;

class CreateLoanApplicationsTable implements MigrationInterface
{
    public function up(DBALWrapper $db): void
    {
        $db->executeStatement("
            CREATE TABLE loan_applications (
                id VARCHAR(36) PRIMARY KEY,
                applicant_name VARCHAR(255) NOT NULL,
                applicant_email VARCHAR(255) NOT NULL,
                loan_amount DECIMAL(15,2) NOT NULL,
                loan_purpose VARCHAR(100) NOT NULL,
                annual_income DECIMAL(15,2) NOT NULL,
                credit_score INTEGER NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'DRAFT',
                stage VARCHAR(50) NOT NULL DEFAULT 'APPLICATION',
                reviewer_notes TEXT,
                approval_decision VARCHAR(50),
                created_at TIMESTAMP NOT NULL,
                updated_at TIMESTAMP NOT NULL,
                e_tag VARCHAR(64) NOT NULL
            )
        ");
        
        $db->executeStatement("
            CREATE INDEX idx_loan_status ON loan_applications(status)
        ");
    }
    
    public function down(DBALWrapper $db): void
    {
        $db->executeStatement("DROP TABLE IF EXISTS loan_applications");
    }
}
```

**DX Controller:**

```php
namespace DxEngine\App\DX;

use DxEngine\Core\DXController;
use DxEngine\Core\Exceptions\ValidationException;
use DxEngine\Core\Jobs\JobDispatcher;
use DxEngine\App\Jobs\CreditCheckJob;
use DxEngine\App\Jobs\LoanApprovalNotificationJob;

class LoanApplicationDX extends DXController
{
    private array $application = [];
    private string $stage = 'APPLICATION';
    
    public function preProcess(): void
    {
        $action = $this->requestData['action'] ?? 'load';
        $dirtyState = $this->getDirtyState();
        
        // Load existing application or create new
        if ($caseId = $this->getCaseId()) {
            $this->application = $this->loadApplication($caseId);
            $this->stage = $this->application['stage'] ?? 'APPLICATION';
        }
        
        // Handle actions
        switch ($action) {
            case 'submit_application':
                $this->validateApplicationData($dirtyState);
                $this->saveApplication($dirtyState);
                $this->stage = 'CREDIT_CHECK';
                $this->dispatchCreditCheck();
                break;
                
            case 'submit_review':
                $this->validateReviewData($dirtyState);
                $this->saveReviewNotes($dirtyState);
                $this->stage = 'APPROVAL';
                break;
                
            case 'approve':
                $this->approveApplication();
                $this->stage = 'APPROVED';
                break;
                
            case 'reject':
                $this->rejectApplication($dirtyState);
                $this->stage = 'REJECTED';
                break;
        }
        
        // Set response data (Product Info - formatted for display)
        $this->setData([
            'application_id' => 'Application ID: ' . ($this->application['id'] ?? 'NEW'),
            'applicant_name' => 'Applicant: ' . ($this->application['applicant_name'] ?? 'Unknown'),
            'loan_amount' => 'Loan Amount: $' . number_format($this->application['loan_amount'] ?? 0, 2),
            'credit_score' => 'Credit Score: ' . ($this->application['credit_score'] ?? 'Pending'),
            'status_label' => 'Status: ' . $this->getStatusLabel($this->stage),
            'risk_assessment' => $this->getRiskAssessment()
        ]);
    }
    
    public function getFlow(): array
    {
        return match($this->stage) {
            'APPLICATION' => $this->buildApplicationFlow(),
            'CREDIT_CHECK' => $this->buildCreditCheckFlow(),
            'REVIEW' => $this->buildReviewFlow(),
            'APPROVAL' => $this->buildApprovalFlow(),
            'APPROVED' => $this->buildApprovedFlow(),
            'REJECTED' => $this->buildRejectedFlow(),
            default => []
        };
    }
    
    public function postProcess(): void
    {
        // Set workflow steps
        $this->setNextAssignmentInfo([
            'steps' => [
                ['label' => 'Application', 'status' => $this->getStepStatus('APPLICATION')],
                ['label' => 'Credit Check', 'status' => $this->getStepStatus('CREDIT_CHECK')],
                ['label' => 'Review', 'status' => $this->getStepStatus('REVIEW')],
                ['label' => 'Approval', 'status' => $this->getStepStatus('APPROVAL')],
                ['label' => 'Decision', 'status' => $this->getStepStatus('APPROVED,REJECTED')]
            ],
            'current_step' => $this->getCurrentStepIndex(),
            'progress_percentage' => $this->getProgressPercentage()
        ]);
        
        // Set confirmation
        $this->setConfirmationNote([
            'message' => $this->getConfirmationMessage(),
            'variant' => $this->stage === 'REJECTED' ? 'warning' : 'success'
        ]);
    }
    
    private function buildApplicationFlow(): array
    {
        return [
            [
                'component_type' => 'section_header',
                'key' => 'applicant_info',
                'label' => 'Applicant Information',
                'required_permission' => null
            ],
            [
                'component_type' => 'text_input',
                'key' => 'applicant_name',
                'label' => 'Full Name',
                'required_permission' => null,
                'validation' => ['required' => true, 'min_length' => 3],
                'placeholder' => 'John Doe',
                'value' => $this->application['applicant_name'] ?? null
            ],
            [
                'component_type' => 'email_input',
                'key' => 'applicant_email',
                'label' => 'Email Address',
                'required_permission' => null,
                'validation' => ['required' => true, 'email' => true],
                'value' => $this->application['applicant_email'] ?? null
            ],
            [
                'component_type' => 'section_header',
                'key' => 'loan_details',
                'label' => 'Loan Details',
                'required_permission' => null
            ],
            [
                'component_type' => 'number_input',
                'key' => 'loan_amount',
                'label' => 'Loan Amount ($)',
                'required_permission' => null,
                'validation' => ['required' => true, 'min' => 1000, 'max' => 500000],
                'value' => $this->application['loan_amount'] ?? null
            ],
            [
                'component_type' => 'dropdown',
                'key' => 'loan_purpose',
                'label' => 'Loan Purpose',
                'required_permission' => null,
                'validation' => ['required' => true],
                'options' => [
                    ['value' => 'HOME_PURCHASE', 'label' => 'Home Purchase'],
                    ['value' => 'AUTO', 'label' => 'Auto Loan'],
                    ['value' => 'BUSINESS', 'label' => 'Business Expansion'],
                    ['value' => 'DEBT_CONSOLIDATION', 'label' => 'Debt Consolidation'],
                    ['value' => 'OTHER', 'label' => 'Other']
                ],
                'value' => $this->application['loan_purpose'] ?? null
            ],
            [
                'component_type' => 'number_input',
                'key' => 'annual_income',
                'label' => 'Annual Income ($)',
                'required_permission' => null,
                'validation' => ['required' => true, 'min' => 0],
                'value' => $this->application['annual_income'] ?? null
            ],
            [
                'component_type' => 'button_primary',
                'key' => 'submit_application',
                'label' => 'Submit Application',
                'action' => 'submit_application',
                'required_permission' => null
            ]
        ];
    }
    
    private function buildReviewFlow(): array
    {
        return [
            [
                'component_type' => 'section_header',
                'key' => 'review_header',
                'label' => 'Application Review',
                'required_permission' => 'loan:review'
            ],
            [
                'component_type' => 'display_text',
                'key' => 'credit_score_display',
                'label' => 'Credit Score',
                'value' => (string)($this->application['credit_score'] ?? 'N/A'),
                'required_permission' => 'loan:review'
            ],
            [
                'component_type' => 'display_text',
                'key' => 'debt_to_income',
                'label' => 'Debt-to-Income Ratio',
                'value' => $this->calculateDebtToIncome() . '%',
                'required_permission' => 'loan:review'
            ],
            [
                'component_type' => 'textarea',
                'key' => 'reviewer_notes',
                'label' => 'Reviewer Notes',
                'required_permission' => 'loan:review',
                'validation' => ['required' => true, 'min_length' => 20],
                'rows' => 5,
                'value' => $this->application['reviewer_notes'] ?? null
            ],
            [
                'component_type' => 'button_primary',
                'key' => 'submit_review',
                'label' => 'Submit Review',
                'action' => 'submit_review',
                'required_permission' => 'loan:review'
            ]
        ];
    }
    
    private function buildApprovalFlow(): array
    {
        $isHighRisk = ($this->application['credit_score'] ?? 0) < 650;
        $requiresManagerApproval = $this->application['loan_amount'] > 100000;
        
        return [
            [
                'component_type' => 'section_header',
                'key' => 'approval_header',
                'label' => 'Loan Approval Decision',
                'required_permission' => 'loan:approve'
            ],
            [
                'component_type' => 'alert_banner',
                'key' => 'high_risk_warning',
                'label' => 'WARNING: High-risk applicant detected. Additional review required.',
                'variant' => 'warning',
                'required_permission' => 'loan:approve',
                'visibility_rule' => $isHighRisk ? 'true' : 'false'
            ],
            [
                'component_type' => 'alert_banner',
                'key' => 'manager_approval_required',
                'label' => 'Manager approval required for loans over $100,000.',
                'variant' => 'info',
                'required_permission' => 'loan:approve',
                'visibility_rule' => $requiresManagerApproval ? 'true' : 'false'
            ],
            [
                'component_type' => 'display_text',
                'key' => 'reviewer_recommendation',
                'label' => 'Reviewer Recommendation',
                'value' => $this->application['reviewer_notes'] ?? 'No notes',
                'required_permission' => 'loan:approve'
            ],
            [
                'component_type' => 'radio_group',
                'key' => 'approval_decision',
                'label' => 'Decision',
                'required_permission' => 'loan:approve',
                'validation' => ['required' => true],
                'options' => [
                    ['value' => 'APPROVE', 'label' => 'Approve Loan'],
                    ['value' => 'REJECT', 'label' => 'Reject Application'],
                    ['value' => 'REQUEST_MORE_INFO', 'label' => 'Request Additional Information']
                ]
            ],
            [
                'component_type' => 'textarea',
                'key' => 'decision_notes',
                'label' => 'Decision Notes',
                'required_permission' => 'loan:approve',
                'validation' => ['required' => true, 'min_length' => 10],
                'rows' => 3
            ],
            [
                'component_type' => 'button_primary',
                'key' => 'approve',
                'label' => 'Approve Loan',
                'action' => 'approve',
                'required_permission' => $requiresManagerApproval ? 'loan:approve:manager' : 'loan:approve',
                'visibility_rule' => 'approval_decision == "APPROVE"'
            ],
            [
                'component_type' => 'button_danger',
                'key' => 'reject',
                'label' => 'Reject Application',
                'action' => 'reject',
                'required_permission' => 'loan:approve',
                'visibility_rule' => 'approval_decision == "REJECT"'
            ]
        ];
    }
    
    private function validateApplicationData(array $data): void
    {
        $errors = [];
        
        if (empty($data['applicant_name'])) {
            $errors['applicant_name'] = ['Full name is required'];
        }
        
        if (empty($data['applicant_email']) || !filter_var($data['applicant_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['applicant_email'] = ['Valid email is required'];
        }
        
        $loanAmount = (float)($data['loan_amount'] ?? 0);
        if ($loanAmount < 1000 || $loanAmount > 500000) {
            $errors['loan_amount'] = ['Loan amount must be between $1,000 and $500,000'];
        }
        
        $annualIncome = (float)($data['annual_income'] ?? 0);
        if ($annualIncome <= 0) {
            $errors['annual_income'] = ['Annual income is required'];
        }
        
        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }
    }
    
    private function saveApplication(array $data): void
    {
        $caseId = $this->getCaseId() ?? 'loan-' . uniqid();
        
        $this->dbal->transactional(function() use ($caseId, $data) {
            if (!$this->getCaseId()) {
                // Create new application
                $this->dbal->insert('loan_applications', [
                    'id' => $caseId,
                    'applicant_name' => $data['applicant_name'],
                    'applicant_email' => $data['applicant_email'],
                    'loan_amount' => $data['loan_amount'],
                    'loan_purpose' => $data['loan_purpose'],
                    'annual_income' => $data['annual_income'],
                    'credit_score' => 0,
                    'status' => 'SUBMITTED',
                    'stage' => 'CREDIT_CHECK',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'e_tag' => hash('sha256', $caseId . time())
                ]);
                
                // Create case
                $this->dbal->insert('dx_cases', [
                    'id' => $caseId,
                    'case_type' => 'LOAN_APPLICATION',
                    'case_status' => 'SUBMITTED',
                    'owner_id' => $this->getCurrentUser()->getAuthId(),
                    'priority' => $data['loan_amount'] > 100000 ? 'HIGH' : 'NORMAL',
                    'payload' => json_encode($data),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'e_tag' => hash('sha256', $caseId . time())
                ]);
            } else {
                // Update existing
                $this->dbal->update('loan_applications', [
                    'applicant_name' => $data['applicant_name'],
                    'applicant_email' => $data['applicant_email'],
                    'loan_amount' => $data['loan_amount'],
                    'updated_at' => date('Y-m-d H:i:s')
                ], ['id' => $caseId]);
            }
            
            // Log history
            $this->dbal->insert('dx_case_history', [
                'id' => 'hist-' . uniqid(),
                'case_id' => $caseId,
                'actor_id' => $this->getCurrentUser()->getAuthId(),
                'action' => 'APPLICATION_SUBMITTED',
                'from_status' => 'DRAFT',
                'to_status' => 'SUBMITTED',
                'details' => json_encode(['loan_amount' => $data['loan_amount']]),
                'occurred_at' => date('Y-m-d H:i:s')
            ]);
        });
        
        $this->application = $this->loadApplication($caseId);
    }
    
    private function dispatchCreditCheck(): void
    {
        $dispatcher = new JobDispatcher($this->dbal);
        $dispatcher->dispatch(CreditCheckJob::class, [
            'application_id' => $this->application['id'],
            'applicant_email' => $this->application['applicant_email'],
            'loan_amount' => $this->application['loan_amount']
        ], 'credit-checks');
    }
    
    private function approveApplication(): void
    {
        $this->dbal->transactional(function() {
            $this->dbal->update('loan_applications', [
                'status' => 'APPROVED',
                'stage' => 'APPROVED',
                'approval_decision' => 'APPROVED',
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $this->application['id']]);
            
            $this->dbal->update('dx_cases', [
                'case_status' => 'APPROVED',
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $this->application['id']]);
            
            // Log approval
            $this->dbal->insert('dx_case_history', [
                'id' => 'hist-' . uniqid(),
                'case_id' => $this->application['id'],
                'actor_id' => $this->getCurrentUser()->getAuthId(),
                'action' => 'LOAN_APPROVED',
                'from_status' => 'REVIEW',
                'to_status' => 'APPROVED',
                'details' => json_encode(['approver' => $this->getCurrentUser()->getAuthId()]),
                'occurred_at' => date('Y-m-d H:i:s')
            ]);
        });
        
        // Send notification
        $dispatcher = new JobDispatcher($this->dbal);
        $dispatcher->dispatch(LoanApprovalNotificationJob::class, [
            'application_id' => $this->application['id'],
            'decision' => 'APPROVED'
        ], 'notifications');
        
        // Trigger webhook
        $this->triggerWebhook('loan.approved', $this->application);
    }
    
    private function loadApplication(string $id): array
    {
        $row = $this->dbal->selectOne(
            'SELECT * FROM loan_applications WHERE id = ?',
            [$id]
        );
        return $row ?? [];
    }
    
    private function getRiskAssessment(): string
    {
        $creditScore = $this->application['credit_score'] ?? 0;
        
        if ($creditScore >= 750) return 'Risk: Low (Excellent Credit)';
        if ($creditScore >= 700) return 'Risk: Low-Medium (Good Credit)';
        if ($creditScore >= 650) return 'Risk: Medium (Fair Credit)';
        if ($creditScore >= 600) return 'Risk: High (Poor Credit)';
        return 'Risk: Very High (Bad Credit)';
    }
    
    private function getStatusLabel(string $stage): string
    {
        return match($stage) {
            'APPLICATION' => 'Draft - In Progress',
            'CREDIT_CHECK' => 'Credit Check - Pending',
            'REVIEW' => 'Under Review',
            'APPROVAL' => 'Awaiting Approval Decision',
            'APPROVED' => 'Approved - Loan Granted',
            'REJECTED' => 'Rejected - Application Denied',
            default => 'Unknown Status'
        };
    }
}
```

### Example 2: Repeating Grid (Dynamic Table)

**Use Case:** Order with multiple line items

```php
private function buildOrderItemsFlow(): array
{
    return [
        [
            'component_type' => 'section_header',
            'key' => 'items_header',
            'label' => 'Order Items',
            'required_permission' => null
        ],
        [
            'component_type' => 'repeating_grid',
            'key' => 'order_items',
            'label' => 'Items',
            'required_permission' => 'order:edit',
            'columns' => [
                [
                    'key' => 'product_code',
                    'label' => 'Product Code',
                    'type' => 'text_input',
                    'validation' => ['required' => true]
                ],
                [
                    'key' => 'description',
                    'label' => 'Description',
                    'type' => 'text_input',
                    'validation' => ['required' => true]
                ],
                [
                    'key' => 'quantity',
                    'label' => 'Quantity',
                    'type' => 'number_input',
                    'validation' => ['required' => true, 'min' => 1]
                ],
                [
                    'key' => 'unit_price',
                    'label' => 'Unit Price',
                    'type' => 'number_input',
                    'validation' => ['required' => true, 'min' => 0]
                ],
                [
                    'key' => 'total',
                    'label' => 'Total',
                    'type' => 'display_text',
                    'computed' => 'quantity * unit_price'
                ]
            ],
            'value' => $this->order['items'] ?? [],
            'allow_add' => true,
            'allow_delete' => true,
            'min_rows' => 1,
            'max_rows' => 50
        ],
        [
            'component_type' => 'display_text',
            'key' => 'grand_total',
            'label' => 'Grand Total',
            'value' => '$' . number_format($this->calculateGrandTotal(), 2),
            'required_permission' => null
        ]
    ];
}
```

### Example 3: Conditional Workflows

```php
public function getFlow(): array
{
    $status = $this->case['status'];
    $amount = (float)($this->case['amount'] ?? 0);
    $hasManager = $this->hasRole('MANAGER');
    
    // Different flows based on conditions
    if ($status === 'DRAFT') {
        return $this->buildDraftFlow();
    }
    
    if ($status === 'PENDING_REVIEW') {
        if ($amount > 10000 && $hasManager) {
            return $this->buildManagerReviewFlow();
        }
        return $this->buildStandardReviewFlow();
    }
    
    if ($status === 'APPROVED') {
        return $this->buildApprovedFlow();
    }
    
    return [];
}

private function buildManagerReviewFlow(): array
{
    return [
        [
            'component_type' => 'alert_banner',
            'key' => 'manager_notice',
            'label' => 'This case requires manager approval due to high value.',
            'variant' => 'warning',
            'required_permission' => null
        ],
        [
            'component_type' => 'textarea',
            'key' => 'manager_notes',
            'label' => 'Manager Notes',
            'required_permission' => 'case:approve:manager',
            'validation' => ['required' => true, 'min_length' => 50],
            'help_text' => 'Provide detailed justification for your decision'
        ],
        [
            'component_type' => 'checkbox',
            'key' => 'risk_acknowledged',
            'label' => 'I acknowledge the financial risk of this approval',
            'required_permission' => 'case:approve:manager',
            'validation' => ['required' => true]
        ],
        [
            'component_type' => 'button_primary',
            'key' => 'manager_approve',
            'label' => 'Approve (Manager)',
            'action' => 'manager_approve',
            'required_permission' => 'case:approve:manager'
        ]
    ];
}
```

---

## Advanced Patterns

### Pattern 1: ABAC (Attribute-Based Access Control)

Combine RBAC with dynamic attributes:

```php
public function preProcess(): void
{
    $case = $this->loadCase($this->getCaseId());
    $user = $this->getCurrentUser();
    
    // Check role-based permission
    if (!$this->can('case:view')) {
        throw new AuthenticationException('Unauthorized');
    }
    
    // Check attribute-based permission
    $isOwner = $case['owner_id'] === $user->getAuthId();
    $isAssignee = $this->isAssignedTo($case, $user);
    $isDepartmentMember = $this->isInDepartment($case, $user);
    
    if (!$isOwner && !$isAssignee && !$isDepartmentMember) {
        throw new AuthenticationException('You do not have access to this case');
    }
    
    // Set ABAC context for payload pruning
    $this->setAbacContext([
        'is_owner' => $isOwner,
        'is_assignee' => $isAssignee,
        'is_department_member' => $isDepartmentMember
    ]);
}

public function getFlow(): array
{
    $isOwner = $this->getAbacContext()['is_owner'] ?? false;
    
    return [
        // Only case owner can delete
        [
            'component_type' => 'button_danger',
            'key' => 'delete_case',
            'label' => 'Delete Case',
            'action' => 'delete',
            'required_permission' => $isOwner ? 'case:delete:own' : 'case:delete:any'
        ]
    ];
}
```

### Pattern 2: Multi-Stage Wizard

```php
class CustomerOnboardingDX extends DXController
{
    private const STAGES = ['CONTACT_INFO', 'PREFERENCES', 'VERIFICATION', 'COMPLETE'];
    private string $currentStage = 'CONTACT_INFO';
    
    public function preProcess(): void
    {
        $action = $this->requestData['action'] ?? 'load';
        
        if ($action === 'next_stage') {
            $this->advanceToNextStage();
        } elseif ($action === 'previous_stage') {
            $this->goToPreviousStage();
        }
        
        $this->setData([
            'stage_label' => $this->getStageLabel($this->currentStage),
            'step_number' => $this->getStepNumber($this->currentStage),
            'total_steps' => count(self::STAGES)
        ]);
    }
    
    public function getFlow(): array
    {
        $components = match($this->currentStage) {
            'CONTACT_INFO' => $this->buildContactInfoStage(),
            'PREFERENCES' => $this->buildPreferencesStage(),
            'VERIFICATION' => $this->buildVerificationStage(),
            'COMPLETE' => $this->buildCompleteStage(),
            default => []
        };
        
        // Add navigation buttons
        $components[] = $this->buildNavigationButtons();
        
        return $components;
    }
    
    private function buildNavigationButtons(): array
    {
        $currentIndex = array_search($this->currentStage, self::STAGES);
        
        return [
            'component_type' => 'button_group',
            'key' => 'navigation',
            'buttons' => [
                [
                    'component_type' => 'button_secondary',
                    'key' => 'previous',
                    'label' => 'Previous',
                    'action' => 'previous_stage',
                    'visibility_rule' => $currentIndex > 0 ? 'true' : 'false'
                ],
                [
                    'component_type' => 'button_primary',
                    'key' => 'next',
                    'label' => $currentIndex === count(self::STAGES) - 1 ? 'Finish' : 'Next',
                    'action' => 'next_stage'
                ]
            ]
        ];
    }
    
    private function advanceToNextStage(): void
    {
        $currentIndex = array_search($this->currentStage, self::STAGES);
        if ($currentIndex < count(self::STAGES) - 1) {
            $this->currentStage = self::STAGES[$currentIndex + 1];
            $this->saveStageProgress();
        }
    }
}
```

### Pattern 3: Dynamic Form Based on Configuration

```php
class ConfigurableFormDX extends DXController
{
    private array $formConfig = [];
    
    public function preProcess(): void
    {
        // Load form configuration from database
        $this->formConfig = $this->loadFormConfig($this->requestData['form_id']);
    }
    
    public function getFlow(): array
    {
        $components = [];
        
        foreach ($this->formConfig['fields'] as $field) {
            $components[] = $this->buildComponentFromConfig($field);
        }
        
        return $components;
    }
    
    private function buildComponentFromConfig(array $field): array
    {
        return [
            'component_type' => $field['type'],
            'key' => $field['key'],
            'label' => $field['label'],
            'required_permission' => $field['permission'] ?? null,
            'validation' => $field['validation'] ?? [],
            'options' => $field['options'] ?? null,
            'value' => $this->case['payload'][$field['key']] ?? null
        ];
    }
    
    private function loadFormConfig(string $formId): array
    {
        $config = $this->dbal->selectOne(
            'SELECT config_json FROM form_configurations WHERE id = ?',
            [$formId]
        );
        
        return json_decode($config['config_json'] ?? '{}', true);
    }
}
```

### Pattern 4: File Upload Handling

```php
public function preProcess(): void
{
    $action = $this->requestData['action'] ?? 'load';
    
    if ($action === 'upload_document') {
        $this->handleFileUpload();
    }
}

private function handleFileUpload(): void
{
    if (!isset($_FILES['document'])) {
        throw new ValidationException('No file uploaded', ['document' => ['File is required']]);
    }
    
    $file = $_FILES['document'];
    
    // Validate file
    $allowedTypes = ['application/pdf', 'application/msword'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new ValidationException('Invalid file type', ['document' => ['Only PDF and DOC files allowed']]);
    }
    
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        throw new ValidationException('File too large', ['document' => ['Maximum file size is 5MB']]);
    }
    
    // Save file
    $uploadDir = dirname(__DIR__, 3) . '/storage/uploads/';
    $fileName = uniqid('doc_') . '_' . basename($file['name']);
    $targetPath = $uploadDir . $fileName;
    
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new \RuntimeException('Failed to upload file');
    }
    
    // Save file reference in database
    $this->dbal->update('dx_cases', [
        'payload' => json_encode([
            'document_path' => $fileName,
            'document_original_name' => $file['name'],
            'document_uploaded_at' => date('Y-m-d H:i:s')
        ])
    ], ['id' => $this->getCaseId()]);
}
```

---

## Performance Optimization

### 1. Eager Loading Relationships

```php
private function loadCaseWithRelations(string $caseId): array
{
    // Single optimized query instead of N+1
    $result = $this->dbal->selectOne("
        SELECT 
            c.*,
            u.full_name as owner_name,
            u.email as owner_email,
            (SELECT COUNT(*) FROM dx_assignments WHERE case_id = c.id) as assignment_count,
            (SELECT COUNT(*) FROM dx_case_history WHERE case_id = c.id) as history_count
        FROM dx_cases c
        LEFT JOIN dx_users u ON c.owner_id = u.id
        WHERE c.id = ?
    ", [$caseId]);
    
    return $result ?? [];
}
```

### 2. Caching Expensive Operations

```php
private function getPermissionsForUser(string $userId): array
{
    $cacheKey = "user_permissions_{$userId}";
    
    // Check cache first
    if ($cached = $this->getFromCache($cacheKey)) {
        return $cached;
    }
    
    // Load from database
    $permissions = $this->dbal->select("
        SELECT DISTINCT p.permission_key
        FROM dx_user_roles ur
        JOIN dx_role_permissions rp ON ur.role_id = rp.role_id
        JOIN dx_permissions p ON rp.permission_id = p.id
        WHERE ur.user_id = ?
    ", [$userId]);
    
    $permissionKeys = array_column($permissions, 'permission_key');
    
    // Cache for 5 minutes
    $this->saveToCache($cacheKey, $permissionKeys, 300);
    
    return $permissionKeys;
}
```

### 3. Batch Operations

```php
private function bulkUpdateAssignments(array $assignmentIds, string $newStatus): void
{
    if (empty($assignmentIds)) {
        return;
    }
    
    $placeholders = implode(',', array_fill(0, count($assignmentIds), '?'));
    
    $this->dbal->executeStatement("
        UPDATE dx_assignments 
        SET assignment_status = ?, updated_at = ?
        WHERE id IN ($placeholders)
    ", array_merge([$newStatus, date('Y-m-d H:i:s')], $assignmentIds));
}
```

---

## Security Best Practices

### 1. Always Use Parameterized Queries

❌ **NEVER DO THIS:**
```php
// DANGEROUS - SQL Injection vulnerability!
$email = $_POST['email'];
$query = "SELECT * FROM dx_users WHERE email = '$email'";
$result = $db->executeStatement($query);
```

✅ **ALWAYS DO THIS:**
```php
// SAFE - Parameterized query
$email = $_POST['email'];
$result = $db->selectOne(
    'SELECT * FROM dx_users WHERE email = ?',
    [$email]
);
```

### 2. Validate All Input

```php
private function validateInput(array $data): void
{
    $errors = [];
    
    // Required fields
    if (empty($data['name'])) {
        $errors['name'] = ['Name is required'];
    }
    
    // Email validation
    if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = ['Valid email required'];
    }
    
    // Numeric range
    $amount = (float)($data['amount'] ?? 0);
    if ($amount < 0 || $amount > 1000000) {
        $errors['amount'] = ['Amount must be between 0 and 1,000,000'];
    }
    
    // Custom validation
    if (!$this->isValidPhoneNumber($data['phone'] ?? '')) {
        $errors['phone'] = ['Invalid phone number format'];
    }
    
    if (!empty($errors)) {
        throw new ValidationException('Validation failed', $errors);
    }
}
```

### 3. Enforce Permissions

```php
public function preProcess(): void
{
    $action = $this->requestData['action'] ?? 'load';
    
    // Check permission based on action
    $requiredPermission = match($action) {
        'create' => 'case:create',
        'update' => 'case:update',
        'delete' => 'case:delete',
        'approve' => 'case:approve',
        default => 'case:read'
    };
    
    if (!$this->can($requiredPermission)) {
        throw new AuthenticationException("Permission denied: $requiredPermission");
    }
    
    // Additional ABAC checks
    $case = $this->loadCase($this->getCaseId());
    if (!$this->canAccessCase($case)) {
        throw new AuthenticationException('You cannot access this case');
    }
}
```

### 4. Sanitize Output

```php
private function sanitizeForDisplay(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

public function preProcess(): void
{
    $customerNotes = $this->case['notes'] ?? '';
    
    $this->setData([
        'customer_notes' => $this->sanitizeForDisplay($customerNotes)
    ]);
}
```

### 5. Rate Limiting

```php
// In middleware
class RateLimitMiddleware implements MiddlewareInterface
{
    public function handle(array $request): void
    {
        $userId = $request['user_id'] ?? 'anonymous';
        $key = "rate_limit_{$userId}_" . date('i'); // Per minute
        
        $attempts = (int)($this->cache->get($key) ?? 0);
        
        if ($attempts > 60) { // Max 60 requests per minute
            http_response_code(429);
            echo json_encode(['error' => 'Rate limit exceeded']);
            exit;
        }
        
        $this->cache->set($key, $attempts + 1, 60);
    }
}
```

---

## Troubleshooting

### Common Issues

#### Issue: "If-Match header is required for updates"

**Cause:** Frontend not sending eTag on update requests

**Solution:**
```javascript
// Ensure DXInterpreter stores and sends eTag
DX.Interpreter.init({
    containerId: 'dx-container',
    dxId: 'my_dx',
    caseId: 'case-123',
    initialETag: '<?= $initialETag ?>' // Pass from server
});
```

#### Issue: "Components not rendering"

**Cause:** ComponentRegistry doesn't recognize component_type

**Solution:** Register custom component in ComponentRegistry.js:
```javascript
DX.ComponentRegistry.register('custom_widget', (component, state) => {
    return `<div class="custom-widget">${component.label}</div>`;
});
```

#### Issue: "Permission denied"

**Cause:** User lacks required permission or payload pruning removed component

**Solution:** Check user permissions in database:
```sql
SELECT DISTINCT p.permission_key
FROM dx_user_roles ur
JOIN dx_role_permissions rp ON ur.role_id = rp.role_id
JOIN dx_permissions p ON rp.permission_id = p.id
WHERE ur.user_id = 'user-123';
```

#### Issue: "ETag mismatch / Concurrent modification"

**Cause:** Case was updated by another user/session

**Solution:** This is expected behavior. User sees modal to refresh and reload latest data.

#### Issue: "Foreign key constraint failed"

**Cause:** SQLite foreign keys not enabled

**Solution:** Enable in database connection:
```php
$db->executeStatement('PRAGMA foreign_keys = ON');
```

---

## Best Practices

### 1. Keep Controllers Focused

```php
// ❌ BAD - Too much logic in one controller
class MegaControllerDX extends DXController { ... }

// ✅ GOOD - Separate controllers per workflow
class CustomerIntakeDX extends DXController { ... }
class OrderProcessingDX extends DXController { ... }
class InvoiceGenerationDX extends DXController { ... }
```

### 2. Use Product Info Everywhere

```php
// ❌ BAD - Raw codes
$this->setData([
    'status' => 'PEND_APPR',
    'priority' => 'P1'
]);

// ✅ GOOD - Formatted, human-readable
$this->setData([
    'status' => 'Status: Pending Approval',
    'priority' => 'Priority: High (P1)'
]);
```

### 3. Leverage Transactions

```php
// ✅ GOOD - Atomic operations
$this->dbal->transactional(function() {
    $this->dbal->insert('dx_cases', $caseData);
    $this->dbal->insert('dx_assignments', $assignmentData);
    $this->dbal->insert('dx_case_history', $historyData);
});
```

### 4. Log Important Events

```php
private function logCaseApproval(string $caseId, string $approverId): void
{
    $this->dbal->insert('dx_case_history', [
        'id' => 'hist-' . uniqid(),
        'case_id' => $caseId,
        'actor_id' => $approverId,
        'action' => 'CASE_APPROVED',
        'from_status' => 'PENDING',
        'to_status' => 'APPROVED',
        'details' => json_encode([
            'approver_name' => $this->getCurrentUser()->getFullName(),
            'approved_at' => date('Y-m-d H:i:s'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]),
        'occurred_at' => date('Y-m-d H:i:s')
    ]);
}
```

---

## Conclusion

DX Engine is a powerful, opinionated framework for building enterprise case management systems. By following the **center-out mandate** and the **10 architectural axioms**, you can build secure, scalable, maintainable applications with minimal frontend complexity.

The framework handles:
- ✅ UI structure and rendering
- ✅ Permission enforcement
- ✅ Optimistic locking
- ✅ Database abstraction
- ✅ Job queue processing
- ✅ Webhook integration
- ✅ Audit trail

You focus on:
- 🎯 Business logic in DX Controllers
- 🎯 Data models and validations
- 🎯 Workflow definitions

**Happy coding!** 🚀

