<?php
/**
 * MIGRATION: Create permissions table
 * 
 * Bảng lưu danh sách quyền chi tiết
 * 
 * @package Migrations
 */

use Core\Database;

class CreatePermissionsTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS permissions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) UNIQUE NOT NULL COMMENT 'Tên quyền (user.view, server.create, ...)',
            module VARCHAR(50) COMMENT 'Module thuộc (user, server, criteria, ...)',
            description TEXT COMMENT 'Mô tả quyền',
            INDEX idx_name (name),
            INDEX idx_module (module)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng quyền'";
        
        $db->query($sql);
        
        echo "✓ Created permissions table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS permissions");
        echo "✓ Dropped permissions table\n";
    }
}