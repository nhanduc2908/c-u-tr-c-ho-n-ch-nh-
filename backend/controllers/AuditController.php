<?php
/**
 * AUDIT CONTROLLER
 * 
 * Quản lý và xem nhật ký hệ thống - CHỈ ADMIN
 * 
 * @package Controllers
 */

namespace Controllers;

use Core\Controller;
use Core\Database;
use Core\Logger;

class AuditController extends Controller
{
    private $db;
    
    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
    }
    
    /**
     * GET /api/audit/logs
     * 
     * Lấy danh sách audit logs
     */
    public function index()
    {
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 50);
        $userId = $_GET['user_id'] ?? null;
        $action = $_GET['action'] ?? null;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;
        $search = $_GET['search'] ?? null;
        
        $offset = ($page - 1) * $limit;
        $params = [];
        
        $sql = "SELECT * FROM audit_logs WHERE 1=1";
        
        if ($userId) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        if ($action) {
            $sql .= " AND action = ?";
            $params[] = $action;
        }
        
        if ($fromDate) {
            $sql .= " AND created_at >= ?";
            $params[] = $fromDate . ' 00:00:00';
        }
        
        if ($toDate) {
            $sql .= " AND created_at <= ?";
            $params[] = $toDate . ' 23:59:59';
        }
        
        if ($search) {
            $sql .= " AND (action LIKE ? OR details LIKE ? OR ip_address LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        
        // Đếm tổng
        $countSql = str_replace("SELECT *", "SELECT COUNT(*) as total", $sql);
        $total = $this->db->fetchColumn($countSql, $params);
        
        // Lấy dữ liệu
        $sql .= " ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";
        $logs = $this->db->fetchAll($sql, $params);
        
        // Thêm thông tin user cho mỗi log
        foreach ($logs as &$log) {
            if ($log['user_id']) {
                $user = $this->db->fetchOne(
                    "SELECT username, email FROM users WHERE id = ?",
                    [$log['user_id']]
                );
                $log['user'] = $user;
            }
        }
        
        // Thống kê nhanh
        $stats = $this->getQuickStats($fromDate, $toDate);
        
        return $this->success([
            'data' => $logs,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => (int)$total,
                'total_pages' => ceil($total / $limit)
            ],
            'stats' => $stats
        ]);
    }
    
    /**
     * GET /api/audit/logs/{id}
     * 
     * Xem chi tiết một log
     */
    public function show($id)
    {
        $log = $this->db->fetchOne(
            "SELECT * FROM audit_logs WHERE id = ?",
            [$id]
        );
        
        if (!$log) {
            return $this->error('Log not found', 404);
        }
        
        if ($log['user_id']) {
            $user = $this->db->fetchOne(
                "SELECT username, email, full_name FROM users WHERE id = ?",
                [$log['user_id']]
            );
            $log['user'] = $user;
        }
        
        return $this->success($log);
    }
    
    /**
     * GET /api/audit/stats
     * 
     * Thống kê audit logs
     */
    public function stats()
    {
        $fromDate = $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $toDate = $_GET['to_date'] ?? date('Y-m-d');
        
        // Tổng số logs
        $totalLogs = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM audit_logs 
             WHERE DATE(created_at) BETWEEN ? AND ?",
            [$fromDate, $toDate]
        );
        
        // Theo hành động
        $byAction = $this->db->fetchAll(
            "SELECT action, COUNT(*) as count 
             FROM audit_logs 
             WHERE DATE(created_at) BETWEEN ? AND ?
             GROUP BY action 
             ORDER BY count DESC 
             LIMIT 20",
            [$fromDate, $toDate]
        );
        
        // Theo người dùng
        $byUser = $this->db->fetchAll(
            "SELECT u.username, COUNT(*) as count 
             FROM audit_logs al
             JOIN users u ON al.user_id = u.id
             WHERE DATE(al.created_at) BETWEEN ? AND ?
             GROUP BY al.user_id 
             ORDER BY count DESC 
             LIMIT 10",
            [$fromDate, $toDate]
        );
        
        // Theo ngày
        $byDate = $this->db->fetchAll(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM audit_logs 
             WHERE DATE(created_at) BETWEEN ? AND ?
             GROUP BY DATE(created_at) 
             ORDER BY date",
            [$fromDate, $toDate]
        );
        
        // Theo IP
        $byIp = $this->db->fetchAll(
            "SELECT ip_address, COUNT(*) as count 
             FROM audit_logs 
             WHERE DATE(created_at) BETWEEN ? AND ? AND ip_address IS NOT NULL
             GROUP BY ip_address 
             ORDER BY count DESC 
             LIMIT 10",
            [$fromDate, $toDate]
        );
        
        return $this->success([
            'total_logs' => (int)$totalLogs,
            'by_action' => $byAction,
            'by_user' => $byUser,
            'by_date' => $byDate,
            'by_ip' => $byIp,
            'period' => [
                'from' => $fromDate,
                'to' => $toDate
            ]
        ]);
    }
    
    /**
     * GET /api/audit/actions
     * 
     * Lấy danh sách các hành động
     */
    public function getActions()
    {
        $actions = $this->db->fetchAll(
            "SELECT DISTINCT action, COUNT(*) as count 
             FROM audit_logs 
             GROUP BY action 
             ORDER BY action"
        );
        
        return $this->success($actions);
    }
    
    /**
     * GET /api/audit/export
     * 
     * Xuất audit logs ra file
     */
    public function export()
    {
        $format = $_GET['format'] ?? 'csv';
        $fromDate = $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $toDate = $_GET['to_date'] ?? date('Y-m-d');
        
        $logs = $this->db->fetchAll(
            "SELECT al.*, u.username 
             FROM audit_logs al
             LEFT JOIN users u ON al.user_id = u.id
             WHERE DATE(al.created_at) BETWEEN ? AND ?
             ORDER BY al.created_at DESC",
            [$fromDate, $toDate]
        );
        
        switch ($format) {
            case 'csv':
                return $this->exportCsv($logs);
            case 'json':
                return $this->exportJson($logs);
            case 'excel':
                return $this->exportExcel($logs);
            default:
                return $this->error('Invalid export format', 400);
        }
    }
    
    /**
     * DELETE /api/audit/cleanup
     * 
     * Xóa logs cũ
     */
    public function cleanup()
    {
        $data = $this->getRequestData();
        
        if (!isset($data['confirm']) || $data['confirm'] !== true) {
            return $this->error('Please confirm cleanup action', 400);
        }
        
        $olderThanDays = (int)($data['older_than_days'] ?? 90);
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$olderThanDays} days"));
        
        // Đếm số logs sẽ xóa
        $count = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM audit_logs WHERE created_at < ?",
            [$cutoffDate]
        );
        
        if ($count === 0) {
            return $this->success(['deleted' => 0], 'No logs to clean up');
        }
        
        // Xóa logs
        $this->db->delete('audit_logs', 'created_at < ?', [$cutoffDate]);
        
        // Ghi log hành động
        $this->logAction('AUDIT_CLEANUP', "Deleted {$count} audit logs older than {$olderThanDays} days");
        
        return $this->success([
            'deleted' => (int)$count,
            'older_than_days' => $olderThanDays
        ], "Deleted {$count} logs successfully");
    }
    
    /**
     * GET /api/audit/realtime
     * 
     * Lấy logs mới (polling)
     */
    public function realtime()
    {
        $lastId = (int)($_GET['last_id'] ?? 0);
        
        $logs = $this->db->fetchAll(
            "SELECT * FROM audit_logs WHERE id > ? ORDER BY id ASC LIMIT 50",
            [$lastId]
        );
        
        foreach ($logs as &$log) {
            if ($log['user_id']) {
                $user = $this->db->fetchOne(
                    "SELECT username FROM users WHERE id = ?",
                    [$log['user_id']]
                );
                $log['username'] = $user['username'] ?? null;
            }
        }
        
        return $this->success([
            'logs' => $logs,
            'last_id' => !empty($logs) ? end($logs)['id'] : $lastId
        ]);
    }
    
    /**
     * GET /api/audit/user/{userId}
     * 
     * Lấy logs theo user
     */
    public function getByUser($userId)
    {
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = ($page - 1) * $limit;
        
        $logs = $this->db->fetchAll(
            "SELECT * FROM audit_logs 
             WHERE user_id = ? 
             ORDER BY created_at DESC 
             LIMIT {$limit} OFFSET {$offset}",
            [$userId]
        );
        
        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM audit_logs WHERE user_id = ?",
            [$userId]
        );
        
        $user = $this->db->fetchOne(
            "SELECT username, email, full_name FROM users WHERE id = ?",
            [$userId]
        );
        
        return $this->success([
            'user' => $user,
            'data' => $logs,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => (int)$total,
                'total_pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    /**
     * GET /api/audit/ip/{ip}
     * 
     * Lấy logs theo IP
     */
    public function getByIp($ip)
    {
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = ($page - 1) * $limit;
        
        $logs = $this->db->fetchAll(
            "SELECT * FROM audit_logs 
             WHERE ip_address = ? 
             ORDER BY created_at DESC 
             LIMIT {$limit} OFFSET {$offset}",
            [$ip]
        );
        
        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM audit_logs WHERE ip_address = ?",
            [$ip]
        );
        
        return $this->success([
            'ip' => $ip,
            'data' => $logs,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => (int)$total,
                'total_pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    // ============================================
    // PRIVATE HELPER METHODS
    // ============================================
    
    /**
     * Lấy thống kê nhanh
     */
    private function getQuickStats($fromDate, $toDate)
    {
        // 24h gần nhất
        $last24h = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM audit_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        // 7 ngày gần nhất
        $last7d = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM audit_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        // 30 ngày gần nhất
        $last30d = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM audit_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        // Top 5 action phổ biến
        $topActions = $this->db->fetchAll(
            "SELECT action, COUNT(*) as count 
             FROM audit_logs 
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY action 
             ORDER BY count DESC 
             LIMIT 5"
        );
        
        return [
            'last_24h' => (int)$last24h,
            'last_7d' => (int)$last7d,
            'last_30d' => (int)$last30d,
            'top_actions' => $topActions
        ];
    }
    
    /**
     * Export CSV
     */
    private function exportCsv($logs)
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'User', 'Action', 'Details', 'IP Address', 'User Agent', 'Created At']);
        
        foreach ($logs as $log) {
            fputcsv($output, [
                $log['id'],
                $log['username'] ?? 'System',
                $log['action'],
                $log['details'],
                $log['ip_address'],
                $log['user_agent'],
                $log['created_at']
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export JSON
     */
    private function exportJson($logs)
    {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d') . '.json"');
        echo json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Export Excel
     */
    private function exportExcel($logs)
    {
        // Sử dụng PhpSpreadsheet để tạo file Excel
        // Đã có trong composer.json
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d') . '.xlsx"');
        
        // Tạo Excel (simplified - cần cài đặt phpoffice/phpspreadsheet)
        echo "Excel export would be generated here";
        exit;
    }
}