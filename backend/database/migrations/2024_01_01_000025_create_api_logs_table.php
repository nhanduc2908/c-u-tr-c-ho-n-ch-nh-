<?php
/**
 * MIGRATION: Create api_logs table
 * 
 * Bảng lưu log các API request
 * Phục vụ debug và phân tích hiệu năng
 * 
 * @package Migrations
 */

use Core\Database;

class CreateApiLogsTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS api_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT COMMENT 'ID người dùng',
            method VARCHAR(10) NOT NULL COMMENT 'HTTP method',
            endpoint VARCHAR(255) NOT NULL COMMENT 'API endpoint',
            status_code INT COMMENT 'HTTP status code',
            response_time INT COMMENT 'Thời gian xử lý (ms)',
            ip_address VARCHAR(45) COMMENT 'IP',
            user_agent TEXT COMMENT 'User Agent',
            request_body TEXT COMMENT 'Request body',
            response_body TEXT COMMENT 'Response body',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_endpoint (endpoint),
            INDEX idx_status_code (status_code),
            INDEX idx_created_at (created_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng log API'";
        
        $db->query($sql);
        
        echo "✓ Created api_logs table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS api_logs");
        echo "✓ Dropped api_logs table\n";
    }
}