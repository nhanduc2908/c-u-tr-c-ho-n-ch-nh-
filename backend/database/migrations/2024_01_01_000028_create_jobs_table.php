<?php
/**
 * MIGRATION: Create jobs table
 * 
 * Bảng lưu queue jobs
 * Xử lý các tác vụ bất đồng bộ
 * 
 * @package Migrations
 */

use Core\Database;

class CreateJobsTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS jobs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            queue VARCHAR(255) NOT NULL COMMENT 'Queue name',
            payload TEXT NOT NULL COMMENT 'Job payload',
            attempts INT DEFAULT 0 COMMENT 'Số lần thử',
            reserved_at INT NULL,
            available_at INT NOT NULL,
            created_at INT NOT NULL,
            INDEX idx_queue (queue)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng queue jobs'";
        
        $db->query($sql);
        
        echo "✓ Created jobs table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS jobs");
        echo "✓ Dropped jobs table\n";
    }
}