<?php
/**
 * MIGRATION: Create vulnerabilities table
 * 
 * Bảng lưu lỗ hổng bảo mật phát hiện được
 * 
 * @package Migrations
 */

use Core\Database;

class CreateVulnerabilitiesTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS vulnerabilities (
            id INT PRIMARY KEY AUTO_INCREMENT,
            server_id INT NOT NULL COMMENT 'ID server',
            criteria_id INT COMMENT 'ID tiêu chí liên quan',
            cve_code VARCHAR(50) COMMENT 'Mã CVE',
            title VARCHAR(255) NOT NULL COMMENT 'Tiêu đề',
            description TEXT COMMENT 'Mô tả chi tiết',
            severity ENUM('critical', 'high', 'medium', 'low') DEFAULT 'medium',
            status ENUM('open', 'in_progress', 'fixed', 'false_positive') DEFAULT 'open',
            detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fixed_at TIMESTAMP NULL,
            fixed_by INT COMMENT 'Người sửa',
            INDEX idx_server_id (server_id),
            INDEX idx_cve_code (cve_code),
            INDEX idx_severity (severity),
            INDEX idx_status (status),
            FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE,
            FOREIGN KEY (criteria_id) REFERENCES assessment_criteria(id) ON DELETE SET NULL,
            FOREIGN KEY (fixed_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng lỗ hổng bảo mật'";
        
        $db->query($sql);
        
        echo "✓ Created vulnerabilities table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS vulnerabilities");
        echo "✓ Dropped vulnerabilities table\n";
    }
}