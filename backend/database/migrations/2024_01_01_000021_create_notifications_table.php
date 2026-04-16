<?php
/**
 * MIGRATION: Create notifications table
 * 
 * Bảng lưu thông báo cho người dùng
 * Hỗ trợ thông báo realtime
 * 
 * @package Migrations
 */

use Core\Database;

class CreateNotificationsTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS notifications (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL COMMENT 'ID người nhận',
            type VARCHAR(50) NOT NULL COMMENT 'Loại thông báo',
            title VARCHAR(255) NOT NULL COMMENT 'Tiêu đề',
            message TEXT NOT NULL COMMENT 'Nội dung',
            data JSON COMMENT 'Dữ liệu bổ sung',
            is_read BOOLEAN DEFAULT FALSE,
            read_at TIMESTAMP NULL,
            reference_id INT COMMENT 'ID tham chiếu',
            reference_type VARCHAR(50) COMMENT 'Loại tham chiếu',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_is_read (is_read),
            INDEX idx_created_at (created_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng thông báo'";
        
        $db->query($sql);
        
        echo "✓ Created notifications table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS notifications");
        echo "✓ Dropped notifications table\n";
    }
}