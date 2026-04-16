<?php
/**
 * MIGRATION: Create alert_notes table
 * 
 * Bảng lưu ghi chú cho cảnh báo
 * 
 * @package Migrations
 */

use Core\Database;

class CreateAlertNotesTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS alert_notes (
            id INT PRIMARY KEY AUTO_INCREMENT,
            alert_id INT NOT NULL,
            note TEXT NOT NULL,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_alert_id (alert_id),
            FOREIGN KEY (alert_id) REFERENCES alerts(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng ghi chú cảnh báo'";
        
        $db->query($sql);
        
        echo "✓ Created alert_notes table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS alert_notes");
        echo "✓ Dropped alert_notes table\n";
    }
}
