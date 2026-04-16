<?php
/**
 * MIGRATION: Create assessment_results table
 * 
 * Bảng lưu kết quả đánh giá từng tiêu chí
 * 
 * @package Migrations
 */

use Core\Database;

class CreateAssessmentResultsTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS assessment_results (
            id INT PRIMARY KEY AUTO_INCREMENT,
            report_id INT NOT NULL COMMENT 'ID báo cáo',
            server_id INT NOT NULL COMMENT 'ID server',
            criteria_id INT NOT NULL COMMENT 'ID tiêu chí',
            status ENUM('pass', 'fail', 'warning', 'not_applicable', 'pending') DEFAULT 'pending',
            actual_value TEXT COMMENT 'Giá trị thực tế',
            score_obtained DECIMAL(5,2) DEFAULT 0 COMMENT 'Điểm đạt được',
            max_score DECIMAL(5,2) DEFAULT 100 COMMENT 'Điểm tối đa',
            evidence_path VARCHAR(500) COMMENT 'Đường dẫn bằng chứng',
            notes TEXT COMMENT 'Ghi chú',
            checked_by INT COMMENT 'Người kiểm tra thủ công',
            checked_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_report_id (report_id),
            INDEX idx_server_id (server_id),
            INDEX idx_criteria_id (criteria_id),
            INDEX idx_status (status),
            FOREIGN KEY (report_id) REFERENCES assessment_reports(id) ON DELETE CASCADE,
            FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE,
            FOREIGN KEY (criteria_id) REFERENCES assessment_criteria(id),
            FOREIGN KEY (checked_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng kết quả đánh giá chi tiết'";
        
        $db->query($sql);
        
        echo "✓ Created assessment_results table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS assessment_results");
        echo "✓ Dropped assessment_results table\n";
    }
}