<?php
/**
 * MIGRATION: Create audit_logs table
 * 
 * Bảng lưu lịch sử hoạt động của hệ thống
 * 
 * @package Migrations
 */

use Core\Database;

class CreateAuditLogsTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS audit_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT COMMENT 'ID người dùng',
            action VARCHAR(100) NOT NULL COMMENT 'Hành động',
            details TEXT COMMENT 'Chi tiết',
            ip_address VARCHAR(45) COMMENT 'Địa chỉ IP',
            user_agent TEXT COMMENT 'User Agent',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng lịch sử hoạt động'";
        
        $db->query($sql);
        
        echo "✓ Created audit_logs table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS audit_logs");
        echo "✓ Dropped audit_logs table\n";
    }
}