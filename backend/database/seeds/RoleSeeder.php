<?php
/**
 * ROLE SEEDER
 * 
 * Tạo 3 role mặc định: admin, security_officer, viewer
 * 
 * @package Seeds
 */

use Core\Database;

class RoleSeeder
{
    public function run()
    {
        $db = Database::getInstance();
        
        // Xóa dữ liệu cũ
        $db->query("TRUNCATE TABLE roles");
        
        // Insert 3 roles
        $roles = [
            [
                'id' => 1,
                'name' => 'admin',
                'description' => 'Quản trị viên - Toàn quyền truy cập và quản lý hệ thống',
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 2,
                'name' => 'security_officer',
                'description' => 'Chuyên gia an ninh - Quét lỗ hổng, đánh giá, xử lý cảnh báo',
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 3,
                'name' => 'viewer',
                'description' => 'Người xem - Chỉ xem báo cáo và dashboard',
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];
        
        foreach ($roles as $role) {
            $db->insert('roles', $role);
        }
        
        echo "      - Created 3 roles: admin, security_officer, viewer\n";
    }
}