<?php
/**
 * ASSESSMENT SEEDER
 * 
 * Tạo dữ liệu đánh giá mẫu cho demo
 * 
 * @package Seeds
 */

use Core\Database;

class AssessmentSeeder
{
    public function run()
    {
        $db = Database::getInstance();
        
        // Xóa dữ liệu cũ
        $db->query("TRUNCATE TABLE assessment_reports");
        $db->query("TRUNCATE TABLE assessment_results");
        
        // Lấy danh sách server và criteria
        $servers = $db->fetchAll("SELECT id FROM servers LIMIT 2");
        $criteria = $db->fetchAll("SELECT id FROM assessment_criteria LIMIT 50");
        
        if (empty($servers) || empty($criteria)) {
            echo "      - No servers or criteria found, skipping assessment seeding\n";
            return;
        }
        
        foreach ($servers as $server) {
            // Tạo report
            $totalCriteria = count($criteria);
            $passedCount = rand(30, 45);
            $failedCount = $totalCriteria - $passedCount;
            $totalScore = round(($passedCount / $totalCriteria) * 100, 1);
            
            $reportId = $db->insert('assessment_reports', [
                'server_id' => $server['id'],
                'report_name' => 'Initial Assessment',
                'total_score' => $totalScore,
                'total_criteria' => $totalCriteria,
                'passed_criteria' => $passedCount,
                'failed_criteria' => $failedCount,
                'status' => 'completed',
                'generated_at' => date('Y-m-d H:i:s', strtotime('-7 days'))
            ]);
            
            // Tạo kết quả chi tiết
            foreach ($criteria as $index => $crit) {
                $status = ($index < $passedCount) ? 'pass' : 'fail';
                $score = ($status === 'pass') ? 100 : 0;
                
                $db->insert('assessment_results', [
                    'report_id' => $reportId,
                    'server_id' => $server['id'],
                    'criteria_id' => $crit['id'],
                    'status' => $status,
                    'score_obtained' => $score,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
        
        echo "      - Created demo assessment data\n";
    }
}
