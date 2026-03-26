# DX Engine Framework - Comprehensive Functional Testing Suite

## Overview

This document describes the comprehensive automated functional testing suite for the DX Engine Framework, designed to validate all architectural axioms, core functionality, and business logic defined in the `TODO.md` roadmap.

## Test Structure

The functional test suite is organized into the following categories:

### 1. **Base Functional Test Case** (`BaseFunctionalTestCase.php`)
- Provides foundational setup for all functional tests
- Creates in-memory SQLite database for fast test execution
- Runs all required migrations
- Seeds test data (users, roles, permissions)
- Includes helper methods for common operations
- Validates 4-Node JSON Contract compliance

### 2. **Architectural Axiom Tests**

#### A. **Center-Out Mandate Test** (`CenterOutMandateTest.php`)
Tests Axiom A-01: Backend as sole source of truth

**Test Coverage:**
- ✅ All UI structure comes from backend payload
- ✅ Component labels are Product Info (formatted), not raw codes
- ✅ Visibility rules are backend-driven
- ✅ Permission gating is enforced in payload (server-side pruning)

#### B. **Database Agnosticism Test** (`DatabaseAgnosticismTest.php`)
Tests Axiom A-03: 100% database-agnostic operations

**Test Coverage:**
- ✅ Framework runs on SQLite (memory)
- ✅ Insert operations are dialect-agnostic
- ✅ Update operations are dialect-agnostic
- ✅ Delete operations are dialect-agnostic
- ✅ Select with bound parameters
- ✅ Transaction support (commit/rollback)
- ✅ NULL value handling
- ✅ Platform-aware identifier quoting
- ✅ DBAL exception wrapping

#### C. **Optimistic Locking Test** (`OptimisticLockingTest.php`)
Tests Axiom A-05: eTag validation on every write

**Test Coverage:**
- ✅ Case update requires valid eTag
- ✅ Concurrent update detection (stale eTag)
- ✅ eTag mismatch creates history entry
- ✅ New case creation doesn't require eTag
- ✅ eTag is regenerated on every update
- ✅ Assignment updates also use eTag

#### D. **4-Node JSON Contract Test** (`FourNodeJsonContractTest.php`)
Tests Axiom A-08: Strict 4-node API response structure

**Test Coverage:**
- ✅ All four required nodes present: `data`, `uiResources`, `nextAssignmentInfo`, `confirmationNote`
- ✅ Data node contains Product Info (formatted values)
- ✅ UI Resources contain component definitions
- ✅ Next Assignment Info contains workflow metadata
- ✅ Confirmation Note structure
- ✅ No additional root nodes allowed
- ✅ Empty responses still conform to contract
- ✅ Component types are valid
- ✅ Visibility rules included when applicable

#### E. **Parameterized Queries Test** (`ParameterizedQueriesTest.php`)
Tests Axiom A-10: No SQL injection vulnerabilities

**Test Coverage:**
- ✅ SELECT uses bound parameters
- ✅ SELECT ONE uses bound parameters
- ✅ INSERT with bound parameters
- ✅ UPDATE with bound parameters
- ✅ DELETE with bound parameters
- ✅ Complex queries with multiple parameters
- ✅ IN clause with parameters
- ✅ LIKE clause with parameters
- ✅ SQL injection attempts safely handled
- ✅ NULL parameters handled correctly
- ✅ Transactions with parameterized queries

### 3. **Business Logic Tests**

#### F. **RBAC Functional Test** (`RbacFunctionalTest.php`)
Tests Role-Based Access Control system

**Test Coverage:**
- ✅ User has assigned roles
- ✅ Role has assigned permissions
- ✅ User inherits permissions from roles
- ✅ Standard user has limited permissions
- ✅ Assign new role to user
- ✅ Remove role from user
- ✅ Permission check for specific action
- ✅ Multiple roles aggregate permissions
- ✅ Create custom role
- ✅ Assign permissions to custom role
- ✅ Cascade delete on user deletion

#### G. **Case Lifecycle Test** (`CaseLifecycleTest.php`)
Tests end-to-end case management

**Test Coverage:**
- ✅ Create new case
- ✅ Case status transition
- ✅ Case history records status changes
- ✅ Create assignment for case
- ✅ Complete assignment
- ✅ Reassign assignment to different user
- ✅ Assign to role instead of user
- ✅ Get all assignments for case
- ✅ Get case history
- ✅ Case ownership transfer
- ✅ Cascade delete assignments on case deletion
- ✅ Multiple open assignments (parallel processing)

#### H. **Job Queue Test** (`JobQueueTest.php`)
Tests asynchronous job processing

**Test Coverage:**
- ✅ Dispatch job to queue
- ✅ Reserve job for processing
- ✅ Mark job as completed
- ✅ Job retry on failure
- ✅ Job marked as failed after max attempts
- ✅ Get pending jobs from queue
- ✅ Delayed job execution
- ✅ Queue prioritization
- ✅ Cleanup old completed jobs

#### I. **Webhook Integration Test** (`WebhookIntegrationTest.php`)
Tests webhook system

**Test Coverage:**
- ✅ Register webhook
- ✅ Get active webhooks for event
- ✅ Log webhook dispatch
- ✅ Log failed webhook dispatch
- ✅ Webhook retry attempts
- ✅ Disable webhook
- ✅ Webhook with custom headers
- ✅ Get webhook logs for case
- ✅ Cascade delete logs on webhook deletion

## Running the Tests

### Run All Functional Tests

```bash
# Windows
php vendor\bin\phpunit --testsuite Functional

# Linux/Mac
php vendor/bin/phpunit --testsuite Functional
```

### Run Specific Test Class

```bash
php vendor\bin\phpunit tests\Functional\CenterOutMandateTest.php
```

### Run with Coverage Report

```bash
php vendor\bin\phpunit --testsuite Functional --coverage-html coverage/
```

### Run with Verbose Output

```bash
php vendor\bin\phpunit --testsuite Functional --verbose
```

## PHPUnit Configuration

Update `phpunit.xml` to include the Functional test suite:

```xml
<testsuites>
    <testsuite name="Unit">
        <directory>tests/Unit</directory>
    </testsuite>
    <testsuite name="Integration">
        <directory>tests/Integration</directory>
    </testsuite>
    <testsuite name="Feature">
        <directory>tests/Feature</directory>
    </testsuite>
    <testsuite name="Functional">
        <directory>tests/Functional</directory>
    </testsuite>
</testsuites>
```

## Test Data

All functional tests use in-memory SQLite database with the following seeded data:

### Users
- **user-admin**: Administrator with full permissions
- **user-manager**: Manager with case management permissions
- **user-standard**: Standard user with read-only permissions

### Roles
- **ROLE_ADMIN**: Administrator role
- **ROLE_MANAGER**: Manager role
- **ROLE_USER**: Standard user role

### Permissions
- **case:create**: Create cases
- **case:read**: Read cases
- **case:update**: Update cases
- **case:delete**: Delete cases
- **rbac:admin**: RBAC administration

## Test Helpers

The `BaseFunctionalTestCase` provides the following helper methods:

### Database Helpers
- `createTestCase(array $data = [])`: Create a test case
- `createTestAssignment(string $caseId, array $data = [])`: Create a test assignment

### Assertion Helpers
- `assert4NodeJsonContract(array $response)`: Validate 4-node JSON structure

## Database Schema

The functional tests create the following tables:

1. **dx_migrations**: Migration tracking
2. **dx_users**: User accounts
3. **dx_sessions**: User sessions
4. **dx_roles**: Role definitions
5. **dx_permissions**: Permission definitions
6. **dx_role_permissions**: Role-permission assignments
7. **dx_user_roles**: User-role assignments
8. **dx_cases**: Case records
9. **dx_assignments**: Work assignments
10. **dx_case_history**: Case audit trail
11. **dx_jobs**: Job queue
12. **dx_webhooks**: Webhook configurations
13. **dx_webhook_logs**: Webhook execution logs

## Coverage Goals

The functional test suite aims for:

- **Line Coverage**: > 80%
- **Branch Coverage**: > 75%
- **Method Coverage**: > 90%

## Continuous Integration

These tests should be run:

1. **On every commit** (pre-commit hook)
2. **On every pull request** (CI/CD pipeline)
3. **Before every deployment** (deployment gate)
4. **Nightly** (full regression suite)

## Test Execution Time

Expected execution time for the complete functional test suite:

- **SQLite (in-memory)**: ~5-10 seconds
- **MySQL/MariaDB**: ~15-30 seconds
- **PostgreSQL**: ~15-30 seconds
- **SQL Server**: ~20-40 seconds

## Known Limitations

1. Tests use in-memory SQLite by default for speed
2. Some database-specific features not testable in SQLite
3. Network-dependent tests (webhooks) are mocked
4. Performance tests require separate suite

## Future Enhancements

1. **Multi-database testing**: Run against all supported databases
2. **Performance benchmarks**: Add performance assertion tests
3. **Load testing**: Concurrent user simulation
4. **Security testing**: Penetration test automation
5. **Browser testing**: Selenium integration for UI tests
6. **API contract testing**: OpenAPI/Swagger validation

## Contributing

When adding new functional tests:

1. Extend `BaseFunctionalTestCase`
2. Use seeded test data whenever possible
3. Clean up test data in `tearDown()`
4. Follow naming convention: `test_<what_is_being_tested>`
5. Add descriptive assertions with custom messages
6. Document complex test scenarios
7. Keep tests atomic and independent

## Troubleshooting

### Tests Fail with "Table not found"
**Solution**: Ensure migrations run in `setUp()` method

### Tests Fail with "Foreign key constraint"
**Solution**: Check cascade delete configurations

### Slow Test Execution
**Solution**: Use SQLite in-memory database for functional tests

### Intermittent Failures
**Solution**: Ensure tests don't depend on execution order

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Doctrine DBAL](https://www.doctrine-project.org/projects/dbal.html)
- [DX Engine TODO.md](../TODO.md)
- [Framework Architecture](../docs/ARCHITECTURE.md)

---

**Last Updated**: 2026-03-26  
**Version**: 1.0.0  
**Maintainer**: DX Engine Development Team
