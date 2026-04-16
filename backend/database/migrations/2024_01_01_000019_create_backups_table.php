<?php
/**
 * MIGRATION: Create backups table
 * 
 * Bảng lưu thông tin các bản backup
 * Quản lý backup database và files
 * 
 * @package Migrations
 */

use Core\Database;

class CreateBackupsTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS backups (
            id INT PRIMARY KEY AUTO_INCREMENT,
            backup_name VARCHAR(255) NOT NULL COMMENT 'Tên file backup',
            backup_type ENUM('database', 'full', 'criteria') DEFAULT 'database',
            file_path VARCHAR(500) NOT NULL COMMENT 'Đường dẫn file',
            file_size BIGINT DEFAULT 0 COMMENT 'Kích thước file (bytes)',
            status ENUM('pending', 'running', 'completed', 'failed') DEFAULT 'completed',
            restored_at TIMESTAMP NULL COMMENT 'Thời gian restore gần nhất',
            created_by INT COMMENT 'Người tạo',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_backup_type (backup_type),
            INDEX idx_created_at (created_at),
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng quản lý backup'";
        
        $db->query($sql);
        
        echo "✓ Created backups table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS backups");
        echo "✓ Dropped backups table\n";
    }
}