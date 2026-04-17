<?php
/**
 * MIGRATION: Create blocked_ips table
 * 
 * Bảng lưu danh sách IP bị chặn
 * Bảo vệ hệ thống khỏi tấn công
 * 
 * @package Migrations
 */

use Core\Database;

class CreateBlockedIpsTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS blocked_ips (
            id INT PRIMARY KEY AUTO_INCREMENT,
            ip_address VARCHAR(45) NOT NULL COMMENT 'Địa chỉ IP bị chặn',
            reason TEXT COMMENT 'Lý do chặn',
            blocked_by INT COMMENT 'Người chặn',
            expires_at TIMESTAMP NULL COMMENT 'Thời gian hết hạn (NULL = vĩnh viễn)',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_address (ip_address),
            INDEX idx_expires_at (expires_at),
            FOREIGN KEY (blocked_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng IP bị chặn'";
        
        $db->query($sql);
        
        echo "✓ Created blocked_ips table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS blocked_ips");
        echo "✓ Dropped blocked_ips table\n";
    }
}