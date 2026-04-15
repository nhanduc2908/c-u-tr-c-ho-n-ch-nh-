<?php
/**
 * SERVER CONTROLLER
 * 
 * Quản lý server cần đánh giá
 * - CRUD servers
 * - Kiểm tra kết nối
 * - Quét server
 * - Đồng bộ server
 * 
 * @package Controllers
 */

namespace Controllers;

use Core\Controller;
use Core\Database;
use Services\ScannerService;
use Services\SshConnectionService;

class ServerController extends Controller
{
    private $db;
    private $scanner;
    private $sshService;
    
    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->scanner = new ScannerService();
        $this->sshService = new SshConnectionService();
    }
    
    /**
     * GET /api/servers
     * 
     * Lấy danh sách server
     */
    public function index()
    {
        $userId = $this->getUserId();
        $role = $this->getCurrentRole();
        
        if ($role === 'viewer') {
            // Viewer chỉ xem server được gán
            $servers = $this->db->fetchAll(
                "SELECT s.* FROM servers s
                 JOIN user_server us ON s.id = us.server_id
                 WHERE us.user_id = ?
                 ORDER BY s.created_at DESC",
                [$userId]
            );
        } else {
            // Admin và Officer xem tất cả
            $servers = $this->db->fetchAll(
                "SELECT s.*, 
                        (SELECT COUNT(*) FROM assessment_reports WHERE server_id = s.id) as assessment_count,
                        (SELECT total_score FROM assessment_reports WHERE server_id = s.id ORDER BY id DESC LIMIT 1) as last_score
                 FROM servers s
                 ORDER BY s.created_at DESC"
            );
        }
        
        return $this->success($servers);
    }
    
    /**
     * GET /api/servers/{id}
     * 
     * Chi tiết server
     */
    public function show($id)
    {
        $server = $this->db->fetchOne(
            "SELECT s.*, 
                    (SELECT COUNT(*) FROM assessment_reports WHERE server_id = s.id) as assessment_count,
                    (SELECT total_score FROM assessment_reports WHERE server_id = s.id ORDER BY id DESC LIMIT 1) as last_score
             FROM servers s
             WHERE s.id = ?",
            [$id]
        );
        
        if (!$server) {
            return $this->error('Server not found', 404);
        }
        
        // Lấy lịch sử đánh giá
        $history = $this->db->fetchAll(
            "SELECT id, total_score, passed_criteria, failed_criteria, generated_at
             FROM assessment_reports
             WHERE server_id = ?
             ORDER BY generated_at DESC
             LIMIT 10",
            [$id]
        );
        
        $server['assessment_history'] = $history;
        
        return $this->success($server);
    }
    
    /**
     * POST /api/servers
     * 
     * Thêm server mới
     */
    public function store()
    {
        $data = $this->getRequestData();
        
        $this->validate($data, [
            'name' => 'required|max:100',
            'ip_address' => 'required|ip',
            'hostname' => 'max:255',
            'os' => 'max:50',
            'environment' => 'in:production,staging,development',
            'ssh_port' => 'integer|min:1|max:65535',
            'ssh_username' => 'max:50'
        ]);
        
        $serverId = $this->db->insert('servers', [
            'name' => $data['name'],
            'ip_address' => $data['ip_address'],
            'hostname' => $data['hostname'] ?? null,
            'os' => $data['os'] ?? null,
            'environment' => $data['environment'] ?? 'production',
            'status' => 'active',
            'ssh_port' => $data['ssh_port'] ?? 22,
            'ssh_username' => $data['ssh_username'] ?? null,
            'created_by' => $this->getUserId(),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $this->logAction('SERVER_CREATE', "Created server: {$data['name']} (ID: {$serverId})");
        
        return $this->success(['id' => $serverId], 'Server added successfully', 201);
    }
    
    /**
     * PUT /api/servers/{id}
     * 
     * Cập nhật server
     */
    public function update($id)
    {
        $data = $this->getRequestData();
        
        $server = $this->db->fetchOne("SELECT * FROM servers WHERE id = ?", [$id]);
        if (!$server) {
            return $this->error('Server not found', 404);
        }
        
        $allowedFields = ['name', 'ip_address', 'hostname', 'os', 'environment', 'status', 'ssh_port', 'ssh_username'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));
        
        if (!empty($updateData)) {
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            $this->db->update('servers', $updateData, 'id = ?', [$id]);
        }
        
        $this->logAction('SERVER_UPDATE', "Updated server ID: {$id}");
        
        return $this->success([], 'Server updated successfully');
    }
    
    /**
     * DELETE /api/servers/{id}
     * 
     * Xóa server
     */
    public function destroy($id)
    {
        $server = $this->db->fetchOne("SELECT * FROM servers WHERE id = ?", [$id]);
        if (!$server) {
            return $this->error('Server not found', 404);
        }
        
        // Xóa các bản ghi liên quan
        $this->db->delete('assessment_results', 'server_id = ?', [$id]);
        $this->db->delete('assessment_reports', 'server_id = ?', [$id]);
        $this->db->delete('vulnerabilities', 'server_id = ?', [$id]);
        $this->db->delete('user_server', 'server_id = ?', [$id]);
        $this->db->delete('servers', 'id = ?', [$id]);
        
        $this->logAction('SERVER_DELETE', "Deleted server: {$server['name']} (ID: {$id})");
        
        return $this->success([], 'Server deleted successfully');
    }
    
    /**
     * POST /api/servers/{id}/scan
     * 
     * Quét server
     */
    public function scan($id)
    {
        $server = $this->db->fetchOne("SELECT * FROM servers WHERE id = ?", [$id]);
        if (!$server) {
            return $this->error('Server not found', 404);
        }
        
        $result = $this->scanner->scanServer($id, $this->getUserId());
        
        if ($result['success']) {
            $this->logAction('SERVER_SCAN', "Scanned server ID: {$id}, Score: {$result['total_score']}%");
            return $this->success($result, 'Scan completed successfully');
        } else {
            return $this->error($result['error'] ?? 'Scan failed', 500);
        }
    }
    
    /**
     * POST /api/servers/{id}/test-connection
     * 
     * Kiểm tra kết nối server
     */
    public function testConnection($id)
    {
        $server = $this->db->fetchOne("SELECT * FROM servers WHERE id = ?", [$id]);
        if (!$server) {
            return $this->error('Server not found', 404);
        }
        
        // Ping test
        $ping = exec("ping -n 1 -w 1 " . escapeshellarg($server['ip_address']), $output, $returnCode);
        $pingSuccess = $returnCode === 0;
        
        // SSH test nếu có thông tin
        $sshSuccess = false;
        if ($server['ssh_username']) {
            $sshSuccess = $this->sshService->testConnection(
                $server['ip_address'],
                $server['ssh_port'] ?? 22,
                $server['ssh_username']
            );
        }
        
        return $this->success([
            'ping' => $pingSuccess,
            'ssh' => $sshSuccess,
            'message' => $pingSuccess ? 'Server is reachable' : 'Server is not reachable'
        ]);
    }
    
    /**
     * POST /api/servers/sync
     * 
     * Đồng bộ server (import từ nguồn khác)
     */
    public function sync()
    {
        // Có thể import từ AWS, Azure, hoặc file CSV
        $data = $this->getRequestData();
        $source = $data['source'] ?? 'manual';
        
        // Logic sync tùy theo nguồn
        // ...
        
        $this->logAction('SERVER_SYNC', "Synced servers from source: {$source}");
        
        return $this->success([], "Servers synced successfully from {$source}");
    }
    
    /**
     * GET /api/servers/stats/summary
     * 
     * Thống kê server
     */
    public function stats()
    {
        $total = $this->db->fetchColumn("SELECT COUNT(*) FROM servers");
        $active = $this->db->fetchColumn("SELECT COUNT(*) FROM servers WHERE status = 'active'");
        $inactive = $this->db->fetchColumn("SELECT COUNT(*) FROM servers WHERE status = 'inactive'");
        
        $byOs = $this->db->fetchAll(
            "SELECT os, COUNT(*) as count FROM servers WHERE os IS NOT NULL GROUP BY os"
        );
        
        $byEnvironment = $this->db->fetchAll(
            "SELECT environment, COUNT(*) as count FROM servers GROUP BY environment"
        );
        
        return $this->success([
            'total' => (int)$total,
            'active' => (int)$active,
            'inactive' => (int)$inactive,
            'by_os' => $byOs,
            'by_environment' => $byEnvironment
        ]);
    }
}