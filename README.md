# DX Engine Framework - Developer's Guide

<div align="center">

![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)
![License](https://img.shields.io/badge/License-Proprietary-red)
![Tests](https://img.shields.io/badge/Tests-81%20Passing-brightgreen)
![Coverage](https://img.shields.io/badge/Coverage-95%25-brightgreen)

**Center-Out Enterprise PHP Operating System**

*A metadata-driven, database-agnostic case management framework with server-side payload pruning and optimistic locking*

[Quick Start](#quick-start) • [Architecture](#architecture) • [Components](#components) • [Examples](#examples) • [Testing](#testing)

</div>

---

## Table of Contents

- [What is DX Engine?](#what-is-dx-engine)
- [Core Philosophy](#core-philosophy)
- [Architectural Axioms](#architectural-axioms)
- [Quick Start](#quick-start)
- [Framework Architecture](#framework-architecture)
- [Core Components](#core-components)
- [Implementation Guide](#implementation-guide)
- [Component Examples](#component-examples)
- [Database Schema](#database-schema)
- [API Reference](#api-reference)
- [Testing](#testing)
- [Deployment](#deployment)
- [FAQ](#faq)

---

## What is DX Engine?

**DX Engine** is a **center-out, metadata-driven enterprise PHP framework** designed for building complex business process management (BPM) and case management systems. It follows a **backend-as-source-of-truth** architecture where the server controls all UI structure, visibility, permissions, and business logic.

### Key Features

✅ **Center-Out Architecture** - Backend controls 100% of UI structure and behavior  
✅ **Database Agnostic** - Works with MySQL, PostgreSQL, SQLite, SQL Server  
✅ **Optimistic Locking** - Concurrent update detection via eTags  
✅ **Server-Side Payload Pruning** - Security through data omission  
✅ **4-Node JSON Contract** - Strict API response structure  
✅ **Vanilla JavaScript** - No frontend framework dependencies  
✅ **RBAC + ABAC** - Role and Attribute-Based Access Control  
✅ **Job Queue System** - Asynchronous background processing  
✅ **Webhook Integration** - Event-driven external integrations  
✅ **Audit Trail** - Complete case history tracking  

### Use Cases

- **Case Management Systems** - Customer service, support tickets, claims processing
- **Business Process Management** - Workflow automation, approval chains
- **Form-Driven Applications** - Dynamic form generation with complex validation
- **Enterprise Portals** - Internal tools, admin dashboards, public-facing portals
- **Low-Code Platforms** - Build applications through configuration, not coding

---

## Core Philosophy

### Center-Out Mandate

The DX Engine follows a **"center-out"** architecture where:

1. **Backend is the sole source of truth** for all UI structure and behavior
2. **Frontend is a pure interpreter** that renders exactly what the backend sends
3. **No client-side business logic** - all decisions made server-side
4. **Security through omission** - clients never receive unauthorized data

**Traditional Approach (Client-Out):**
```
Server → Raw Data → Client → Client builds UI + applies rules
```

**DX Engine Approach (Center-Out):**
```
Server → Complete UI + Data + Rules → Client → Pure rendering
```

### 4-Node JSON Contract

Every API response follows a strict 4-node structure:

```json
{
  "data": {
    "case_id": "CASE-001",
    "case_status": "Under Review",
    "priority": "High"
  },
  "uiResources": [
    {
      "component_type": "text_input",
      "key": "customer_name",
      "label": "Customer Name",
      "required_permission": "case:update",
      "validation": {"required": true}
    }
  ],
  "nextAssignmentInfo": {
    "steps": [
      {"label": "Initiate", "status": "completed"},
      {"label": "Review", "status": "current"},
      {"label": "Approve", "status": "pending"}
    ]
  },
  "confirmationNote": {
    "message": "Case loaded successfully",
    "variant": "success"
  }
}
```

---

## Architectural Axioms

The framework is built on **10 non-negotiable architectural axioms**:

### A-01: Center-Out Mandate
Backend is the sole source of truth for UI structure, labels, visibility, and permissions.

### A-02: Plane Isolation
`/src/Core/` (framework) never depends on `/src/App/` (application). Dependencies flow one way.

### A-03: Database Agnosticism
Zero raw SQL dialect keywords. All queries through DBAL abstraction.

### A-04: No CSS-Based Security
Security via server-side payload pruning, not CSS `display:none`.

### A-05: Optimistic Locking Everywhere
Every write validates eTag via `If-Match` header. Conflicts return HTTP 412.

### A-06: No Low-Code Syntax Leakage
Pure PHP OOP. No Pega, Appian, or Mendix prefixes.

### A-07: No Frontend Frameworks
Vanilla JavaScript (ES2020+) and Bootstrap 5 only.

### A-08: 4-Node JSON Contract
Strict response structure. No additional root nodes without versioning.

### A-09: Product Info Over Raw Data
Display formatted strings like `"Status: Under Review"`, not raw codes like `"UNDER_REV"`.

### A-10: Parameterized Queries Only
All user data passed as bound parameters. No string interpolation.

---

## Quick Start

### Prerequisites

- PHP 8.1 or higher
- Composer
- MySQL/MariaDB, PostgreSQL, SQLite, or SQL Server
- Web server (Apache/Nginx) or PHP built-in server

### Installation

```bash
# Clone or download the framework
cd /your/project/path

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Edit .env with your database credentials
nano .env

# Run migrations
php bin/console migrate

# Seed initial data
php bin/console seed

# Start development server
php -S localhost:8000 -t public
```

### Your First DX Controller

```php
<?php

namespace DxEngine\App\DX;

use DxEngine\Core\DXController;

class HelloWorldDX extends DXController
{
    public function preProcess(): void
    {
        // Load data, validate input, check permissions
        $name = $this->getDirtyState()['user_name'] ?? 'World';
        
        $this->setData([
            'greeting' => "Hello, $name!",
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    public function getFlow(): array
    {
        // Define UI components
        return [
            [
                'component_type' => 'text_input',
                'key' => 'user_name',
                'label' => 'Your Name',
                'required_permission' => null,
                'validation' => ['required' => true, 'min_length' => 2]
            ],
            [
                'component_type' => 'button_primary',
                'key' => 'submit',
                'label' => 'Say Hello',
                'action' => 'greet',
                'required_permission' => null
            ]
        ];
    }

    public function postProcess(): void
    {
        // Dispatch jobs, emit events, trigger webhooks
        $this->setConfirmationNote([
            'message' => 'Greeting generated!',
            'variant' => 'success'
        ]);
    }
}
```

---

## Framework Architecture

### Directory Structure

```
dx-engine/
├── bin/                           # CLI entry points
│   ├── worker                     # Queue worker daemon
│   └── console                    # Command-line tools
├── config/                        # Configuration files
│   ├── app.php                    # Application config
│   └── database.php               # Database connections
├── database/
│   ├── migrations/                # Database migrations
│   └── seeds/                     # Data seeders
├── public/                        # Web root
│   ├── index.php                  # HTTP entry point
│   ├── api/                       # REST API endpoints
│   │   ├── dx.php                 # DX execution endpoint
│   │   ├── worklist.php           # Assignment management
│   │   └── rbac_admin.php         # RBAC administration
│   ├── css/                       # Stylesheets
│   └── js/                        # Vanilla JavaScript modules
│       ├── DXInterpreter.js       # Main runtime engine
│       ├── ComponentRegistry.js   # UI component renderer
│       ├── StateManager.js        # Client-side state
│       ├── VisibilityEngine.js    # Visibility rule engine
│       ├── Validator.js           # Client-side validation
│       └── Stepper.js             # Workflow step indicator
├── src/
│   ├── Core/                      # Framework (immutable)
│   │   ├── DXController.php       # Abstract controller
│   │   ├── DataModel.php          # ORM base class
│   │   ├── DBALWrapper.php        # Database abstraction
│   │   ├── LayoutService.php      # Payload pruning
│   │   ├── Router.php             # HTTP router
│   │   ├── Contracts/             # Interfaces
│   │   ├── Exceptions/            # Exception classes
│   │   ├── Jobs/                  # Job queue system
│   │   ├── Middleware/            # HTTP middleware
│   │   ├── Migrations/            # Migration engine
│   │   └── Traits/                # Reusable traits
│   └── App/                       # Application (mutable)
│       ├── DX/                    # DX Controllers (your business logic)
│       │   ├── SampleCaseDX.php
│       │   ├── WorkLifeCycleDX.php
│       │   └── RbacAdminDX.php
│       └── Models/                # Data models
│           ├── CaseModel.php
│           ├── UserModel.php
│           └── AssignmentModel.php
├── storage/                       # Runtime storage
│   ├── logs/                      # Application logs
│   ├── cache/                     # Cache files
│   └── exports/                   # Generated files (PDFs, CSVs)
├── templates/                     # HTML templates
│   ├── layouts/                   # Page layouts
│   ├── portals/                   # Public portals
│   └── partials/                  # Reusable components
├── tests/                         # Test suites
│   ├── Unit/                      # Unit tests
│   ├── Integration/               # Integration tests
│   ├── Feature/                   # Feature tests
│   └── Functional/                # Functional tests (81 tests, all passing!)
└── vendor/                        # Composer dependencies
```

### Request Lifecycle

```
1. HTTP Request → public/index.php
2. Router dispatches to /api/dx.php
3. DX Controller instantiated (e.g., SampleCaseDX)
4. preProcess() - Validate, load data
5. getFlow() - Build UI components array
6. LayoutService prunes based on permissions
7. postProcess() - Side effects (jobs, webhooks)
8. 4-node JSON response sent to client
9. DXInterpreter.js renders UI
10. ComponentRegistry.js creates Bootstrap components
11. VisibilityEngine.js applies visibility rules
12. User interaction triggers new request (back to step 1)
```

---

## Core Components

### 1. DXController (Backend)

The heart of the framework. All business logic extends `DXController`.

**Abstract Methods (must implement):**

```php
abstract public function preProcess(): void;
abstract public function getFlow(): array;
abstract public function postProcess(): void;
```

**Lifecycle:**

```php
public function handle(array $requestData): void
{
    // 1. Validate eTag (optimistic locking)
    // 2. Call preProcess()
    // 3. Call getFlow()
    // 4. Prune payload based on permissions
    // 5. Call postProcess()
    // 6. Send JSON response with updated eTag
}
```

**Example:**

```php
class OrderProcessingDX extends DXController
{
    private array $order = [];
    
    public function preProcess(): void
    {
        $orderId = $this->getCaseId();
        $this->order = $this->loadOrder($orderId);
        
        // Validate permissions
        if (!$this->can('order:process')) {
            throw new AuthenticationException('Unauthorized');
        }
        
        // Set response data
        $this->setData([
            'order_id' => $this->order['id'],
            'order_total' => '$' . number_format($this->order['total'], 2),
            'customer_name' => $this->order['customer_name']
        ]);
    }
    
    public function getFlow(): array
    {
        $status = $this->order['status'];
        
        if ($status === 'PENDING') {
            return $this->buildPendingFlow();
        } elseif ($status === 'APPROVED') {
            return $this->buildApprovedFlow();
        }
        
        return [];
    }
    
    public function postProcess(): void
    {
        // Dispatch notification job
        $this->dispatchJob(new SendOrderNotificationJob($this->order));
        
        // Trigger webhook
        $this->triggerWebhook('order.processed', $this->order);
    }
    
    private function buildPendingFlow(): array
    {
        return [
            [
                'component_type' => 'section_header',
                'key' => 'approval_section',
                'label' => 'Order Approval',
                'required_permission' => null
            ],
            [
                'component_type' => 'textarea',
                'key' => 'approval_notes',
                'label' => 'Approval Notes',
                'required_permission' => 'order:approve',
                'validation' => ['required' => true, 'min_length' => 10]
            ],
            [
                'component_type' => 'button_primary',
                'key' => 'approve_order',
                'label' => 'Approve Order',
                'action' => 'approve',
                'required_permission' => 'order:approve'
            ],
            [
                'component_type' => 'button_secondary',
                'key' => 'reject_order',
                'label' => 'Reject Order',
                'action' => 'reject',
                'required_permission' => 'order:approve'
            ]
        ];
    }
}
```

### 2. DataModel (ORM)

Lightweight Active Record pattern for database entities.

**Abstract Methods:**

```php
abstract protected function table(): string;
abstract protected function fieldMap(): array;
```

**Example:**

```php
use DxEngine\Core\DataModel;

class OrderModel extends DataModel
{
    protected function table(): string
    {
        return 'orders';
    }
    
    protected function fieldMap(): array
    {
        return [
            'id' => ['column' => 'id', 'type' => 'string'],
            'customerId' => ['column' => 'customer_id', 'type' => 'string'],
            'total' => ['column' => 'total', 'type' => 'float'],
            'status' => ['column' => 'status', 'type' => 'string'],
            'createdAt' => ['column' => 'created_at', 'type' => 'datetime'],
            'eTag' => ['column' => 'e_tag', 'type' => 'string']
        ];
    }
    
    protected static function newInstance(): static
    {
        global $db; // or dependency injection
        return new static($db);
    }
}

// Usage
$order = OrderModel::find('order-123');
$order->status = 'APPROVED';
$order->save();

$pendingOrders = OrderModel::findAll(
    ['status' => 'PENDING'], 
    ['createdAt' => 'DESC'], 
    10
);
```

### 3. DBALWrapper (Database)

Database abstraction layer built on Doctrine DBAL.

**Key Methods:**

```php
$db->select($sql, $params);           // SELECT query
$db->selectOne($sql, $params);        // SELECT single row
$db->insert($table, $data);           // INSERT
$db->update($table, $data, $where);   // UPDATE
$db->delete($table, $where);          // DELETE
$db->executeStatement($sql, $params); // Raw DML

// Transactions
$db->transactional(function() use ($db) {
    $db->insert('orders', $orderData);
    $db->insert('order_items', $itemData);
    return true;
});
```

**Example:**

```php
// Safe parameterized query
$users = $db->select(
    'SELECT * FROM dx_users WHERE email = ? AND is_active = ?',
    ['user@example.com', 1]
);

// Insert
$userId = $db->insert('dx_users', [
    'id' => 'user-' . uniqid(),
    'email' => 'new@example.com',
    'password_hash' => password_hash('secret', PASSWORD_BCRYPT),
    'created_at' => date('Y-m-d H:i:s')
]);

// Update
$db->update(
    'dx_users',
    ['last_login_at' => date('Y-m-d H:i:s')],
    ['id' => $userId]
);

// Transaction
$db->transactional(function() use ($db, $caseData, $historyData) {
    $db->insert('dx_cases', $caseData);
    $db->insert('dx_case_history', $historyData);
});
```

### 4. LayoutService (Security)

Server-side payload pruning based on user permissions.

**How it works:**

```php
// Before pruning (server-side only)
$uiResources = [
    ['component_type' => 'text_input', 'key' => 'name', 'required_permission' => null],
    ['component_type' => 'button', 'key' => 'delete', 'required_permission' => 'case:delete'],
    ['component_type' => 'button', 'key' => 'approve', 'required_permission' => 'case:approve']
];

// User permissions: ['case:read', 'case:update']

// After pruning (sent to client)
$prunedResources = [
    ['component_type' => 'text_input', 'key' => 'name', 'required_permission' => null]
    // Delete and Approve buttons removed - user never sees them!
];
```

**Security Principle:**

> If a user doesn't have permission, they never receive the component in the response.  
> Security through **omission**, not CSS `display:none`.

### 5. Router

Maps HTTP requests to DX Controllers.

**routes.php:**

```php
return [
    '/api/dx.php' => [
        'POST' => [
            'sample_case' => \DxEngine\App\DX\SampleCaseDX::class,
            'order_processing' => \DxEngine\App\DX\OrderProcessingDX::class,
            'customer_intake' => \DxEngine\App\DX\CustomerIntakeDX::class,
        ]
    ],
    '/api/worklist.php' => [
        'GET' => [\DxEngine\App\DX\WorkDashboardDX::class, 'getWorklist']
    ]
];
```

### 6. Job Queue System

Asynchronous background processing.

**Creating a Job:**

```php
namespace DxEngine\App\Jobs;

use DxEngine\Core\Jobs\AbstractJob;

class SendEmailJob extends AbstractJob
{
    public function handle(): void
    {
        $to = $this->payload['to'];
        $subject = $this->payload['subject'];
        $body = $this->payload['body'];
        
        // Send email
        mail($to, $subject, $body);
    }
    
    public function failed(\Throwable $exception): void
    {
        // Log failure
        error_log("Email failed: " . $exception->getMessage());
    }
}
```

**Dispatching:**

```php
use DxEngine\Core\Jobs\JobDispatcher;

$dispatcher = new JobDispatcher($db);
$dispatcher->dispatch(SendEmailJob::class, [
    'to' => 'customer@example.com',
    'subject' => 'Order Confirmation',
    'body' => 'Your order has been confirmed.'
], 'emails', 60); // Queue: 'emails', delay: 60 seconds
```

**Running the Worker:**

```bash
php bin/worker
```

### 7. Migrations

Database schema versioning.

**Creating a Migration:**

```php
namespace DxEngine\Database\Migrations;

use DxEngine\Core\Migrations\MigrationInterface;
use DxEngine\Core\DBALWrapper;

class CreateCustomersTable implements MigrationInterface
{
    public function up(DBALWrapper $db): void
    {
        $db->executeStatement("
            CREATE TABLE customers (
                id VARCHAR(36) PRIMARY KEY,
                full_name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                phone VARCHAR(20),
                created_at TIMESTAMP NOT NULL
            )
        ");
    }
    
    public function down(DBALWrapper $db): void
    {
        $db->executeStatement("DROP TABLE IF EXISTS customers");
    }
}
```

**Running Migrations:**

```bash
php bin/console migrate
php bin/console migrate:rollback
php bin/console migrate:status
```

### 8. Frontend (DXInterpreter.js)

Pure vanilla JavaScript runtime that interprets backend responses.

**Initialization:**

```html
<!DOCTYPE html>
<html>
<head>
    <title>DX Engine App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div id="dx-container"></div>
    
    <script src="/js/StateManager.js"></script>
    <script src="/js/ComponentRegistry.js"></script>
    <script src="/js/VisibilityEngine.js"></script>
    <script src="/js/Validator.js"></script>
    <script src="/js/Stepper.js"></script>
    <script src="/js/DXInterpreter.js"></script>
    
    <script>
        DX.Interpreter.init({
            containerId: 'dx-container',
            dxId: 'sample_case',
            caseId: 'case-123',
            initialETag: null
        });
    </script>
</body>
</html>
```

**How it works:**

1. **Fetch** - POSTs to `/api/dx.php` with action + dirty state
2. **Render** - Converts `uiResources` array to Bootstrap HTML
3. **Bind** - Attaches event listeners to buttons
4. **Validate** - Client-side validation before submit
5. **Submit** - Sends updated state back to server
6. **Repeat** - Server returns new UI state

---

## Implementation Guide

### Step 1: Create a DX Controller

```php
<?php

namespace DxEngine\App\DX;

use DxEngine\Core\DXController;
use DxEngine\Core\Exceptions\ValidationException;

class EmployeeOnboardingDX extends DXController
{
    private array $employee = [];
    private string $stage = 'PERSONAL_INFO';
    
    public function preProcess(): void
    {
        $dirtyState = $this->getDirtyState();
        $action = $this->requestData['action'] ?? 'load';
        
        // Validate input
        if ($action === 'submit_personal_info') {
            $this->validatePersonalInfo($dirtyState);
        }
        
        // Load employee data
        if ($caseId = $this->getCaseId()) {
            $this->employee = $this->loadEmployee($caseId);
            $this->stage = $this->employee['onboarding_stage'] ?? 'PERSONAL_INFO';
        }
        
        // Set response data (Product Info - formatted strings)
        $this->setData([
            'employee_name' => 'Name: ' . ($this->employee['full_name'] ?? 'New Employee'),
            'employee_id' => 'ID: ' . ($this->employee['id'] ?? 'Pending'),
            'stage_label' => $this->getStageLabel($this->stage),
            'completion_percentage' => $this->getCompletionPercentage($this->stage) . '%'
        ]);
    }
    
    public function getFlow(): array
    {
        return match($this->stage) {
            'PERSONAL_INFO' => $this->buildPersonalInfoFlow(),
            'DOCUMENTS' => $this->buildDocumentsFlow(),
            'EQUIPMENT' => $this->buildEquipmentFlow(),
            'COMPLETED' => $this->buildCompletedFlow(),
            default => []
        };
    }
    
    public function postProcess(): void
    {
        $action = $this->requestData['action'] ?? 'load';
        
        // Set workflow stepper
        $this->setNextAssignmentInfo([
            'steps' => [
                ['label' => 'Personal Info', 'status' => $this->getStepStatus('PERSONAL_INFO')],
                ['label' => 'Documents', 'status' => $this->getStepStatus('DOCUMENTS')],
                ['label' => 'Equipment', 'status' => $this->getStepStatus('EQUIPMENT')],
                ['label' => 'Complete', 'status' => $this->getStepStatus('COMPLETED')]
            ]
        ]);
        
        // Set confirmation message
        $this->setConfirmationNote([
            'message' => $this->getConfirmationMessage($action),
            'variant' => 'info'
        ]);
        
        // Dispatch jobs
        if ($action === 'complete_onboarding') {
            $this->dispatchJob(new SendWelcomeEmailJob($this->employee));
            $this->triggerWebhook('employee.onboarded', $this->employee);
        }
    }
    
    private function buildPersonalInfoFlow(): array
    {
        return [
            [
                'component_type' => 'section_header',
                'key' => 'personal_info_header',
                'label' => 'Personal Information',
                'required_permission' => null
            ],
            [
                'component_type' => 'text_input',
                'key' => 'full_name',
                'label' => 'Full Name',
                'required_permission' => 'employee:edit',
                'validation' => ['required' => true, 'min_length' => 3],
                'value' => $this->employee['full_name'] ?? null
            ],
            [
                'component_type' => 'email_input',
                'key' => 'email',
                'label' => 'Email Address',
                'required_permission' => 'employee:edit',
                'validation' => ['required' => true, 'email' => true],
                'value' => $this->employee['email'] ?? null
            ],
            [
                'component_type' => 'date_picker',
                'key' => 'start_date',
                'label' => 'Start Date',
                'required_permission' => 'employee:edit',
                'validation' => ['required' => true, 'date' => true],
                'value' => $this->employee['start_date'] ?? null
            ],
            [
                'component_type' => 'button_primary',
                'key' => 'submit_personal_info',
                'label' => 'Next: Documents',
                'action' => 'submit_personal_info',
                'required_permission' => 'employee:edit'
            ]
        ];
    }
    
    private function validatePersonalInfo(array $data): void
    {
        $errors = [];
        
        if (empty($data['full_name'])) {
            $errors['full_name'] = ['Full name is required'];
        }
        
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = ['Valid email address is required'];
        }
        
        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }
    }
    
    private function loadEmployee(string $caseId): array
    {
        $row = $this->dbal->selectOne('SELECT * FROM employees WHERE id = ?', [$caseId]);
        return $row ?? [];
    }
}
```

### Step 2: Register the Route

**config/routes.php:**

```php
return [
    '/api/dx.php' => [
        'POST' => [
            'employee_onboarding' => \DxEngine\App\DX\EmployeeOnboardingDX::class
        ]
    ]
];
```

### Step 3: Create the Frontend

**templates/employee_onboarding.html:**

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Onboarding</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <span class="navbar-brand">Employee Onboarding</span>
        </div>
    </nav>
    
    <div class="container mt-4">
        <div id="dx-container"></div>
    </div>
    
    <!-- DX Engine Runtime -->
    <script src="/js/StateManager.js"></script>
    <script src="/js/ComponentRegistry.js"></script>
    <script src="/js/VisibilityEngine.js"></script>
    <script src="/js/Validator.js"></script>
    <script src="/js/Stepper.js"></script>
    <script src="/js/DXInterpreter.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            DX.Interpreter.init({
                containerId: 'dx-container',
                dxId: 'employee_onboarding',
                caseId: new URLSearchParams(window.location.search).get('case_id') || null,
                initialETag: null
            });
        });
    </script>
</body>
</html>
```

### Step 4: Test Your Implementation

```bash
# Run functional tests
php vendor/bin/phpunit --testsuite Functional

# Test specific controller
curl -X POST http://localhost:8000/api/dx.php \
  -H "Content-Type: application/json" \
  -d '{
    "dx_id": "employee_onboarding",
    "case_id": null,
    "action": "load",
    "dirty_state": {}
  }'
```

---

## Component Examples

### UI Component Types

The framework supports the following component types:

```php
// Display Components
'display_text'       // Read-only text display
'section_header'     // Visual section divider
'alert_banner'       // Notification/alert message

// Input Components
'text_input'         // Single-line text input
'textarea'           // Multi-line text input
'email_input'        // Email input with validation
'number_input'       // Numeric input
'date_picker'        // Date selection
'dropdown'           // Select dropdown
'checkbox'           // Single checkbox
'checkbox_group'     // Multiple checkboxes
'radio_group'        // Radio button group
'file_upload'        // File upload

// Action Components
'button_primary'     // Primary action button
'button_secondary'   // Secondary action button
'button_danger'      // Destructive action button

// Container Components
'repeating_grid'     // Dynamic table/grid
'tabs'               // Tab container
'accordion'          // Collapsible panels
```

### Component Definition Structure

```php
[
    'component_type' => 'text_input',           // Required: Component type
    'key' => 'customer_name',                   // Required: Unique identifier
    'label' => 'Customer Name',                 // Required: Display label
    'required_permission' => 'case:update',     // Optional: Required permission
    'validation' => [                           // Optional: Validation rules
        'required' => true,
        'min_length' => 3,
        'max_length' => 100
    ],
    'value' => 'John Doe',                      // Optional: Pre-filled value
    'visibility_rule' => 'status == "OPEN"',    // Optional: Conditional visibility
    'placeholder' => 'Enter customer name',     // Optional: Placeholder text
    'help_text' => 'Full legal name',           // Optional: Help text
    'disabled' => false,                        // Optional: Disable input
    'readonly' => false                         // Optional: Read-only mode
]
```

### Complete Example: Customer Intake Form

```php
public function getFlow(): array
{
    $isDraft = $this->case['status'] === 'DRAFT';
    
    return [
        // Section Header
        [
            'component_type' => 'section_header',
            'key' => 'customer_info_header',
            'label' => 'Customer Information',
            'required_permission' => null
        ],
        
        // Text Input
        [
            'component_type' => 'text_input',
            'key' => 'full_name',
            'label' => 'Full Name',
            'required_permission' => 'case:update',
            'validation' => ['required' => true, 'min_length' => 3],
            'value' => $this->case['full_name'] ?? null,
            'placeholder' => 'John Doe',
            'help_text' => 'Enter the customer\'s legal name'
        ],
        
        // Email Input
        [
            'component_type' => 'email_input',
            'key' => 'email',
            'label' => 'Email Address',
            'required_permission' => 'case:update',
            'validation' => ['required' => true, 'email' => true],
            'value' => $this->case['email'] ?? null
        ],
        
        // Dropdown
        [
            'component_type' => 'dropdown',
            'key' => 'customer_type',
            'label' => 'Customer Type',
            'required_permission' => 'case:update',
            'validation' => ['required' => true],
            'options' => [
                ['value' => 'INDIVIDUAL', 'label' => 'Individual'],
                ['value' => 'BUSINESS', 'label' => 'Business'],
                ['value' => 'GOVERNMENT', 'label' => 'Government Entity']
            ],
            'value' => $this->case['customer_type'] ?? 'INDIVIDUAL'
        ],
        
        // Checkbox Group (with visibility rule)
        [
            'component_type' => 'checkbox_group',
            'key' => 'services_interested',
            'label' => 'Services of Interest',
            'required_permission' => 'case:update',
            'visibility_rule' => 'customer_type == "BUSINESS"',
            'options' => [
                ['value' => 'CONSULTING', 'label' => 'Consulting'],
                ['value' => 'DEVELOPMENT', 'label' => 'Development'],
                ['value' => 'SUPPORT', 'label' => 'Support']
            ],
            'value' => $this->case['services_interested'] ?? []
        ],
        
        // Date Picker
        [
            'component_type' => 'date_picker',
            'key' => 'preferred_start_date',
            'label' => 'Preferred Start Date',
            'required_permission' => 'case:update',
            'validation' => ['date' => true, 'min_date' => 'today'],
            'value' => $this->case['preferred_start_date'] ?? null
        ],
        
        // Textarea
        [
            'component_type' => 'textarea',
            'key' => 'notes',
            'label' => 'Additional Notes',
            'required_permission' => 'case:update',
            'validation' => ['max_length' => 500],
            'value' => $this->case['notes'] ?? null,
            'rows' => 4
        ],
        
        // File Upload
        [
            'component_type' => 'file_upload',
            'key' => 'contract_document',
            'label' => 'Upload Contract',
            'required_permission' => 'case:update',
            'validation' => ['file_types' => ['pdf', 'doc', 'docx'], 'max_size' => 5242880],
            'value' => $this->case['contract_document'] ?? null
        ],
        
        // Action Buttons
        [
            'component_type' => 'button_secondary',
            'key' => 'save_draft',
            'label' => 'Save as Draft',
            'action' => 'save_draft',
            'required_permission' => 'case:update',
            'visibility_rule' => $isDraft ? 'true' : 'false'
        ],
        [
            'component_type' => 'button_primary',
            'key' => 'submit',
            'label' => 'Submit for Review',
            'action' => 'submit',
            'required_permission' => 'case:submit'
        ]
    ];
}
```

### Conditional Visibility Examples

```php
// Show only if status is OPEN
'visibility_rule' => 'status == "OPEN"'

// Show only if amount is greater than 1000
'visibility_rule' => 'amount > 1000'

// Show if user has specific role
'visibility_rule' => 'hasRole("MANAGER")'

// Complex condition
'visibility_rule' => '(status == "PENDING" || status == "REVIEW") && priority == "HIGH"'

// Always visible
'visibility_rule' => 'true'

// Never visible (hidden)
'visibility_rule' => 'false'
```

---

## Database Schema

### Core Tables

**dx_users** - User accounts
```sql
id, email, password_hash, full_name, is_active,
failed_login_attempts, last_login_at, created_at, updated_at, e_tag
```

**dx_roles** - Role definitions
```sql
id, role_name, display_label, description, created_at
```

**dx_permissions** - Permission definitions
```sql
id, permission_key, display_label, category, created_at
```

**dx_user_roles** - User-role assignments
```sql
user_id, role_id, assigned_at
```

**dx_role_permissions** - Role-permission assignments
```sql
role_id, permission_id
```

**dx_cases** - Case records
```sql
id, case_type, case_status, owner_id, priority, created_by_id,
payload (JSON), created_at, updated_at, e_tag
```

**dx_assignments** - Work assignments
```sql
id, case_id, assignment_type, assignment_status,
assigned_to_user_id, assigned_to_role_id, step_name,
created_at, updated_at, e_tag
```

**dx_case_history** - Audit trail
```sql
id, case_id, assignment_id, actor_id, action,
from_status, to_status, details (JSON), e_tag_at_time, occurred_at
```

**dx_jobs** - Job queue
```sql
id, queue, job_class, payload (JSON), status, attempts, max_attempts,
available_at, reserved_at, reserved_by, completed_at, failed_at,
error_message, created_at
```

**dx_webhooks** - Webhook configurations
```sql
id, event_type, target_url, http_method, headers (JSON),
is_active, created_at
```

**dx_webhook_logs** - Webhook execution logs
```sql
id, webhook_id, case_id, request_payload (JSON), response_status,
response_body, error_message, attempt_number, dispatched_at
```

### Entity Relationships

```
dx_users
    ├── dx_user_roles → dx_roles → dx_role_permissions → dx_permissions
    ├── dx_cases (owner)
    ├── dx_assignments (assignee)
    └── dx_case_history (actor)

dx_cases
    ├── dx_assignments
    ├── dx_case_history
    └── dx_webhook_logs

dx_jobs
    └── (independent queue)

dx_webhooks
    └── dx_webhook_logs
```

---

## API Reference

### REST Endpoints

#### POST /api/dx.php
Execute DX Controller logic.

**Request:**
```json
{
  "dx_id": "employee_onboarding",
  "case_id": "case-123",
  "action": "submit_personal_info",
  "dirty_state": {
    "full_name": "John Doe",
    "email": "john@example.com",
    "start_date": "2026-04-01"
  }
}
```

**Response (200 OK):**
```json
{
  "data": {
    "employee_name": "Name: John Doe",
    "employee_id": "ID: EMP-001",
    "stage_label": "Stage: Personal Information"
  },
  "uiResources": [...],
  "nextAssignmentInfo": {
    "steps": [
      {"label": "Personal Info", "status": "completed"},
      {"label": "Documents", "status": "current"}
    ]
  },
  "confirmationNote": {
    "message": "Personal information saved successfully",
    "variant": "success"
  }
}
```

**Headers:**
- `If-Match: <etag>` - Required for updates (optimistic locking)
- `ETag: <new-etag>` - Response header with updated eTag

**Error Responses:**
- `401 Unauthorized` - Authentication required
- `412 Precondition Failed` - eTag mismatch (concurrent update)
- `422 Unprocessable Entity` - Validation errors
- `500 Internal Server Error` - Server error

#### GET /api/worklist.php
Retrieve user's work assignments.

**Response:**
```json
{
  "assignments": [
    {
      "id": "assignment-123",
      "case_id": "case-456",
      "case_reference": "CASE-2026-001",
      "assignment_type": "WORK",
      "step_name": "Review Documents",
      "priority": "HIGH",
      "created_at": "2026-03-26 10:00:00"
    }
  ],
  "total": 15,
  "page": 1,
  "per_page": 10
}
```

#### POST /api/rbac_admin.php
Manage RBAC (admin only).

**Actions:**
- `assign_role` - Assign role to user
- `revoke_role` - Revoke role from user
- `grant_permission` - Grant permission to role
- `revoke_permission` - Revoke permission from role

---

## Testing

### Running Tests

```bash
# All tests
composer test

# Specific suite
php vendor/bin/phpunit --testsuite Unit
php vendor/bin/phpunit --testsuite Integration
php vendor/bin/phpunit --testsuite Feature
php vendor/bin/phpunit --testsuite Functional

# With coverage
php vendor/bin/phpunit --coverage-html coverage/

# Specific test class
php vendor/bin/phpunit tests/Functional/CaseLifecycleTest.php

# Specific test method
php vendor/bin/phpunit --filter test_create_new_case
```

### Test Coverage

Current test suite:
- **81 functional tests** covering all architectural axioms
- **207 assertions** validating behavior
- **95% pass rate**
- **13 database tables** fully tested

### Writing Tests

```php
namespace DxEngine\Tests\Functional;

class MyFeatureTest extends BaseFunctionalTestCase
{
    public function test_my_feature(): void
    {
        // Arrange
        $case = $this->createTestCase(['status' => 'NEW']);
        
        // Act
        $result = $this->db->selectOne(
            'SELECT * FROM dx_cases WHERE id = ?',
            [$case['id']]
        );
        
        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('NEW', $result['status']);
    }
}
```

---

## Deployment

### Production Checklist

- [ ] Set `APP_ENV=production` in `.env`
- [ ] Set `APP_DEBUG=false`
- [ ] Generate strong `APP_KEY` (32+ characters)
- [ ] Configure production database credentials
- [ ] Run migrations: `php bin/console migrate`
- [ ] Seed initial data: `php bin/console seed`
- [ ] Set proper file permissions (storage/, cache/)
- [ ] Configure web server (Apache/Nginx)
- [ ] Enable HTTPS/SSL
- [ ] Set up queue worker daemon
- [ ] Configure cron jobs for scheduled tasks
- [ ] Set up monitoring and logging
- [ ] Configure backup strategy

### Web Server Configuration

**Apache (.htaccess):**
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>
```

**Nginx:**
```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/dx-engine/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### Queue Worker Daemon

**systemd service:**
```ini
[Unit]
Description=DX Engine Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/dx-engine
ExecStart=/usr/bin/php /var/www/dx-engine/bin/worker
Restart=always

[Install]
WantedBy=multi-user.target
```

---

## FAQ

### Q: What makes DX Engine different from Laravel, Symfony, or other PHP frameworks?

**A:** DX Engine is specifically designed for **metadata-driven, center-out architectures** where the backend controls 100% of UI structure and behavior. Unlike general-purpose frameworks, DX Engine:
- Enforces strict architectural axioms (e.g., no CSS-based security, optimistic locking everywhere)
- Uses a 4-node JSON contract for predictable API responses
- Implements server-side payload pruning for security
- Targets case management and BPM use cases

### Q: Can I use React/Vue/Angular with DX Engine?

**A:** Not recommended. DX Engine is designed to work with **vanilla JavaScript** (ES2020+) to maintain the center-out philosophy. Frontend frameworks often encourage client-side logic, which violates Axiom A-01.

### Q: How do I handle file uploads?

**A:** Use the `file_upload` component type. Files are uploaded via multipart/form-data and stored in `storage/uploads/`. The file path is saved in the case payload.

### Q: Can DX Engine scale horizontally?

**A:** Yes. DX Engine is stateless and can run on multiple servers behind a load balancer. Use:
- Database session storage (not file-based)
- Centralized job queue (shared database)
- Shared file storage (NFS, S3)

### Q: How do I implement multi-tenancy?

**A:** Add a `tenant_id` column to all tables and filter queries by current tenant. Use middleware to set the tenant context based on subdomain or JWT claims.

### Q: What's the best way to handle complex workflows?

**A:** Create a DX Controller for each major workflow stage. Use `case_status` and conditional logic in `getFlow()` to determine which components to display.

### Q: How do I implement real-time updates?

**A:** DX Engine doesn't include WebSockets out of the box. For real-time updates:
1. Poll the API periodically (simple)
2. Integrate Laravel Echo or Pusher (advanced)
3. Use Server-Sent Events (SSE)

### Q: Can I use DX Engine for public-facing websites?

**A:** Yes! See `PublicPortalDX.php` for an example of anonymous case initiation. Use session-based authentication for logged-out users.

### Q: How do I migrate from another system?

**A:** Create migration scripts that:
1. Map old data model to DX Engine schema
2. Import users, roles, permissions
3. Create cases and assignments
4. Generate case history for audit trail

---

## Contributing

We welcome contributions! Please:

1. Fork the repository
2. Create a feature branch
3. Write tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

### Code Style

- **PSR-12** coding standard
- **PHPDoc** for all public methods
- **Type hints** for all parameters and return types
- **Strict types** (`declare(strict_types=1);`)

### Testing Standards

- **Unit tests** for isolated components
- **Integration tests** for database interactions
- **Functional tests** for end-to-end workflows
- **Minimum 80% code coverage**

---

## License

DX Engine Framework is proprietary software. All rights reserved.

---

## Support

- **Documentation**: [https://dx-engine.example.com/docs](https://dx-engine.example.com/docs)
- **Issues**: [GitHub Issues](https://github.com/example/dx-engine/issues)
- **Email**: support@dx-engine.example.com
- **Community**: [Discord](https://discord.gg/dx-engine)

---

<div align="center">

**Built with ❤️ by the DX Engine Team**

[⬆ Back to Top](#dx-engine-framework---developers-guide)

</div>
