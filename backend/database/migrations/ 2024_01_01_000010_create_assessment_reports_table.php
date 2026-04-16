<?php
/**
 * MIGRATION: Create assessment_reports table
 * 
 * Bảng lưu báo cáo tổng hợp đánh giá
 * 
 * @package Migrations
 */

use Core\Database;

class CreateAssessmentReportsTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS assessment_reports (
            id INT PRIMARY KEY AUTO_INCREMENT,
            server_id INT NOT NULL COMMENT 'ID server',
            report_name VARCHAR(255) COMMENT 'Tên báo cáo',
            total_score DECIMAL(5,2) DEFAULT 0 COMMENT 'Tổng điểm %',
            total_criteria INT DEFAULT 0 COMMENT 'Tổng số tiêu chí',
            passed_criteria INT DEFAULT 0 COMMENT 'Số tiêu chí đạt',
            failed_criteria INT DEFAULT 0 COMMENT 'Số tiêu chí không đạt',
            warning_criteria INT DEFAULT 0 COMMENT 'Số tiêu chí cảnh báo',
            not_applicable_criteria INT DEFAULT 0,
            score_by_category JSON COMMENT 'Điểm theo từng lĩnh vực',
            status ENUM('running', 'completed', 'failed', 'cancelled', 'approved') DEFAULT 'completed',
            file_path VARCHAR(500) COMMENT 'Đường dẫn file báo cáo',
            generated_by INT COMMENT 'Người tạo',
            approved_by INT COMMENT 'Người phê duyệt',
            approved_at TIMESTAMP NULL,
            generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_server_id (server_id),
            INDEX idx_status (status),
            INDEX idx_generated_at (generated_at),
            FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE,
            FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng báo cáo đánh giá tổng hợp'";
        
        $db->query($sql);
        
        echo "✓ Created assessment_reports table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS assessment_reports");
        echo "✓ Dropped assessment_reports table\n";
    }
}