<?php

declare(strict_types=1);

namespace DxEngine\Database\Migrations;

use DxEngine\Core\Migrations\MigrationInterface;
use DxEngine\Core\DBALWrapper;

/**
 * Create Student Admissions Tables
 * 
 * Supports comprehensive admission workflow including:
 * - Student applications
 * - Document uploads
 * - Academic records
 * - Test scores
 * - References
 * - Financial information
 * - Admission decisions
 */
class CreateAdmissionTables implements MigrationInterface
{
    public function up(DBALWrapper $db): void
    {
        // Main admission applications table
        $db->executeStatement("
            CREATE TABLE admission_applications (
                id VARCHAR(36) PRIMARY KEY,
                application_number VARCHAR(20) NOT NULL UNIQUE,
                applicant_type VARCHAR(50) NOT NULL DEFAULT 'UNDERGRADUATE',
                admission_term VARCHAR(20) NOT NULL,
                admission_year INTEGER NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'DRAFT',
                stage VARCHAR(50) NOT NULL DEFAULT 'PERSONAL_INFO',
                priority VARCHAR(20) NOT NULL DEFAULT 'NORMAL',
                assigned_to_user_id VARCHAR(36),
                assigned_to_role_id VARCHAR(36),
                submitted_at TIMESTAMP,
                reviewed_at TIMESTAMP,
                decision_made_at TIMESTAMP,
                created_at TIMESTAMP NOT NULL,
                updated_at TIMESTAMP NOT NULL,
                e_tag VARCHAR(64) NOT NULL,
                FOREIGN KEY (assigned_to_user_id) REFERENCES dx_users(id),
                FOREIGN KEY (assigned_to_role_id) REFERENCES dx_roles(id)
            )
        ");

        // Personal information
        $db->executeStatement("
            CREATE TABLE admission_personal_info (
                id VARCHAR(36) PRIMARY KEY,
                application_id VARCHAR(36) NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                middle_name VARCHAR(100),
                last_name VARCHAR(100) NOT NULL,
                date_of_birth DATE NOT NULL,
                gender VARCHAR(20),
                nationality VARCHAR(100) NOT NULL,
                country_of_birth VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                alternate_phone VARCHAR(20),
                created_at TIMESTAMP NOT NULL,
                updated_at TIMESTAMP NOT NULL,
                FOREIGN KEY (application_id) REFERENCES admission_applications(id) ON DELETE CASCADE
            )
        ");

        // Address information
        $db->executeStatement("
            CREATE TABLE admission_addresses (
                id VARCHAR(36) PRIMARY KEY,
                application_id VARCHAR(36) NOT NULL,
                address_type VARCHAR(20) NOT NULL,
                street_address VARCHAR(255) NOT NULL,
                city VARCHAR(100) NOT NULL,
                state_province VARCHAR(100) NOT NULL,
                postal_code VARCHAR(20) NOT NULL,
                country VARCHAR(100) NOT NULL,
                is_current INTEGER NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL,
                FOREIGN KEY (application_id) REFERENCES admission_applications(id) ON DELETE CASCADE
            )
        ");

        // Academic history
        $db->executeStatement("
            CREATE TABLE admission_academic_history (
                id VARCHAR(36) PRIMARY KEY,
                application_id VARCHAR(36) NOT NULL,
                institution_name VARCHAR(255) NOT NULL,
                institution_type VARCHAR(50) NOT NULL,
                degree_earned VARCHAR(100),
                field_of_study VARCHAR(100),
                start_date DATE NOT NULL,
                end_date DATE,
                gpa DECIMAL(3,2),
                gpa_scale DECIMAL(3,2) DEFAULT 4.00,
                is_current INTEGER NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL,
                FOREIGN KEY (application_id) REFERENCES admission_applications(id) ON DELETE CASCADE
            )
        ");

        // Test scores (SAT, ACT, GRE, GMAT, TOEFL, IELTS, etc.)
        $db->executeStatement("
            CREATE TABLE admission_test_scores (
                id VARCHAR(36) PRIMARY KEY,
                application_id VARCHAR(36) NOT NULL,
                test_type VARCHAR(50) NOT NULL,
                test_date DATE NOT NULL,
                overall_score INTEGER,
                section_scores TEXT,
                created_at TIMESTAMP NOT NULL,
                FOREIGN KEY (application_id) REFERENCES admission_applications(id) ON DELETE CASCADE
            )
        ");

        // Program selections
        $db->executeStatement("
            CREATE TABLE admission_program_selections (
                id VARCHAR(36) PRIMARY KEY,
                application_id VARCHAR(36) NOT NULL,
                program_code VARCHAR(50) NOT NULL,
                program_name VARCHAR(255) NOT NULL,
                department VARCHAR(100) NOT NULL,
                degree_level VARCHAR(50) NOT NULL,
                preference_order INTEGER NOT NULL DEFAULT 1,
                specialization VARCHAR(100),
                start_term VARCHAR(20) NOT NULL,
                created_at TIMESTAMP NOT NULL,
                FOREIGN KEY (application_id) REFERENCES admission_applications(id) ON DELETE CASCADE
            )
        ");

        // References/Recommendations
        $db->executeStatement("
            CREATE TABLE admission_references (
                id VARCHAR(36) PRIMARY KEY,
                application_id VARCHAR(36) NOT NULL,
                reference_type VARCHAR(50) NOT NULL,
                referee_name VARCHAR(255) NOT NULL,
                referee_title VARCHAR(100),
                referee_organization VARCHAR(255) NOT NULL,
                referee_email VARCHAR(255) NOT NULL,
                referee_phone VARCHAR(20),
                relationship VARCHAR(100) NOT NULL,
                recommendation_status VARCHAR(50) NOT NULL DEFAULT 'PENDING',
                submitted_at TIMESTAMP,
                created_at TIMESTAMP NOT NULL,
                FOREIGN KEY (application_id) REFERENCES admission_applications(id) ON DELETE CASCADE
            )
        ");

        // Supporting documents
        $db->executeStatement("
            CREATE TABLE admission_documents (
                id VARCHAR(36) PRIMARY KEY,
                application_id VARCHAR(36) NOT NULL,
                document_type VARCHAR(50) NOT NULL,
                document_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_size INTEGER NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                uploaded_by VARCHAR(36),
                verification_status VARCHAR(50) NOT NULL DEFAULT 'PENDING',
                verified_by VARCHAR(36),
                verified_at TIMESTAMP,
                uploaded_at TIMESTAMP NOT NULL,
                FOREIGN KEY (application_id) REFERENCES admission_applications(id) ON DELETE CASCADE,
                FOREIGN KEY (uploaded_by) REFERENCES dx_users(id),
                FOREIGN KEY (verified_by) REFERENCES dx_users(id)
            )
        ");

        // Financial information
        $db->executeStatement("
            CREATE TABLE admission_financial_info (
                id VARCHAR(36) PRIMARY KEY,
                application_id VARCHAR(36) NOT NULL,
                requires_financial_aid INTEGER NOT NULL DEFAULT 0,
                estimated_family_contribution DECIMAL(15,2),
                household_income DECIMAL(15,2),
                number_in_household INTEGER,
                number_in_college INTEGER,
                scholarship_applied INTEGER NOT NULL DEFAULT 0,
                scholarship_types TEXT,
                created_at TIMESTAMP NOT NULL,
                updated_at TIMESTAMP NOT NULL,
                FOREIGN KEY (application_id) REFERENCES admission_applications(id) ON DELETE CASCADE
            )
        ");

        // Review comments and evaluations
        $db->executeStatement("
            CREATE TABLE admission_reviews (
                id VARCHAR(36) PRIMARY KEY,
                application_id VARCHAR(36) NOT NULL,
                reviewer_id VARCHAR(36) NOT NULL,
                review_type VARCHAR(50) NOT NULL,
                academic_rating INTEGER,
                extracurricular_rating INTEGER,
                leadership_rating INTEGER,
                recommendation_rating INTEGER,
                overall_rating INTEGER,
                comments TEXT,
                recommendation VARCHAR(50),
                reviewed_at TIMESTAMP NOT NULL,
                FOREIGN KEY (application_id) REFERENCES admission_applications(id) ON DELETE CASCADE,
                FOREIGN KEY (reviewer_id) REFERENCES dx_users(id)
            )
        ");

        // Admission decisions
        $db->executeStatement("
            CREATE TABLE admission_decisions (
                id VARCHAR(36) PRIMARY KEY,
                application_id VARCHAR(36) NOT NULL,
                decision VARCHAR(50) NOT NULL,
                decision_reason TEXT,
                conditional_requirements TEXT,
                financial_aid_offered DECIMAL(15,2),
                scholarship_awarded DECIMAL(15,2),
                response_deadline DATE,
                applicant_response VARCHAR(50),
                applicant_responded_at TIMESTAMP,
                decided_by VARCHAR(36) NOT NULL,
                decided_at TIMESTAMP NOT NULL,
                notified_at TIMESTAMP,
                FOREIGN KEY (application_id) REFERENCES admission_applications(id) ON DELETE CASCADE,
                FOREIGN KEY (decided_by) REFERENCES dx_users(id)
            )
        ");

        // Application fees and payments
        $db->executeStatement("
            CREATE TABLE admission_payments (
                id VARCHAR(36) PRIMARY KEY,
                application_id VARCHAR(36) NOT NULL,
                payment_type VARCHAR(50) NOT NULL,
                amount DECIMAL(15,2) NOT NULL,
                currency VARCHAR(3) NOT NULL DEFAULT 'USD',
                payment_method VARCHAR(50),
                transaction_id VARCHAR(100),
                payment_status VARCHAR(50) NOT NULL DEFAULT 'PENDING',
                paid_at TIMESTAMP,
                created_at TIMESTAMP NOT NULL,
                FOREIGN KEY (application_id) REFERENCES admission_applications(id) ON DELETE CASCADE
            )
        ");

        // Create indexes for performance
        $db->executeStatement("CREATE INDEX idx_admission_status ON admission_applications(status)");
        $db->executeStatement("CREATE INDEX idx_admission_stage ON admission_applications(stage)");
        $db->executeStatement("CREATE INDEX idx_admission_term_year ON admission_applications(admission_term, admission_year)");
        $db->executeStatement("CREATE INDEX idx_admission_assignee ON admission_applications(assigned_to_user_id)");
        $db->executeStatement("CREATE INDEX idx_personal_info_app ON admission_personal_info(application_id)");
        $db->executeStatement("CREATE INDEX idx_documents_app ON admission_documents(application_id)");
        $db->executeStatement("CREATE INDEX idx_reviews_app ON admission_reviews(application_id)");
    }

    public function down(DBALWrapper $db): void
    {
        $db->executeStatement("DROP TABLE IF EXISTS admission_payments");
        $db->executeStatement("DROP TABLE IF EXISTS admission_decisions");
        $db->executeStatement("DROP TABLE IF EXISTS admission_reviews");
        $db->executeStatement("DROP TABLE IF EXISTS admission_financial_info");
        $db->executeStatement("DROP TABLE IF EXISTS admission_documents");
        $db->executeStatement("DROP TABLE IF EXISTS admission_references");
        $db->executeStatement("DROP TABLE IF EXISTS admission_program_selections");
        $db->executeStatement("DROP TABLE IF EXISTS admission_test_scores");
        $db->executeStatement("DROP TABLE IF EXISTS admission_academic_history");
        $db->executeStatement("DROP TABLE IF EXISTS admission_addresses");
        $db->executeStatement("DROP TABLE IF EXISTS admission_personal_info");
        $db->executeStatement("DROP TABLE IF EXISTS admission_applications");
    }
}
