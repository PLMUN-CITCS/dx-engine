<?php

declare(strict_types=1);

namespace DxEngine\App\DX;

use DxEngine\Core\DXController;

class RbacAdminDX extends DXController
{
    private string $view = 'role_list';

    public function preProcess(): void
    {
        $dirtyState = $this->getDirtyState();
        $requestedView = isset($dirtyState['view']) ? (string) $dirtyState['view'] : '';

        if ($requestedView === '' && isset($dirtyState['action'])) {
            $requestedView = (string) $dirtyState['action'];
        }

        $allowedViews = ['role_list', 'role_detail', 'permission_list', 'user_role_assignment'];
        $this->view = in_array($requestedView, $allowedViews, true) ? $requestedView : 'role_list';
    }

    public function getFlow(): array
    {
        $roles = $this->db->select(
            'SELECT id, name, display_name, description, is_system, created_at FROM dx_roles ORDER BY name ASC'
        );

        $permissions = $this->db->select(
            'SELECT id, `key`, category, description, created_at FROM dx_permissions ORDER BY category ASC, `key` ASC'
        );

        $userRoleAssignments = $this->db->select(
            'SELECT ur.user_id, ur.role_id, ur.context_type, ur.context_id, ur.granted_at, r.name AS role_name
             FROM dx_user_roles ur
             JOIN dx_roles r ON r.id = ur.role_id
             ORDER BY ur.granted_at DESC'
        );

        $this->setData([
            'portal_title' => 'Access Management Portal',
            'active_view' => $this->view,
            'role_count' => 'Roles: ' . count($roles),
            'permission_count' => 'Permissions: ' . count($permissions),
            'assignment_count' => 'Assignments: ' . count($userRoleAssignments),
        ]);

        $this->setNextAssignmentInfo([
            'steps' => [
                ['label' => 'Role List', 'key' => 'role_list', 'status' => $this->view === 'role_list' ? 'active' : 'pending'],
                ['label' => 'Role Detail', 'key' => 'role_detail', 'status' => $this->view === 'role_detail' ? 'active' : 'pending'],
                ['label' => 'Permission List', 'key' => 'permission_list', 'status' => $this->view === 'permission_list' ? 'active' : 'pending'],
                ['label' => 'User Role Assignment', 'key' => 'user_role_assignment', 'status' => $this->view === 'user_role_assignment' ? 'active' : 'pending'],
            ],
            'current_step_index' => $this->stepIndexForView($this->view),
            'is_final_step' => false,
            'next_action_label' => 'Manage RBAC',
        ]);

        $this->setConfirmationNote([
            'message' => 'RBAC management view loaded: ' . str_replace('_', ' ', $this->view),
            'variant' => 'info',
            'action_required' => null,
        ]);

        return $this->buildUiResources($roles, $permissions, $userRoleAssignments);
    }

    public function postProcess(): void
    {
        // Read-focused OOTB controller scaffold; mutations occur via rbac_admin API routes.
    }

    private function buildUiResources(array $roles, array $permissions, array $userRoleAssignments): array
    {
        $resources = [
            [
                'component_type' => 'section_header',
                'key' => 'rbac_portal_header',
                'label' => 'Access Management Portal',
                'required_permission' => 'rbac:manage',
            ],
            [
                'component_type' => 'badge',
                'key' => 'rbac_roles_count',
                'label' => 'Roles: ' . count($roles),
                'variant' => 'primary',
                'required_permission' => 'rbac:manage',
            ],
            [
                'component_type' => 'badge',
                'key' => 'rbac_permissions_count',
                'label' => 'Permissions: ' . count($permissions),
                'variant' => 'info',
                'required_permission' => 'rbac:manage',
            ],
            [
                'component_type' => 'badge',
                'key' => 'rbac_assignments_count',
                'label' => 'Assignments: ' . count($userRoleAssignments),
                'variant' => 'warning',
                'required_permission' => 'rbac:manage',
            ],
            [
                'component_type' => 'button_secondary',
                'key' => 'rbac_nav_role_list',
                'label' => 'Role List',
                'action' => 'role_list',
                'required_permission' => 'rbac:manage',
            ],
            [
                'component_type' => 'button_secondary',
                'key' => 'rbac_nav_role_detail',
                'label' => 'Role Detail',
                'action' => 'role_detail',
                'required_permission' => 'rbac:manage',
            ],
            [
                'component_type' => 'button_secondary',
                'key' => 'rbac_nav_permission_list',
                'label' => 'Permission List',
                'action' => 'permission_list',
                'required_permission' => 'rbac:manage',
            ],
            [
                'component_type' => 'button_secondary',
                'key' => 'rbac_nav_user_role_assignment',
                'label' => 'User Role Assignment',
                'action' => 'user_role_assignment',
                'required_permission' => 'rbac:manage',
            ],
        ];

        if ($this->view === 'role_list') {
            $resources[] = [
                'component_type' => 'data_table',
                'key' => 'rbac_role_list_table',
                'rows' => array_map(static function (array $row): array {
                    return [
                        'Role Name' => (string) ($row['name'] ?? ''),
                        'Display Name' => (string) ($row['display_name'] ?? ''),
                        'Description' => (string) ($row['description'] ?? ''),
                        'System Role' => ((int) ($row['is_system'] ?? 0) === 1) ? 'Yes' : 'No',
                        'Created At' => (string) ($row['created_at'] ?? ''),
                    ];
                }, $roles),
                'required_permission' => 'rbac:manage',
            ];

            $resources[] = [
                'component_type' => 'button_primary',
                'key' => 'rbac_create_role',
                'label' => 'Create Role',
                'action' => 'create_role',
                'required_permission' => 'rbac:manage',
            ];
        }

        if ($this->view === 'role_detail') {
            $resources = array_merge($resources, [
                [
                    'component_type' => 'text_input',
                    'key' => 'role_id',
                    'label' => 'Role ID',
                    'placeholder' => 'Enter role ID',
                    'required_permission' => 'rbac:manage',
                ],
                [
                    'component_type' => 'text_input',
                    'key' => 'role_display_name',
                    'label' => 'Display Name',
                    'placeholder' => 'Role display name',
                    'required_permission' => 'rbac:manage',
                ],
                [
                    'component_type' => 'textarea',
                    'key' => 'role_description',
                    'label' => 'Description',
                    'placeholder' => 'Role description',
                    'required_permission' => 'rbac:manage',
                ],
                [
                    'component_type' => 'button_primary',
                    'key' => 'rbac_update_role',
                    'label' => 'Update Role',
                    'action' => 'update_role',
                    'required_permission' => 'rbac:manage',
                ],
            ]);
        }

        if ($this->view === 'permission_list') {
            $resources[] = [
                'component_type' => 'data_table',
                'key' => 'rbac_permission_list_table',
                'rows' => array_map(static function (array $row): array {
                    return [
                        'Permission Key' => (string) ($row['key'] ?? ''),
                        'Category' => (string) ($row['category'] ?? ''),
                        'Description' => (string) ($row['description'] ?? ''),
                        'Created At' => (string) ($row['created_at'] ?? ''),
                    ];
                }, $permissions),
                'required_permission' => 'rbac:manage',
            ];
        }

        if ($this->view === 'user_role_assignment') {
            $resources = array_merge($resources, [
                [
                    'component_type' => 'text_input',
                    'key' => 'assignment_user_id',
                    'label' => 'User ID',
                    'placeholder' => 'Search/enter user ID',
                    'required_permission' => 'rbac:manage',
                ],
                [
                    'component_type' => 'text_input',
                    'key' => 'assignment_role_id',
                    'label' => 'Role ID',
                    'placeholder' => 'Enter role ID',
                    'required_permission' => 'rbac:manage',
                ],
                [
                    'component_type' => 'text_input',
                    'key' => 'context_type',
                    'label' => 'Context Type (optional)',
                    'placeholder' => 'e.g. business_unit',
                    'required_permission' => 'rbac:manage',
                ],
                [
                    'component_type' => 'text_input',
                    'key' => 'context_id',
                    'label' => 'Context ID (optional)',
                    'placeholder' => 'e.g. BU-05',
                    'required_permission' => 'rbac:manage',
                ],
                [
                    'component_type' => 'button_primary',
                    'key' => 'rbac_assign_role',
                    'label' => 'Assign Role',
                    'action' => 'assign_role',
                    'required_permission' => 'rbac:manage',
                ],
                [
                    'component_type' => 'data_table',
                    'key' => 'rbac_user_role_assignment_table',
                    'rows' => array_map(static function (array $row): array {
                        return [
                            'User ID' => (string) ($row['user_id'] ?? ''),
                            'Role' => (string) ($row['role_name'] ?? ''),
                            'Context Type' => (string) (($row['context_type'] ?? '') ?: '-'),
                            'Context ID' => (string) (($row['context_id'] ?? '') ?: '-'),
                            'Granted At' => (string) ($row['granted_at'] ?? ''),
                        ];
                    }, $userRoleAssignments),
                    'required_permission' => 'rbac:manage',
                ],
            ]);
        }

        return $resources;
    }

    private function stepIndexForView(string $view): int
    {
        return match ($view) {
            'role_list' => 0,
            'role_detail' => 1,
            'permission_list' => 2,
            'user_role_assignment' => 3,
            default => 0,
        };
    }
}
