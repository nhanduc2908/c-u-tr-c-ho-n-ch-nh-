<?php
/**
 * ASSESSMENT CONTROLLER
 * 
 * Quản lý đánh giá bảo mật server - QUAN TRỌNG NHẤT
 * - Chạy đánh giá 280 tiêu chí
 * - Tính điểm theo trọng số
 * - Lưu kết quả vào database
 * - Xuất báo cáo đánh giá
 * - So sánh kết quả giữa các lần đánh giá
 * 
 * @package Controllers
 */

namespace Controllers;

use Core\Controller;
use Core\Database;
use Core\Logger;
use Services\ScannerService;
use Services\ScoreCalculatorService;
use Services\RealtimeService;

class AssessmentController extends Controller
{
    private $db;
    private $scanner;
    private $scoreCalculator;
    private $realtime;
    
    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->scanner = new ScannerService();
        $this->scoreCalculator = new ScoreCalculatorService();
        $this->realtime = new RealtimeService();
    }
    
    /**
     * GET /api/assessments
     * 
     * Lấy danh sách đánh giá
     */
    public function index()
    {
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $serverId = $_GET['server_id'] ?? null;
        $status = $_GET['status'] ?? null;
        
        $offset = ($page - 1) * $limit;
        $params = [];
        
        $sql = "SELECT ar.*, s.name as server_name, u.username as created_by_name
                FROM assessment_reports ar
                JOIN servers s ON ar.server_id = s.id
                LEFT JOIN users u ON ar.generated_by = u.id
                WHERE 1=1";
        
        if ($serverId) {
            $sql .= " AND ar.server_id = ?";
            $params[] = $serverId;
        }
        
        if ($status) {
            $sql .= " AND ar.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY ar.generated_at DESC LIMIT {$limit} OFFSET {$offset}";
        
        $assessments = $this->db->fetchAll($sql, $params);
        
        // Đếm tổng
        $countSql = str_replace("ar.*, s.name as server_name, u.username as created_by_name", 
                                "COUNT(*) as total", $sql);
        $total = $this->db->fetchColumn($countSql, $params);
        
        return $this->success([
            'data' => $assessments,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => (int)$total,
                'total_pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    /**
     * GET /api/assessments/{id}
     * 
     * Chi tiết đánh giá
     */
    public function show($id)
    {
        $assessment = $this->db->fetchOne(
            "SELECT ar.*, s.name as server_name, s.ip_address, u.username as created_by_name
             FROM assessment_reports ar
             JOIN servers s ON ar.server_id = s.id
             LEFT JOIN users u ON ar.generated_by = u.id
             WHERE ar.id = ?",
            [$id]
        );
        
        if (!$assessment) {
            return $this->error('Assessment not found', 404);
        }
        
        // Lấy chi tiết kết quả từng tiêu chí
        $details = $this->db->fetchAll(
            "SELECT r.*, c.code, c.name as criteria_name, c.category_id, c.severity, c.weight,
                    cat.name as category_name
             FROM assessment_results r
             JOIN assessment_criteria c ON r.criteria_id = c.id
             JOIN assessment_categories cat ON c.category_id = cat.id
             WHERE r.report_id = ?
             ORDER BY cat.sort_order, c.sort_order",
            [$id]
        );
        
        $assessment['details'] = $details;
        
        // Thống kê theo category
        $categoryStats = [];
        foreach ($details as $detail) {
            $catId = $detail['category_id'];
            if (!isset($categoryStats[$catId])) {
                $categoryStats[$catId] = [
                    'category_name' => $detail['category_name'],
                    'total' => 0,
                    'passed' => 0,
                    'failed' => 0,
                    'score' => 0
                ];
            }
            $categoryStats[$catId]['total']++;
            if ($detail['status'] === 'pass') {
                $categoryStats[$catId]['passed']++;
            } else {
                $categoryStats[$catId]['failed']++;
            }
            $categoryStats[$catId]['score'] += $detail['score_obtained'];
        }
        
        foreach ($categoryStats as &$stat) {
            $stat['score'] = $stat['total'] > 0 ? round($stat['score'] / $stat['total'], 1) : 0;
        }
        
        $assessment['category_stats'] = array_values($categoryStats);
        
        return $this->success($assessment);
    }
    
    /**
     * GET /api/assessments/server/{serverId}
     * 
     * Lấy kết quả đánh giá theo server
     */
    public function getByServer($serverId)
    {
        $server = $this->db->fetchOne("SELECT * FROM servers WHERE id = ?", [$serverId]);
        if (!$server) {
            return $this->error('Server not found', 404);
        }
        
        // Lấy đánh giá mới nhất
        $latest = $this->db->fetchOne(
            "SELECT * FROM assessment_reports WHERE server_id = ? ORDER BY generated_at DESC LIMIT 1",
            [$serverId]
        );
        
        // Lấy lịch sử đánh giá
        $history = $this->db->fetchAll(
            "SELECT id, total_score, passed_criteria, failed_criteria, generated_at
             FROM assessment_reports
             WHERE server_id = ?
             ORDER BY generated_at DESC
             LIMIT 10",
            [$serverId]
        );
        
        // Lấy điểm trung bình
        $avgScore = $this->db->fetchColumn(
            "SELECT AVG(total_score) FROM assessment_reports WHERE server_id = ?",
            [$serverId]
        );
        
        // Lấy xu hướng (so sánh với lần trước)
        $trend = null;
        if (count($history) >= 2) {
            $trend = [
                'previous_score' => $history[1]['total_score'],
                'current_score' => $history[0]['total_score'],
                'change' => round($history[0]['total_score'] - $history[1]['total_score'], 1)
            ];
        }
        
        return $this->success([
            'server' => $server,
            'latest_assessment' => $latest,
            'history' => $history,
            'average_score' => round($avgScore ?: 0, 1),
            'trend' => $trend
        ]);
    }
    
    /**
     * POST /api/assessments/run
     * 
     * Chạy đánh giá mới
     */
    public function run()
    {
        $data = $this->getRequestData();
        
        $this->validate($data, [
            'server_id' => 'required|exists:servers,id'
        ]);
        
        $serverId = $data['server_id'];
        $userId = $this->getUserId();
        
        // Kiểm tra server tồn tại
        $server = $this->db->fetchOne("SELECT * FROM servers WHERE id = ?", [$serverId]);
        if (!$server) {
            return $this->error('Server not found', 404);
        }
        
        // Gửi realtime notification bắt đầu scan
        $this->realtime->push('scan_started', [
            'server_id' => $serverId,
            'server_name' => $server['name'],
            'user_id' => $userId
        ]);
        
        try {
            // Chạy scan
            $result = $this->scanner->scanServer($serverId, $userId);
            
            if (!$result['success']) {
                throw new \Exception($result['error'] ?? 'Scan failed');
            }
            
            // Lưu report
            $reportId = $this->saveAssessmentReport($serverId, $result, $userId);
            
            // Lưu chi tiết kết quả
            $this->saveAssessmentResults($reportId, $result['details']);
            
            // Gửi realtime notification hoàn thành
            $this->realtime->push('scan_completed', [
                'server_id' => $serverId,
                'server_name' => $server['name'],
                'score' => $result['total_score'],
                'report_id' => $reportId
            ]);
            
            // Ghi log
            $this->logAction('ASSESSMENT_RUN', "Run assessment for server ID: {$serverId}, Score: {$result['total_score']}%");
            
            // Tạo cảnh báo nếu điểm thấp
            if ($result['total_score'] < 60) {
                $this->createLowScoreAlert($serverId, $result['total_score'], $reportId);
            }
            
            return $this->success([
                'report_id' => $reportId,
                'total_score' => $result['total_score'],
                'passed' => $result['passed'],
                'failed' => $result['failed'],
                'total' => $result['total'],
                'details' => $result['details']
            ], 'Assessment completed successfully');
            
        } catch (\Exception $e) {
            Logger::error('Assessment failed', [
                'server_id' => $serverId,
                'error' => $e->getMessage()
            ]);
            
            $this->realtime->push('scan_failed', [
                'server_id' => $serverId,
                'server_name' => $server['name'],
                'error' => $e->getMessage()
            ]);
            
            return $this->error('Assessment failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * POST /api/assessments/server/{serverId}/run
     * 
     * Chạy đánh giá cho một server cụ thể
     */
    public function runForServer($serverId)
    {
        return $this->run(['server_id' => $serverId]);
    }
    
    /**
     * PUT /api/assessments/{resultId}/manual
     * 
     * Đánh giá thủ công (cập nhật kết quả)
     */
    public function manualUpdate($resultId)
    {
        $data = $this->getRequestData();
        
        $this->validate($data, [
            'status' => 'required|in:pass,fail,warning'
        ]);
        
        $result = $this->db->fetchOne("SELECT * FROM assessment_results WHERE id = ?", [$resultId]);
        if (!$result) {
            return $this->error('Assessment result not found', 404);
        }
        
        $this->db->update('assessment_results', [
            'status' => $data['status'],
            'score_obtained' => $data['status'] === 'pass' ? 100 : 0,
            'notes' => $data['notes'] ?? null,
            'checked_by' => $this->getUserId(),
            'checked_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$resultId]);
        
        // Cập nhật lại tổng điểm của report
        $this->recalculateReportScore($result['report_id']);
        
        $this->logAction('ASSESSMENT_MANUAL', "Manual update for result ID: {$resultId}");
        
        return $this->success([], 'Assessment result updated manually');
    }
    
    /**
     * POST /api/assessments/{resultId}/evidence
     * 
     * Upload bằng chứng cho assessment
     */
    public function uploadEvidence($resultId)
    {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            return $this->error('No file uploaded', 400);
        }
        
        $result = $this->db->fetchOne("SELECT * FROM assessment_results WHERE id = ?", [$resultId]);
        if (!$result) {
            return $this->error('Assessment result not found', 404);
        }
        
        $file = $_FILES['file'];
        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        
        if (!in_array($file['type'], $allowedTypes)) {
            return $this->error('Invalid file type', 400);
        }
        
        $uploadPath = __DIR__ . '/../storage/uploads/evidence/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
        
        $filename = 'evidence_' . $resultId . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $file['name']);
        $filepath = $uploadPath . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $this->db->update('assessment_results', [
                'evidence_path' => $filename,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$resultId]);
            
            return $this->success([
                'evidence_path' => $filename,
                'evidence_url' => $_ENV['APP_URL'] . '/uploads/evidence/' . $filename
            ], 'Evidence uploaded successfully');
        }
        
        return $this->error('Failed to upload file', 500);
    }
    
    /**
     * POST /api/assessments/{reportId}/approve
     * 
     * Phê duyệt assessment
     */
    public function approve($reportId)
    {
        $report = $this->db->fetchOne("SELECT * FROM assessment_reports WHERE id = ?", [$reportId]);
        if (!$report) {
            return $this->error('Assessment report not found', 404);
        }
        
        $this->db->update('assessment_reports', [
            'status' => 'approved',
            'approved_by' => $this->getUserId(),
            'approved_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$reportId]);
        
        $this->logAction('ASSESSMENT_APPROVE', "Approved assessment report ID: {$reportId}");
        
        return $this->success([], 'Assessment approved successfully');
    }
    
    /**
     * POST /api/assessments/{reportId}/cancel
     * 
     * Hủy assessment đang chạy
     */
    public function cancel($reportId)
    {
        $report = $this->db->fetchOne("SELECT * FROM assessment_reports WHERE id = ?", [$reportId]);
        if (!$report) {
            return $this->error('Assessment report not found', 404);
        }
        
        if ($report['status'] !== 'running') {
            return $this->error('Only running assessments can be cancelled', 400);
        }
        
        $this->db->update('assessment_reports', [
            'status' => 'cancelled',
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$reportId]);
        
        $this->logAction('ASSESSMENT_CANCEL', "Cancelled assessment report ID: {$reportId}");
        
        return $this->success([], 'Assessment cancelled');
    }
    
    /**
     * GET /api/assessments/compare/{reportId1}/{reportId2}
     * 
     * So sánh kết quả giữa 2 lần đánh giá
     */
    public function compare($reportId1, $reportId2)
    {
        $report1 = $this->db->fetchOne("SELECT * FROM assessment_reports WHERE id = ?", [$reportId1]);
        $report2 = $this->db->fetchOne("SELECT * FROM assessment_reports WHERE id = ?", [$reportId2]);
        
        if (!$report1 || !$report2) {
            return $this->error('One or both reports not found', 404);
        }
        
        if ($report1['server_id'] !== $report2['server_id']) {
            return $this->error('Reports must be for the same server', 400);
        }
        
        // Lấy chi tiết kết quả
        $details1 = $this->db->fetchAll(
            "SELECT r.*, c.code, c.name FROM assessment_results r
             JOIN assessment_criteria c ON r.criteria_id = c.id
             WHERE r.report_id = ?",
            [$reportId1]
        );
        
        $details2 = $this->db->fetchAll(
            "SELECT r.*, c.code, c.name FROM assessment_results r
             JOIN assessment_criteria c ON r.criteria_id = c.id
             WHERE r.report_id = ?",
            [$reportId2]
        );
        
        // Tạo map để so sánh
        $map1 = [];
        foreach ($details1 as $d) {
            $map1[$d['criteria_id']] = $d;
        }
        
        $comparison = [];
        foreach ($details2 as $d2) {
            $d1 = $map1[$d2['criteria_id']] ?? null;
            $comparison[] = [
                'code' => $d2['code'],
                'name' => $d2['name'],
                'previous_status' => $d1 ? $d1['status'] : 'N/A',
                'current_status' => $d2['status'],
                'previous_score' => $d1 ? $d1['score_obtained'] : 0,
                'current_score' => $d2['score_obtained'],
                'changed' => $d1 ? ($d1['status'] !== $d2['status']) : true
            ];
        }
        
        return $this->success([
            'report_1' => [
                'id' => $report1['id'],
                'date' => $report1['generated_at'],
                'score' => $report1['total_score']
            ],
            'report_2' => [
                'id' => $report2['id'],
                'date' => $report2['generated_at'],
                'score' => $report2['total_score']
            ],
            'score_change' => round($report2['total_score'] - $report1['total_score'], 1),
            'comparison' => $comparison
        ]);
    }
    
    /**
     * DELETE /api/assessments/{id}
     * 
     * Xóa assessment report (CHỦ ADMIN)
     */
    public function destroy($id)
    {
        $role = $this->getCurrentRole();
        if ($role !== 'admin') {
            return $this->error('Admin only', 403);
        }
        
        $report = $this->db->fetchOne("SELECT * FROM assessment_reports WHERE id = ?", [$id]);
        if (!$report) {
            return $this->error('Assessment report not found', 404);
        }
        
        // Xóa chi tiết trước
        $this->db->delete('assessment_results', 'report_id = ?', [$id]);
        
        // Xóa report
        $this->db->delete('assessment_reports', 'id = ?', [$id]);
        
        $this->logAction('ASSESSMENT_DELETE', "Deleted assessment report ID: {$id}");
        
        return $this->success([], 'Assessment report deleted');
    }
    
    /**
     * DELETE /api/assessments/cleanup
     * 
     * Xóa tất cả assessment cũ (CHỦ ADMIN)
     */
    public function cleanup()
    {
        $role = $this->getCurrentRole();
        if ($role !== 'admin') {
            return $this->error('Admin only', 403);
        }
        
        $data = $this->getRequestData();
        $olderThanDays = (int)($data['older_than_days'] ?? 90);
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$olderThanDays} days"));
        
        // Lấy danh sách report cần xóa
        $reports = $this->db->fetchAll(
            "SELECT id FROM assessment_reports WHERE generated_at < ?",
            [$cutoffDate]
        );
        
        $deleted = 0;
        foreach ($reports as $report) {
            $this->db->delete('assessment_results', 'report_id = ?', [$report['id']]);
            $this->db->delete('assessment_reports', 'id = ?', [$report['id']]);
            $deleted++;
        }
        
        $this->logAction('ASSESSMENT_CLEANUP', "Deleted {$deleted} old assessment reports");
        
        return $this->success(['deleted' => $deleted], "Deleted {$deleted} old assessments");
    }
    
    // ============================================
    // PRIVATE METHODS
    // ============================================
    
    private function saveAssessmentReport($serverId, $result, $userId)
    {
        return $this->db->insert('assessment_reports', [
            'server_id' => $serverId,
            'report_name' => 'Assessment_' . date('Ymd_His'),
            'total_score' => $result['total_score'],
            'total_criteria' => $result['total'],
            'passed_criteria' => $result['passed'],
            'failed_criteria' => $result['failed'],
            'warning_criteria' => $result['warning'] ?? 0,
            'status' => 'completed',
            'generated_by' => $userId,
            'generated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    private function saveAssessmentResults($reportId, $details)
    {
        foreach ($details as $detail) {
            $this->db->insert('assessment_results', [
                'report_id' => $reportId,
                'server_id' => $detail['server_id'] ?? null,
                'criteria_id' => $detail['criteria_id'],
                'status' => $detail['status'],
                'actual_value' => $detail['actual_value'] ?? null,
                'score_obtained' => $detail['score'] ?? 0,
                'notes' => $detail['notes'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
    
    private function recalculateReportScore($reportId)
    {
        $results = $this->db->fetchAll(
            "SELECT status, score_obtained FROM assessment_results WHERE report_id = ?",
            [$reportId]
        );
        
        $total = count($results);
        $passed = 0;
        $totalScore = 0;
        
        foreach ($results as $result) {
            if ($result['status'] === 'pass') {
                $passed++;
            }
            $totalScore += $result['score_obtained'];
        }
        
        $avgScore = $total > 0 ? round($totalScore / $total, 1) : 0;
        
        $this->db->update('assessment_reports', [
            'total_score' => $avgScore,
            'passed_criteria' => $passed,
            'failed_criteria' => $total - $passed,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$reportId]);
    }
    
    private function createLowScoreAlert($serverId, $score, $reportId)
    {
        $server = $this->db->fetchOne("SELECT name FROM servers WHERE id = ?", [$serverId]);
        
        $this->db->insert('alerts', [
            'server_id' => $serverId,
            'type' => 'low_score',
            'severity' => $score < 40 ? 'critical' : 'high',
            'title' => 'Low Security Score Detected',
            'message' => "Server {$server['name']} has a security score of {$score}%. Immediate attention required.",
            'reference_id' => $reportId,
            'reference_type' => 'assessment_report',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Gửi realtime alert
        $this->realtime->push('alert', [
            'server_id' => $serverId,
            'server_name' => $server['name'],
            'score' => $score,
            'severity' => $score < 40 ? 'critical' : 'high'
        ]);
    }
}