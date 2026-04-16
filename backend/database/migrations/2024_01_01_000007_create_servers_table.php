<?php
/**
 * MIGRATION: Create servers table
 * 
 * Bảng lưu thông tin server cần đánh giá
 * 
 * @package Migrations
 */

use Core\Database;

class CreateServersTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS servers (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL COMMENT 'Tên server',
            ip_address VARCHAR(45) NOT NULL COMMENT 'Địa chỉ IP',
            hostname VARCHAR(255) COMMENT 'Hostname',
            os VARCHAR(100) COMMENT 'Hệ điều hành',
            environment ENUM('production', 'staging', 'development') DEFAULT 'production',
            status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
            ssh_port INT DEFAULT 22 COMMENT 'SSH port',
            ssh_username VARCHAR(50) COMMENT 'SSH username',
            ssh_key_path VARCHAR(255) COMMENT 'Đường dẫn SSH key',
            last_scan_at TIMESTAMP NULL COMMENT 'Lần quét cuối',
            created_by INT COMMENT 'Người tạo',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL COMMENT 'Soft delete',
            INDEX idx_ip_address (ip_address),
            INDEX idx_status (status),
            INDEX idx_environment (environment),
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng quản lý server'";
        
        $db->query($sql);
        
        echo "✓ Created servers table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS servers");
        echo "✓ Dropped servers table\n";
    }
}