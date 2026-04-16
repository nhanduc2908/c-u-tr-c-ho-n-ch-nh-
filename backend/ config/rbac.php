<?php
/**
 * RBAC CONFIGURATION
 * 
 * Định nghĩa 3 loại tài khoản và quyền tương ứng
 * 
 * @package Config
 */

return [
    // ============================================
    // ĐỊNH NGHĨA 3 ROLE
    // ============================================
    'roles' => [
        'admin' => [
            'id' => 1,
            'name' => 'admin',
            'display_name' => 'Quản trị viên',
            'description' => 'Toàn quyền quản lý hệ thống',
            'color' => '#dc2626',
        ],
        'security_officer' => [
            'id' => 2,
            'name' => 'security_officer',
            'display_name' => 'Chuyên gia an ninh',
            'description' => 'Quét lỗ hổng, đánh giá, xử lý cảnh báo',
            'color' => '#eab308',
        ],
        'viewer' => [
            'id' => 3,
            'name' => 'viewer',
            'display_name' => 'Người xem',
            'description' => 'Chỉ xem báo cáo và dashboard',
            'color' => '#3b82f6',
        ],
    ],
    
    // ============================================
    // PERMISSIONS THEO MODULE
    // ============================================
    'permissions' => [
        // User Management (Chỉ admin)
        'user' => [
            'user.view' => 'Xem danh sách người dùng',
            'user.create' => 'Tạo người dùng mới',
            'user.edit' => 'Sửa thông tin người dùng',
            'user.delete' => 'Xóa người dùng',
            'user.assign_role' => 'Phân quyền cho người dùng',
        ],
        
        // Role Management (Chỉ admin)
        'role' => [
            'role.view' => 'Xem danh sách vai trò',
            'role.create' => 'Tạo vai trò mới',
            'role.edit' => 'Sửa vai trò',
            'role.delete' => 'Xóa vai trò',
            'role.assign_permission' => 'Gán quyền cho vai trò',
        ],
        
        // Server Management
        'server' => [
            'server.view' => 'Xem danh sách server',
            'server.create' => 'Thêm server mới',
            'server.edit' => 'Sửa thông tin server',
            'server.delete' => 'Xóa server',
        ],
        
        // Criteria Management (280 tiêu chí)
        'criteria' => [
            'criteria.view' => 'Xem danh sách tiêu chí',
            'criteria.create' => 'Thêm tiêu chí mới',
            'criteria.edit' => 'Sửa tiêu chí',
            'criteria.delete' => 'Xóa tiêu chí',
            'criteria.import' => 'Import tiêu chí từ Excel',
            'criteria.export' => 'Export tiêu chí ra Excel',
        ],
        
        // Assessment
        'assessment' => [
            'assessment.view' => 'Xem kết quả đánh giá',
            'assessment.run' => 'Chạy đánh giá tự động',
            'assessment.manual' => 'Đánh giá thủ công',
            'assessment.approve' => 'Phê duyệt kết quả',
            'assessment.delete' => 'Xóa kết quả đánh giá',
        ],
        
        // Alert Management
        'alert' => [
            'alert.view' => 'Xem cảnh báo',
            'alert.acknowledge' => 'Xác nhận cảnh báo',
            'alert.resolve' => 'Giải quyết cảnh báo',
            'alert.delete' => 'Xóa cảnh báo',
        ],
        
        // Report
        'report' => [
            'report.view' => 'Xem báo cáo',
            'report.export' => 'Xuất báo cáo PDF/Excel',
            'report.schedule' => 'Lên lịch báo cáo',
        ],
        
        // Backup (Chỉ admin)
        'backup' => [
            'backup.view' => 'Xem danh sách backup',
            'backup.create' => 'Tạo backup mới',
            'backup.restore' => 'Khôi phục từ backup',
            'backup.delete' => 'Xóa backup',
        ],
        
        // Audit Log (Chỉ admin)
        'audit' => [
            'audit.view' => 'Xem audit logs',
            'audit.export' => 'Xuất audit logs',
            'audit.cleanup' => 'Dọn dẹp audit logs',
        ],
    ],
    
    // ============================================
    // GÁN QUYỀN CHO TỪNG ROLE
    // ============================================
    'role_permissions' => [
        // ADMIN: Tất cả quyền
        'admin' => ['*'],
        
        // SECURITY_OFFICER: Quyền về an ninh
        'security_officer' => [
            // Server
            'server.view', 'server.create', 'server.edit',
            // Criteria
            'criteria.view',
            // Assessment
            'assessment.view', 'assessment.run', 'assessment.manual',
            // Alert
            'alert.view', 'alert.acknowledge', 'alert.resolve',
            // Report
            'report.view', 'report.export',
        ],
        
        // VIEWER: Chỉ xem
        'viewer' => [
            'server.view',
            'criteria.view',
            'assessment.view',
            'alert.view',
            'report.view',
        ],
    ],
    
    // ============================================
    // DEFAULT PERMISSIONS CHO USER MỚI
    // ============================================
    'default_permissions' => [
        'viewer' => [
            'server.view',
            'criteria.view',
            'assessment.view',
            'alert.view',
            'report.view',
        ],
    ],
];