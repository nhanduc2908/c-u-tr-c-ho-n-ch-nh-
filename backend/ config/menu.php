<?php
/**
 * MENU CONFIGURATION
 * 
 * Cấu hình menu hiển thị theo role
 * 
 * @package Config
 */

return [
    // ============================================
    // MENU CHO ADMIN (Full quyền)
    // ============================================
    'admin' => [
        'main' => [
            [
                'icon' => '📊',
                'name' => 'Dashboard',
                'path' => '/dashboard',
                'permission' => '*',
            ],
            [
                'icon' => '🖥️',
                'name' => 'Servers',
                'path' => '/servers',
                'permission' => 'server.view',
                'submenu' => [
                    ['name' => 'All Servers', 'path' => '/servers', 'permission' => 'server.view'],
                    ['name' => 'Add Server', 'path' => '/servers-add', 'permission' => 'server.create'],
                    ['name' => 'Categories', 'path' => '/categories', 'permission' => 'criteria.view'],
                ],
            ],
            [
                'icon' => '✅',
                'name' => 'Criteria',
                'path' => '/criteria',
                'permission' => 'criteria.view',
                'submenu' => [
                    ['name' => 'All Criteria (280)', 'path' => '/criteria', 'permission' => 'criteria.view'],
                    ['name' => 'Add Criteria', 'path' => '/criteria-add', 'permission' => 'criteria.create'],
                    ['name' => 'Import/Export', 'path' => '/criteria-import', 'permission' => 'criteria.import'],
                ],
            ],
            [
                'icon' => '🔍',
                'name' => 'Assessments',
                'path' => '/assessments',
                'permission' => 'assessment.view',
                'submenu' => [
                    ['name' => 'Run Assessment', 'path' => '/assessment-run', 'permission' => 'assessment.run'],
                    ['name' => 'History', 'path' => '/assessments', 'permission' => 'assessment.view'],
                    ['name' => 'Reports', 'path' => '/reports', 'permission' => 'report.view'],
                ],
            ],
            [
                'icon' => '⚠️',
                'name' => 'Vulnerabilities',
                'path' => '/vulnerabilities',
                'permission' => 'assessment.view',
            ],
            [
                'icon' => '🔔',
                'name' => 'Alerts',
                'path' => '/alerts',
                'permission' => 'alert.view',
                'badge' => 'unresolved_alerts',
            ],
            [
                'icon' => '👥',
                'name' => 'Users',
                'path' => '/users',
                'permission' => 'user.view',
                'submenu' => [
                    ['name' => 'All Users', 'path' => '/users', 'permission' => 'user.view'],
                    ['name' => 'Roles', 'path' => '/roles', 'permission' => 'role.view'],
                    ['name' => 'Audit Logs', 'path' => '/audit', 'permission' => 'audit.view'],
                ],
            ],
            [
                'icon' => '💾',
                'name' => 'Backup',
                'path' => '/backup',
                'permission' => 'backup.view',
            ],
            [
                'icon' => '📄',
                'name' => 'Reports',
                'path' => '/reports',
                'permission' => 'report.view',
                'submenu' => [
                    ['name' => 'Generate Report', 'path' => '/reports/generate', 'permission' => 'report.export'],
                    ['name' => 'Scheduled Reports', 'path' => '/reports/schedule', 'permission' => 'report.schedule'],
                    ['name' => 'Compliance', 'path' => '/reports/compliance', 'permission' => 'report.view'],
                ],
            ],
            [
                'icon' => '⚙️',
                'name' => 'Settings',
                'path' => '/settings',
                'permission' => '*',
                'submenu' => [
                    ['name' => 'General', 'path' => '/settings/general', 'permission' => '*'],
                    ['name' => 'Security', 'path' => '/settings/security', 'permission' => '*'],
                    ['name' => 'Email', 'path' => '/settings/email', 'permission' => '*'],
                    ['name' => 'API', 'path' => '/settings/api', 'permission' => '*'],
                ],
            ],
            [
                'icon' => '👤',
                'name' => 'Profile',
                'path' => '/profile',
                'permission' => '*',
            ],
        ],
    ],
    
    // ============================================
    // MENU CHO SECURITY OFFICER
    // ============================================
    'security_officer' => [
        'main' => [
            [
                'icon' => '📊',
                'name' => 'Dashboard',
                'path' => '/dashboard',
                'permission' => '*',
            ],
            [
                'icon' => '🖥️',
                'name' => 'Servers',
                'path' => '/servers',
                'permission' => 'server.view',
                'submenu' => [
                    ['name' => 'All Servers', 'path' => '/servers', 'permission' => 'server.view'],
                    ['name' => 'Add Server', 'path' => '/servers-add', 'permission' => 'server.create'],
                ],
            ],
            [
                'icon' => '✅',
                'name' => 'Criteria',
                'path' => '/criteria',
                'permission' => 'criteria.view',
            ],
            [
                'icon' => '🔍',
                'name' => 'Assessments',
                'path' => '/assessments',
                'permission' => 'assessment.view',
                'submenu' => [
                    ['name' => 'Run Assessment', 'path' => '/assessment-run', 'permission' => 'assessment.run'],
                    ['name' => 'History', 'path' => '/assessments', 'permission' => 'assessment.view'],
                ],
            ],
            [
                'icon' => '⚠️',
                'name' => 'Vulnerabilities',
                'path' => '/vulnerabilities',
                'permission' => 'assessment.view',
            ],
            [
                'icon' => '🔔',
                'name' => 'Alerts',
                'path' => '/alerts',
                'permission' => 'alert.view',
                'badge' => 'unresolved_alerts',
            ],
            [
                'icon' => '📄',
                'name' => 'Reports',
                'path' => '/reports',
                'permission' => 'report.view',
            ],
            [
                'icon' => '👤',
                'name' => 'Profile',
                'path' => '/profile',
                'permission' => '*',
            ],
        ],
    ],
    
    // ============================================
    // MENU CHO VIEWER (Chỉ xem)
    // ============================================
    'viewer' => [
        'main' => [
            [
                'icon' => '📊',
                'name' => 'Dashboard',
                'path' => '/dashboard',
                'permission' => '*',
            ],
            [
                'icon' => '🖥️',
                'name' => 'Servers',
                'path' => '/servers',
                'permission' => 'server.view',
            ],
            [
                'icon' => '✅',
                'name' => 'Criteria',
                'path' => '/criteria',
                'permission' => 'criteria.view',
            ],
            [
                'icon' => '🔍',
                'name' => 'Assessments',
                'path' => '/assessments',
                'permission' => 'assessment.view',
            ],
            [
                'icon' => '⚠️',
                'name' => 'Vulnerabilities',
                'path' => '/vulnerabilities',
                'permission' => 'assessment.view',
            ],
            [
                'icon' => '🔔',
                'name' => 'Alerts',
                'path' => '/alerts',
                'permission' => 'alert.view',
            ],
            [
                'icon' => '📄',
                'name' => 'Reports',
                'path' => '/reports',
                'permission' => 'report.view',
            ],
            [
                'icon' => '👤',
                'name' => 'Profile',
                'path' => '/profile',
                'permission' => '*',
            ],
        ],
    ],
];