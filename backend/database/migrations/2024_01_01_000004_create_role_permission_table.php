<?php
/**
 * MIGRATION: Create role_permission table
 * 
 * Bảng liên kết vai trò và quyền (Many-to-Many)
 * 
 * @package Migrations
 */

use Core\Database;

class CreateRolePermissionTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS role_permission (
            role_id INT NOT NULL,
            permission_id INT NOT NULL,
            PRIMARY KEY (role_id, permission_id),
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng liên kết vai trò-quyền'";
        
        $db->query($sql);
        
        echo "✓ Created role_permission table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS role_permission");
        echo "✓ Dropped role_permission table\n";
    }
}