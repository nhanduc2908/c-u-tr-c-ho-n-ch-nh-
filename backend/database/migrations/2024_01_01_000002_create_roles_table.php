<?php
/**
 * MIGRATION: Create roles table
 * 
 * Bảng lưu vai trò người dùng
 * 3 role mặc định: admin, security_officer, viewer
 * 
 * @package Migrations
 */

use Core\Database;

class CreateRolesTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS roles (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(50) UNIQUE NOT NULL COMMENT 'Tên vai trò (admin, security_officer, viewer)',
            description TEXT COMMENT 'Mô tả vai trò',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng vai trò'";
        
        $db->query($sql);
        
        // Insert default roles
        $db->query("INSERT IGNORE INTO roles (id, name, description) VALUES 
            (1, 'admin', 'Quản trị viên - Toàn quyền truy cập và quản lý hệ thống'),
            (2, 'security_officer', 'Chuyên gia an ninh - Quét lỗ hổng, đánh giá, xử lý cảnh báo'),
            (3, 'viewer', 'Người xem - Chỉ xem báo cáo và dashboard')");
        
        echo "✓ Created roles table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS roles");
        echo "✓ Dropped roles table\n";
    }
}