<?php
/**
 * MIGRATION: Create rate_limits table
 * 
 * Bảng lưu thông tin rate limiting
 * Chống spam và DoS attack
 * 
 * @package Migrations
 */

use Core\Database;

class CreateRateLimitsTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS rate_limits (
            id INT PRIMARY KEY AUTO_INCREMENT,
            `key` VARCHAR(100) NOT NULL COMMENT 'Key (IP + endpoint)',
            requests INT DEFAULT 1 COMMENT 'Số request',
            first_request TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_request TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_key (`key`),
            INDEX idx_first_request (first_request)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng rate limiting'";
        
        $db->query($sql);
        
        echo "✓ Created rate_limits table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS rate_limits");
        echo "✓ Dropped rate_limits table\n";
    }
}