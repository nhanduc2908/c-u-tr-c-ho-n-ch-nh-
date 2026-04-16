<?php
/**
 * MIGRATION: Create assessment_criteria table
 * 
 * Bảng lưu 280 tiêu chí đánh giá bảo mật
 * 
 * @package Migrations
 */

use Core\Database;

class CreateCriteriaTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS assessment_criteria (
            id INT PRIMARY KEY AUTO_INCREMENT,
            code VARCHAR(20) UNIQUE NOT NULL COMMENT 'Mã tiêu chí (IAM-001, NET-002, ...)',
            category_id INT NOT NULL COMMENT 'ID lĩnh vực (FK)',
            name VARCHAR(255) NOT NULL COMMENT 'Tên tiêu chí',
            description TEXT COMMENT 'Mô tả chi tiết',
            check_method ENUM('auto', 'manual', 'api', 'command', 'sql') DEFAULT 'auto',
            check_command TEXT COMMENT 'Lệnh kiểm tra (SSH)',
            api_endpoint VARCHAR(255) COMMENT 'API endpoint',
            sql_query TEXT COMMENT 'Câu lệnh SQL kiểm tra',
            expected_value TEXT COMMENT 'Giá trị kỳ vọng',
            severity ENUM('critical', 'high', 'medium', 'low', 'info') DEFAULT 'medium',
            weight INT DEFAULT 1 COMMENT 'Trọng số (1-10)',
            is_auto_check BOOLEAN DEFAULT TRUE,
            requires_manual BOOLEAN DEFAULT FALSE,
            requires_evidence BOOLEAN DEFAULT FALSE,
            reference_standard VARCHAR(100) COMMENT 'ISO 27001, NIST, PCI-DSS',
            remediation_guide TEXT COMMENT 'Hướng dẫn khắc phục',
            sort_order INT DEFAULT 0,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_code (code),
            INDEX idx_category_id (category_id),
            INDEX idx_severity (severity),
            INDEX idx_is_active (is_active),
            FOREIGN KEY (category_id) REFERENCES assessment_categories(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng 280 tiêu chí đánh giá'";
        
        $db->query($sql);
        
        echo "✓ Created assessment_criteria table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS assessment_criteria");
        echo "✓ Dropped assessment_criteria table\n";
    }
}