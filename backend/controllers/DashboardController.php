<?php
/**
 * DASHBOARD CONTROLLER
 * 
 * Cung cấp dữ liệu cho dashboard
 * - Thống kê tổng quan
 - Dữ liệu biểu đồ
 * - Xu hướng
 * - Hoạt động gần đây
 * 
 * @package Controllers
 */

namespace Controllers;

use Core\Controller;
use Core\Database;

class DashboardController extends Controller
{
    private $db;
    
    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
    }
    
    /**
     * GET /api/dashboard/stats
     * 
     * Thống kê tổng quan
     */
    public function stats()
    {
        $role = $this->getCurrentRole();
        $userId = $this->getUserId();
        
        // Số lượng server
        if ($role === 'viewer') {
            $totalServers = $this->db->fetchColumn(
                "SELECT COUNT(*) FROM user_server WHERE user_id = ?",
                [$userId]
            );
        } else {
            $totalServers = $this->db->fetchColumn("SELECT COUNT(*) FROM servers");
        }
        
        // Số lượng tiêu chí
        $totalCriteria = $this->db->fetchColumn("SELECT COUNT(*) FROM assessment_criteria WHERE is_active = 1");
        
        // Số lượng cảnh báo chưa xử lý
        $pendingAlerts = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM alerts WHERE is_resolved = 0"
        );
        
        // Điểm bảo mật trung bình
        $avgScore = $this->db->fetchColumn(
            "SELECT AVG(total_score) FROM assessment_reports WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $avgScore = round($avgScore ?: 0, 1);
        
        // Số lần đánh giá trong tháng
        $assessmentsThisMonth = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM assessment_reports WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())"
        );
        
        return $this->success([
            'total_servers' => (int)$totalServers,
            'total_criteria' => (int)$totalCriteria,
            'pending_alerts' => (int)$pendingAlerts,
            'avg_security_score' => (float)$avgScore,
            'assessments_this_month' => (int)$assessmentsThisMonth
        ]);
    }
    
    /**
     * GET /api/dashboard/charts
     * 
     * Dữ liệu biểu đồ
     */
    public function charts()
    {
        // Biểu đồ phân bố severity
        $severityData = $this->db->fetchAll(
            "SELECT severity, COUNT(*) as count 
             FROM assessment_criteria 
             WHERE is_active = 1 
             GROUP BY severity"
        );
        
        // Biểu đồ điểm theo category
        $categoryScores = $this->db->fetchAll(
            "SELECT c.name, AVG(r.score_obtained) as avg_score
             FROM assessment_results r
             JOIN assessment_criteria cr ON r.criteria_id = cr.id
             JOIN assessment_categories c ON cr.category_id = c.id
             WHERE r.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY c.id
             ORDER BY c.sort_order
             LIMIT 10"
        );
        
        // Biểu đồ top server có điểm thấp nhất
        $lowestScoreServers = $this->db->fetchAll(
            "SELECT s.name, ar.total_score
             FROM assessment_reports ar
             JOIN servers s ON ar.server_id = s.id
             WHERE ar.id IN (
                 SELECT MAX(id) FROM assessment_reports GROUP BY server_id
             )
             ORDER BY ar.total_score ASC
             LIMIT 5"
        );
        
        return $this->success([
            'severity_distribution' => $severityData,
            'category_scores' => $categoryScores,
            'lowest_score_servers' => $lowestScoreServers
        ]);
    }
    
    /**
     * GET /api/dashboard/trends
     * 
     * Xu hướng điểm bảo mật
     */
    public function trends()
    {
        $days = (int)($_GET['days'] ?? 30);
        
        $trends = $this->db->fetchAll(
            "SELECT DATE(created_at) as date, 
                    AVG(total_score) as avg_score,
                    COUNT(*) as assessments_count
             FROM assessment_reports
             WHERE created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(created_at)
             ORDER BY date",
            [$days]
        );
        
        return $this->success($trends);
    }
    
    /**
     * GET /api/dashboard/recent-alerts
     * 
     * Cảnh báo gần đây
     */
    public function recentAlerts()
    {
        $limit = (int)($_GET['limit'] ?? 10);
        
        $alerts = $this->db->fetchAll(
            "SELECT a.*, s.name as server_name
             FROM alerts a
             LEFT JOIN servers s ON a.server_id = s.id
             ORDER BY a.created_at DESC
             LIMIT ?",
            [$limit]
        );
        
        return $this->success($alerts);
    }
    
    /**
     * GET /api/dashboard/recent-activities
     * 
     * Hoạt động gần đây
     */
    public function recentActivities()
    {
        $limit = (int)($_GET['limit'] ?? 10);
        
        $activities = $this->db->fetchAll(
            "SELECT al.*, u.username
             FROM audit_logs al
             LEFT JOIN users u ON al.user_id = u.id
             ORDER BY al.created_at DESC
             LIMIT ?",
            [$limit]
        );
        
        return $this->success($activities);
    }
    
    /**
     * GET /api/dashboard/category-stats
     * 
     * Thống kê theo 17 lĩnh vực
     */
    public function categoryStats()
    {
        $stats = $this->db->fetchAll(
            "SELECT c.id, c.code, c.name, c.weight_percent,
                    COUNT(cr.id) as total_criteria,
                    SUM(CASE WHEN cr.is_active = 1 THEN 1 ELSE 0 END) as active_criteria
             FROM assessment_categories c
             LEFT JOIN assessment_criteria cr ON c.id = cr.category_id
             GROUP BY c.id
             ORDER BY c.sort_order"
        );
        
        return $this->success($stats);
    }
    
    /**
     * GET /api/dashboard/compliance
     * 
     * Tổng hợp tuân thủ (ISO 27001, NIST)
     */
    public function complianceSummary()
    {
        // Tính phần trăm tuân thủ theo các chuẩn
        $iso27001 = $this->db->fetchColumn(
            "SELECT AVG(CASE WHEN status = 'pass' THEN 100 ELSE 0 END) 
             FROM assessment_results r
             JOIN assessment_criteria c ON r.criteria_id = c.id
             WHERE c.reference_standard LIKE '%ISO 27001%'
             AND r.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        $nist = $this->db->fetchColumn(
            "SELECT AVG(CASE WHEN status = 'pass' THEN 100 ELSE 0 END) 
             FROM assessment_results r
             JOIN assessment_criteria c ON r.criteria_id = c.id
             WHERE c.reference_standard LIKE '%NIST%'
             AND r.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        $pci = $this->db->fetchColumn(
            "SELECT AVG(CASE WHEN status = 'pass' THEN 100 ELSE 0 END) 
             FROM assessment_results r
             JOIN assessment_criteria c ON r.criteria_id = c.id
             WHERE c.reference_standard LIKE '%PCI-DSS%'
             AND r.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        return $this->success([
            'iso_27001' => round($iso27001 ?: 0, 1),
            'nist' => round($nist ?: 0, 1),
            'pci_dss' => round($pci ?: 0, 1),
            'last_updated' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * GET /api/dashboard/export
     * 
     * Export dashboard data
     */
    public function export()
    {
        $format = $_GET['format'] ?? 'csv';
        
        $data = [
            'stats' => $this->stats()['data'],
            'trends' => $this->trends()['data'],
            'category_stats' => $this->categoryStats()['data']
        ];
        
        if ($format === 'json') {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="dashboard_data_' . date('Y-m-d') . '.json"');
            echo json_encode($data, JSON_PRETTY_PRINT);
            exit;
        }
        
        // CSV export
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="dashboard_data_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Metric', 'Value']);
        fputcsv($output, ['Total Servers', $data['stats']['total_servers']]);
        fputcsv($output, ['Total Criteria', $data['stats']['total_criteria']]);
        fputcsv($output, ['Pending Alerts', $data['stats']['pending_alerts']]);
        fputcsv($output, ['Average Security Score', $data['stats']['avg_security_score'] . '%']);
        
        fclose($output);
        exit;
    }
}