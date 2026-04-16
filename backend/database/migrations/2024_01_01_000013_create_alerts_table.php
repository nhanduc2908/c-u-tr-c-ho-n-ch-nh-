<?php
/**
 * MIGRATION: Create alerts table
 * 
 * Bảng lưu cảnh báo an ninh
 * 
 * @package Migrations
 */

use Core\Database;

class CreateAlertsTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS alerts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            server_id INT COMMENT 'ID server liên quan',
            type VARCHAR(50) NOT NULL COMMENT 'Loại cảnh báo',
            severity ENUM('critical', 'high', 'medium', 'low', 'info') DEFAULT 'info',
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            is_read BOOLEAN DEFAULT FALSE,
            is_resolved BOOLEAN DEFAULT FALSE,
            reference_id INT COMMENT 'ID tham chiếu',
            reference_type VARCHAR(50) COMMENT 'Loại tham chiếu',
            assigned_to INT COMMENT 'Người được gán',
            resolution_note TEXT,
            acknowledged_by INT,
            acknowledged_at TIMESTAMP NULL,
            resolved_by INT,
            resolved_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_server_id (server_id),
            INDEX idx_severity (severity),
            INDEX idx_is_resolved (is_resolved),
            INDEX idx_created_at (created_at),
            FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE SET NULL,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (acknowledged_by) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng cảnh báo an ninh'";
        
        $db->query($sql);
        
        echo "✓ Created alerts table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS alerts");
        echo "✓ Dropped alerts table\n";
    }
}