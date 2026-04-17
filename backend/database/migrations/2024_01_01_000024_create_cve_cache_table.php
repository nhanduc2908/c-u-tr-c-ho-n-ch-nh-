<?php
/**
 * MIGRATION: Create cve_cache table
 * 
 * Bảng cache thông tin CVE từ NVD API
 * Giảm số lần gọi API
 * 
 * @package Migrations
 */

use Core\Database;

class CreateCveCacheTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS cve_cache (
            id INT PRIMARY KEY AUTO_INCREMENT,
            cve_code VARCHAR(50) NOT NULL COMMENT 'Mã CVE',
            data JSON NOT NULL COMMENT 'Dữ liệu CVE',
            expires_at TIMESTAMP NOT NULL COMMENT 'Thời gian hết hạn',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_cve_code (cve_code),
            INDEX idx_expires_at (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng cache CVE'";
        
        $db->query($sql);
        
        echo "✓ Created cve_cache table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS cve_cache");
        echo "✓ Dropped cve_cache table\n";
    }
}