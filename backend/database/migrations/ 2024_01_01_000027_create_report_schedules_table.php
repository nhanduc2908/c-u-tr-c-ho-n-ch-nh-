<?php
/**
 * MIGRATION: Create report_schedules table
 * 
 * Bảng lưu lịch trình tạo báo cáo tự động
 * 
 * @package Migrations
 */

use Core\Database;

class CreateReportSchedulesTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS report_schedules (
            id INT PRIMARY KEY AUTO_INCREMENT,
            report_type VARCHAR(50) NOT NULL COMMENT 'Loại báo cáo',
            schedule ENUM('daily', 'weekly', 'monthly') NOT NULL,
            filters JSON COMMENT 'Bộ lọc',
            recipient_emails JSON NOT NULL COMMENT 'Danh sách email nhận',
            format ENUM('pdf', 'excel', 'csv') DEFAULT 'pdf',
            is_active BOOLEAN DEFAULT TRUE,
            last_run_at TIMESTAMP NULL,
            next_run_at TIMESTAMP NULL,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_is_active (is_active),
            INDEX idx_next_run_at (next_run_at),
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng lịch trình báo cáo'";
        
        $db->query($sql);
        
        echo "✓ Created report_schedules table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS report_schedules");
        echo "✓ Dropped report_schedules table\n";
    }
}