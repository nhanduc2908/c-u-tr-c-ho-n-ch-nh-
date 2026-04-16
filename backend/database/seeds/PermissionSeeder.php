<?php
/**
 * PERMISSION SEEDER
 * 
 * Tạo danh sách permissions chi tiết cho hệ thống
 * 
 * @package Seeds
 */

use Core\Database;

class PermissionSeeder
{
    public function run()
    {
        $db = Database::getInstance();
        
        // Xóa dữ liệu cũ
        $db->query("TRUNCATE TABLE permissions");
        $db->query("TRUNCATE TABLE role_permission");
        
        // Định nghĩa permissions
        $permissions = [
            // User Management (Chỉ admin)
            ['name' => 'user.view', 'module' => 'user', 'description' => 'Xem danh sách người dùng'],
            ['name' => 'user.create', 'module' => 'user', 'description' => 'Tạo người dùng mới'],
            ['name' => 'user.edit', 'module' => 'user', 'description' => 'Sửa thông tin người dùng'],
            ['name' => 'user.delete', 'module' => 'user', 'description' => 'Xóa người dùng'],
            ['name' => 'user.assign_role', 'module' => 'user', 'description' => 'Phân quyền cho người dùng'],
            
            // Role Management (Chỉ admin)
            ['name' => 'role.view', 'module' => 'role', 'description' => 'Xem danh sách vai trò'],
            ['name' => 'role.create', 'module' => 'role', 'description' => 'Tạo vai trò mới'],
            ['name' => 'role.edit', 'module' => 'role', 'description' => 'Sửa vai trò'],
            ['name' => 'role.delete', 'module' => 'role', 'description' => 'Xóa vai trò'],
            ['name' => 'role.assign_permission', 'module' => 'role', 'description' => 'Gán quyền cho vai trò'],
            
            // Server Management
            ['name' => 'server.view', 'module' => 'server', 'description' => 'Xem danh sách server'],
            ['name' => 'server.create', 'module' => 'server', 'description' => 'Thêm server mới'],
            ['name' => 'server.edit', 'module' => 'server', 'description' => 'Sửa thông tin server'],
            ['name' => 'server.delete', 'module' => 'server', 'description' => 'Xóa server'],
            
            // Criteria Management (280 tiêu chí)
            ['name' => 'criteria.view', 'module' => 'criteria', 'description' => 'Xem danh sách tiêu chí'],
            ['name' => 'criteria.create', 'module' => 'criteria', 'description' => 'Thêm tiêu chí mới'],
            ['name' => 'criteria.edit', 'module' => 'criteria', 'description' => 'Sửa tiêu chí'],
            ['name' => 'criteria.delete', 'module' => 'criteria', 'description' => 'Xóa tiêu chí'],
            ['name' => 'criteria.import', 'module' => 'criteria', 'description' => 'Import tiêu chí từ Excel'],
            ['name' => 'criteria.export', 'module' => 'criteria', 'description' => 'Export tiêu chí ra Excel'],
            
            // Assessment
            ['name' => 'assessment.view', 'module' => 'assessment', 'description' => 'Xem kết quả đánh giá'],
            ['name' => 'assessment.run', 'module' => 'assessment', 'description' => 'Chạy đánh giá tự động'],
            ['name' => 'assessment.manual', 'module' => 'assessment', 'description' => 'Đánh giá thủ công'],
            ['name' => 'assessment.approve', 'module' => 'assessment', 'description' => 'Phê duyệt kết quả'],
            ['name' => 'assessment.delete', 'module' => 'assessment', 'description' => 'Xóa kết quả đánh giá'],
            
            // Alert Management
            ['name' => 'alert.view', 'module' => 'alert', 'description' => 'Xem cảnh báo'],
            ['name' => 'alert.acknowledge', 'module' => 'alert', 'description' => 'Xác nhận cảnh báo'],
            ['name' => 'alert.resolve', 'module' => 'alert', 'description' => 'Giải quyết cảnh báo'],
            ['name' => 'alert.delete', 'module' => 'alert', 'description' => 'Xóa cảnh báo'],
            
            // Report
            ['name' => 'report.view', 'module' => 'report', 'description' => 'Xem báo cáo'],
            ['name' => 'report.export', 'module' => 'report', 'description' => 'Xuất báo cáo PDF/Excel'],
            ['name' => 'report.schedule', 'module' => 'report', 'description' => 'Lên lịch báo cáo'],
            
            // Backup (Chỉ admin)
            ['name' => 'backup.view', 'module' => 'backup', 'description' => 'Xem danh sách backup'],
            ['name' => 'backup.create', 'module' => 'backup', 'description' => 'Tạo backup mới'],
            ['name' => 'backup.restore', 'module' => 'backup', 'description' => 'Khôi phục từ backup'],
            ['name' => 'backup.delete', 'module' => 'backup', 'description' => 'Xóa backup'],
            
            // Audit Log (Chỉ admin)
            ['name' => 'audit.view', 'module' => 'audit', 'description' => 'Xem audit logs'],
            ['name' => 'audit.export', 'module' => 'audit', 'description' => 'Xuất audit logs'],
            ['name' => 'audit.cleanup', 'module' => 'audit', 'description' => 'Dọn dẹp audit logs'],
        ];
        
        // Insert permissions
        $permissionIds = [];
        foreach ($permissions as $perm) {
            $id = $db->insert('permissions', $perm);
            $permissionIds[$perm['name']] = $id;
        }
        
        // Gán permissions cho roles
        // Admin: tất cả permissions
        $allPermIds = array_values($permissionIds);
        foreach ($allPermIds as $permId) {
            $db->insert('role_permission', ['role_id' => 1, 'permission_id' => $permId]);
        }
        
        // Security Officer: một số permissions
        $officerPerms = [
            'server.view', 'server.create', 'server.edit',
            'criteria.view',
            'assessment.view', 'assessment.run', 'assessment.manual',
            'alert.view', 'alert.acknowledge', 'alert.resolve',
            'report.view', 'report.export'
        ];
        foreach ($officerPerms as $permName) {
            if (isset($permissionIds[$permName])) {
                $db->insert('role_permission', ['role_id' => 2, 'permission_id' => $permissionIds[$permName]]);
            }
        }
        
        // Viewer: chỉ xem
        $viewerPerms = [
            'server.view',
            'criteria.view',
            'assessment.view',
            'alert.view',
            'report.view'
        ];
        foreach ($viewerPerms as $permName) {
            if (isset($permissionIds[$permName])) {
                $db->insert('role_permission', ['role_id' => 3, 'permission_id' => $permissionIds[$permName]]);
            }
        }
        
        echo "      - Created " . count($permissions) . " permissions\n";
        echo "      - Assigned permissions to 3 roles\n";
    }
}