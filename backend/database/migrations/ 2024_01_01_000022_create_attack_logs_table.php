<?php
/**
 * MIGRATION: Create attack_logs table
 * 
 * Bảng lưu log các cuộc tấn công phát hiện được
 * Phục vụ phân tích bảo mật
 * 
 * @package Migrations
 */

use Core\Database;

class CreateAttackLogsTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS attack_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            ip_address VARCHAR(45) NOT NULL COMMENT 'Địa chỉ IP tấn công',
            attack_type VARCHAR(50) NOT NULL COMMENT 'Loại tấn công',
            severity ENUM('critical', 'high', 'medium', 'low') DEFAULT 'medium',
            description TEXT COMMENT 'Mô tả',
            request_data TEXT COMMENT 'Dữ liệu request',
            user_agent TEXT COMMENT 'User Agent',
            request_uri VARCHAR(500) COMMENT 'URI bị tấn công',
            is_blocked BOOLEAN DEFAULT FALSE,
            detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_address (ip_address),
            INDEX idx_attack_type (attack_type),
            INDEX idx_severity (severity),
            INDEX idx_detected_at (detected_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng log tấn công'";
        
        $db->query($sql);
        
        echo "✓ Created attack_logs table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS attack_logs");
        echo "✓ Dropped attack_logs table\n";
    }
}