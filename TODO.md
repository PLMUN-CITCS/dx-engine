# DX Engine Framework — Master Development Roadmap

> **Role:** Lead Technical Project Manager & Senior PHP Enterprise Architect
> **Stack:** PHP 8.x · Composer · PDO/Doctrine DBAL · Bootstrap 5 · Vanilla JS (ES2020+)
> **Architecture:** Center-Out · Metadata Bridge (4-Node JSON) · Stateless Cluster · Server-Side Payload Pruning
> **Plane Separation:** `/src/Core/` (Immutable Framework Plane) vs. `/src/App/` (Mutable Application Plane)
> **Status:** Pre-Development — Roadmap Only. No functional code has been written.

---

## Legend

| Symbol | Meaning |
|---|---|
| `- [ ]` | Task not started |
| `- [x]` | Task completed |
| `[INTERFACE]` | Must be defined as a PHP `interface` |
| `[ABSTRACT]` | Must be a PHP `abstract class` |
| `[TRAIT]` | Must be a PHP `trait` |
| `[CONTRACT]` | Canonical data-shape / JSON schema definition |
| `[SCHEMA]` | Database migration / DDL definition |
| `[CONFIG]` | Configuration file — zero business logic |
| `[OOTB]` | Out-of-the-Box bundled capability |
| `[TEST]` | Corresponding PHPUnit test class required |
| `[JS]` | Vanilla JavaScript ES2020+ module |
| `[SEED]` | Database seed file |

---

## Architectural Axioms (Non-Negotiable Constraints)

> Every implementation decision in this roadmap is subordinate to these axioms. A task that violates any axiom is a defect, not a feature.

- [ ] **A-01 — Center-Out Mandate:** The backend is the sole source of truth. The frontend NEVER derives structure, labels, visibility rules, or permissions from its own logic. All such decisions arrive exclusively inside the 4-node JSON Metadata Bridge payload.
- [ ] **A-02 — Plane Isolation:** Code inside `/src/Core/` MUST NOT import or reference anything in `/src/App/`. The App plane depends on Core; Core never depends on App. Core classes are sealed contracts; App classes are the designated extension points.
- [ ] **A-03 — Database Agnosticism:** Zero raw SQL dialect keywords (`AUTO_INCREMENT`, `SERIAL`, `TOP`, `ILIKE`) may appear in Core code. All DDL and DML MUST route through the DBAL abstraction layer. The framework MUST run unchanged on MySQL/MariaDB, PostgreSQL, SQL Server, and SQLite.
- [ ] **A-04 — No CSS-Based Security:** Permission-gated component visibility MUST be enforced exclusively via Server-Side Payload Pruning in `LayoutService.php`. A client MUST NOT receive any component it is not authorized to render — not even in a hidden state.
- [ ] **A-05 — Optimistic Locking Everywhere:** Every write to `dx_cases` MUST validate the `eTag` via the `If-Match` HTTP header before persisting. A mismatch MUST return HTTP 412 Precondition Failed and append a record to `dx_case_history`.
- [ ] **A-06 — No Low-Code Syntax Leakage:** Zero Pega (`px`/`py`/`pz`), Appian (`pv!`), or Mendix prefixes in any class name, variable, or configuration key. All low-code concepts are expressed in pure PHP OOP paradigms.
- [ ] **A-07 — No Frontend Frameworks:** The JS Runtime is pure Vanilla JS (ES2020+). No React, Vue, Angular, Alpine, or jQuery. No Tailwind CSS. Bootstrap 5 utility classes are the exclusive client-side styling system.
- [ ] **A-08 — 4-Node JSON Contract:** Every API response from `/public/api/dx.php` MUST conform exactly to: `{ "data": {}, "uiResources": [], "nextAssignmentInfo": {}, "confirmationNote": {} }`. Additional root nodes are forbidden without a versioned contract amendment.
- [ ] **A-09 — Product Info Over Raw Data:** The `data` node and all `uiResources` labels MUST carry formatted, ready-to-display "Product Info" strings (e.g., `"Case Status: Under Review"`), never raw domain codes (e.g., `"status": "UNDER_REV"`).
- [ ] **A-10 — Parameterized Queries Only:** `DBALWrapper` MUST reject any attempt to interpolate user-supplied data directly into SQL strings. All user data MUST be passed as bound parameters.

---

## Canonical Directory Structure

```
dx-engine/
├── bin/
│   └── worker                          ← CLI cron entry point for the queue worker
├── config/
│   ├── app.php                         ← [CONFIG] Application-level config array
│   └── database.php                    ← [CONFIG] Driver-keyed DBAL connection params
├── database/
│   ├── migrations/                     ← One class per migration, named YYYYMMDD_NNNNNN_*.php
│   └── seeds/                          ← Idempotent seed classes
├── public/
│   ├── api/
│   │   ├── dx.php                      ← REST: case flow execution
│   │   ├── worklist.php                ← REST: assignment management
│   │   └── rbac_admin.php              ← REST: RBAC administration
│   ├── js/
│   │   ├── DXInterpreter.js            ← [JS] Fetch-Render-Submit pipeline orchestrator
│   │   ├── ComponentRegistry.js        ← [JS] component_type → Bootstrap 5 widget mapper
│   │   ├── VisibilityEngine.js         ← [JS] visibility_rule evaluator (d-none toggler)
│   │   ├── Validator.js                ← [JS] Symmetric client-side UX validation
│   │   ├── Stepper.js                  ← [JS] Step indicator / nextAssignmentInfo renderer
│   │   ├── StateManager.js             ← [JS] Client-side dirty state store
│   │   ├── DashboardPage.js            ← [JS] OOTB: Work Dashboard page init
│   │   ├── RbacAdminPage.js            ← [JS] OOTB: Access Management Portal page init
│   │   └── InitiateCaseButton.js       ← [JS] OOTB: Anonymous intake modal trigger
│   ├── css/
│   │   └── dx-engine.css               ← Framework-specific CSS overrides (minimal)
│   └── index.php                       ← Single HTTP entry point; no business logic
├── src/
│   ├── Core/                           ← IMMUTABLE FRAMEWORK PLANE
│   │   ├── Autoloader.php
│   │   ├── DBALWrapper.php
│   │   ├── DataModel.php
│   │   ├── DXController.php
│   │   ├── Router.php
│   │   ├── LayoutService.php
│   │   ├── DxWorklistService.php
│   │   ├── Contracts/
│   │   │   ├── AuthenticatableInterface.php
│   │   │   ├── GuardInterface.php
│   │   │   └── MiddlewareInterface.php
│   │   ├── Traits/
│   │   │   ├── HasRoles.php
│   │   │   ├── HasPermissions.php
│   │   │   └── HasAbacContext.php
│   │   ├── Middleware/
│   │   │   ├── AuthMiddleware.php
│   │   │   ├── CsrfMiddleware.php
│   │   │   ├── RateLimitMiddleware.php
│   │   │   └── SessionGuard.php
│   │   ├── Migrations/
│   │   │   ├── MigrationInterface.php
│   │   │   ├── MigrationRunner.php
│   │   │   └── SchemaBuilder.php
│   │   ├── Jobs/
│   │   │   ├── JobInterface.php
│   │   │   ├── AbstractJob.php
│   │   │   ├── JobDispatcher.php
│   │   │   ├── QueueWorker.php
│   │   │   ├── WebhookDispatchJob.php
│   │   │   ├── SpreadsheetImportJob.php
│   │   │   └── PdfGenerationJob.php
│   │   └── Exceptions/
│   │       ├── DatabaseException.php
│   │       ├── ETagMismatchException.php
│   │       ├── ValidationException.php
│   │       ├── AuthenticationException.php
│   │       └── PayloadPruningException.php
│   └── App/                            ← MUTABLE APPLICATION PLANE
│       ├── Models/
│       │   ├── CaseModel.php
│       │   ├── AssignmentModel.php
│       │   ├── UserModel.php
│       │   └── JobModel.php
│       └── DX/
│           ├── SampleCaseDX.php
│           ├── AnonymousIntakeDX.php
│           ├── WorkLifeCycleDX.php
│           ├── WorkDashboardDX.php
│           ├── PublicPortalDX.php
│           └── RbacAdminDX.php
├── storage/
│   ├── logs/
│   ├── cache/
│   └── exports/
├── templates/
│   ├── layouts/
│   │   ├── work_lifecycle.html
│   │   ├── dashboard.html
│   │   └── rbac_admin.html
│   ├── portals/
│   │   └── public_portal.html
│   └── partials/
│       └── initiate_case_button.html
├── tests/
│   ├── Unit/
│   │   └── Core/
│   │       └── Jobs/
│   ├── Integration/
│   │   └── Api/
│   └── Feature/
├── vendor/
├── composer.json
├── phpunit.xml
├── .env.example
└── .gitignore
```

---

## Phase 1: Database Agnosticism & Core Foundation

> **Objective:** Establish the canonical directory scaffold, register the custom PSR-4 Autoloader, define all configuration contracts, create the single public entry point, build the 100% database-agnostic DBAL wrapper, define the abstract DataModel ORM contract, wire the migration engine, and execute all core framework database schema migrations.
>
> **Phase Gate:** Phase 2 cannot start until `Autoloader.php` resolves both namespace planes without errors, `DBALWrapper` integration tests pass against all four drivers (MySQL/MariaDB, PostgreSQL, SQLite, SQL Server), and every migration listed in §1.7 has been executed successfully against the test database.

---

### 1.1 — Canonical Directory Scaffold

- [ ] **Task — Create full directory tree** as defined in the Canonical Directory Structure above.
  - Create `/bin/`, `/config/`, `/database/migrations/`, `/database/seeds/`
  - Create `/public/api/`, `/public/js/`, `/public/css/`
  - Create `/src/Core/Contracts/`, `/src/Core/Traits/`, `/src/Core/Middleware/`
  - Create `/src/Core/Migrations/`, `/src/Core/Jobs/`, `/src/Core/Exceptions/`
  - Create `/src/App/Models/`, `/src/App/DX/`
  - Create `/storage/logs/`, `/storage/cache/`, `/storage/exports/`
  - Create `/templates/layouts/`, `/templates/portals/`, `/templates/partials/`
  - Create `/tests/Unit/Core/Jobs/`, `/tests/Integration/Api/`, `/tests/Feature/`
  - Add `.gitkeep` to every empty leaf directory so the tree is tracked by Git.

---

### 1.2 — Composer Bootstrap

- [ ] **File:** `composer.json` [CONFIG]
  - **Objective:** Declare all runtime and dev dependencies. Register the PSR-4 namespace-to-directory map for both the Core and App planes.
  - **Required `require` entries:**
    - `php: ^8.1`
    - `doctrine/dbal: ^3.x` — Database Abstraction Layer (the ONLY permitted DB interaction mechanism)
    - `guzzlehttp/guzzle: ^7.x` — Webhook HTTP client
    - `phpoffice/phpspreadsheet: ^2.x` — ETL Excel/CSV import-export
    - `dompdf/dompdf: ^2.x` — Server-side PDF generation
    - `vlucas/phpdotenv: ^5.x` — Environment variable loader
    - `ramsey/uuid: ^4.x` — UUID v4 generation for Case IDs, Assignment IDs, and eTag seeds
    - `monolog/monolog: ^3.x` — PSR-3 structured logging
  - **Required `require-dev` entries:**
    - `phpunit/phpunit: ^11.x`
    - `phpstan/phpstan: ^1.x` — Static analysis (target: Level 8)
    - `squizlabs/php_codesniffer: ^3.x` — PSR-12 code style enforcement
    - `fakerphp/faker: ^1.x` — Test data generation
  - **PSR-4 autoload map:**
    - `"DxEngine\\Core\\": "src/Core/"`
    - `"DxEngine\\App\\": "src/App/"`
    - `"DxEngine\\Tests\\": "tests/"`
  - **Composer scripts to define:** `test` (run PHPUnit), `analyse` (run PHPStan), `lint` (run PHPCS), `migrate` (run MigrationRunner), `seed` (run seeders)

---

### 1.3 — Environment & Application Configuration

- [ ] **File:** `.env.example` [CONFIG]
  - **Objective:** Document every environment variable the framework requires. The live `.env` file MUST be listed in `.gitignore` and MUST never be committed to version control.
  - **Required variable groups:**
    - **Application:** `APP_ENV` (development|staging|production), `APP_KEY` (32-byte HMAC secret, required), `APP_DEBUG` (bool), `APP_URL`
    - **Database:** `DB_DRIVER` (mysql|pgsql|sqlite|sqlsrv), `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`
    - **Session:** `SESSION_DRIVER` (database|file), `SESSION_LIFETIME` (minutes, default 120)
    - **Queue:** `QUEUE_DRIVER` (database|sync), `QUEUE_WORKER_ID` (unique string per server node, e.g. hostname)
    - **Logging:** `LOG_CHANNEL` (file|stderr), `LOG_LEVEL` (debug|info|warning|error)
    - **Webhooks:** `WEBHOOK_RETRY_MAX` (int, default 3), `WEBHOOK_RETRY_BACKOFF_SECONDS` (int, default 60)
    - **Storage:** `PDF_STORAGE_PATH` (default `storage/exports/`), `EXPORT_STORAGE_PATH`

- [ ] **File:** `config/app.php` [CONFIG]
  - **Objective:** A PHP file returning a single, flat associative configuration array. Loaded at bootstrap via `vlucas/phpdotenv`. Contains zero business logic — all values are derived from `$_ENV`.
  - **Required top-level array keys:** `name`, `env`, `debug`, `url`, `key`, `session`, `queue`, `logging`, `security`
  - **`security` sub-keys:** `etag_algo` (default `sha256`), `bcrypt_cost` (default 12), `session_regenerate_on_login` (bool, default `true`), `max_failed_login_attempts` (int, default 5)

- [ ] **File:** `config/database.php` [CONFIG]
  - **Objective:** Returns a structured, driver-keyed array of Doctrine DBAL connection parameters. The active driver is selected at runtime by reading `DB_DRIVER` from the environment. MariaDB is the primary deployment target but is specified as `pdo_mysql` in DBAL terms.
  - **Required driver config keys:** `mysql`, `pgsql`, `sqlite`, `sqlsrv`
  - **Each driver entry contains:** `driver`, `host`, `port`, `dbname`, `user`, `password`, `charset`, `driverOptions` (array)
  - **Required inline comment:** A PHPDoc block explaining the MariaDB-specific `pdo_mysql` driver alias in DBAL and noting that no dialect-specific SQL features may be used in Core migrations.

---

### 1.4 — Custom Autoloader

- [ ] **File:** `src/Core/Autoloader.php`
  - **Objective:** A self-contained, custom PSR-4 namespace resolver. Registered via `spl_autoload_register()` in `public/index.php` before the Composer autoloader is engaged, allowing Core namespace overrides at runtime. Maps the `DxEngine\Core\` and `DxEngine\App\` namespace prefixes to their respective `src/` directories.
  - **Required methods:**
    - `register(): void` — Calls `spl_autoload_register([$this, 'load'])`. Returns `void`.
    - `load(string $fullyQualifiedClassName): void` — Resolves the FQCN to an absolute file path by replacing namespace separators with `DIRECTORY_SEPARATOR`. Requires the file if it exists. MUST be a silent no-op if the file does not exist — allowing the Composer autoloader to serve as the fallback. MUST NOT throw.
    - `addNamespace(string $prefix, string $baseDirectory): static` — Registers additional namespace-to-directory mappings at runtime. Returns `$this` for chaining.
    - `getRegisteredNamespaces(): array` — Returns all currently mapped `prefix => baseDirectory` pairs for debugging and introspection tooling.
  - **Design constraints:** Must function entirely independently of Composer. Must normalize all file paths using the `DIRECTORY_SEPARATOR` constant. Must handle both forward-slash and backslash namespace separator characters.

---

### 1.5 — Public Entry Point

- [ ] **File:** `public/index.php`
  - **Objective:** The single, framework-level HTTP entry point. Loads all bootstrapping dependencies and dispatches the HTTP request to the `Router`. Contains zero business logic.
  - **Strict execution sequence (must be sequential, non-reorderable):**
    1. `require_once '../vendor/autoload.php'` — Load Composer autoloader.
    2. `define('APP_ROOT', dirname(__DIR__))` — Set the global root constant. All other paths derive from this.
    3. Instantiate and register `(new DxEngine\Core\Autoloader())->register()`.
    4. Load `.env` via `Dotenv\Dotenv::createImmutable(APP_ROOT)`.
    5. Load `config/app.php` and `config/database.php` into a global config registry singleton.
    6. Instantiate `DxEngine\Core\Router` and call `Router::dispatch($_SERVER)`.
  - **Design constraint:** The file MUST contain fewer than 30 lines. Any initialization logic that grows beyond a few lines MUST be extracted into a dedicated `AppKernel` or `Bootstrap` class inside `src/Core/`.

---

### 1.6 — DBAL Wrapper

- [ ] **File:** `src/Core/DBALWrapper.php`
  - **Objective:** A thin, stateless decorator around `Doctrine\DBAL\Connection`. Provides the framework-native API for all database interactions. Enforces parameterized queries as the ONLY permitted interaction pattern. Abstracts all dialect-specific quirks. Is the ONLY class in the entire framework permitted to hold or use a live Doctrine DBAL connection object directly.
  - **Constructor:** `__construct(array $config)` — Accepts the active driver's config array from `config/database.php` and establishes the DBAL connection via `Doctrine\DBAL\DriverManager::getConnection()`.
  - **Required public methods:**
    - `select(string $sql, array $params = [], array $types = []): array` — Executes a SELECT query. Returns an array of associative rows. Returns an empty `[]` (never `null`) when no rows match.
    - `selectOne(string $sql, array $params = [], array $types = []): ?array` — Returns the first row as an associative array, or `null` if no rows match.
    - `insert(string $table, array $data, array $types = []): string|int` — Inserts a single row using the DBAL `insert()` method. Returns the last inserted ID.
    - `update(string $table, array $data, array $criteria, array $types = []): int` — Updates rows matching `$criteria`. Returns the count of affected rows.
    - `delete(string $table, array $criteria, array $types = []): int` — Deletes rows matching `$criteria`. Returns the count of affected rows.
    - `executeStatement(string $sql, array $params = [], array $types = []): int` — Executes arbitrary DML (e.g., batch operations). Returns the count of affected rows.
    - `beginTransaction(): void`
    - `commit(): void`
    - `rollBack(): void`
    - `transactional(\Closure $callback): mixed` — Wraps the callback in a transaction. On any `\Throwable`: calls `rollBack()` and re-throws. On success: calls `commit()` and returns the callback's return value. This is the canonical pattern for all atomic operations in the framework.
    - `getSchemaManager(): \Doctrine\DBAL\Schema\AbstractSchemaManager` — Exposes the DBAL Schema Manager for use exclusively by `MigrationRunner` and `SchemaBuilder`.
    - `quoteIdentifier(string $identifier): string` — Wraps an identifier in the dialect-correct quoting character using the DBAL platform.
    - `getPlatform(): \Doctrine\DBAL\Platforms\AbstractPlatform` — Returns the active platform object.
    - `getConnection(): \Doctrine\DBAL\Connection` — Escape hatch. MUST be marked `@internal`. MUST log a `WARNING` every time it is called in non-testing environments.
  - **Design constraints:**
    - MUST wrap every `Doctrine\DBAL\Exception` in `DxEngine\Core\Exceptions\DatabaseException` before re-throwing.
    - MUST log every executed SQL statement at `DEBUG` level via the PSR-3 logger, including query string and bound parameters.
    - MUST log every database error at `ERROR` level with the full SQL, parameter set, and stack trace (redacting fields named `password`, `password_hash`, `secret_key`).

---

### 1.7 — Abstract Data Model (ORM Contract)

- [ ] **File:** `src/Core/DataModel.php` [ABSTRACT]
  - **Objective:** The abstract base class for all framework and application-plane data entities. Provides a lightweight Active Record-style interface without a full ORM dependency. Designed to be optimized for MariaDB/PDO via `DBALWrapper` while remaining 100% database-agnostic. Concrete models in `/src/App/Models/` extend this class and implement the two required abstract methods.
  - **Required abstract methods (must be implemented by every concrete subclass):**
    - `table(): string` — Returns the unquoted database table name for this entity (e.g., `'dx_cases'`).
    - `fieldMap(): array` — Returns an associative array mapping PHP property names (camelCase) to database column metadata. Shape: `['propertyName' => ['column' => 'db_column_name', 'type' => 'string|integer|boolean|datetime|json']]`.
  - **Required concrete methods (implemented in the abstract class, available to all subclasses):**
    - `find(string|int $id): ?static` — Fetches a single record by primary key. Returns a hydrated instance or `null`.
    - `findAll(array $criteria = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array` — Returns an array of hydrated model instances. All parameter-driven filtering MUST use `DBALWrapper` bound parameters.
    - `findOneBy(array $criteria): ?static` — Returns the first matching hydrated instance, or `null`.
    - `save(): bool` — Upsert logic: calls `DBALWrapper::insert()` when the primary key is unset; calls `DBALWrapper::update()` when the primary key is already set. Fires `beforeSave()` before and `afterSave()` after the persist operation. Returns `true` on success.
    - `delete(): bool` — Deletes the current record using its primary key. Returns `true` on success.
    - `fill(array $attributes): static` — Mass-assigns attributes from a PHP array, applying only keys present in `fieldMap()`. Returns `$this` for method chaining.
    - `toArray(): array` — Returns all mapped properties as a PHP array keyed by PHP property names (camelCase).
    - `toDatabaseRow(): array` — Returns all mapped properties keyed by their database column names, for direct use with `DBALWrapper::insert()` / `DBALWrapper::update()`.
    - `hydrate(array $row): static` — Static factory. Accepts a raw database row (column-keyed array) and returns a fully hydrated model instance.
    - `getPrimaryKey(): string` — Returns the PHP property name serving as the primary key. Defaults to `'id'`. Overridable by concrete subclasses.
  - **Required lifecycle hook methods (protected, overridable by subclasses):**
    - `beforeSave(): void` — Called by `save()` before the DBAL write. Default implementation is empty.
    - `afterSave(): void` — Called by `save()` after a successful DBAL write. Default implementation is empty.
  - **Design constraints:** MUST use `DBALWrapper` exclusively — zero direct PDO or Doctrine calls. MUST never construct raw SQL strings containing user-supplied data directly.

---

### 1.8 — Application-Plane Model Scaffolds

> Concrete `DataModel` subclasses residing in `/src/App/Models/`. These are in the Mutable Application Plane and are the reference scaffold for developer-defined entities.

- [ ] **File:** `src/App/Models/CaseModel.php`
  - **Objective:** Concrete `DataModel` for the `dx_cases` table. Implements `table()` (returns `'dx_cases'`) and `fieldMap()` (maps all case columns). Provides case-lifecycle query methods consumed by `DXController`.
  - **Required additional methods beyond base `DataModel`:**
    - `findByReference(string $caseRef): ?static` — Fetch by human-readable case reference (e.g., `CASE-00001`).
    - `findByCaseType(string $caseType, array $statuses = []): array`
    - `findByOwner(string $userId): array`
    - `updateETag(string $caseId, string $newETag): bool` — Atomic single-column update.
    - `lockCase(string $caseId, string $userId): bool` — Sets `locked_by` and `locked_at`. Fails atomically if already locked by another user.
    - `unlockCase(string $caseId): bool` — Clears `locked_by` and `locked_at`.

- [ ] **File:** `src/App/Models/AssignmentModel.php`
  - **Objective:** Concrete `DataModel` for the `dx_assignments` table.
  - **Required additional methods:**
    - `findActiveByCase(string $caseId): ?static`
    - `findByAssignee(string $userId, string $status = 'active'): array`
    - `findByRole(string $roleName, string $status = 'pending'): array`
    - `completeAssignment(string $assignmentId, string $userId, array $completionData): bool` — Sets `status`, `completed_at`, `completed_by`, and `completion_data` atomically.

- [ ] **File:** `src/App/Models/UserModel.php`
  - **Objective:** Concrete `DataModel` for `dx_users`. Handles password hashing and failed-login tracking internally. MUST never expose the `password_hash` value via `toArray()`.
  - **Required additional methods:**
    - `findByEmail(string $email): ?static`
    - `findByUsername(string $username): ?static`
    - `verifyPassword(string $plaintext): bool` — Compares against the stored bcrypt hash using `password_verify()`.
    - `setPassword(string $plaintext): void` — bcrypt-hashes the plaintext and stores it internally before the next `save()` call.
    - `incrementFailedLogin(): void`
    - `resetFailedLogin(): void`
    - `isLocked(): bool` — Returns `true` if `failed_login_count >= config('security.max_failed_login_attempts')`.

- [ ] **File:** `src/App/Models/JobModel.php`
  - **Objective:** Concrete `DataModel` for `dx_jobs`. Provides atomic queue operations consumed by `QueueWorker`.
  - **Required additional methods:**
    - `claimNextPending(string $queue, string $workerId): ?static` — MUST use `DBALWrapper::transactional()` to atomically select and lock the next `pending` job where `available_at <= NOW()`, set `status = 'processing'`, `reserved_by = workerId`, `reserved_at = NOW()`. Prevents duplicate processing in a multi-node cluster.
    - `markProcessing(string $jobId, string $workerId): bool`
    - `markCompleted(string $jobId): bool`
    - `markFailed(string $jobId, string $errorMessage, string $trace): bool`
    - `releaseStaleJobs(int $timeoutSeconds): int` — Resets jobs stuck in `processing` state for longer than `$timeoutSeconds`. Returns the count of released jobs.

---

### 1.9 — Database Migration Engine

- [ ] **File:** `src/Core/Migrations/MigrationInterface.php` [INTERFACE]
  - **Objective:** The contract every migration class in `/database/migrations/` must implement.
  - **Required methods:**
    - `up(DBALWrapper $db): void` — Applies the schema change (create table, add column, add index).
    - `down(DBALWrapper $db): void` — Precisely reverses the change applied by `up()`.
    - `getVersion(): string` — Returns a zero-padded, lexicographically sortable version string in the format `YYYYMMDD_NNNNNN` (e.g., `20240101_000001`).

- [ ] **File:** `src/Core/Migrations/MigrationRunner.php`
  - **Objective:** Discovers, sorts, and executes all migration classes found in `/database/migrations/`. Tracks applied migrations in a self-bootstrapped `dx_migrations` meta-table. Fully database-agnostic — all schema operations go through `SchemaBuilder` and `DBALWrapper`.
  - **Required methods:**
    - `migrate(): void` — Runs all pending `up()` methods in ascending `getVersion()` order.
    - `rollback(int $steps = 1): void` — Calls `down()` on the last N applied migrations in descending order.
    - `reset(): void` — Calls `down()` on every applied migration in full descending order.
    - `status(): array` — Returns all discovered migrations with their `applied` boolean and `applied_at` timestamp.
    - `ensureMigrationsTable(): void` — Creates the `dx_migrations` tracking table if it does not already exist. Idempotent.
    - `getPendingMigrations(): array` — Returns migration objects not yet recorded in `dx_migrations`.
    - `getAppliedMigrations(): array` — Returns migration version strings recorded in `dx_migrations`, ordered ascending.
    - `discoverMigrationFiles(): array` — Scans `/database/migrations/` for files, requires them, instantiates each class, and returns an array of `MigrationInterface` objects sorted by `getVersion()`.

- [ ] **File:** `src/Core/Migrations/SchemaBuilder.php`
  - **Objective:** A fluent, database-agnostic DDL builder wrapping `Doctrine\DBAL\Schema\Schema`. Migration `up()` and `down()` methods MUST use this class exclusively for all schema manipulation — never raw DDL strings.
  - **Required methods:**
    - `createTable(string $name, \Closure $blueprint): void` — `$blueprint` receives a `Doctrine\DBAL\Schema\Table` instance to define columns and indexes.
    - `dropTable(string $name): void`
    - `alterTable(string $name, \Closure $blueprint): void`
    - `hasTable(string $name): bool`
    - `hasColumn(string $tableName, string $columnName): bool`
    - `addIndex(string $tableName, array $columns, bool $unique = false, ?string $indexName = null): void`
    - `dropIndex(string $tableName, string $indexName): void`
    - `execute(): void` — Generates the diff SQL between the current and desired schema using DBAL's `Comparator` and executes each statement via `DBALWrapper::executeStatement()`.

---

### 1.10 — Core Framework Database Schema Migrations

> All migration files reside in `/database/migrations/` using the naming convention `YYYYMMDD_NNNNNN_snake_case_description.php`. Each file contains exactly one class implementing `MigrationInterface`. Use `SchemaBuilder` exclusively — no raw DDL strings.

- [ ] **File:** `database/migrations/20240101_000001_create_dx_migrations.php` [SCHEMA]
  - **Table:** `dx_migrations` — The migration runner's own meta-table. This MUST be the very first migration executed.
  - **Columns:** `id` (integer, auto-increment PK), `version` (string 20, unique, not null), `migration_class` (string 255), `applied_at` (datetime, default current timestamp)

- [ ] **File:** `database/migrations/20240101_000002_create_dx_users.php` [SCHEMA]
  - **Table:** `dx_users` — Framework user identity store.
  - **Columns:** `id` (guid/UUID PK), `username` (string 255, unique), `email` (string 255, unique), `password_hash` (string 255, not null), `display_name` (string 255, nullable), `status` (string 20, default `active`; values: active|inactive|locked), `last_login_at` (datetime, nullable), `password_changed_at` (datetime, nullable), `failed_login_count` (smallint, default 0), `created_at` (datetime), `updated_at` (datetime)
  - **Indexes:** `idx_users_email`, `idx_users_status`, `idx_users_username`

- [ ] **File:** `database/migrations/20240101_000003_create_dx_roles.php` [SCHEMA]
  - **Table:** `dx_roles` — RBAC role definitions.
  - **Columns:** `id` (guid PK), `name` (string 100, unique), `display_name` (string 255), `description` (text, nullable), `is_system` (boolean, default false — system roles are protected from deletion via application logic), `created_at` (datetime), `updated_at` (datetime)
  - **Seed note:** A companion seed MUST pre-populate: `ROLE_SUPER_ADMIN`, `ROLE_ADMIN`, `ROLE_MANAGER`, `ROLE_OPERATOR`, `ROLE_VIEWER`

- [ ] **File:** `database/migrations/20240101_000004_create_dx_permissions.php` [SCHEMA]
  - **Table:** `dx_permissions` — Granular permission atoms following the `resource:action` convention.
  - **Columns:** `id` (guid PK), `key` (string 150, unique; e.g., `case:create`, `case:approve`, `worklist:claim`, `rbac:manage`), `description` (text, nullable), `category` (string 100; used for grouping in the Admin UI), `created_at` (datetime)

- [ ] **File:** `database/migrations/20240101_000005_create_dx_role_permissions.php` [SCHEMA]
  - **Table:** `dx_role_permissions` — Many-to-many pivot: roles ↔ permissions.
  - **Columns:** `role_id` (guid, FK → `dx_roles.id`, cascade delete), `permission_id` (guid, FK → `dx_permissions.id`, cascade delete)
  - **Primary key:** Composite `(role_id, permission_id)`

- [ ] **File:** `database/migrations/20240101_000006_create_dx_user_roles.php` [SCHEMA]
  - **Table:** `dx_user_roles` — Many-to-many pivot: users ↔ roles, with optional ABAC scope columns.
  - **Columns:** `user_id` (guid, FK → `dx_users.id`, cascade delete), `role_id` (guid, FK → `dx_roles.id`, cascade delete), `context_type` (string 100, nullable — enables ABAC scoping; e.g., `'business_unit'`), `context_id` (string 150, nullable — the specific context instance identifier), `granted_by` (guid, FK → `dx_users.id`, nullable), `granted_at` (datetime)
  - **Primary key:** Composite `(user_id, role_id, context_type, context_id)`
  - **Indexes:** `idx_user_roles_user_id`, `idx_user_roles_role_id`

- [ ] **File:** `database/migrations/20240101_000007_create_dx_sessions.php` [SCHEMA]
  - **Table:** `dx_sessions` — Database-backed session store. Eliminates sticky-session requirements in a clustered deployment.
  - **Columns:** `id` (string 128 PK — PHP session ID), `user_id` (guid, FK → `dx_users.id`, nullable — null for anonymous sessions), `payload` (text — serialized, encrypted session data), `ip_address` (string 45), `user_agent` (text, nullable), `last_activity` (integer — Unix timestamp), `created_at` (datetime)
  - **Indexes:** `idx_sessions_user_id`, `idx_sessions_last_activity`

- [ ] **File:** `database/migrations/20240101_000008_create_dx_cases.php` [SCHEMA]
  - **Table:** `dx_cases` — The central case lifecycle record. The most critical table in the framework.
  - **Columns:** `id` (guid PK), `case_type` (string 150, not null — FQCN fragment that maps to an App DX controller, e.g., `'SampleCaseDX'`), `case_reference` (string 50, unique — human-readable ID generated by the framework, e.g., `CASE-00001`), `status` (string 50), `stage` (string 100, nullable), `current_assignment_id` (guid, FK → `dx_assignments.id`, nullable), `owner_id` (guid, FK → `dx_users.id`, nullable), `created_by` (guid, FK → `dx_users.id`, not null), `updated_by` (guid, FK → `dx_users.id`, nullable), `e_tag` (string 64, not null — HMAC-SHA256 for optimistic locking), `locked_by` (guid, FK → `dx_users.id`, nullable), `locked_at` (datetime, nullable), `resolved_at` (datetime, nullable), `sla_due_at` (datetime, nullable), `priority` (smallint, default 2; 1=Critical 2=High 3=Medium 4=Low), `case_data` (text/longtext — full persisted JSON payload), `created_at` (datetime), `updated_at` (datetime)
  - **Indexes:** `idx_cases_status`, `idx_cases_case_type`, `idx_cases_owner_id`, `idx_cases_sla_due_at`, `idx_cases_priority`, `idx_cases_case_reference`

- [ ] **File:** `database/migrations/20240101_000009_create_dx_assignments.php` [SCHEMA]
  - **Table:** `dx_assignments` — A single work item (Step/Task) within a case.
  - **Columns:** `id` (guid PK), `case_id` (guid, FK → `dx_cases.id`, cascade delete), `assignment_type` (string 100; e.g., `UserTask`, `ApprovalTask`, `ServiceTask`), `step_name` (string 150, not null), `status` (string 50; pending|active|completed|rejected|skipped|error), `assigned_to_user` (guid, FK → `dx_users.id`, nullable), `assigned_to_role` (string 100, nullable), `instructions` (text, nullable — "Product Info" displayed to the assignee, ready-to-render), `form_schema_key` (string 150), `deadline_at` (datetime, nullable), `started_at` (datetime, nullable), `completed_at` (datetime, nullable), `completed_by` (guid, FK → `dx_users.id`, nullable), `completion_data` (text/JSON), `created_at` (datetime)
  - **Indexes:** `idx_assignments_case_id`, `idx_assignments_status`, `idx_assignments_assigned_to_user`, `idx_assignments_assigned_to_role`

- [ ] **File:** `database/migrations/20240101_000010_create_dx_case_history.php` [SCHEMA]
  - **Table:** `dx_case_history` — Immutable, append-only audit trail. The application database user MUST be granted only INSERT and SELECT on this table — no UPDATE or DELETE.
  - **Columns:** `id` (guid PK), `case_id` (guid, FK → `dx_cases.id`), `assignment_id` (guid, nullable), `actor_id` (guid, FK → `dx_users.id`, nullable — null for system actions), `action` (string 100; e.g., `CASE_CREATED`, `STATUS_CHANGED`, `ASSIGNMENT_COMPLETED`, `ETAG_CONFLICT`, `PAYLOAD_PRUNED`, `ASSIGNMENT_CLAIMED`, `ASSIGNMENT_RELEASED`), `from_status` (string 50, nullable), `to_status` (string 50, nullable), `details` (text/JSON), `e_tag_at_time` (string 64 — snapshot of the eTag at the moment the event occurred), `occurred_at` (datetime, default current timestamp)
  - **Indexes:** `idx_history_case_id`, `idx_history_occurred_at`, `idx_history_actor_id`, `idx_history_action`
  - **Constraint note:** Add a comment in the seed file explicitly documenting that the DB user should have UPDATE and DELETE privileges revoked on this table.

- [ ] **File:** `database/migrations/20240101_000011_create_dx_jobs.php` [SCHEMA]
  - **Table:** `dx_jobs` — Database-backed async job queue.
  - **Columns:** `id` (guid PK), `queue` (string 100, default `'default'`), `job_class` (string 255, not null — FQCN of the concrete `AbstractJob` subclass), `payload` (text/JSON — serialized constructor arguments), `status` (string 20; pending|processing|completed|failed|cancelled), `attempts` (smallint, default 0), `max_attempts` (smallint, default 3), `priority` (smallint, default 5), `available_at` (datetime — supports delayed dispatch), `reserved_at` (datetime, nullable), `reserved_by` (string 50, nullable — `QUEUE_WORKER_ID` of the claiming node), `completed_at` (datetime, nullable), `failed_at` (datetime, nullable), `error_message` (text, nullable), `error_trace` (text, nullable), `created_at` (datetime)
  - **Indexes:** `idx_jobs_status_available` (composite: status + available_at), `idx_jobs_queue_status` (composite: queue + status), `idx_jobs_reserved_by`

- [ ] **File:** `database/migrations/20240101_000012_create_dx_webhooks.php` [SCHEMA]
  - **Table:** `dx_webhooks` — Registry of outbound webhook endpoint configurations.
  - **Columns:** `id` (guid PK), `name` (string 150, not null), `url` (text, not null), `event_type` (string 100, not null — the case or system event that triggers this webhook), `secret_key` (string 255, nullable — used for HMAC-SHA256 signing of outgoing payloads), `headers` (text/JSON — additional HTTP headers to include), `is_active` (boolean, default true), `last_triggered_at` (datetime, nullable), `created_at` (datetime), `updated_at` (datetime)
  - **Index:** `idx_webhooks_event_type`, `idx_webhooks_is_active`

- [ ] **File:** `database/migrations/20240101_000013_create_dx_webhook_logs.php` [SCHEMA]
  - **Table:** `dx_webhook_logs` — Delivery attempt log for all outgoing webhook dispatches.
  - **Columns:** `id` (guid PK), `webhook_id` (guid, FK → `dx_webhooks.id`), `case_id` (guid, FK → `dx_cases.id`, nullable), `job_id` (guid, FK → `dx_jobs.id`, nullable), `http_status` (smallint, nullable), `response_body` (text, nullable), `attempt_number` (smallint, not null), `duration_ms` (integer, nullable), `attempted_at` (datetime, default current timestamp)

---

### 1.11 — Database Seed Files

- [ ] **File:** `database/seeds/RolePermissionSeeder.php` [SEED]
  - **Objective:** Seeds the five default system roles and all core permission atoms into `dx_roles`, `dx_permissions`, and `dx_role_permissions`. MUST be fully idempotent (use DBAL upsert or check-before-insert pattern).
  - **System roles to seed:** `ROLE_SUPER_ADMIN`, `ROLE_ADMIN`, `ROLE_MANAGER`, `ROLE_OPERATOR`, `ROLE_VIEWER`
  - **Permission atoms to seed:** `case:create`, `case:read`, `case:update`, `case:delete`, `case:approve`, `case:reassign`, `worklist:claim`, `worklist:release`, `report:export`, `user:manage`, `rbac:manage`
  - **Role-Permission mapping to seed:** `ROLE_SUPER_ADMIN` → all permissions. `ROLE_ADMIN` → all except `rbac:manage`. `ROLE_MANAGER` → `case:*`, `worklist:*`, `report:export`. `ROLE_OPERATOR` → `case:read`, `case:update`, `worklist:claim`, `worklist:release`. `ROLE_VIEWER` → `case:read` only.

---

## Phase 2: IAM & Security Middleware

> **Objective:** Build the complete Identity and Access Management (IAM) stack. This covers all Core IAM contracts (interfaces), the authentication middleware pipeline, the session-based auth guard, the RBAC/ABAC trait engine, and the `LayoutService` — the server-side security gate that performs payload pruning. This is the highest security-criticality phase. It MUST be independently code-reviewed before Phase 3 begins.
>
> **Phase Gate:** Phase 3 cannot start until all IAM unit tests pass, and `LayoutService::isAllowed()` is verified by tests to correctly prune disallowed components at every nesting depth from all test fixture payloads.

---

### 2.1 — Core IAM Contracts

- [ ] **File:** `src/Core/Contracts/AuthenticatableInterface.php` [INTERFACE]
  - **Objective:** The contract any user entity must satisfy to be recognized by the Auth Middleware and `LayoutService`.
  - **Required methods:**
    - `getAuthId(): string|int` — Returns the unique user identifier.
    - `getAuthEmail(): string`
    - `getAuthRoles(): array` — Returns an array of role name strings (e.g., `['ROLE_MANAGER', 'ROLE_OPERATOR']`).
    - `getAuthPermissions(): array` — Returns the pre-resolved, flattened, deduplicated permission key array.
    - `isActive(): bool` — Returns `false` for `inactive` or `locked` users; MUST be checked by `AuthMiddleware`.

- [ ] **File:** `src/Core/Contracts/GuardInterface.php` [INTERFACE]
  - **Objective:** Contract for interchangeable authentication guards (session-based, token-based). Allows the auth mechanism to be swapped without touching any Core business logic.
  - **Required methods:**
    - `check(): bool` — Returns `true` if a user is currently authenticated.
    - `user(): ?AuthenticatableInterface` — Returns the current authenticated user, or `null`.
    - `login(AuthenticatableInterface $user): void` — Authenticates the given user and persists the session.
    - `logout(): void` — Destroys the current session and authentication state.
    - `id(): string|int|null` — Returns the current user's ID, or `null`.

- [ ] **File:** `src/Core/Contracts/MiddlewareInterface.php` [INTERFACE]
  - **Objective:** Standard middleware pipeline contract for all `src/Core/Middleware/` classes.
  - **Required methods:**
    - `handle(array $request, \Closure $next): mixed` — Receives the request context array and the next middleware closure. MUST call `$next($request)` to continue the pipeline or return/emit a response to terminate it.

---

### 2.2 — Authentication Middleware & Session Guard

- [ ] **File:** `src/Core/Middleware/AuthMiddleware.php`
  - **Implements:** `MiddlewareInterface`
  - **Objective:** Intercepts every incoming HTTP request. Validates the session-based identity via `GuardInterface`. If the user is unauthenticated, terminates the pipeline. Must distinguish API requests (responds with HTTP 401 JSON) from browser requests (redirects to the login portal).
  - **Required methods:**
    - `handle(array $request, \Closure $next): mixed`
    - `isApiRequest(array $request): bool` — Returns `true` if the `Accept` header contains `application/json` or the `X-Requested-With` header is present.
    - `sendUnauthorizedResponse(): void` — Emits a standard JSON error envelope: `{ "error": "Unauthenticated", "code": 401 }` and terminates.
    - `redirectToLogin(): void` — Issues a `Location` redirect to the login portal URL.

- [ ] **File:** `src/Core/Middleware/CsrfMiddleware.php`
  - **Implements:** `MiddlewareInterface`
  - **Objective:** Generates and validates CSRF tokens for all non-GET state-changing browser-originated requests. API requests authenticated via token (not session) are exempt.
  - **Required methods:**
    - `handle(array $request, \Closure $next): mixed`
    - `generateToken(): string` — Generates a cryptographically secure random token and stores it in the session.
    - `validateToken(string $token): bool` — Constant-time comparison against the session-stored token using `hash_equals()`.
    - `getTokenFromRequest(array $request): ?string` — Extracts the token from `X-CSRF-Token` header or `_token` form field.

- [ ] **File:** `src/Core/Middleware/RateLimitMiddleware.php`
  - **Implements:** `MiddlewareInterface`
  - **Objective:** Enforces per-IP and per-user rate limiting on sensitive endpoints (login, all API routes). Uses the `dx_sessions` table or configurable in-memory counters as the counter store.
  - **Required methods:**
    - `handle(array $request, \Closure $next): mixed`
    - `tooManyAttempts(string $key, int $maxAttempts): bool`
    - `incrementAttempts(string $key): void`
    - `resetAttempts(string $key): void`
    - `sendThrottleResponse(): void` — Emits HTTP 429 with `Retry-After` header.

- [ ] **File:** `src/Core/Middleware/SessionGuard.php`
  - **Implements:** `GuardInterface`
  - **Objective:** Concrete `GuardInterface` implementation using PHP's native session engine with the database-backed session driver (`dx_sessions` table via `DBALWrapper`).
  - **Required methods:** All methods from `GuardInterface`, plus:
    - `attempt(string $email, string $password): bool` — Fetches the user by email, calls `UserModel::verifyPassword()`, checks `isActive()`, starts the session on success via `login()`, increments the failed login counter on failure.
    - `recallFromSession(): ?AuthenticatableInterface` — Reconstructs the authenticated user object from session-cached data. Performs a full DB refresh on a configurable interval (e.g., every 5 minutes) to pick up role/permission changes without requiring re-login.

---

### 2.3 — RBAC/ABAC Engine (Traits)

- [ ] **File:** `src/Core/Traits/HasRoles.php` [TRAIT]
  - **Objective:** Provides role-checking capabilities to any consuming class (primarily `DXController`). Reads roles from the `AuthenticatableInterface` user object sourced from the injected `GuardInterface`.
  - **Required methods:**
    - `hasRole(string $roleName): bool`
    - `hasAnyRole(array $roleNames): bool`
    - `hasAllRoles(array $roleNames): bool`
    - `getRoles(): array` — Returns the authenticated user's role name array.

- [ ] **File:** `src/Core/Traits/HasPermissions.php` [TRAIT]
  - **Objective:** Provides granular, resolved permission-checking. Expands the current user's roles through the RBAC lookup table (`dx_role_permissions`) and caches the result per request to prevent N+1 DB queries.
  - **Required methods:**
    - `can(string $permissionKey): bool` — Primary check. Resolves `resource:action` string against the computed permission set. `ROLE_SUPER_ADMIN` always returns `true`.
    - `cannot(string $permissionKey): bool` — Strict inverse of `can()`.
    - `canAny(array $permissionKeys): bool`
    - `canAll(array $permissionKeys): bool`
    - `getPermissions(): array` — Returns the flattened, deduplicated permission key array for the current user.
    - `resolvePermissions(array $roles): array` — Performs the DB lookup via `DBALWrapper` to join roles → `dx_role_permissions` → `dx_permissions`. Result is cached in a protected instance property for the lifetime of the request.

- [ ] **File:** `src/Core/Traits/HasAbacContext.php` [TRAIT]
  - **Objective:** Extends the permission system with Attribute-Based Access Control. Allows permission checks to be scoped to a specific named context (e.g., "can this user approve cases ONLY within Business Unit 5?").
  - **Required methods:**
    - `canInContext(string $permissionKey, string $contextType, string $contextId): bool` — Returns `true` if the user has a role assignment in `dx_user_roles` with the matching `context_type` and `context_id` that grants the specified permission.
    - `getContextualRoles(string $contextType, string $contextId): array` — Queries `dx_user_roles` filtered by `context_type` and `context_id`. Returns an array of role name strings.

---

### 2.4 — Layout Service (The Security Gate)

- [ ] **File:** `src/Core/LayoutService.php`
  - **Objective:** The most security-critical class in the framework. Acts as the mandatory final gatekeeper before any JSON payload is serialized and transmitted to the client. Iterates over the `uiResources` array in the 4-node Metadata Bridge payload and completely removes (prunes) any component for which the current authenticated user lacks the required permission. The client MUST NEVER receive a component it is not authorized to see — not even in a hidden or disabled state.
  - **Design constraint:** This class is called exclusively by `DXController::buildResponse()` as the very last operation before `json_encode()`. It MUST NOT be bypassable by any subclass or override.
  - **Required methods:**
    - `__construct(GuardInterface $guard)` — Accepts the current auth guard to access the authenticated user object.
    - `prunePayload(array $payload): array` — The primary entry point. Receives the complete 4-node Metadata Bridge array. Returns a new array with all unauthorized `uiResources` components excised. MUST NOT mutate the input array.
    - `isAllowed(array $component, AuthenticatableInterface $user): bool` — Evaluates a single component descriptor's `required_permission` field (if present) against the user's resolved permission set. Returns `true` if: (a) no `required_permission` is specified (public component) or (b) the user has the required permission. Returns `false` otherwise.
    - `pruneComponentTree(array $components, AuthenticatableInterface $user): array` — Recursively filters a nested component array, applying `isAllowed()` at every level. If a parent component is pruned, its entire child subtree is also removed without inspection.
    - `logPruningEvent(string $componentKey, string $requiredPermission, AuthenticatableInterface $user): void` — Appends a `PAYLOAD_PRUNED` record to the audit log via the PSR-3 logger at `INFO` level, including the user ID, the component key, and the required permission that was not met.

- [ ] **File:** `public/api/rbac_admin.php`
  - **Objective:** REST endpoint for the Access Management Portal. Provides CRUD operations for roles, permissions, and user-role assignments. All routes MUST require the `rbac:manage` permission, verified by the `LayoutService` and `AuthMiddleware`.
  - **Middleware pipeline executed:** `AuthMiddleware → RateLimitMiddleware → PermissionCheck(rbac:manage)`
  - **Supported HTTP routes:**
    - `GET /roles` — List all roles with their associated permissions.
    - `POST /roles` — Create a new non-system role.
    - `PUT /roles/{id}` — Update a role's display name and description.
    - `DELETE /roles/{id}` — Delete a role if `is_system = false`.
    - `GET /permissions` — List all permissions, grouped by `category`.
    - `POST /roles/{id}/permissions` — Bulk-assign permission keys to a role (replaces existing).
    - `DELETE /roles/{id}/permissions/{permKey}` — Revoke a single permission from a role.
    - `GET /users/{id}/roles` — List a user's current role assignments (including ABAC context).
    - `POST /users/{id}/roles` — Assign a role to a user with optional ABAC `context_type` and `context_id`.
    - `DELETE /users/{id}/roles/{roleId}` — Revoke a role from a user.

---

## Phase 3: Core Orchestrator & State Machine

> **Objective:** Build the central `DXController` abstract orchestrator and the `DxWorklistService`. Wire together the eTag optimistic locking lifecycle, the dirty state extraction pipeline, and the mandatory 3-method lifecycle contract (`preProcess → getFlow → postProcess`). Provide the annotated App-plane example DX controllers. This phase implements the stateful heart of the framework.
>
> **Phase Gate:** Phase 4 cannot start until a full end-to-end round-trip — HTTP POST in, 4-node JSON payload out with eTag header — is validated via an integration test using a concrete App-plane DX controller against a live database.

---

### 3.1 — Abstract DX Controller (Core Orchestrator)

- [ ] **File:** `src/Core/DXController.php` [ABSTRACT]
  - **Objective:** The most important class in the framework. The abstract core orchestrator that all application-plane case controllers MUST extend. Defines the mandatory 3-method lifecycle contract and implements the full request-response pipeline: eTag validation → preProcess → getFlow → buildResponse (with pruning) → postProcess → sendResponse.
  - **Uses traits:** `HasRoles`, `HasPermissions`, `HasAbacContext`
  - **Required abstract methods (MUST be implemented by every `/src/App/DX/` controller):**
    - `preProcess(): void` — Runs before `getFlow()`. Used for input validation, dirty-state hydration, precondition checks. MUST throw `ValidationException` on invalid input to halt the pipeline before any state is changed.
    - `getFlow(): array` — The primary business logic method. Determines the current case stage, process, and step. Returns the raw (pre-pruned) `uiResources` component descriptor array for the current step. MUST return "Product Info" (formatted, human-readable strings), not raw database codes.
    - `postProcess(): void` — Runs after `buildResponse()` but before `sendResponse()`. Used exclusively for side effects: dispatching async jobs, triggering webhooks, updating linked records, emitting audit events. MUST NOT alter the already-built response payload.
  - **Required concrete methods (implemented in Core, NOT fully overridable without calling `parent::`):**
    - `handle(array $requestData): void` — The main pipeline dispatcher. Orchestrates the full sequence: `validateETag() → preProcess() → getFlow() → buildResponse() → postProcess() → sendResponse()`. Wraps each step in structured exception handling as defined in §3.1 Error Handling.
    - `validateETag(string $caseId, string $clientETag): void` — Reads the `If-Match` HTTP header value. Fetches the server-side `e_tag` from `dx_cases` via `CaseModel`. Throws `ETagMismatchException` (triggering HTTP 412) if the values do not match exactly. A missing `If-Match` header on a non-CREATE action MUST also throw `ETagMismatchException`.
    - `refreshETag(string $caseId): string` — Generates a new `e_tag` value using `hash_hmac('sha256', $caseId . microtime() . $updatedAt, APP_KEY)` and persists it atomically to `dx_cases` within the same `DBALWrapper::transactional()` block as the case data save.
    - `buildResponse(array $uiResources): array` — Assembles the canonical 4-node Metadata Bridge payload: `{ data, uiResources, nextAssignmentInfo, confirmationNote }`. Calls `LayoutService::prunePayload()` as the final operation before returning. MUST set the `data` node using "Product Info" formatted values.
    - `sendResponse(array $payload, int $httpStatusCode = 200): void` — JSON-encodes the pruned payload with `JSON_THROW_ON_ERROR`. Sets `Content-Type: application/json`. Sets the `ETag` response header with the newly minted eTag value. Emits the HTTP status code. Terminates execution after output.
    - `getDirtyState(): array` — Extracts the `dirty_state` node from the current request body. Returns `[]` if the node is absent (e.g., initial case load).
    - `getCaseId(): ?string` — Extracts `case_id` from the current request body. Returns `null` for case creation requests.
    - `getCurrentUser(): AuthenticatableInterface` — Returns the authenticated user from the injected `GuardInterface`. Throws `AuthenticationException` if no user is present.
    - `fail(string $message, int $httpCode = 400, array $errors = []): void` — Immediately emits a structured JSON error envelope `{ "error": $message, "code": $httpCode, "errors": $errors }` and terminates execution. Used for recoverable user-facing errors.
  - **Error handling matrix (strictly enforced inside `handle()`):**
    - `ETagMismatchException` → HTTP 412, log to `dx_case_history` with action `ETAG_CONFLICT`
    - `ValidationException` → HTTP 422, return field-level errors in `errors[]`
    - `AuthenticationException` → HTTP 401
    - `\Throwable` (unhandled) → HTTP 500, full trace logged at `ERROR` level, sanitized message sent to client (full trace only when `APP_DEBUG=true`)

---

### 3.2 — Worklist Service (Core)

- [ ] **File:** `src/Core/DxWorklistService.php`
  - **Objective:** The core service class implementing all "basket" (worklist/work queue) business logic. This is a Core-plane service that operates exclusively on framework tables (`dx_assignments`, `dx_cases`, `dx_case_history`). Contains zero application-specific logic. Used by `WorkDashboardDX`, `WorkLifeCycleDX`, and directly by `/public/api/worklist.php`.
  - **Required methods:**
    - `getPersonalWorklist(string $userId, array $filters = []): array` — Returns all assignments where `status = 'active'` AND `assigned_to_user = $userId`. Supports optional `$filters` for status, deadline, priority.
    - `getGroupQueue(string $roleName, array $filters = []): array` — Returns all assignments where `status = 'pending'` AND `assigned_to_role = $roleName`. Supports optional filters.
    - `claimAssignment(string $assignmentId, string $userId): bool` — Atomically (via `DBALWrapper::transactional()`) sets `assigned_to_user = $userId`, changes `status` from `pending` to `active`, sets `started_at = NOW()`. Logs `ASSIGNMENT_CLAIMED` to `dx_case_history`. Fails (returns `false`) if `assigned_to_user` is already set.
    - `releaseAssignment(string $assignmentId, string $userId): bool` — Atomically resets `assigned_to_user` to `NULL`, reverts `status` to `pending`, clears `started_at`. Logs `ASSIGNMENT_RELEASED`. MUST fail if the `$userId` is not the current claimant and is not a `ROLE_ADMIN` or `ROLE_SUPER_ADMIN`.
    - `logEvent(string $caseId, string $action, string $actorId, array $details = [], ?string $assignmentId = null): void` — Appends an immutable record to `dx_case_history` via `DBALWrapper::insert()`. MUST never throw; wraps errors in a `WARNING` log entry.
    - `getCaseHistory(string $caseId): array` — Returns all `dx_case_history` records for a case, ordered by `occurred_at DESC`.
    - `getAssignmentSummary(string $userId): array` — Returns aggregate counts for the current user's assignments, formatted as "Product Info": `{ "my_active": 5, "my_overdue": 2, "my_due_today": 1 }`. Used by Work Dashboard widgets.

---

### 3.3 — Application-Plane DX Controller Scaffolds

> Concrete `DXController` subclasses in `/src/App/DX/`. These are in the Mutable Application Plane and serve as both functional OOTB features and annotated developer reference implementations.

- [ ] **File:** `src/App/DX/SampleCaseDX.php`
  - **Objective:** A fully documented, annotation-rich reference implementation of a concrete `DXController` subclass. Demonstrates the canonical and correct usage of all three lifecycle methods. Implements a simple multi-step approval workflow as the developer "Hello World" example.
  - **Stages modeled (must be fully implemented):** `INTAKE` → `REVIEW` → `PENDING_APPROVAL` → `RESOLVED`
  - **Must demonstrate:** eTag validation, dirty state extraction, `uiResources` descriptor construction, `nextAssignmentInfo` assembly, and `confirmationNote` usage.

- [ ] **File:** `src/App/DX/AnonymousIntakeDX.php`
  - **Objective:** A specialized `DXController` subclass for the **Initiate Case Button** OOTB capability. Handles anonymous case intake before authentication is required. Implements a reduced `getFlow()` that returns ONLY components with no `required_permission` or `required_permission: 'public'`. On first-step submission, creates a partial `dx_cases` record with `status = 'ANONYMOUS_INTAKE'` and triggers an authentication challenge.
  - **Design constraint:** `preProcess()` MUST NOT call the standard `AuthMiddleware` validation chain. It MUST enforce its own IP-based rate limiting via `RateLimitMiddleware`.

---

## Phase 4: APIs, Routing & Background Jobs

> **Objective:** Build the native PHP Router, wire the three public REST API entry-point files, and build the full async background job infrastructure including the database-backed queue, the cron-driven `QueueWorker`, the Guzzle-powered webhook dispatcher, the PhpSpreadsheet ETL importer, and the Dompdf PDF generator.
>
> **Phase Gate:** Phase 5 (JS Runtime) cannot start until the full `dx.php` round-trip — POST in, 4-node JSON + ETag header out — is validated end-to-end in an integration test. All job queue integration tests (including atomic claim and stale release) must pass.

---

### 4.1 — Native PHP Router

- [ ] **File:** `src/Core/Router.php`
  - **Objective:** A lightweight, dependency-free HTTP router. Dispatches incoming requests to the correct public API entry point or internal handler based on HTTP method and URI path. Does not use external routing libraries or configuration files — routes are registered programmatically via method calls.
  - **Required methods:**
    - `get(string $path, callable|string $handler): void` — Registers a GET route.
    - `post(string $path, callable|string $handler): void` — Registers a POST route.
    - `put(string $path, callable|string $handler): void`
    - `delete(string $path, callable|string $handler): void`
    - `dispatch(array $serverVars): void` — Extracts `REQUEST_METHOD` and `REQUEST_URI` from `$_SERVER`. Strips query strings from the URI. Matches against registered routes. Calls `notFound()` or `methodNotAllowed()` if no match.
    - `notFound(): void` — Emits HTTP 404 with a standard JSON error envelope.
    - `methodNotAllowed(): void` — Emits HTTP 405 with `Allow` header listing permitted methods.
    - `extractUriParams(string $pattern, string $uri): array` — Parses named route parameters from URI patterns using the `{paramName}` syntax.
    - `resolveDxId(array $requestBody): ?string` — Extracts the `dx_id` parameter from the decoded JSON request body. Used by `public/api/dx.php` to dynamically instantiate the correct App-plane `DXController` subclass.

---

### 4.2 — Public REST API Entry Points

- [ ] **File:** `public/api/dx.php`
  - **Objective:** The primary REST endpoint for all case flow execution. Receives POST requests containing `{ dx_id, case_id, action, dirty_state }` with the `If-Match` eTag in the HTTP header. Uses `Router::resolveDxId()` to dynamically instantiate the correct `DXController` subclass from `/src/App/DX/`. Passes the full decoded request body to `DXController::handle()`.
  - **HTTP Method:** POST only.
  - **Middleware pipeline (strictly ordered):** `RateLimitMiddleware → AuthMiddleware → CsrfMiddleware → DXController::handle()`
  - **Anonymous exception:** When `dx_id = 'AnonymousIntakeDX'`, `AuthMiddleware` MUST be bypassed and `AnonymousIntakeDX::handle()` is called directly with only `RateLimitMiddleware` applied.
  - **Error handling:** `ETagMismatchException` → 412. `ValidationException` → 422. `AuthenticationException` → 401. Unhandled `\Throwable` → 500 with sanitized message.
  - **Response contract:** MUST always return the 4-node Metadata Bridge JSON. MUST always set the `ETag` response header on every successful (2xx) response.

- [ ] **File:** `public/api/worklist.php`
  - **Objective:** REST endpoint for all assignment and worklist management operations. Delegates all data operations to `DxWorklistService`.
  - **Middleware pipeline:** `AuthMiddleware → RateLimitMiddleware`
  - **Supported routes:**
    - `GET /` — Fetch the current user's personal worklist (active assignments formatted as Product Info).
    - `GET /queue/{roleName}` — Fetch the group work queue for a role (requires `worklist:claim` permission, verified inline).
    - `POST /claim/{assignmentId}` — Claim an assignment from a group queue.
    - `POST /release/{assignmentId}` — Release a claimed assignment back to the queue.
    - `GET /case/{caseId}/history` — Fetch the full, immutable audit trail for a case (requires `case:read` permission).

---

### 4.3 — Background Job Infrastructure

- [ ] **File:** `src/Core/Jobs/JobInterface.php` [INTERFACE]
  - **Objective:** The contract every async job class MUST implement.
  - **Required methods:**
    - `handle(): void` — The job's primary execution logic.
    - `failed(\Throwable $exception): void` — Called by `QueueWorker` after all retry attempts are exhausted. Used for cleanup or notifications.
    - `getQueue(): string` — Returns the queue name this job belongs to.
    - `getMaxAttempts(): int` — Returns the maximum retry attempt count.
    - `getBackoffSeconds(): int` — Returns the delay in seconds before a failed job becomes available for retry.

- [ ] **File:** `src/Core/Jobs/AbstractJob.php` [ABSTRACT]
  - **Objective:** Base class providing default implementations of `getQueue()` (`'default'`), `getMaxAttempts()` (3), and `getBackoffSeconds()` (60). Provides a protected `array $payload` property hydrated by `QueueWorker` before `handle()` is invoked. All application-specific jobs extend this class.

- [ ] **File:** `src/Core/Jobs/JobDispatcher.php`
  - **Objective:** The synchronous interface for enqueueing jobs onto the `dx_jobs` table. Any Core or App class calls `JobDispatcher::dispatch()` to schedule async work.
  - **Required methods:**
    - `dispatch(JobInterface $job, int $delaySeconds = 0): string` — Serializes the job class FQCN and its payload to `dx_jobs` via `JobModel::save()`. Sets `available_at = NOW() + $delaySeconds` for delayed dispatch. Returns the new job UUID.
    - `dispatchNow(JobInterface $job): void` — Synchronous fallback. Bypasses the queue and calls `$job->handle()` immediately. Used automatically when `QUEUE_DRIVER=sync` in the environment config.
    - `cancel(string $jobId): bool` — Sets `status = 'cancelled'` on a job if it is still in `pending` state. Returns `false` if the job has already been claimed or completed.

- [ ] **File:** `src/Core/Jobs/QueueWorker.php`
  - **Objective:** The queue consumer daemon. Polls `dx_jobs` for available pending jobs, claims them atomically (preventing duplicate processing in a multi-node cluster), executes them, and manages retry logic and failure recording.
  - **Required methods:**
    - `work(string $queue = 'default', int $sleepSeconds = 5): void` — Main run loop. Continuously polls for available jobs using `processNext()`, sleeps `$sleepSeconds` between empty polls. Must handle `SIGTERM` gracefully (finishes the current job before exiting).
    - `processNext(string $queue): bool` — Claims and processes one job. Returns `true` if a job was found and processed, `false` if the queue was empty.
    - `claimJob(string $queue): ?JobModel` — Uses `JobModel::claimNextPending()` inside `DBALWrapper::transactional()` to atomically select the next eligible job. Prevents race conditions in multi-node environments using the `QUEUE_WORKER_ID` env var as the reservation marker.
    - `executeJob(JobModel $jobRecord): void` — Resolves the FQCN from `job_class`, deserializes the `payload`, hydrates `$job->payload`, and calls `$job->handle()`. On success: calls `JobModel::markCompleted()`. On failure: increments `attempts`, schedules retry by setting `available_at = NOW() + getBackoffSeconds()`, or calls `handleJobFailure()` if `max_attempts` is exceeded.
    - `handleJobFailure(JobModel $jobRecord, \Throwable $e): void` — Records `error_message` and `error_trace` via `JobModel::markFailed()`. Calls `$job->failed($e)`. Logs at `ERROR` level.

- [ ] **File:** `bin/worker`
  - **Objective:** The CLI entry point for the queue worker daemon. Bootstraps the application kernel and starts `QueueWorker::work()`. Designed to be called by a cron job every minute.
  - **Required cron entry (to be documented in comments):** `* * * * * php /path/to/dx-engine/bin/worker >> /storage/logs/worker.log 2>&1`
  - **Required behaviors:**
    - MUST acquire a file-based mutex lock via `flock()` on a `.worker.lock` file in `/storage/cache/` to prevent overlapping cron executions. If the lock cannot be acquired, the process exits silently.
    - MUST handle `SIGTERM` gracefully by setting a stop flag that is checked after each job completes.
    - MUST log a start event (with `QUEUE_WORKER_ID`) at `INFO` level when the lock is acquired and a stop event when the lock is released.
    - MUST call `JobModel::releaseStaleJobs()` once at startup to reset any jobs orphaned by a previous crashed worker.

---

### 4.4 — Concrete Background Job Implementations

- [ ] **File:** `src/Core/Jobs/WebhookDispatchJob.php`
  - **Extends:** `AbstractJob`
  - **Objective:** Concrete async job dispatched by `DXController::postProcess()` when a case action matches a registered event in `dx_webhooks`. Uses Guzzle to POST the case payload to the registered external URL with exponential backoff retry.
  - **Required `handle()` logic:**
    - Fetch the registered webhook configuration from `dx_webhooks` using the `webhook_id` from `$this->payload`.
    - Build the outgoing JSON body: `{ event_type, case_id, case_reference, case_data, occurred_at }`.
    - If `secret_key` is configured: compute `HMAC-SHA256` of the serialized body and attach it as the `X-DX-Signature` request header.
    - Send the Guzzle POST request with a configurable timeout.
    - On any outcome (success or Guzzle exception): insert a record into `dx_webhook_logs` with the HTTP status, response body snippet, attempt number, and duration.
  - **Required `failed()` logic:** Insert a terminal failure record into `dx_webhook_logs` with the error details and final attempt count.
  - **`getBackoffSeconds()` implementation:** Implement exponential backoff — attempt 1: 60s, attempt 2: 300s, attempt 3: 3600s. Formula: `60 * (5 ** ($attempts - 1))`.

- [ ] **File:** `src/Core/Jobs/SpreadsheetImportJob.php`
  - **Extends:** `AbstractJob`
  - **Objective:** Concrete async job for Excel/CSV bulk import using `PhpSpreadsheet`. Maps spreadsheet rows to a target `DataModel` entity via a configurable column-to-field map, validates each row, and persists records via `DataModel::save()`. Reports per-row errors without halting the batch.
  - **Required `handle()` logic:**
    - Load the file from `$this->payload['file_path']` using `PhpSpreadsheet\IOFactory::load()`.
    - Resolve the target model FQCN from `$this->payload['target_model']`.
    - Iterate all rows (skip the header row if `$this->payload['has_header_row'] === true`).
    - For each row: apply `$this->payload['column_map']` (e.g., `['A' => 'email', 'B' => 'display_name']`) to build the attribute array. Call `DataModel::fill()`. Validate required fields. Call `DataModel::save()`. Collect errors per row without stopping.
    - Write a JSON summary file to `storage/exports/{jobId}_import_result.json` containing: `{ total_rows, success_count, error_count, errors: [{ row, message }] }`.
  - **Required payload keys:** `file_path`, `target_model` (FQCN), `column_map` (array), `has_header_row` (bool)

- [ ] **File:** `src/Core/Jobs/PdfGenerationJob.php`
  - **Extends:** `AbstractJob`
  - **Objective:** Concrete async job for server-side PDF document generation using `Dompdf`. Merges a case's `case_data` with a named HTML template and outputs a PDF file to `storage/exports/`.
  - **Required `handle()` logic:**
    - Load the case record from `dx_cases` using `$this->payload['case_id']` via `CaseModel`.
    - Load the HTML template from `$this->payload['template_path']` (relative to `/templates/`).
    - Perform string replacement of `{{ variable_name }}` placeholders in the template with the corresponding values from `case_data`.
    - Instantiate `Dompdf\Dompdf`, call `loadHtml()`, `render()`, and `output()`.
    - Write the PDF to `storage/exports/{caseReference}_{timestamp}.pdf`.
    - Update the `dx_cases` record with the generated PDF file path in `case_data`.
  - **Required payload keys:** `case_id`, `template_path`

---

## Phase 5: Frontend Vanilla JS Runtime Engine

> **Objective:** Build the complete client-side JavaScript runtime. All files reside in `/public/js/`. Every module is a plain ES2020+ vanilla JS file — no build tools, no transpilers, no bundlers. Bootstrap 5 is loaded via CDN. Each module is a self-contained class or singleton exported to the global `window.DX` namespace.
>
> **Phase Gate:** Phase 6 (OOTB Capabilities) cannot start until the full fetch-render-submit pipeline is verified as working end-to-end in a live browser session against a running backend, including eTag header round-trip and VisibilityEngine toggling.

---

### 5.1 — State Manager

- [ ] **File:** `public/js/StateManager.js` [JS]
  - **Objective:** The authoritative client-side state store. Manages the single "dirty state" object holding all unpersisted form field values between render cycles.
  - **Critical design constraint:** The dirty state is strictly ephemeral — it MUST NEVER be written to `localStorage`, `sessionStorage`, or any browser storage API. It lives only in-memory for the current page session. On a full page reload, it is rebuilt from the server's `data` node returned by the next `DXInterpreter.fetch()` call.
  - **Required public API (all methods on a singleton object `window.DX.StateManager`):**
    - `get(key: string): any` — Returns the current value for a single field key.
    - `set(key: string, value: any): void` — Sets a single field value and dispatches a `dx:statechange` custom DOM event on `document`.
    - `setAll(data: object): void` — Bulk-replaces the entire dirty state from the server's `data` node. Dispatches `dx:statechange`.
    - `getAll(): object` — Returns a shallow clone of the entire dirty state map. Used by `DXInterpreter` when assembling the POST request body.
    - `clear(): void` — Resets the state map to an empty object. Called on case close, logout, or navigation away.
    - `patch(key: string, partialValue: any): void` — Deep-merges a partial object value into a nested key.
    - `subscribe(callback: Function): Function` — Adds a listener to the `dx:statechange` DOM event. Returns an unsubscribe function.

---

### 5.2 — Component Registry

- [ ] **File:** `public/js/ComponentRegistry.js` [JS]
  - **Objective:** A declarative mapping engine translating the server's `component_type` string (from `uiResources`) to a render function that outputs valid Bootstrap 5 HTML markup. No component is hardcoded anywhere else. The registry is extensible — application-plane pages can call `register()` to override or add component types.
  - **Required public API (singleton object `window.DX.ComponentRegistry`):**
    - `register(type: string, renderFn: Function): void` — Registers a `component_type` → render function mapping. Calling `register` with an existing type REPLACES the existing renderer (enables developer overrides).
    - `render(component: object, state: object): string` — Looks up the `component.component_type` in the registry and invokes the matching render function, passing the component descriptor and current dirty state. Returns an HTML string. If the type is unregistered, returns a `<div class="alert alert-warning">` indicating an unknown component type (never throws).
    - `renderAll(components: Array<object>, state: object): string` — Iterates the component array and concatenates all rendered HTML strings into a single result.
    - `isRegistered(type: string): boolean`
  - **Required OOTB component types (registered by default in `ComponentRegistry.js`):**
    - `text_input` → `<input type="text" class="form-control">` with `id`, `name`, `value`, `placeholder`, `data-*` validation attributes
    - `number_input` → `<input type="number" class="form-control">`
    - `email_input` → `<input type="email" class="form-control">`
    - `textarea` → `<textarea class="form-control">`
    - `select_dropdown` → `<select class="form-select">` with `<option>` elements from `component.options[]`
    - `checkbox` → `<div class="form-check"><input type="checkbox" class="form-check-input">`
    - `radio_group` → Bootstrap 5 form-check radio group from `component.options[]`
    - `date_picker` → `<input type="date" class="form-control">`
    - `datetime_picker` → `<input type="datetime-local" class="form-control">`
    - `file_upload` → `<input type="file" class="form-control">`
    - `display_text` → `<p class="form-text text-body-secondary">` (read-only Product Info label)
    - `section_header` → `<h5 class="fw-semibold border-bottom pb-2 mb-3">`
    - `data_table` → `<table class="table table-striped table-hover table-bordered">` with `<thead>` and `<tbody>` from `component.rows[]`
    - `alert_banner` → `<div class="alert alert-{component.variant}">` (variant: info|success|warning|danger)
    - `button_primary` → `<button type="button" class="btn btn-primary" data-dx-action="{component.action}">`
    - `button_secondary` → `<button type="button" class="btn btn-secondary" data-dx-action="{component.action}">`
    - `button_danger` → `<button type="button" class="btn btn-danger" data-dx-action="{component.action}">`
    - `card_container` → `<div class="card"><div class="card-body">` with nested children rendered recursively
    - `accordion` → Bootstrap 5 accordion component from `component.sections[]`
    - `modal_trigger` → `<button>` that targets a Bootstrap 5 modal defined in `component.modal_id`
    - `progress_bar` → `<div class="progress"><div class="progress-bar" style="width:{component.value}%">`
    - `badge` → `<span class="badge text-bg-{component.variant}">` with `component.label`
    - `separator` → `<hr class="my-3">`

---

### 5.3 — Visibility Engine

- [ ] **File:** `public/js/VisibilityEngine.js` [JS]
  - **Objective:** Evaluates `visibility_rule` JSON objects on rendered component DOM nodes against the current dirty state to dynamically show or hide components in real time as the user fills in the form. Uses the Bootstrap 5 `d-none` CSS class exclusively for toggling — never inline `style` attributes.
  - **Critical design constraint:** The Visibility Engine operates ONLY on components the server has already permitted to send (post-`LayoutService` pruning). It is a UX convenience layer — it MUST NEVER be used as a security mechanism.
  - **Required public API (singleton `window.DX.VisibilityEngine`):**
    - `evaluate(rule: object, state: object): boolean` — Evaluates a single `visibility_rule` descriptor against the current state object. Returns `true` if the component should be visible (the `d-none` class should be absent).
    - `applyAll(containerElement: HTMLElement, state: object): void` — Queries all elements within `containerElement` that have a `data-visibility-rule` attribute. For each: calls `parseRule()`, calls `evaluate()`, and adds or removes `d-none` accordingly.
    - `parseRule(ruleString: string): object` — JSON-parses the `data-visibility-rule` attribute string. Returns an empty object `{}` on parse failure (treated as "always visible").
    - `subscribeToStateChanges(containerElement: HTMLElement): void` — Listens on `document` for `dx:statechange` events and calls `applyAll(containerElement, StateManager.getAll())` automatically.
  - **Supported atomic rule operators:** `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `contains`, `in`, `not_in`, `empty`, `not_empty`
  - **Compound rule support:** `and` (all conditions must evaluate to `true`) and `or` (at least one condition must evaluate to `true`)
  - **Rule object shape:** `{ "operator": "eq", "field": "status", "value": "approved" }` for atomic rules; `{ "and": [ rule1, rule2 ] }` for compound rules.

---

### 5.4 — Client-Side Validator

- [ ] **File:** `public/js/Validator.js` [JS]
  - **Objective:** Performs fast, symmetric client-side UX validation before a POST request is submitted. "Symmetric" means: the backend's component descriptor defines the rules; the `ComponentRegistry` renders them as `data-*` attributes on the input element; and `Validator.js` reads those same `data-*` attributes at submit time. This prevents a round-trip for obvious user errors but MUST NOT replace server-side validation in `DXController::preProcess()`.
  - **Required public API (singleton `window.DX.Validator`):**
    - `validate(formElement: HTMLElement): ValidationResult` — Validates all inputs within `formElement`. Returns `{ isValid: boolean, errors: { [fieldKey]: string[] } }`.
    - `validateField(inputElement: HTMLElement): string[]` — Validates a single input element against all its `data-*` rule attributes. Returns an array of error strings (empty array if valid).
    - `applyErrorStyles(formElement: HTMLElement, errors: object): void` — Adds the Bootstrap `is-invalid` class to each invalid input and renders a `<div class="invalid-feedback">` element adjacent to it.
    - `clearErrorStyles(formElement: HTMLElement): void` — Removes all `is-invalid` classes and `invalid-feedback` divs within the form element.
  - **Supported `data-*` validation attributes (rendered by `ComponentRegistry`):**
    - `data-required="true"` — Field value must not be empty or whitespace-only.
    - `data-min-length="{n}"` — Minimum character count.
    - `data-max-length="{n}"` — Maximum character count.
    - `data-regex="{pattern}"` — Must match the JavaScript regex pattern.
    - `data-min-value="{n}"` — Numeric minimum (for `number_input`).
    - `data-max-value="{n}"` — Numeric maximum.
    - `data-email="true"` — Must match a standard email regex.
    - `data-match-field="{fieldKey}"` — Must match the current value of another field identified by `fieldKey` (e.g., for password confirmation).

---

### 5.5 — Stepper

- [ ] **File:** `public/js/Stepper.js` [JS]
  - **Objective:** Manages the visual and logical representation of multi-step workflow progression. Reads the `nextAssignmentInfo` node from the server's JSON response to determine step labels, the current step index, and directional navigation. Renders and updates a Bootstrap 5-compatible step indicator component.
  - **Required public API (singleton `window.DX.Stepper`):**
    - `init(containerElement: HTMLElement): void` — Mounts the stepper's DOM structure into the given container element. Must be called once before `render()`.
    - `render(nextAssignmentInfo: object): void` — Builds and injects the step indicator HTML into the container based on `nextAssignmentInfo.steps[]` and `nextAssignmentInfo.current_step_index`. Marks completed, active, and pending steps with appropriate Bootstrap classes and `aria-` attributes.
    - `advance(toStepIndex: number): void` — Visually transitions the stepper forward to the given step index. Applies completed styling to all prior steps.
    - `retreat(toStepIndex: number): void` — Visually transitions the stepper backward.
    - `markStepCompleted(stepIndex: number): void`
    - `markStepError(stepIndex: number): void` — Applies error indicator styling to the given step.
    - `getCurrentStep(): number`
  - **Canonical `nextAssignmentInfo` object shape this module must consume:**
    ```
    {
      steps: [{ label: string, key: string, status: 'completed'|'active'|'pending'|'error' }],
      current_step_index: number,
      is_final_step: boolean,
      next_action_label: string
    }
    ```

---

### 5.6 — DX Interpreter (Main Runtime Orchestrator)

- [ ] **File:** `public/js/DXInterpreter.js` [JS]
  - **Objective:** The central JS runtime orchestrator and the ONLY module that makes HTTP `fetch()` calls to `/public/api/dx.php`. Implements the full **Fetch → Render → Submit** pipeline. Coordinates all other JS modules: parses the 4-node response, passes `uiResources` to `ComponentRegistry`, hydrates `StateManager`, triggers `VisibilityEngine`, updates `Stepper`, displays `confirmationNote`, and wires form submission handlers.
  - **Critical design constraint:** `DXInterpreter` is a singleton per page load. Only one instance exists. It is exposed as `window.DX.Interpreter`.
  - **Required public API:**
    - `init(config: object): void` — Bootstraps the runtime. Accepts `{ containerId: string, dxId: string, caseId: string|null, initialETag: string|null }`. Immediately calls `fetch('load')` to retrieve and render the first step.
    - `fetch(action: string = 'load'): Promise<void>` — Assembles the POST request body `{ dx_id, case_id, action, dirty_state: StateManager.getAll() }`. Sets the `If-Match` header with the current stored eTag. Calls `/public/api/dx.php`. On a successful (2xx) response: extracts and stores the `ETag` response header, then calls `render()`.
    - `render(payload: object): void` — Receives the full 4-node JSON payload. Calls `StateManager.setAll(payload.data)`. Renders `payload.uiResources` via `ComponentRegistry.renderAll()` and injects the resulting HTML into the container DOM node. Calls `Stepper.render(payload.nextAssignmentInfo)`. Calls `VisibilityEngine.applyAll()`. Displays `payload.confirmationNote` (if present and non-empty) as an alert banner. Calls `bindSubmitHandlers()` after DOM injection.
    - `submit(action: string): void` — Called by button click handlers. First calls `Validator.validate()` on the current form. If the result is valid: calls `fetch(action)`. If invalid: calls `Validator.applyErrorStyles()` and halts.
    - `handleError(response: Response): void` — Handles all HTTP error responses. On 412: displays a Bootstrap modal explaining the eTag conflict and offers a "Refresh" button that calls `fetch('load')`. On 401: redirects the browser to the login URL. On 422: calls `Validator.applyErrorStyles()` with the `errors` array from the response body. On 500: renders an `alert_banner` (danger variant) with the sanitized error message.
    - `bindSubmitHandlers(containerElement: HTMLElement): void` — After `render()`, queries all elements with `data-dx-action` attributes in the container and attaches `click` event listeners that call `submit(element.dataset.dxAction)`.
    - `destroy(): void` — Teardown method. Removes all bound event listeners, calls `StateManager.clear()`, and empties the container DOM node. Called when navigating away from a case.

---

## Phase 6: OOTB Capabilities & Dashboards

> **Objective:** Build all five Out-of-the-Box bundled capabilities that ship with the framework. These are fully functional, zero-configuration features for standard deployments. Each OOTB capability consists of a backend App-plane DX controller, a Bootstrap 5 HTML shell template, and a page-specific JS initialization script.
>
> **Phase Gate:** All OOTB capability integration tests MUST pass before the framework is declared feature-complete. The Work Dashboard `ROLE_VIEWER` payload pruning test (`test_dashboard_omits_group_queue_for_viewer_role_user`) is the single most important validation test in the entire test suite.

---

### 6.1 — Work Life Cycle Manager [OOTB]

- [ ] **File:** `src/App/DX/WorkLifeCycleDX.php`
  - **Objective:** The primary DX controller implementing the Work Life Cycle Manager. Orchestrates the full Stage → Process → Step hierarchy for all case types. Reads `stage` and `status` from `dx_cases` and the active assignment from `dx_assignments` to determine which step is currently active. Delegates all worklist mutations to `DxWorklistService`.
  - **Required `getFlow()` logic:**
    - MUST implement stage-gating: a stage cannot begin until the prior stage's terminal step is marked `completed`.
    - MUST support parallel assignments within a single stage (fan-out/fan-in pattern — multiple concurrent assignments in `pending` status that all must complete before the stage advances).
    - MUST assemble `nextAssignmentInfo` correctly with all step labels, statuses, and the `is_final_step` flag.
  - **Reference Stage flow (must be implemented):** `INITIATION → IN_PROGRESS → PENDING_APPROVAL → RESOLVED`

- [ ] **File:** `templates/layouts/work_lifecycle.html`
  - **Objective:** Bootstrap 5 HTML shell for the Work Life Cycle Manager UI. Contains: a case reference header bar (showing case ID, status badge, and SLA indicator), the `<div id="dx-container">` mount point for `DXInterpreter`, a step indicator placeholder for `Stepper.js`, and a Bootstrap 5 offcanvas sidebar for stage history and audit trail.

---

### 6.2 — Work Dashboard [OOTB]

- [ ] **File:** `src/App/DX/WorkDashboardDX.php`
  - **Objective:** DX controller for the Work Dashboard. Returns a `uiResources` payload showing the current user's personal worklist and, for users with `worklist:claim` permission, the group Work Queues for their assigned roles. The Group Queue component MUST include `required_permission: 'worklist:claim'` so that `LayoutService` prunes it for `ROLE_VIEWER` users automatically.
  - **Required `getFlow()` uiResources:** `data_table` for "My Active Assignments"; `data_table` for "Group Queue" (`required_permission: 'worklist:claim'`); `badge` components for overdue/due-today/upcoming counts; `button_primary` for Claim action (`required_permission: 'worklist:claim'`); `button_secondary` for Release action.

- [ ] **File:** `templates/layouts/dashboard.html`
  - **Objective:** Bootstrap 5 shell for the Work Dashboard. Includes: a top navigation bar with user avatar and logout link; a two-column grid layout (worklist table left, summary widget cards right); and the `<div id="dx-container">` mount point.

- [ ] **File:** `public/js/DashboardPage.js` [JS]
  - **Objective:** Page-specific JS initialization script for the Work Dashboard.
  - **Required behaviors:**
    - Calls `window.DX.Interpreter.init({ containerId: 'dx-container', dxId: 'WorkDashboardDX', caseId: null })` on `DOMContentLoaded`.
    - Implements a 60-second interval polling loop using `setInterval` that calls `window.DX.Interpreter.fetch('refresh')` to silently update the worklist data without a full page reload.
    - On page unload (`beforeunload` event), calls `clearInterval` on the polling loop and `window.DX.Interpreter.destroy()`.

---

### 6.3 — Public Portal [OOTB]

- [ ] **File:** `src/App/DX/PublicPortalDX.php`
  - **Objective:** DX controller for the Public Portal capability. Handles anonymous case intake from unauthenticated external users. Operates with a minimal RBAC scope.
  - **Required `preProcess()` logic:** MUST NOT invoke standard `AuthMiddleware` session validation. MUST enforce its own IP-based rate limiting via `RateLimitMiddleware`. MUST validate that the submission does not exceed the configured anonymous submission limit per IP per hour.
  - **Required `getFlow()` behavior:** On first load (no `case_id`): return the intake form with only public components (no `required_permission` or `required_permission: 'public'`). On first submission: create a `dx_cases` record with `status = 'ANONYMOUS_INTAKE'` and trigger an authentication challenge by returning a special `confirmationNote` with `action_required: 'authenticate'`.
  - **Required post-authentication `getFlow()` behavior:** After the user authenticates, transition the case status from `ANONYMOUS_INTAKE` to `OPEN` and return the next step in the standard workflow.

- [ ] **File:** `templates/portals/public_portal.html`
  - **Objective:** Minimal, accessible Bootstrap 5 HTML shell for the Public Portal. Contains: a simple header with the organization logo (configurable via template variable), the `<div id="dx-container">` mount point, and a minimal footer. MUST contain no authenticated navigation elements.

---

### 6.4 — Initiate Case Button [OOTB]

- [ ] **File:** `src/App/DX/AnonymousIntakeDX.php`
  *(Defined and scaffolded in Phase 3 — confirm full OOTB wiring here)*
  - **Objective:** Verify that `AnonymousIntakeDX` is fully wired to the Public Portal flow and that the Initiate Case Button initializes it correctly. Document all required `dx_id` → controller mappings in `Router.php`.

- [ ] **File:** `templates/partials/initiate_case_button.html`
  - **Objective:** A standalone, fully self-contained embeddable Bootstrap 5 HTML snippet containing the Initiate Case Button and the minimal inline JS needed to mount `DXInterpreter` into a target container. MUST be safe to embed inside any external HTML document without causing style conflicts (all styles scoped via Bootstrap utility classes only).

- [ ] **File:** `public/js/InitiateCaseButton.js` [JS]
  - **Objective:** Lightweight JS module managing the Initiate Case Button's complete UX lifecycle.
  - **Required methods (singleton or module pattern):**
    - `mount(buttonSelector: string, config: object): void` — Queries all elements matching `buttonSelector` and attaches click handlers.
    - `openIntakeModal(config: object): void` — Programmatically creates a Bootstrap 5 modal, initializes `DXInterpreter` inside the modal body with `dxId: 'AnonymousIntakeDX'`, and opens the modal.
    - `handleAuthChallenge(modalElement: HTMLElement): void` — When the server returns `confirmationNote.action_required === 'authenticate'`, replaces the modal body content with the embedded login form. On successful authentication, resumes the `DXInterpreter` fetch pipeline for the next step.
    - `closeModal(): void` — Closes and destroys the modal instance. Calls `DXInterpreter.destroy()`.

---

### 6.5 — Access Management Portal [OOTB]

- [ ] **File:** `src/App/DX/RbacAdminDX.php`
  - **Objective:** DX controller for the Access Management Portal. Provides the admin UI for configuring RBAC at the Case, Stage, and Process levels. ALL routes and ALL `uiResources` components in this controller's `getFlow()` MUST include `required_permission: 'rbac:manage'` so that `LayoutService` prunes the entire payload for non-admin users.
  - **Required `getFlow()` views (each is a distinct `step_name`):**
    - `role_list` — `data_table` of all roles with edit/delete buttons.
    - `role_detail` — Form for editing a role's display name, description, and assigned permissions.
    - `permission_list` — `data_table` of all permissions grouped by category.
    - `user_role_assignment` — Searchable user lookup + role assignment form with optional ABAC context fields.

- [ ] **File:** `templates/layouts/rbac_admin.html`
  - **Objective:** Bootstrap 5 shell for the Access Management Portal. Includes: a persistent left sidebar with navigation links for each of the four admin views, a breadcrumb navigation bar, and the `<div id="dx-container">` mount point.

- [ ] **File:** `public/js/RbacAdminPage.js` [JS]
  - **Objective:** Page-specific JS for the RBAC Admin Portal.
  - **Required behaviors:**
    - Calls `window.DX.Interpreter.init({ containerId: 'dx-container', dxId: 'RbacAdminDX', caseId: null })` on `DOMContentLoaded`.
    - Implements client-side view navigation between the four admin views by calling `window.DX.Interpreter.fetch(viewName)` when a sidebar navigation link is clicked, without a full page reload.
    - Updates the URL hash (e.g., `#role_list`) on each navigation for bookmarkability and browser back/forward support.

---

## Phase 7: PHPUnit Testing & Hardening

> **Objective:** Achieve comprehensive test coverage across all security boundaries, every state machine transition, every DBAL driver scenario, and every async job execution path. The target is 100% coverage of defined security and state-machine logic, not 100% line coverage of utility code.
>
> **Phase Gate:** Zero failing PHPUnit tests. PHPStan at Level 8 with zero errors. PHP_CodeSniffer at PSR-12 with zero violations. All tests must pass against all four DBAL drivers (MySQL/MariaDB, PostgreSQL, SQLite, SQL Server).

---

### 7.1 — Test Infrastructure Setup

- [ ] **File:** `phpunit.xml`
  - **Objective:** PHPUnit 11 configuration file. Defines three test suites: `Unit`, `Integration`, `Feature`. Sets test environment overrides: `DB_DRIVER=sqlite`, `DB_NAME=:memory:`, `QUEUE_DRIVER=sync`, `APP_ENV=testing`, `APP_DEBUG=true`. Configures code coverage exclusions for `/vendor/`, `/database/`, `/bin/`.

- [ ] **File:** `tests/Unit/BaseUnitTestCase.php`
  - **Objective:** Abstract base for all Unit tests. Provides reusable `createMock()` helper methods for: `DBALWrapper`, `GuardInterface`, `AuthenticatableInterface`, `JobModel`. Provides a `makeUser(array $overrides = [])` factory that returns a mock `AuthenticatableInterface` with configurable roles and permissions.

- [ ] **File:** `tests/Integration/BaseIntegrationTestCase.php`
  - **Objective:** Abstract base for all Integration tests. Bootstraps the full application kernel against an in-memory SQLite database. Calls `MigrationRunner::migrate()` once before the entire suite (via `setUpBeforeClass()`). Wraps each individual test in a `DBALWrapper::beginTransaction()` / `rollBack()` pair to guarantee test isolation without truncating tables.

---

### 7.2 — Phase 1 Tests: DBAL Agnosticism & Data Models

- [ ] **File:** `tests/Unit/Core/DBALWrapperTest.php` [TEST]
  - `test_select_returns_empty_array_when_no_rows_found()`
  - `test_select_one_returns_null_when_no_row_found()`
  - `test_insert_returns_last_insert_id()`
  - `test_update_returns_affected_row_count()`
  - `test_delete_returns_affected_row_count()`
  - `test_transactional_commits_on_successful_callback()`
  - `test_transactional_rolls_back_and_rethrows_on_exception()`
  - `test_all_select_methods_use_parameterized_queries()` — Asserts that no method constructs SQL by string interpolation with the test input `'; DROP TABLE dx_cases; --`.
  - `test_get_connection_logs_warning_in_non_testing_environment()`

- [ ] **File:** `tests/Unit/Core/DataModelTest.php` [TEST]
  - `test_fill_correctly_maps_camel_case_attributes_via_field_map()`
  - `test_fill_ignores_keys_not_in_field_map()`
  - `test_to_array_returns_all_properties_keyed_by_php_property_name()`
  - `test_to_database_row_uses_column_names_as_keys()`
  - `test_hydrate_returns_correct_model_class_instance()`
  - `test_save_calls_dbal_insert_when_primary_key_is_unset()`
  - `test_save_calls_dbal_update_when_primary_key_is_set()`
  - `test_before_save_hook_is_called_before_dbal_write()`
  - `test_after_save_hook_is_called_after_successful_dbal_write()`
  - `test_delete_calls_dbal_delete_with_primary_key_criteria()`

- [ ] **File:** `tests/Integration/Core/DBALAgnosticismTest.php` [TEST]
  - **Objective:** Verifies that all `DBALWrapper` CRUD operations produce identical results against all four DBAL drivers. Tests run in a loop over `['mysql', 'pgsql', 'sqlite', 'sqlsrv']` using driver-specific test database connections.
  - `test_crud_operations_produce_identical_results_on_all_drivers()`
  - `test_migrations_run_to_completion_on_all_drivers()`
  - `test_transactional_isolation_works_correctly_on_all_drivers()`
  - `test_schema_builder_creates_and_drops_tables_on_all_drivers()`

---

### 7.3 — Phase 2 Tests: IAM & Security Gate

- [ ] **File:** `tests/Unit/Core/LayoutServiceTest.php` [TEST]
  - `test_prune_payload_removes_component_when_user_lacks_required_permission()`
  - `test_prune_payload_retains_component_when_user_has_required_permission()`
  - `test_prune_payload_retains_public_component_that_has_no_required_permission()`
  - `test_prune_component_tree_removes_entire_child_subtree_when_parent_component_is_pruned()`
  - `test_prune_payload_logs_pruning_event_for_every_removed_component()`
  - `test_is_allowed_returns_true_for_super_admin_regardless_of_any_permission()`
  - `test_prune_payload_does_not_mutate_the_original_input_array()`
  - `test_prune_payload_handles_empty_ui_resources_array_without_error()`

- [ ] **File:** `tests/Unit/Core/HasPermissionsTraitTest.php` [TEST]
  - `test_can_returns_true_when_user_role_grants_permission()`
  - `test_can_returns_false_when_no_user_role_grants_permission()`
  - `test_cannot_is_the_strict_inverse_of_can()`
  - `test_can_any_returns_true_when_at_least_one_permission_key_matches()`
  - `test_can_all_returns_false_when_any_one_permission_is_missing()`
  - `test_get_permissions_returns_deduplicated_flattened_permission_set()`
  - `test_permissions_are_cached_in_instance_property_and_db_is_not_queried_twice()`

- [ ] **File:** `tests/Unit/Core/HasAbacContextTraitTest.php` [TEST]
  - `test_can_in_context_returns_true_when_user_has_scoped_role_with_matching_context_id()`
  - `test_can_in_context_returns_false_when_user_role_has_different_context_id()`
  - `test_can_in_context_returns_false_when_user_has_no_assignment_in_context_type()`
  - `test_get_contextual_roles_queries_with_correct_context_type_and_context_id_filters()`

- [ ] **File:** `tests/Unit/Core/AuthMiddlewareTest.php` [TEST]
  - `test_handle_calls_next_closure_when_user_is_authenticated_and_active()`
  - `test_handle_returns_401_json_when_request_is_api_type_and_user_is_unauthenticated()`
  - `test_handle_redirects_to_login_when_request_is_browser_type_and_user_is_unauthenticated()`
  - `test_handle_returns_401_when_authenticated_user_is_inactive()`
  - `test_is_api_request_correctly_detects_application_json_accept_header()`
  - `test_is_api_request_correctly_detects_x_requested_with_header()`

- [ ] **File:** `tests/Unit/Core/SessionGuardTest.php` [TEST]
  - `test_attempt_returns_true_and_starts_session_on_correct_credentials()`
  - `test_attempt_returns_false_when_password_does_not_match()`
  - `test_attempt_increments_failed_login_count_on_incorrect_password()`
  - `test_attempt_returns_false_when_user_account_is_locked()`
  - `test_check_returns_true_when_valid_user_id_is_in_session()`
  - `test_check_returns_false_when_session_is_empty()`
  - `test_logout_destroys_session_data_and_sets_check_to_false()`

---

### 7.4 — Phase 3 Tests: Core Orchestrator & eTag State Machine

- [ ] **File:** `tests/Unit/Core/DXControllerTest.php` [TEST]
  - `test_handle_calls_pre_process_before_get_flow()`
  - `test_handle_calls_post_process_after_build_response()`
  - `test_handle_aborts_pipeline_and_returns_422_when_pre_process_throws_validation_exception()`
  - `test_validate_etag_throws_etag_mismatch_exception_when_client_etag_does_not_match_server()`
  - `test_validate_etag_throws_etag_mismatch_exception_when_if_match_header_is_absent_on_non_create_action()`
  - `test_validate_etag_passes_silently_when_etag_matches()`
  - `test_build_response_calls_layout_service_prune_payload_as_last_operation()`
  - `test_send_response_sets_etag_response_header_on_every_successful_response()`
  - `test_send_response_sets_correct_http_status_code()`
  - `test_get_dirty_state_returns_empty_array_when_dirty_state_node_is_absent()`
  - `test_fail_emits_json_error_envelope_and_terminates_execution()`

- [ ] **File:** `tests/Integration/Api/DxApiTest.php` [TEST]
  - `test_post_to_dx_api_returns_canonical_four_node_metadata_bridge_payload()`
  - `test_post_without_if_match_header_on_non_create_action_returns_http_412()`
  - `test_post_with_mismatched_etag_returns_http_412_and_logs_etag_conflict_to_case_history()`
  - `test_post_with_correct_etag_returns_http_200_with_refreshed_etag_header()`
  - `test_post_when_unauthenticated_returns_http_401()`
  - `test_response_ui_resources_does_not_contain_pruned_components_for_viewer_role_user()`
  - `test_response_ui_resources_contains_all_components_for_admin_role_user()`

---

### 7.5 — Phase 4 Tests: Worklist Service

- [ ] **File:** `tests/Unit/Core/DxWorklistServiceTest.php` [TEST]
  - `test_claim_assignment_atomically_sets_assigned_user_and_active_status()`
  - `test_claim_assignment_returns_false_when_assignment_is_already_claimed_by_another_user()`
  - `test_release_assignment_resets_to_pending_status_and_clears_assigned_user()`
  - `test_release_assignment_returns_false_when_called_by_non_claimant_without_admin_role()`
  - `test_release_assignment_succeeds_when_called_by_admin_regardless_of_claimant()`
  - `test_get_personal_worklist_returns_only_active_assignments_for_the_specified_user()`
  - `test_get_group_queue_returns_only_pending_assignments_for_specified_role()`
  - `test_log_event_inserts_immutable_record_with_correct_fields_to_case_history()`
  - `test_get_assignment_summary_returns_product_info_formatted_counts()`

---

### 7.6 — Phase 4 Tests: Background Jobs, Webhooks & ETL

- [ ] **File:** `tests/Unit/Core/Jobs/QueueWorkerTest.php` [TEST]
  - `test_claim_job_atomically_prevents_double_claim_in_concurrent_scenario()`
  - `test_execute_job_calls_handle_on_correctly_deserialized_job_class()`
  - `test_execute_job_increments_attempt_count_on_failure()`
  - `test_execute_job_marks_job_as_failed_after_max_attempts_are_exhausted()`
  - `test_execute_job_calls_failed_hook_after_max_attempts_are_exhausted()`
  - `test_process_next_returns_false_when_queue_is_empty()`
  - `test_release_stale_jobs_resets_processing_jobs_beyond_the_configured_timeout()`
  - `test_worker_acquires_file_lock_and_exits_silently_if_lock_cannot_be_acquired()`

- [ ] **File:** `tests/Unit/Core/Jobs/WebhookDispatchJobTest.php` [TEST]
  - `test_handle_sends_post_request_to_the_registered_webhook_url()`
  - `test_handle_attaches_hmac_sha256_signature_header_when_secret_key_is_configured()`
  - `test_handle_logs_success_record_to_webhook_logs_table_on_http_200_response()`
  - `test_handle_logs_failure_record_to_webhook_logs_on_guzzle_exception()`
  - `test_failed_hook_inserts_terminal_failure_record_to_webhook_logs()`
  - `test_get_backoff_seconds_implements_exponential_backoff_formula()`

- [ ] **File:** `tests/Unit/Core/Jobs/SpreadsheetImportJobTest.php` [TEST]
  - `test_handle_correctly_maps_spreadsheet_columns_to_model_fields_via_column_map()`
  - `test_handle_skips_header_row_when_has_header_row_flag_is_true()`
  - `test_handle_writes_json_summary_file_to_export_storage_path()`
  - `test_handle_continues_processing_remaining_rows_on_per_row_validation_failure()`
  - `test_summary_file_contains_correct_success_and_error_counts()`

- [ ] **File:** `tests/Unit/Core/Jobs/PdfGenerationJobTest.php` [TEST]
  - `test_handle_replaces_all_placeholder_tokens_in_html_template_with_case_data_values()`
  - `test_handle_outputs_pdf_file_to_correct_path_in_export_storage()`
  - `test_handle_updates_case_data_record_with_generated_pdf_file_path()`
  - `test_handle_throws_when_template_file_does_not_exist()`

---

### 7.7 — Phase 6 Feature Tests: OOTB Capabilities

- [ ] **File:** `tests/Feature/WorkDashboardTest.php` [TEST]
  - `test_dashboard_renders_personal_worklist_data_table_for_all_authenticated_users()`
  - `test_dashboard_renders_group_queue_data_table_for_user_with_worklist_claim_permission()`
  - `test_dashboard_omits_group_queue_data_table_for_role_viewer_user()` — **Critical LayoutService validation. This test MUST verify the component is absent from the JSON payload, not merely hidden.**
  - `test_dashboard_claim_action_delegates_to_worklist_service_claim_assignment()`
  - `test_dashboard_polling_fetch_returns_refreshed_worklist_data_on_second_call()`

- [ ] **File:** `tests/Feature/PublicPortalTest.php` [TEST]
  - `test_anonymous_user_can_load_intake_form_without_an_active_session()`
  - `test_anonymous_intake_submission_creates_case_with_anonymous_intake_status()`
  - `test_portal_returns_auth_challenge_confirmation_note_after_first_step_submission()`
  - `test_portal_transitions_case_to_open_status_after_successful_user_authentication()`
  - `test_anonymous_submission_is_rate_limited_after_configured_threshold_per_ip()`

- [ ] **File:** `tests/Feature/RbacAdminPortalTest.php` [TEST]
  - `test_rbac_admin_portal_is_fully_pruned_for_non_rbac_manage_users()`
  - `test_role_list_view_returns_all_non_system_and_system_roles()`
  - `test_permission_assignment_view_updates_role_permissions_via_rbac_admin_api()`
  - `test_system_role_cannot_be_deleted_via_api()`
  - `test_user_role_assignment_with_abac_context_is_persisted_correctly()`

---

## Phase Gate Summary

| Gate | Prerequisite | Unlocks |
|---|---|---|
| Phase 1 Complete | Autoloader resolves both planes; DBAL integration tests green on all 4 drivers; all 13 migrations executed | Phase 2 |
| Phase 2 Complete | All IAM unit tests green; `LayoutService::isAllowed()` verified by all 8 `LayoutServiceTest` scenarios | Phase 3 |
| Phase 3 Complete | End-to-end eTag round-trip integration test green; `DXControllerTest` all passing | Phase 4 |
| Phase 4 Complete | `DxApiTest` full suite green; `QueueWorkerTest` atomic claim and stale release tests green | Phase 5 |
| Phase 5 Complete | Full Fetch-Render-Submit pipeline verified in live browser; VisibilityEngine toggle confirmed | Phase 6 |
| Phase 6 Complete | All 5 OOTB feature tests green, including critical `test_dashboard_omits_group_queue_for_role_viewer_user` | Phase 7 |
| Phase 7 Complete | Zero PHPUnit failures; PHPStan Level 8 zero errors; PHPCS PSR-12 zero violations; all 4 DBAL drivers green | **RELEASE** |

---

## Appendix A — Canonical 4-Node JSON Metadata Bridge [CONTRACT]

> Every response from `/public/api/dx.php` MUST conform exactly to this shape. This is the authoritative contract between the backend and the JS Runtime.

```json
{
  "data": {
    "case_id": "uuid-string",
    "case_reference": "CASE-00001",
    "case_status_label": "Under Review",
    "owner_display_name": "Jane Smith",
    "sla_due_label": "Due in 2 days",
    "stage_label": "Stage 2 of 4: In Progress"
  },
  "uiResources": [
    {
      "component_type": "section_header",
      "key": "review_header",
      "label": "Case Review Details",
      "required_permission": null,
      "visibility_rule": null,
      "children": []
    },
    {
      "component_type": "textarea",
      "key": "review_notes",
      "label": "Reviewer Notes",
      "placeholder": "Enter your review findings...",
      "required_permission": "case:update",
      "visibility_rule": { "operator": "eq", "field": "status", "value": "IN_REVIEW" },
      "validation": { "required": true, "min_length": 20 },
      "value": null
    }
  ],
  "nextAssignmentInfo": {
    "steps": [
      { "label": "Intake", "key": "intake", "status": "completed" },
      { "label": "Review", "key": "review", "status": "active" },
      { "label": "Approval", "key": "approval", "status": "pending" },
      { "label": "Resolution", "key": "resolution", "status": "pending" }
    ],
    "current_step_index": 1,
    "is_final_step": false,
    "next_action_label": "Submit Review"
  },
  "confirmationNote": {
    "message": null,
    "variant": null,
    "action_required": null
  }
}
```

---

## Appendix B — UI Component Descriptor Shape [CONTRACT]

> Every object in `uiResources[]` MUST conform to this schema. The `children` property enables recursive nesting (e.g., inside a `card_container`).

```json
{
  "component_type": "string (required) — matches a key in ComponentRegistry",
  "key": "string (required) — unique identifier within the current step payload",
  "label": "string (optional) — human-readable, ready-to-display Product Info label",
  "required_permission": "string|null — permission key; null means public component",
  "visibility_rule": "object|null — evaluated by VisibilityEngine.js",
  "validation": "object|null — validation rules rendered as data-* attributes",
  "value": "any — current field value from the data node (read-only display)",
  "options": "array|null — for select_dropdown, radio_group, etc.",
  "action": "string|null — the dx action dispatched when a button is clicked",
  "variant": "string|null — Bootstrap variant (primary, danger, warning, etc.)",
  "rows": "array|null — for data_table component row data",
  "children": "array|null — nested component descriptors (recursive)"
}
```

---

## Appendix C — Proprietary Low-Code Concept Translation Reference

> For any developer unfamiliar with the Pega/Appian concepts this framework is inspired by, the following table maps each low-code concept to its pure PHP paradigm equivalent in this framework.

| Low-Code Concept | Low-Code Syntax | DX Engine Equivalent |
|---|---|---|
| Case | `pxCaseID` | `dx_cases.id` (UUID) + `dx_cases.case_reference` (human ID) |
| Assignment / Work Item | `pxAssignmentKey` | `dx_assignments.id` |
| Data Page / Data Transform | `D_CaseData`, `pyWorkPage` | `DataModel::find()` + `DXController::getDirtyState()` |
| Section / Harness | `pyWorkPage.pySection` | `uiResources[]` component descriptor tree |
| Flow Action | `pxFlowAction` | `action` field in POST body; `data-dx-action` HTML attribute |
| Stage | `pxStage` | `dx_cases.stage` column + `WorkLifeCycleDX` stage-gating logic |
| Work Queue / Worklist | `pxWorkList`, `pxWorkBasket` | `DxWorklistService::getPersonalWorklist()` / `getGroupQueue()` |
| Activity / Utility Rule | `Activity` rule type | `AbstractJob` subclass dispatched by `JobDispatcher` |
| Declare Expression | `Declare Expression` | `VisibilityEngine.js` `visibility_rule` evaluation |
| Access Group | `AccessGroup` | `dx_roles` table + `HasRoles` trait |
| Privilege | `Privilege` | `dx_permissions` table + `HasPermissions` trait |
| Clipboard Page Dirty State | `pyDirtyState` | `StateManager.js` in-memory state object |
| eTag / Optimistic Lock | `pyLockHandle` | `dx_cases.e_tag` column + `DXController::validateETag()` |
| Portal | `Portal rule` | `PublicPortalDX` + `templates/portals/public_portal.html` |
| Correspondence | `Correspondence rule` | `PdfGenerationJob` + `/templates/` HTML merge templates |
