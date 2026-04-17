<?php
/**
 * MIGRATION: Create failed_jobs table
 * 
 * Bảng lưu các job thất bại
 * Để xử lý lại sau
 * 
 * @package Migrations
 */

use Core\Database;

class CreateFailedJobsTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS failed_jobs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            uuid VARCHAR(255) NOT NULL UNIQUE,
            connection TEXT NOT NULL,
            queue TEXT NOT NULL,
            payload TEXT NOT NULL,
            exception TEXT NOT NULL,
            failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_failed_at (failed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng jobs thất bại'";
        
        $db->query($sql);
        
        echo "✓ Created failed_jobs table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS failed_jobs");
        echo "✓ Dropped failed_jobs table\n";
    }
}