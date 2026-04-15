<?php
/**
 * ALERT CONTROLLER
 * 
 * Quản lý cảnh báo an ninh
 * - Danh sách cảnh báo
 * - Xác nhận cảnh báo
 * - Giải quyết cảnh báo
 * - Realtime alerts qua WebSocket
 * 
 * @package Controllers
 */

namespace Controllers;

use Core\Controller;
use Core\Database;
use Services\RealtimeService;

class AlertController extends Controller
{
    private $db;
    private $realtime;
    
    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->realtime = new RealtimeService();
    }
    
    /**
     * GET /api/alerts
     * 
     * Lấy danh sách cảnh báo
     */
    public function index()
    {
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $severity = $_GET['severity'] ?? null;
        $status = $_GET['status'] ?? null;
        $type = $_GET['type'] ?? null;
        $serverId = $_GET['server_id'] ?? null;
        
        $offset = ($page - 1) * $limit;
        $params = [];
        
        $sql = "SELECT a.*, s.name as server_name, u.username as resolved_by_name
                FROM alerts a
                LEFT JOIN servers s ON a.server_id = s.id
                LEFT JOIN users u ON a.resolved_by = u.id
                WHERE 1=1";
        
        if ($severity) {
            $sql .= " AND a.severity = ?";
            $params[] = $severity;
        }
        
        if ($status === 'unresolved') {
            $sql .= " AND a.is_resolved = 0";
        } elseif ($status === 'resolved') {
            $sql .= " AND a.is_resolved = 1";
        }
        
        if ($type) {
            $sql .= " AND a.type = ?";
            $params[] = $type;
        }
        
        if ($serverId) {
            $sql .= " AND a.server_id = ?";
            $params[] = $serverId;
        }
        
        $sql .= " ORDER BY 
                    CASE a.severity 
                        WHEN 'critical' THEN 1 
                        WHEN 'high' THEN 2 
                        WHEN 'medium' THEN 3 
                        WHEN 'low' THEN 4 
                    END,
                    a.created_at DESC 
                  LIMIT {$limit} OFFSET {$offset}";
        
        $alerts = $this->db->fetchAll($sql, $params);
        
        // Đếm tổng
        $countSql = str_replace("a.*, s.name as server_name, u.username as resolved_by_name", 
                                "COUNT(*) as total", $sql);
        $total = $this->db->fetchColumn($countSql, $params);
        
        // Thống kê
        $stats = $this->getStats();
        
        return $this->success([
            'data' => $alerts,
            'stats' => $stats,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => (int)$total,
                'total_pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    /**
     * GET /api/alerts/{id}
     * 
     * Chi tiết cảnh báo
     */
    public function show($id)
    {
        $alert = $this->db->fetchOne(
            "SELECT a.*, s.name as server_name, s.ip_address,
                    u1.username as resolved_by_name, u2.username as acknowledged_by_name
             FROM alerts a
             LEFT JOIN servers s ON a.server_id = s.id
             LEFT JOIN users u1 ON a.resolved_by = u1.id
             LEFT JOIN users u2 ON a.acknowledged_by = u2.id
             WHERE a.id = ?",
            [$id]
        );
        
        if (!$alert) {
            return $this->error('Alert not found', 404);
        }
        
        return $this->success($alert);
    }
    
    /**
     * PUT /api/alerts/{id}/acknowledge
     * 
     * Xác nhận đã thấy cảnh báo
     */
    public function acknowledge($id)
    {
        $alert = $this->db->fetchOne("SELECT * FROM alerts WHERE id = ?", [$id]);
        if (!$alert) {
            return $this->error('Alert not found', 404);
        }
        
        $this->db->update('alerts', [
            'is_read' => 1,
            'acknowledged_by' => $this->getUserId(),
            'acknowledged_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$id]);
        
        $this->logAction('ALERT_ACKNOWLEDGE', "Acknowledged alert ID: {$id}");
        
        return $this->success([], 'Alert acknowledged');
    }
    
    /**
     * PUT /api/alerts/{id}/resolve
     * 
     * Đánh dấu đã giải quyết
     */
    public function resolve($id)
    {
        $data = $this->getRequestData();
        
        $alert = $this->db->fetchOne("SELECT * FROM alerts WHERE id = ?", [$id]);
        if (!$alert) {
            return $this->error('Alert not found', 404);
        }
        
        $this->db->update('alerts', [
            'is_resolved' => 1,
            'resolution_note' => $data['note'] ?? null,
            'resolved_by' => $this->getUserId(),
            'resolved_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$id]);
        
        // Gửi realtime notification
        $this->realtime->push('alert_resolved', [
            'alert_id' => $id,
            'resolved_by' => $this->getCurrentUser()['username'] ?? 'System'
        ]);
        
        $this->logAction('ALERT_RESOLVE', "Resolved alert ID: {$id}");
        
        return $this->success([], 'Alert resolved');
    }
    
    /**
     * POST /api/alerts/{id}/note
     * 
     * Thêm ghi chú cho cảnh báo
     */
    public function addNote($id)
    {
        $data = $this->getRequestData();
        
        $this->validate($data, [
            'note' => 'required'
        ]);
        
        $alert = $this->db->fetchOne("SELECT * FROM alerts WHERE id = ?", [$id]);
        if (!$alert) {
            return $this->error('Alert not found', 404);
        }
        
        $this->db->insert('alert_notes', [
            'alert_id' => $id,
            'note' => $data['note'],
            'created_by' => $this->getUserId(),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return $this->success([], 'Note added successfully');
    }
    
    /**
     * PUT /api/alerts/{id}/assign
     * 
     * Gán cảnh báo cho người xử lý
     */
    public function assign($id)
    {
        $data = $this->getRequestData();
        
        $this->validate($data, [
            'assigned_to' => 'required|exists:users,id'
        ]);
        
        $alert = $this->db->fetchOne("SELECT * FROM alerts WHERE id = ?", [$id]);
        if (!$alert) {
            return $this->error('Alert not found', 404);
        }
        
        $this->db->update('alerts', [
            'assigned_to' => $data['assigned_to'],
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$id]);
        
        $this->logAction('ALERT_ASSIGN', "Assigned alert ID: {$id} to user ID: {$data['assigned_to']}");
        
        return $this->success([], 'Alert assigned');
    }
    
    /**
     * POST /api/alerts/bulk-acknowledge
     * 
     * Xác nhận nhiều cảnh báo cùng lúc
     */
    public function bulkAcknowledge()
    {
        $data = $this->getRequestData();
        
        $this->validate($data, [
            'alert_ids' => 'required|array'
        ]);
        
        $alertIds = $data['alert_ids'];
        $userId = $this->getUserId();
        
        foreach ($alertIds as $id) {
            $this->db->update('alerts', [
                'is_read' => 1,
                'acknowledged_by' => $userId,
                'acknowledged_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$id]);
        }
        
        $this->logAction('ALERT_BULK_ACKNOWLEDGE', "Bulk acknowledged " . count($alertIds) . " alerts");
        
        return $this->success(['count' => count($alertIds)], "Acknowledged " . count($alertIds) . " alerts");
    }
    
    /**
     * POST /api/alerts/bulk-resolve
     * 
     * Giải quyết nhiều cảnh báo cùng lúc
     */
    public function bulkResolve()
    {
        $data = $this->getRequestData();
        
        $this->validate($data, [
            'alert_ids' => 'required|array'
        ]);
        
        $alertIds = $data['alert_ids'];
        $userId = $this->getUserId();
        
        foreach ($alertIds as $id) {
            $this->db->update('alerts', [
                'is_resolved' => 1,
                'resolved_by' => $userId,
                'resolved_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$id]);
        }
        
        $this->logAction('ALERT_BULK_RESOLVE', "Bulk resolved " . count($alertIds) . " alerts");
        
        return $this->success(['count' => count($alertIds)], "Resolved " . count($alertIds) . " alerts");
    }
    
    /**
     * GET /api/alerts/stats/summary
     * 
     * Thống kê cảnh báo
     */
    public function stats()
    {
        $stats = $this->getStats();
        return $this->success($stats);
    }
    
    /**
     * GET /api/alerts/realtime
     * 
     * Lấy cảnh báo mới (polling)
     */
    public function realtime()
    {
        $lastId = (int)($_GET['last_id'] ?? 0);
        
        $alerts = $this->db->fetchAll(
            "SELECT a.*, s.name as server_name
             FROM alerts a
             LEFT JOIN servers s ON a.server_id = s.id
             WHERE a.id > ? AND a.is_resolved = 0
             ORDER BY a.id ASC
             LIMIT 50",
            [$lastId]
        );
        
        return $this->success([
            'alerts' => $alerts,
            'last_id' => !empty($alerts) ? end($alerts)['id'] : $lastId
        ]);
    }
    
    /**
     * DELETE /api/alerts/{id}
     * 
     * Xóa cảnh báo (CHỦ ADMIN)
     */
    public function destroy($id)
    {
        $role = $this->getCurrentRole();
        if ($role !== 'admin') {
            return $this->error('Admin only', 403);
        }
        
        $this->db->delete('alert_notes', 'alert_id = ?', [$id]);
        $this->db->delete('alerts', 'id = ?', [$id]);
        
        $this->logAction('ALERT_DELETE', "Deleted alert ID: {$id}");
        
        return $this->success([], 'Alert deleted');
    }
    
    /**
     * DELETE /api/alerts/cleanup
     * 
     * Xóa cảnh báo cũ (CHỦ ADMIN)
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
        
        // Lấy danh sách alert cần xóa
        $alerts = $this->db->fetchAll(
            "SELECT id FROM alerts WHERE created_at < ? AND is_resolved = 1",
            [$cutoffDate]
        );
        
        $deleted = 0;
        foreach ($alerts as $alert) {
            $this->db->delete('alert_notes', 'alert_id = ?', [$alert['id']]);
            $this->db->delete('alerts', 'id = ?', [$alert['id']]);
            $deleted++;
        }
        
        $this->logAction('ALERT_CLEANUP', "Deleted {$deleted} old alerts");
        
        return $this->success(['deleted' => $deleted], "Deleted {$deleted} old alerts");
    }
    
    /**
     * Thống kê cảnh báo
     */
    private function getStats()
    {
        // Tổng số
        $total = $this->db->fetchColumn("SELECT COUNT(*) FROM alerts");
        
        // Chưa giải quyết
        $unresolved = $this->db->fetchColumn("SELECT COUNT(*) FROM alerts WHERE is_resolved = 0");
        
        // Theo severity
        $bySeverity = $this->db->fetchAll(
            "SELECT severity, COUNT(*) as count FROM alerts GROUP BY severity"
        );
        
        // Theo type
        $byType = $this->db->fetchAll(
            "SELECT type, COUNT(*) as count FROM alerts GROUP BY type"
        );
        
        // 24h gần nhất
        $last24h = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM alerts WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        return [
            'total' => (int)$total,
            'unresolved' => (int)$unresolved,
            'by_severity' => $bySeverity,
            'by_type' => $byType,
            'last_24h' => (int)$last24h
        ];
    }
}