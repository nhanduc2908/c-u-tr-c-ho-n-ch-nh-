<?php
/**
 * BACKUP CONTROLLER
 * 
 * Quản lý backup và restore - CHỈ ADMIN
 * - Tạo backup database
 * - Tạo backup file
 * - Restore từ backup
 * - Lên lịch backup tự động
 * 
 * @package Controllers
 */

namespace Controllers;

use Core\Controller;
use Core\Database;
use Services\BackupService;

class BackupController extends Controller
{
    private $db;
    private $backupService;
    
    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->backupService = new BackupService();
    }
    
    /**
     * GET /api/backup
     * 
     * Lấy danh sách backups
     */
    public function index()
    {
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $type = $_GET['type'] ?? null;
        
        $offset = ($page - 1) * $limit;
        $params = [];
        
        $sql = "SELECT b.*, u.username as created_by_name
                FROM backups b
                LEFT JOIN users u ON b.created_by = u.id
                WHERE 1=1";
        
        if ($type) {
            $sql .= " AND b.backup_type = ?";
            $params[] = $type;
        }
        
        $sql .= " ORDER BY b.created_at DESC LIMIT {$limit} OFFSET {$offset}";
        
        $backups = $this->db->fetchAll($sql, $params);
        
        // Đếm tổng
        $countSql = str_replace("b.*, u.username as created_by_name", "COUNT(*) as total", $sql);
        $total = $this->db->fetchColumn($countSql, $params);
        
        // Thống kê dung lượng
        $storageInfo = $this->getStorageInfo();
        
        return $this->success([
            'data' => $backups,
            'storage_info' => $storageInfo,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => (int)$total,
                'total_pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    /**
     * GET /api/backup/{id}
     * 
     * Chi tiết backup
     */
    public function show($id)
    {
        $backup = $this->db->fetchOne(
            "SELECT b.*, u.username as created_by_name
             FROM backups b
             LEFT JOIN users u ON b.created_by = u.id
             WHERE b.id = ?",
            [$id]
        );
        
        if (!$backup) {
            return $this->error('Backup not found', 404);
        }
        
        return $this->success($backup);
    }
    
    /**
     * POST /api/backup/create
     * 
     * Tạo backup mới
     */
    public function create()
    {
        $data = $this->getRequestData();
        $backupType = $data['backup_type'] ?? 'database';
        
        try {
            $result = $this->backupService->createBackup($backupType, $this->getUserId());
            
            $this->logAction('BACKUP_CREATE', "Created backup: {$result['filename']}");
            
            return $this->success($result, 'Backup created successfully');
            
        } catch (\Exception $e) {
            return $this->error('Backup failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/backup/{id}/download
     * 
     * Tải file backup
     */
    public function download($id)
    {
        $backup = $this->db->fetchOne("SELECT * FROM backups WHERE id = ?", [$id]);
        if (!$backup) {
            return $this->error('Backup not found', 404);
        }
        
        $filepath = $backup['file_path'];
        if (!file_exists($filepath)) {
            return $this->error('Backup file not found', 404);
        }
        
        $this->logAction('BACKUP_DOWNLOAD', "Downloaded backup ID: {$id}");
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
    
    /**
     * POST /api/backup/{id}/restore
     * 
     * Khôi phục từ backup
     */
    public function restore($id)
    {
        $data = $this->getRequestData();
        
        if (!isset($data['confirm']) || $data['confirm'] !== true) {
            return $this->error('Please confirm restore action', 400);
        }
        
        $backup = $this->db->fetchOne("SELECT * FROM backups WHERE id = ?", [$id]);
        if (!$backup) {
            return $this->error('Backup not found', 404);
        }
        
        try {
            $result = $this->backupService->restoreBackup($backup['file_path'], $backup['backup_type']);
            
            $this->logAction('BACKUP_RESTORE', "Restored from backup ID: {$id}");
            
            return $this->success($result, 'Restore completed successfully');
            
        } catch (\Exception $e) {
            return $this->error('Restore failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * DELETE /api/backup/{id}
     * 
     * Xóa backup
     */
    public function destroy($id)
    {
        $backup = $this->db->fetchOne("SELECT * FROM backups WHERE id = ?", [$id]);
        if (!$backup) {
            return $this->error('Backup not found', 404);
        }
        
        // Xóa file
        if (file_exists($backup['file_path'])) {
            unlink($backup['file_path']);
        }
        
        // Xóa record
        $this->db->delete('backups', 'id = ?', [$id]);
        
        $this->logAction('BACKUP_DELETE', "Deleted backup ID: {$id}");
        
        return $this->success([], 'Backup deleted');
    }
    
    /**
     * GET /api/backup/storage/info
     * 
     * Thông tin dung lượng backup
     */
    public function storageInfo()
    {
        $info = $this->getStorageInfo();
        return $this->success($info);
    }
    
    /**
     * PUT /api/backup/config
     * 
     * Cấu hình backup tự động
     */
    public function updateConfig()
    {
        $data = $this->getRequestData();
        
        $config = [
            'auto_backup_enabled' => $data['auto_backup_enabled'] ?? true,
            'backup_time' => $data['backup_time'] ?? '02:00',
            'backup_retention_days' => $data['backup_retention_days'] ?? 30,
            'backup_path' => $data['backup_path'] ?? __DIR__ . '/../storage/backups/'
        ];
        
        // Lưu config vào database hoặc file
        $this->saveBackupConfig($config);
        
        $this->logAction('BACKUP_CONFIG', "Updated backup configuration");
        
        return $this->success($config, 'Backup configuration updated');
    }
    
    /**
     * POST /api/backup/run-auto
     * 
     * Chạy backup tự động ngay lập tức
     */
    public function runAutoBackup()
    {
        try {
            $result = $this->backupService->runScheduledBackup();
            
            $this->logAction('BACKUP_AUTO', "Ran scheduled backup");
            
            return $this->success($result, 'Scheduled backup completed');
            
        } catch (\Exception $e) {
            return $this->error('Scheduled backup failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * DELETE /api/backup/cleanup
     * 
     * Xóa backups cũ
     */
    public function cleanup()
    {
        $data = $this->getRequestData();
        $olderThanDays = (int)($data['older_than_days'] ?? 30);
        
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$olderThanDays} days"));
        
        $backups = $this->db->fetchAll(
            "SELECT id, file_path FROM backups WHERE created_at < ?",
            [$cutoffDate]
        );
        
        $deleted = 0;
        foreach ($backups as $backup) {
            if (file_exists($backup['file_path'])) {
                unlink($backup['file_path']);
            }
            $this->db->delete('backups', 'id = ?', [$backup['id']]);
            $deleted++;
        }
        
        $this->logAction('BACKUP_CLEANUP', "Deleted {$deleted} old backups");
        
        return $this->success(['deleted' => $deleted], "Deleted {$deleted} old backups");
    }
    
    /**
     * Lấy thông tin dung lượng storage
     */
    private function getStorageInfo()
    {
        $backupPath = __DIR__ . '/../storage/backups/';
        
        // Tổng dung lượng
        $totalSize = 0;
        $files = glob($backupPath . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                $totalSize += filesize($file);
            }
        }
        
        // Dung lượng theo loại
        $databaseSize = 0;
        $fullSize = 0;
        
        $backups = $this->db->fetchAll("SELECT backup_type, file_size FROM backups");
        foreach ($backups as $backup) {
            if ($backup['backup_type'] === 'database') {
                $databaseSize += $backup['file_size'];
            } else {
                $fullSize += $backup['file_size'];
            }
        }
        
        // Dung lượng còn lại của ổ đĩa
        $diskFree = disk_free_space('/');
        $diskTotal = disk_total_space('/');
        
        return [
            'total_size' => $this->formatBytes($totalSize),
            'total_size_bytes' => $totalSize,
            'database_backups' => $this->formatBytes($databaseSize),
            'full_backups' => $this->formatBytes($fullSize),
            'backup_count' => count($backups),
            'disk_free' => $this->formatBytes($diskFree),
            'disk_total' => $this->formatBytes($diskTotal),
            'disk_used_percent' => round((1 - $diskFree / $diskTotal) * 100, 1)
        ];
    }
    
    /**
     * Lưu cấu hình backup
     */
    private function saveBackupConfig($config)
    {
        foreach ($config as $key => $value) {
            $exists = $this->db->fetchColumn(
                "SELECT COUNT(*) FROM system_settings WHERE setting_group = 'backup' AND setting_key = ?",
                [$key]
            );
            
            if ($exists) {
                $this->db->update('system_settings', 
                    ['setting_value' => $value], 
                    'setting_group = ? AND setting_key = ?', 
                    ['backup', $key]
                );
            } else {
                $this->db->insert('system_settings', [
                    'setting_group' => 'backup',
                    'setting_key' => $key,
                    'setting_value' => $value,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }
    
    /**
     * Format bytes thành human readable
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}