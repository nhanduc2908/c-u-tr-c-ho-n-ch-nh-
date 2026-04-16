<?php
/**
 * MIGRATION: Create user_sessions table
 * 
 * Bảng lưu phiên đăng nhập của người dùng
 * Hỗ trợ quản lý session và đăng xuất từ xa
 * 
 * @package Migrations
 */

use Core\Database;

class CreateUserSessionsTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS user_sessions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL COMMENT 'ID người dùng',
            token VARCHAR(500) NOT NULL COMMENT 'JWT token',
            ip_address VARCHAR(45) COMMENT 'Địa chỉ IP',
            user_agent TEXT COMMENT 'User Agent',
            expires_at TIMESTAMP NOT NULL COMMENT 'Thời gian hết hạn',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_token (token(255)),
            INDEX idx_expires_at (expires_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng phiên đăng nhập'";
        
        $db->query($sql);
        
        echo "✓ Created user_sessions table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS user_sessions");
        echo "✓ Dropped user_sessions table\n";
    }
}