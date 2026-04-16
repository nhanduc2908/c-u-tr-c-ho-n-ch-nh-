<?php
/**
 * MIGRATION: Create login_attempts table
 * 
 * Bảng lưu lịch sử đăng nhập thất bại
 * Dùng để phát hiện tấn công brute force
 * 
 * @package Migrations
 */

use Core\Database;

class CreateLoginAttemptsTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(100) COMMENT 'Tên đăng nhập thử',
            ip VARCHAR(45) NOT NULL COMMENT 'Địa chỉ IP',
            user_agent TEXT COMMENT 'User Agent',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_ip (ip),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng lịch sử đăng nhập thất bại'";
        
        $db->query($sql);
        
        echo "✓ Created login_attempts table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS login_attempts");
        echo "✓ Dropped login_attempts table\n";
    }
}