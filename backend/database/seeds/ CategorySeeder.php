<?php
/**
 * CATEGORY SEEDER
 * 
 * Tạo 17 lĩnh vực đánh giá bảo mật
 * 
 * @package Seeds
 */

use Core\Database;

class CategorySeeder
{
    public function run()
    {
        $db = Database::getInstance();
        
        // Xóa dữ liệu cũ
        $db->query("TRUNCATE TABLE assessment_categories");
        
        // 17 lĩnh vực
        $categories = [
            ['code' => 'IAM', 'name' => 'Quản lý danh tính và truy cập', 'weight_percent' => 10, 'sort_order' => 1],
            ['code' => 'NET', 'name' => 'An ninh mạng', 'weight_percent' => 12, 'sort_order' => 2],
            ['code' => 'SEC', 'name' => 'Cấu hình bảo mật', 'weight_percent' => 15, 'sort_order' => 3],
            ['code' => 'LOG', 'name' => 'Giám sát và ghi log', 'weight_percent' => 8, 'sort_order' => 4],
            ['code' => 'DATA', 'name' => 'Bảo vệ dữ liệu', 'weight_percent' => 10, 'sort_order' => 5],
            ['code' => 'APP', 'name' => 'An ninh ứng dụng', 'weight_percent' => 12, 'sort_order' => 6],
            ['code' => 'END', 'name' => 'An ninh thiết bị đầu cuối', 'weight_percent' => 8, 'sort_order' => 7],
            ['code' => 'CLOUD', 'name' => 'An ninh đám mây', 'weight_percent' => 7, 'sort_order' => 8],
            ['code' => 'DEV', 'name' => 'DevSecOps', 'weight_percent' => 5, 'sort_order' => 9],
            ['code' => 'PHY', 'name' => 'An ninh vật lý', 'weight_percent' => 3, 'sort_order' => 10],
            ['code' => 'POL', 'name' => 'Chính sách và quy trình', 'weight_percent' => 5, 'sort_order' => 11],
            ['code' => 'BCP', 'name' => 'Khắc phục thảm họa', 'weight_percent' => 5, 'sort_order' => 12],
            ['code' => 'THIRD', 'name' => 'An ninh bên thứ ba', 'weight_percent' => 3, 'sort_order' => 13],
            ['code' => 'MOB', 'name' => 'An ninh thiết bị di động', 'weight_percent' => 3, 'sort_order' => 14],
            ['code' => 'IOT', 'name' => 'An ninh IoT', 'weight_percent' => 2, 'sort_order' => 15],
            ['code' => 'AI', 'name' => 'An ninh AI/ML', 'weight_percent' => 2, 'sort_order' => 16],
            ['code' => 'COMP', 'name' => 'Tuân thủ và pháp lý', 'weight_percent' => 5, 'sort_order' => 17],
        ];
        
        foreach ($categories as $cat) {
            $db->insert('assessment_categories', [
                'code' => $cat['code'],
                'name' => $cat['name'],
                'description' => "Mô tả chi tiết cho lĩnh vực {$cat['name']}",
                'weight_percent' => $cat['weight_percent'],
                'sort_order' => $cat['sort_order'],
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        echo "      - Created 17 categories\n";
    }
}