<?php
/**
 * MIGRATION: Create assessment_categories table
 * 
 * Bảng lưu 17 lĩnh vực đánh giá bảo mật
 * 
 * @package Migrations
 */

use Core\Database;

class CreateCategoriesTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS assessment_categories (
            id INT PRIMARY KEY AUTO_INCREMENT,
            code VARCHAR(20) UNIQUE NOT NULL COMMENT 'Mã lĩnh vực (IAM, NET, SEC, ...)',
            name VARCHAR(100) NOT NULL COMMENT 'Tên lĩnh vực',
            description TEXT COMMENT 'Mô tả chi tiết',
            weight_percent DECIMAL(5,2) DEFAULT 0 COMMENT 'Trọng số % của lĩnh vực',
            expected_score DECIMAL(5,2) DEFAULT 100 COMMENT 'Điểm tối đa',
            sort_order INT DEFAULT 0 COMMENT 'Thứ tự hiển thị',
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_code (code),
            INDEX idx_sort_order (sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng 17 lĩnh vực đánh giá'";
        
        $db->query($sql);
        
        echo "✓ Created assessment_categories table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS assessment_categories");
        echo "✓ Dropped assessment_categories table\n";
    }
}