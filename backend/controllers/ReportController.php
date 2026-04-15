<?php
/**
 * REPORT CONTROLLER
 * 
 * Xuất báo cáo PDF, Excel, CSV
 * - Báo cáo đánh giá server
 * - Báo cáo tổng hợp
 * - Báo cáo compliance
 * - Lên lịch báo cáo tự động
 * 
 * @package Controllers
 */

namespace Controllers;

use Core\Controller;
use Core\Database;
use Services\ReportGeneratorService;
use Services\NotificationService;

class ReportController extends Controller
{
    private $db;
    private $reportGenerator;
    private $notification;
    
    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->reportGenerator = new ReportGeneratorService();
        $this->notification = new NotificationService();
    }
    
    /**
     * GET /api/reports
     * 
     * Lấy danh sách báo cáo đã tạo
     */
    public function index()
    {
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $type = $_GET['type'] ?? null;
        
        $offset = ($page - 1) * $limit;
        $params = [];
        
        $sql = "SELECT r.*, u.username as created_by_name
                FROM reports r
                LEFT JOIN users u ON r.created_by = u.id
                WHERE 1=1";
        
        if ($type) {
            $sql .= " AND r.report_type = ?";
            $params[] = $type;
        }
        
        $sql .= " ORDER BY r.created_at DESC LIMIT {$limit} OFFSET {$offset}";
        
        $reports = $this->db->fetchAll($sql, $params);
        
        // Đếm tổng
        $countSql = str_replace("r.*, u.username as created_by_name", "COUNT(*) as total", $sql);
        $total = $this->db->fetchColumn($countSql, $params);
        
        return $this->success([
            'data' => $reports,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => (int)$total,
                'total_pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    /**
     * GET /api/reports/{id}
     * 
     * Chi tiết báo cáo
     */
    public function show($id)
    {
        $report = $this->db->fetchOne(
            "SELECT r.*, u.username as created_by_name
             FROM reports r
             LEFT JOIN users u ON r.created_by = u.id
             WHERE r.id = ?",
            [$id]
        );
        
        if (!$report) {
            return $this->error('Report not found', 404);
        }
        
        return $this->success($report);
    }
    
    /**
     * POST /api/reports/generate
     * 
     * Tạo báo cáo mới (PDF)
     */
    public function generate()
    {
        $data = $this->getRequestData();
        
        $this->validate($data, [
            'report_type' => 'required|in:assessment,summary,compliance,vulnerability',
            'server_id' => 'required_if:report_type,assessment|exists:servers,id',
            'format' => 'in:pdf,excel,csv'
        ]);
        
        $format = $data['format'] ?? 'pdf';
        $userId = $this->getUserId();
        
        try {
            $result = $this->reportGenerator->generate($data, $format, $userId);
            
            // Lưu thông tin báo cáo vào database
            $reportId = $this->db->insert('reports', [
                'report_name' => $result['filename'],
                'report_type' => $data['report_type'],
                'file_path' => $result['filepath'],
                'file_size' => $result['size'] ?? 0,
                'format' => $format,
                'status' => 'completed',
                'created_by' => $userId,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $this->logAction('REPORT_GENERATE', "Generated report: {$result['filename']}");
            
            return $this->success([
                'report_id' => $reportId,
                'filename' => $result['filename'],
                'download_url' => "/api/reports/{$reportId}/download"
            ], 'Report generated successfully');
            
        } catch (\Exception $e) {
            return $this->error('Failed to generate report: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/reports/{id}/download
     * 
     * Tải báo cáo
     */
    public function download($id)
    {
        $report = $this->db->fetchOne("SELECT * FROM reports WHERE id = ?", [$id]);
        if (!$report) {
            return $this->error('Report not found', 404);
        }
        
        $filepath = $report['file_path'];
        if (!file_exists($filepath)) {
            return $this->error('Report file not found', 404);
        }
        
        // Ghi log tải xuống
        $this->logAction('REPORT_DOWNLOAD', "Downloaded report ID: {$id}");
        
        // Xuất file
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $report['report_name'] . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
    
    /**
     * GET /api/reports/{id}/preview
     * 
     * Xem trước báo cáo
     */
    public function preview($id)
    {
        $report = $this->db->fetchOne("SELECT * FROM reports WHERE id = ?", [$id]);
        if (!$report) {
            return $this->error('Report not found', 404);
        }
        
        if ($report['format'] === 'pdf') {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $report['report_name'] . '"');
            readfile($report['file_path']);
            exit;
        }
        
        return $this->error('Preview not available for this format', 400);
    }
    
    /**
     * POST /api/reports/export-excel
     * 
     * Xuất báo cáo Excel
     */
    public function exportExcel()
    {
        $data = $this->getRequestData();
        
        $this->validate($data, [
            'data_type' => 'required|in:assessments,vulnerabilities,alerts,servers'
        ]);
        
        $result = $this->reportGenerator->exportToExcel($data['data_type'], $data['filters'] ?? []);
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
        echo $result['content'];
        exit;
    }
    
    /**
     * POST /api/reports/export-csv
     * 
     * Xuất báo cáo CSV
     */
    public function exportCsv()
    {
        $data = $this->getRequestData();
        
        $this->validate($data, [
            'data_type' => 'required|in:assessments,vulnerabilities,alerts,servers'
        ]);
        
        $result = $this->reportGenerator->exportToCsv($data['data_type'], $data['filters'] ?? []);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
        echo $result['content'];
        exit;
    }
    
    /**
     * POST /api/reports/generate-multi
     * 
     * Tạo báo cáo tổng hợp nhiều server
     */
    public function generateMultiServer()
    {
        $data = $this->getRequestData();
        
        $this->validate($data, [
            'server_ids' => 'required|array',
            'format' => 'in:pdf,excel'
        ]);
        
        $result = $this->reportGenerator->generateMultiServerReport(
            $data['server_ids'],
            $data['format'] ?? 'pdf',
            $this->getUserId()
        );
        
        return $this->success($result, 'Multi-server report generated');
    }
    
    /**
     * POST /api/reports/generate-compliance
     * 
     * Tạo báo cáo compliance (ISO 27001, NIST, PCI-DSS)
     */
    public function generateCompliance()
    {
        $data = $this->getRequestData();
        
        $this->validate($data, [
            'standard' => 'required|in:iso27001,nist,pci_dss',
            'format' => 'in:pdf,excel'
        ]);
        
        $result = $this->reportGenerator->generateComplianceReport(
            $data['standard'],
            $data['format'] ?? 'pdf',
            $this->getUserId()
        );
        
        return $this->success($result, 'Compliance report generated');
    }
    
    /**
     * POST /api/reports/schedule
     * 
     * Lên lịch tạo báo cáo tự động
     */
    public function schedule()
    {
        $data = $this->getRequestData();
        
        $this->validate($data, [
            'report_type' => 'required|in:assessment,summary,compliance,vulnerability',
            'schedule' => 'required|in:daily,weekly,monthly',
            'recipient_emails' => 'required|array'
        ]);
        
        $scheduleId = $this->db->insert('report_schedules', [
            'report_type' => $data['report_type'],
            'schedule' => $data['schedule'],
            'filters' => json_encode($data['filters'] ?? []),
            'recipient_emails' => json_encode($data['recipient_emails']),
            'format' => $data['format'] ?? 'pdf',
            'is_active' => 1,
            'created_by' => $this->getUserId(),
            'created_at' => date('Y-m-d H:i:s'),
            'last_run_at' => null,
            'next_run_at' => $this->calculateNextRun($data['schedule'])
        ]);
        
        $this->logAction('REPORT_SCHEDULE', "Scheduled report ID: {$scheduleId}");
        
        return $this->success(['schedule_id' => $scheduleId], 'Report scheduled successfully');
    }
    
    /**
     * POST /api/reports/{id}/email
     * 
     * Gửi báo cáo qua email
     */
    public function sendEmail($id)
    {
        $data = $this->getRequestData();
        
        $report = $this->db->fetchOne("SELECT * FROM reports WHERE id = ?", [$id]);
        if (!$report) {
            return $this->error('Report not found', 404);
        }
        
        $recipients = $data['recipients'] ?? [];
        if (empty($recipients)) {
            return $this->error('No recipients specified', 400);
        }
        
        $result = $this->notification->sendReportEmail(
            $recipients,
            $report['report_name'],
            $report['file_path']
        );
        
        $this->logAction('REPORT_EMAIL', "Sent report ID: {$id} to " . implode(',', $recipients));
        
        return $this->success([], 'Report sent via email');
    }
    
    /**
     * GET /api/reports/history
     * 
     * Lịch sử báo cáo
     */
    public function history()
    {
        $limit = (int)($_GET['limit'] ?? 50);
        
        $history = $this->db->fetchAll(
            "SELECT r.*, u.username as created_by_name
             FROM reports r
             LEFT JOIN users u ON r.created_by = u.id
             ORDER BY r.created_at DESC
             LIMIT ?",
            [$limit]
        );
        
        return $this->success($history);
    }
    
    /**
     * DELETE /api/reports/{id}
     * 
     * Xóa báo cáo (CHỦ ADMIN)
     */
    public function destroy($id)
    {
        $role = $this->getCurrentRole();
        if ($role !== 'admin') {
            return $this->error('Admin only', 403);
        }
        
        $report = $this->db->fetchOne("SELECT * FROM reports WHERE id = ?", [$id]);
        if (!$report) {
            return $this->error('Report not found', 404);
        }
        
        // Xóa file
        if (file_exists($report['file_path'])) {
            unlink($report['file_path']);
        }
        
        // Xóa record
        $this->db->delete('reports', 'id = ?', [$id]);
        
        $this->logAction('REPORT_DELETE', "Deleted report ID: {$id}");
        
        return $this->success([], 'Report deleted');
    }
    
    /**
     * DELETE /api/reports/cleanup
     * 
     * Xóa báo cáo cũ (CHỦ ADMIN)
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
        
        $reports = $this->db->fetchAll(
            "SELECT id, file_path FROM reports WHERE created_at < ?",
            [$cutoffDate]
        );
        
        $deleted = 0;
        foreach ($reports as $report) {
            if (file_exists($report['file_path'])) {
                unlink($report['file_path']);
            }
            $this->db->delete('reports', 'id = ?', [$report['id']]);
            $deleted++;
        }
        
        $this->logAction('REPORT_CLEANUP', "Deleted {$deleted} old reports");
        
        return $this->success(['deleted' => $deleted], "Deleted {$deleted} old reports");
    }
    
    /**
     * Tính ngày chạy tiếp theo
     */
    private function calculateNextRun($schedule)
    {
        $now = new \DateTime();
        
        switch ($schedule) {
            case 'daily':
                return $now->modify('+1 day')->format('Y-m-d 00:00:00');
            case 'weekly':
                return $now->modify('+1 week')->format('Y-m-d 00:00:00');
            case 'monthly':
                return $now->modify('+1 month')->format('Y-m-d 00:00:00');
            default:
                return $now->modify('+1 day')->format('Y-m-d 00:00:00');
        }
    }
}