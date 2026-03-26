<?php

declare(strict_types=1);

namespace DxEngine\App\Models;

use DxEngine\Core\DataModel;
use DxEngine\Core\DBALWrapper;

/**
 * AdmissionApplicationModel
 * 
 * Represents a student admission application with full lifecycle management
 */
class AdmissionApplicationModel extends DataModel
{
    protected function table(): string
    {
        return 'admission_applications';
    }

    protected function fieldMap(): array
    {
        return [
            'id' => ['column' => 'id', 'type' => 'string'],
            'applicationNumber' => ['column' => 'application_number', 'type' => 'string'],
            'applicantType' => ['column' => 'applicant_type', 'type' => 'string'],
            'admissionTerm' => ['column' => 'admission_term', 'type' => 'string'],
            'admissionYear' => ['column' => 'admission_year', 'type' => 'integer'],
            'status' => ['column' => 'status', 'type' => 'string'],
            'stage' => ['column' => 'stage', 'type' => 'string'],
            'priority' => ['column' => 'priority', 'type' => 'string'],
            'assignedToUserId' => ['column' => 'assigned_to_user_id', 'type' => 'string'],
            'assignedToRoleId' => ['column' => 'assigned_to_role_id', 'type' => 'string'],
            'submittedAt' => ['column' => 'submitted_at', 'type' => 'datetime'],
            'reviewedAt' => ['column' => 'reviewed_at', 'type' => 'datetime'],
            'decisionMadeAt' => ['column' => 'decision_made_at', 'type' => 'datetime'],
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

    /**
     * Get personal information for this application
     */
    public function getPersonalInfo(): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM admission_personal_info WHERE application_id = ?',
            [$this->id]
        );
    }

    /**
     * Get all addresses for this application
     */
    public function getAddresses(): array
    {
        return $this->db->select(
            'SELECT * FROM admission_addresses WHERE application_id = ? ORDER BY address_type',
            [$this->id]
        );
    }

    /**
     * Get academic history
     */
    public function getAcademicHistory(): array
    {
        return $this->db->select(
            'SELECT * FROM admission_academic_history WHERE application_id = ? ORDER BY start_date DESC',
            [$this->id]
        );
    }

    /**
     * Get test scores
     */
    public function getTestScores(): array
    {
        return $this->db->select(
            'SELECT * FROM admission_test_scores WHERE application_id = ? ORDER BY test_date DESC',
            [$this->id]
        );
    }

    /**
     * Get program selections
     */
    public function getProgramSelections(): array
    {
        return $this->db->select(
            'SELECT * FROM admission_program_selections WHERE application_id = ? ORDER BY preference_order',
            [$this->id]
        );
    }

    /**
     * Get references
     */
    public function getReferences(): array
    {
        return $this->db->select(
            'SELECT * FROM admission_references WHERE application_id = ? ORDER BY created_at',
            [$this->id]
        );
    }

    /**
     * Get uploaded documents
     */
    public function getDocuments(): array
    {
        return $this->db->select(
            'SELECT * FROM admission_documents WHERE application_id = ? ORDER BY document_type, uploaded_at',
            [$this->id]
        );
    }

    /**
     * Get financial information
     */
    public function getFinancialInfo(): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM admission_financial_info WHERE application_id = ?',
            [$this->id]
        );
    }

    /**
     * Get reviews
     */
    public function getReviews(): array
    {
        return $this->db->select(
            'SELECT r.*, u.full_name as reviewer_name 
             FROM admission_reviews r
             JOIN dx_users u ON r.reviewer_id = u.id
             WHERE r.application_id = ? 
             ORDER BY r.reviewed_at DESC',
            [$this->id]
        );
    }

    /**
     * Get admission decision
     */
    public function getDecision(): ?array
    {
        return $this->db->selectOne(
            'SELECT d.*, u.full_name as decided_by_name
             FROM admission_decisions d
             JOIN dx_users u ON d.decided_by = u.id
             WHERE d.application_id = ?',
            [$this->id]
        );
    }

    /**
     * Get payment information
     */
    public function getPayments(): array
    {
        return $this->db->select(
            'SELECT * FROM admission_payments WHERE application_id = ? ORDER BY created_at',
            [$this->id]
        );
    }

    /**
     * Check if application is complete
     */
    public function isComplete(): bool
    {
        $personalInfo = $this->getPersonalInfo();
        $academicHistory = $this->getAcademicHistory();
        $programSelections = $this->getProgramSelections();
        
        return $personalInfo !== null 
            && !empty($academicHistory) 
            && !empty($programSelections);
    }

    /**
     * Calculate application completion percentage
     */
    public function getCompletionPercentage(): int
    {
        $sections = [
            'personal_info' => $this->getPersonalInfo() !== null,
            'address' => !empty($this->getAddresses()),
            'academic_history' => !empty($this->getAcademicHistory()),
            'test_scores' => !empty($this->getTestScores()),
            'program_selection' => !empty($this->getProgramSelections()),
            'references' => count($this->getReferences()) >= 2,
            'documents' => count($this->getDocuments()) >= 3,
            'financial_info' => $this->getFinancialInfo() !== null
        ];

        $completed = array_filter($sections);
        return (int) ((count($completed) / count($sections)) * 100);
    }
}
