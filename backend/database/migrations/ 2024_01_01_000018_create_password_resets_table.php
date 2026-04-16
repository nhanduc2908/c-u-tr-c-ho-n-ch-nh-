<?php
/**
 * MIGRATION: Create password_resets table
 * 
 * Bảng lưu token đặt lại mật khẩu
 * Hỗ trợ chức năng quên mật khẩu
 * 
 * @package Migrations
 */

use Core\Database;

class CreatePasswordResetsTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS password_resets (
            id INT PRIMARY KEY AUTO_INCREMENT,
            email VARCHAR(100) NOT NULL COMMENT 'Email người dùng',
            token VARCHAR(255) NOT NULL COMMENT 'Token đã hash',
            expires_at TIMESTAMP NOT NULL COMMENT 'Thời gian hết hạn',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_token (token),
            INDEX idx_expires_at (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng token đặt lại mật khẩu'";
        
        $db->query($sql);
        
        echo "✓ Created password_resets table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS password_resets");
        echo "✓ Dropped password_resets table\n";
    }
}