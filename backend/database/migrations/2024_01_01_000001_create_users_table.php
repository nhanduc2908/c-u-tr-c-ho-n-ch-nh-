<?php
/**
 * MIGRATION: Create users table
 * 
 * Bảng lưu thông tin người dùng
 * Hỗ trợ 3 loại tài khoản: admin, security_officer, viewer
 * 
 * @package Migrations
 */

use Core\Database;

class CreateUsersTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL COMMENT 'Tên đăng nhập',
            email VARCHAR(100) UNIQUE NOT NULL COMMENT 'Email',
            password_hash VARCHAR(255) NOT NULL COMMENT 'Mật khẩu đã mã hóa',
            full_name VARCHAR(100) COMMENT 'Họ tên đầy đủ',
            avatar VARCHAR(255) COMMENT 'Đường dẫn avatar',
            phone VARCHAR(20) COMMENT 'Số điện thoại',
            address TEXT COMMENT 'Địa chỉ',
            role_id INT NOT NULL COMMENT 'ID vai trò (FK to roles)',
            is_active BOOLEAN DEFAULT TRUE COMMENT 'Trạng thái hoạt động',
            last_login TIMESTAMP NULL COMMENT 'Lần đăng nhập cuối',
            last_ip VARCHAR(45) COMMENT 'IP lần đăng nhập cuối',
            two_factor_enabled BOOLEAN DEFAULT FALSE COMMENT 'Bật 2FA',
            two_factor_secret VARCHAR(255) COMMENT 'Secret key 2FA',
            email_verified_at TIMESTAMP NULL COMMENT 'Thời gian xác thực email',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_email (email),
            INDEX idx_role_id (role_id),
            INDEX idx_is_active (is_active),
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng người dùng'";
        
        $db->query($sql);
        
        echo "✓ Created users table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS users");
        echo "✓ Dropped users table\n";
    }
}