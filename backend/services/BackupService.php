<?php
/**
 * BACKUP SERVICE
 * 
 * Quản lý backup và restore database
 * - Backup database MySQL
 * - Backup files
 * - Restore từ backup
 * - Scheduled backups
 * 
 * @package Services
 */

namespace Services;

use Core\Database;
use Core\Logger;
use Core\Setting;

class BackupService
{
    private $db;
    private $backupPath;
    private $retentionDays;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->backupPath = $_ENV['BACKUP_PATH'] ?? __DIR__ . '/../storage/backups/';
        $this->retentionDays = $_ENV['BACKUP_RETENTION_DAYS'] ?? 30;
        
        // Tạo thư mục backup nếu chưa tồn tại
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
        if (!is_dir($this->backupPath . 'database/')) {
            mkdir($this->backupPath . 'database/', 0755, true);
        }
        if (!is_dir($this->backupPath . 'files/')) {
            mkdir($this->backupPath . 'files/', 0755, true);
        }
    }
    
    /**
     * Tạo backup database
     * 
     * @param string $type Loại backup (database/full)
     * @param int|null $userId ID người tạo
     * @return array Kết quả
     */
    public function createBackup($type = 'database', $userId = null)
    {
        $filename = $type . '_backup_' . date('Ymd_His') . '.sql';
        $filepath = $this->backupPath . 'database/' . $filename;
        
        try {
            // Lấy cấu hình database
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $port = $_ENV['DB_PORT'] ?? 3306;
            $dbname = $_ENV['DB_NAME'] ?? 'security_db';
            $user = $_ENV['DB_USER'] ?? 'root';
            $password = $_ENV['DB_PASSWORD'] ?? '';
            
            // Tạo backup bằng mysqldump
            $command = sprintf(
                'mysqldump --host=%s --port=%s --user=%s --password=%s %s --add-drop-table --add-locks --create-options --disable-keys --extended-insert --single-transaction --quick --set-charset --routines --triggers > %s 2>&1',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($user),
                escapeshellarg($password),
                escapeshellarg($dbname),
                escapeshellarg($filepath)
            );
            
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new \Exception("mysqldump failed: " . implode("\n", $output));
            }
            
            // Nén file
            $this->compressFile($filepath);
            $filepath .= '.gz';
            $filesize = filesize($filepath);
            
            // Lưu thông tin backup
            $backupId = $this->db->insert('backups', [
                'backup_name' => $filename,
                'backup_type' => $type,
                'file_path' => $filepath,
                'file_size' => $filesize,
                'status' => 'completed',
                'created_by' => $userId,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            Logger::info("Backup created", [
                'type' => $type,
                'filename' => $filename,
                'size' => $filesize
            ]);
            
            // Xóa backup cũ
            $this->cleanupOldBackups();
            
            return [
                'success' => true,
                'backup_id' => $backupId,
                'filename' => $filename . '.gz',
                'size' => $this->formatBytes($filesize),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            Logger::error("Backup failed", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Khôi phục từ backup
     * 
     * @param string $filepath Đường dẫn file backup
     * @param string $type Loại backup
     * @return array Kết quả
     */
    public function restoreBackup($filepath, $type = 'database')
    {
        try {
            if (!file_exists($filepath)) {
                throw new \Exception("Backup file not found: {$filepath}");
            }
            
            // Giải nén nếu cần
            if (pathinfo($filepath, PATHINFO_EXTENSION) === 'gz') {
                $sqlFile = str_replace('.gz', '', $filepath);
                $this->decompressFile($filepath, $sqlFile);
                $filepath = $sqlFile;
            }
            
            // Lấy cấu hình database
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $port = $_ENV['DB_PORT'] ?? 3306;
            $dbname = $_ENV['DB_NAME'] ?? 'security_db';
            $user = $_ENV['DB_USER'] ?? 'root';
            $password = $_ENV['DB_PASSWORD'] ?? '';
            
            // Restore bằng mysql
            $command = sprintf(
                'mysql --host=%s --port=%s --user=%s --password=%s %s < %s 2>&1',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($user),
                escapeshellarg($password),
                escapeshellarg($dbname),
                escapeshellarg($filepath)
            );
            
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new \Exception("mysql restore failed: " . implode("\n", $output));
            }
            
            Logger::info("Backup restored", ['filepath' => $filepath]);
            
            return [
                'success' => true,
                'message' => 'Database restored successfully'
            ];
            
        } catch (\Exception $e) {
            Logger::error("Restore failed", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Chạy backup theo lịch tự động
     */
    public function runScheduledBackup()
    {
        // Kiểm tra cấu hình
        $autoBackupEnabled = Setting::get('backup', 'auto_backup_enabled', true);
        
        if (!$autoBackupEnabled) {
            return ['success' => false, 'message' => 'Auto backup disabled'];
        }
        
        return $this->createBackup('database', null);
    }
    
    /**
     * Xóa backup cũ
     */
    private function cleanupOldBackups()
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$this->retentionDays} days"));
        
        $oldBackups = $this->db->fetchAll(
            "SELECT id, file_path FROM backups WHERE created_at < ?",
            [$cutoffDate]
        );
        
        foreach ($oldBackups as $backup) {
            if (file_exists($backup['file_path'])) {
                unlink($backup['file_path']);
            }
            $this->db->delete('backups', 'id = ?', [$backup['id']]);
        }
        
        return count($oldBackups);
    }
    
    /**
     * Nén file
     */
    private function compressFile($filepath)
    {
        $data = file_get_contents($filepath);
        $gzData = gzencode($data, 9);
        file_put_contents($filepath . '.gz', $gzData);
        unlink($filepath);
    }
    
    /**
     * Giải nén file
     */
    private function decompressFile($gzFilepath, $outputPath)
    {
        $gzData = file_get_contents($gzFilepath);
        $data = gzdecode($gzData);
        file_put_contents($outputPath, $data);
    }
    
    /**
     * Format bytes
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
    
    /**
     * Lấy danh sách backups
     */
    public function getBackups($type = null, $limit = 20)
    {
        $sql = "SELECT * FROM backups";
        $params = [];
        
        if ($type) {
            $sql .= " WHERE backup_type = ?";
            $params[] = $type;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Lấy thông tin dung lượng backup
     */
    public function getStorageInfo()
    {
        $backups = $this->db->fetchAll("SELECT file_size FROM backups");
        
        $totalSize = 0;
        foreach ($backups as $backup) {
            $totalSize += $backup['file_size'];
        }
        
        $diskFree = disk_free_space('/');
        $diskTotal = disk_total_space('/');
        
        return [
            'total_backups' => count($backups),
            'total_size' => $this->formatBytes($totalSize),
            'disk_free' => $this->formatBytes($diskFree),
            'disk_total' => $this->formatBytes($diskTotal),
            'disk_used_percent' => round((1 - $diskFree / $diskTotal) * 100, 1)
        ];
    }
}