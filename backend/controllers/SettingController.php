<?php
/**
 * SETTINGS CONTROLLER
 * 
 * Quản lý cài đặt hệ thống - CHỈ ADMIN
 * 
 * @package Controllers
 */

namespace Controllers;

use Core\Controller;
use Core\Database;
use Core\Cache;
use Services\NotificationService;

class SettingController extends Controller
{
    private $db;
    private $cache;
    private $notification;
    
    // Cài đặt mặc định
    private $defaultSettings = [
        'general' => [
            'app_name' => 'Security Assessment Platform',
            'app_url' => 'http://localhost',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'date_format' => 'Y-m-d H:i:s',
            'language' => 'vi',
            'maintenance_mode' => false,
            'maintenance_message' => 'Hệ thống đang bảo trì. Vui lòng quay lại sau.'
        ],
        'security' => [
            'session_lifetime' => 120,
            'password_min_length' => 8,
            'password_require_number' => true,
            'password_require_uppercase' => true,
            'login_attempts' => 5,
            'lockout_time' => 15,
            'two_factor_required' => false,
            'jwt_ttl' => 3600,
            'rate_limit' => 100,
            'rate_window' => 60
        ],
        'email' => [
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
            'from_address' => 'noreply@security.com',
            'from_name' => 'Security Platform'
        ],
        'backup' => [
            'auto_backup_enabled' => true,
            'backup_time' => '02:00',
            'backup_retention_days' => 30,
            'backup_path' => '/var/backups/security',
            'backup_include_files' => true
        ],
        'assessment' => [
            'default_score_threshold' => 60,
            'auto_scan_enabled' => true,
            'scan_interval' => 'daily',
            'scan_time' => '01:00',
            'critical_alert_enabled' => true,
            'report_auto_generate' => true
        ],
        'notification' => [
            'email_notifications' => true,
            'alert_notifications' => true,
            'report_notifications' => true,
            'webhook_enabled' => false,
            'webhook_url' => ''
        ],
        'api' => [
            'api_rate_limit' => 100,
            'api_rate_window' => 60,
            'api_version' => 'v1',
            'enable_api_docs' => true,
            'allowed_origins' => ['http://localhost:3000', 'http://localhost:8080']
        ]
    ];
    
    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->cache = Cache::getInstance();
        $this->notification = new NotificationService();
    }
    
    /**
     * GET /api/settings
     * 
     * Lấy tất cả cài đặt
     */
    public function index()
    {
        // Thử lấy từ cache
        $settings = $this->cache->get('system_settings');
        
        if (!$settings) {
            $settings = [];
            
            foreach (array_keys($this->defaultSettings) as $group) {
                $settings[$group] = $this->getSettingsGroup($group);
            }
            
            // Lưu cache 1 giờ
            $this->cache->set('system_settings', $settings, 3600);
        }
        
        return $this->success($settings);
    }
    
    /**
     * GET /api/settings/{key}
     * 
     * Lấy cài đặt theo nhóm
     */
    public function get($key)
    {
        if (!isset($this->defaultSettings[$key])) {
            return $this->error('Invalid settings group', 400);
        }
        
        $settings = $this->getSettingsGroup($key);
        
        // Ẩn mật khẩu trong response
        if ($key === 'email' && isset($settings['smtp_password'])) {
            $settings['smtp_password'] = '********';
        }
        
        return $this->success($settings);
    }
    
    /**
     * PUT /api/settings/general
     * 
     * Cập nhật cài đặt chung
     */
    public function updateGeneral()
    {
        $data = $this->getRequestData();
        
        $allowed = ['app_name', 'app_url', 'timezone', 'date_format', 'language', 'maintenance_mode', 'maintenance_message'];
        $updateData = array_intersect_key($data, array_flip($allowed));
        
        foreach ($updateData as $key => $value) {
            $this->saveSetting('general', $key, $value);
        }
        
        // Clear cache
        $this->cache->delete('system_settings');
        
        // Ghi log
        $this->logAction('SETTINGS_UPDATE', 'Updated general settings');
        
        return $this->success([], 'General settings updated');
    }
    
    /**
     * PUT /api/settings/security
     * 
     * Cập nhật cài đặt bảo mật
     */
    public function updateSecurity()
    {
        $data = $this->getRequestData();
        
        $allowed = [
            'session_lifetime', 'password_min_length', 'password_require_number',
            'password_require_uppercase', 'login_attempts', 'lockout_time',
            'two_factor_required', 'jwt_ttl', 'rate_limit', 'rate_window', 'allowed_ips'
        ];
        
        $updateData = array_intersect_key($data, array_flip($allowed));
        
        foreach ($updateData as $key => $value) {
            $this->saveSetting('security', $key, $value);
        }
        
        $this->cache->delete('system_settings');
        $this->logAction('SETTINGS_UPDATE', 'Updated security settings');
        
        return $this->success([], 'Security settings updated');
    }
    
    /**
     * PUT /api/settings/email
     * 
     * Cập nhật cài đặt email
     */
    public function updateEmail()
    {
        $data = $this->getRequestData();
        
        $allowed = ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'from_address', 'from_name'];
        $updateData = array_intersect_key($data, array_flip($allowed));
        
        // Chỉ cập nhật password nếu không phải dấu ***
        if (isset($updateData['smtp_password']) && $updateData['smtp_password'] === '********') {
            unset($updateData['smtp_password']);
        }
        
        foreach ($updateData as $key => $value) {
            $this->saveSetting('email', $key, $value);
        }
        
        $this->cache->delete('system_settings');
        $this->logAction('SETTINGS_UPDATE', 'Updated email settings');
        
        return $this->success([], 'Email settings updated');
    }
    
    /**
     * POST /api/settings/email/test
     * 
     * Gửi email test
     */
    public function testEmail()
    {
        $data = $this->getRequestData();
        $toEmail = $data['to_email'] ?? $this->getCurrentUser()['email'] ?? '';
        
        if (!$toEmail) {
            return $this->error('Recipient email is required', 400);
        }
        
        // Lấy cấu hình email hiện tại
        $smtpHost = $this->getSetting('email', 'smtp_host');
        $smtpPort = $this->getSetting('email', 'smtp_port');
        $smtpUsername = $this->getSetting('email', 'smtp_username');
        $smtpPassword = $this->getSetting('email', 'smtp_password');
        $fromAddress = $this->getSetting('email', 'from_address');
        $fromName = $this->getSetting('email', 'from_name');
        
        // Gửi email test
        $result = $this->notification->sendTestEmail($toEmail, [
            'smtp_host' => $smtpHost,
            'smtp_port' => $smtpPort,
            'smtp_username' => $smtpUsername,
            'smtp_password' => $smtpPassword,
            'from_address' => $fromAddress,
            'from_name' => $fromName
        ]);
        
        if ($result) {
            return $this->success([], 'Test email sent successfully');
        } else {
            return $this->error('Failed to send test email. Please check your SMTP settings.', 500);
        }
    }
    
    /**
     * PUT /api/settings/backup
     * 
     * Cập nhật cài đặt backup
     */
    public function updateBackup()
    {
        $data = $this->getRequestData();
        
        $allowed = ['auto_backup_enabled', 'backup_time', 'backup_retention_days', 'backup_path', 'backup_include_files'];
        $updateData = array_intersect_key($data, array_flip($allowed));
        
        foreach ($updateData as $key => $value) {
            $this->saveSetting('backup', $key, $value);
        }
        
        $this->cache->delete('system_settings');
        $this->logAction('SETTINGS_UPDATE', 'Updated backup settings');
        
        return $this->success([], 'Backup settings updated');
    }
    
    /**
     * PUT /api/settings/assessment
     * 
     * Cập nhật cài đặt đánh giá
     */
    public function updateAssessment()
    {
        $data = $this->getRequestData();
        
        $allowed = ['default_score_threshold', 'auto_scan_enabled', 'scan_interval', 'scan_time', 'critical_alert_enabled', 'report_auto_generate'];
        $updateData = array_intersect_key($data, array_flip($allowed));
        
        foreach ($updateData as $key => $value) {
            $this->saveSetting('assessment', $key, $value);
        }
        
        // Lưu trọng số tiêu chí nếu có
        if (isset($data['criteria_weights']) && is_array($data['criteria_weights'])) {
            $this->saveSetting('assessment', 'criteria_weights', json_encode($data['criteria_weights']));
        }
        
        $this->cache->delete('system_settings');
        $this->logAction('SETTINGS_UPDATE', 'Updated assessment settings');
        
        return $this->success([], 'Assessment settings updated');
    }
    
    /**
     * PUT /api/settings/notification
     * 
     * Cập nhật cài đặt thông báo
     */
    public function updateNotification()
    {
        $data = $this->getRequestData();
        
        $allowed = ['email_notifications', 'alert_notifications', 'report_notifications', 'webhook_enabled', 'webhook_url'];
        $updateData = array_intersect_key($data, array_flip($allowed));
        
        foreach ($updateData as $key => $value) {
            $this->saveSetting('notification', $key, $value);
        }
        
        $this->cache->delete('system_settings');
        $this->logAction('SETTINGS_UPDATE', 'Updated notification settings');
        
        return $this->success([], 'Notification settings updated');
    }
    
    /**
     * PUT /api/settings/api
     * 
     * Cập nhật cài đặt API
     */
    public function updateApi()
    {
        $data = $this->getRequestData();
        
        $allowed = ['api_rate_limit', 'api_rate_window', 'api_version', 'enable_api_docs', 'allowed_origins'];
        $updateData = array_intersect_key($data, array_flip($allowed));
        
        // Xử lý allowed_origins là mảng
        if (isset($updateData['allowed_origins']) && is_array($updateData['allowed_origins'])) {
            $updateData['allowed_origins'] = json_encode($updateData['allowed_origins']);
        }
        
        foreach ($updateData as $key => $value) {
            $this->saveSetting('api', $key, $value);
        }
        
        $this->cache->delete('system_settings');
        $this->logAction('SETTINGS_UPDATE', 'Updated API settings');
        
        return $this->success([], 'API settings updated');
    }
    
    /**
     * POST /api/settings/reset/{group}
     * 
     * Đặt lại cài đặt về mặc định
     */
    public function reset($group)
    {
        if ($group === 'all') {
            foreach (array_keys($this->defaultSettings) as $g) {
                $this->resetGroup($g);
            }
            $message = 'All settings reset to default';
        } elseif (isset($this->defaultSettings[$group])) {
            $this->resetGroup($group);
            $message = ucfirst($group) . ' settings reset to default';
        } else {
            return $this->error('Invalid settings group', 400);
        }
        
        $this->cache->delete('system_settings');
        $this->logAction('SETTINGS_RESET', "Reset {$group} settings to default");
        
        return $this->success([], $message);
    }
    
    /**
     * GET /api/settings/backup/download
     * 
     * Tải file backup cài đặt
     */
    public function downloadBackup()
    {
        $settings = [];
        
        foreach (array_keys($this->defaultSettings) as $group) {
            $settings[$group] = $this->getSettingsGroup($group);
        }
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="settings_backup_' . date('Y-m-d_H-i-s') . '.json"');
        echo json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * POST /api/settings/backup/restore
     * 
     * Khôi phục cài đặt từ file backup
     */
    public function restoreBackup()
    {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            return $this->error('No file uploaded', 400);
        }
        
        $content = file_get_contents($_FILES['file']['tmp_name']);
        $settings = json_decode($content, true);
        
        if (!$settings || !is_array($settings)) {
            return $this->error('Invalid backup file', 400);
        }
        
        foreach ($settings as $group => $values) {
            if (isset($this->defaultSettings[$group]) && is_array($values)) {
                foreach ($values as $key => $value) {
                    $this->saveSetting($group, $key, $value);
                }
            }
        }
        
        $this->cache->delete('system_settings');
        $this->logAction('SETTINGS_RESTORE', 'Restored settings from backup');
        
        return $this->success([], 'Settings restored successfully');
    }
    
    /**
     * GET /api/settings/health
     * 
     * Kiểm tra sức khỏe hệ thống
     */
    public function healthCheck()
    {
        // Kiểm tra database
        try {
            $this->db->fetchColumn("SELECT 1");
            $dbStatus = 'ok';
        } catch (\Exception $e) {
            $dbStatus = 'error: ' . $e->getMessage();
        }
        
        // Kiểm tra storage
        $storagePath = __DIR__ . '/../storage';
        $storageStatus = is_writable($storagePath) ? 'ok' : 'not writable';
        
        // Kiểm tra cache
        try {
            $this->cache->set('health_check', 'ok', 10);
            $cacheStatus = $this->cache->get('health_check') === 'ok' ? 'ok' : 'failed';
        } catch (\Exception $e) {
            $cacheStatus = 'error: ' . $e->getMessage();
        }
        
        // Kiểm tra WebSocket
        $socketHost = $_ENV['SOCKET_HOST'] ?? 'localhost';
        $socketPort = $_ENV['SOCKET_PORT'] ?? 3000;
        $socketStatus = @fsockopen($socketHost, $socketPort) ? 'ok' : 'not reachable';
        
        // Thông tin hệ thống
        $systemInfo = [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'os' => PHP_OS,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize')
        ];
        
        return $this->success([
            'database' => $dbStatus,
            'storage' => $storageStatus,
            'cache' => $cacheStatus,
            'websocket' => $socketStatus,
            'system' => $systemInfo,
            'version' => '3.0.0',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    // ============================================
    // PRIVATE HELPER METHODS
    // ============================================
    
    /**
     * Lấy cài đặt theo nhóm
     */
    private function getSettingsGroup($group)
    {
        $settings = [];
        $defaults = $this->defaultSettings[$group] ?? [];
        
        foreach ($defaults as $key => $defaultValue) {
            $value = $this->getSetting($group, $key);
            $settings[$key] = $value !== null ? $value : $defaultValue;
            
            // Parse JSON nếu cần
            if (in_array($key, ['allowed_origins', 'criteria_weights']) && is_string($settings[$key])) {
                $settings[$key] = json_decode($settings[$key], true) ?: $settings[$key];
            }
        }
        
        return $settings;
    }
    
    /**
     * Lấy một cài đặt
     */
    private function getSetting($group, $key)
    {
        $sql = "SELECT setting_value FROM system_settings WHERE setting_group = ? AND setting_key = ?";
        $result = $this->db->fetchOne($sql, [$group, $key]);
        
        return $result ? $result['setting_value'] : null;
    }
    
    /**
     * Lưu cài đặt
     */
    private function saveSetting($group, $key, $value)
    {
        $exists = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM system_settings WHERE setting_group = ? AND setting_key = ?",
            [$group, $key]
        );
        
        if ($exists) {
            $this->db->update(
                'system_settings',
                ['setting_value' => $value, 'updated_at' => date('Y-m-d H:i:s')],
                'setting_group = ? AND setting_key = ?',
                [$group, $key]
            );
        } else {
            $this->db->insert('system_settings', [
                'setting_group' => $group,
                'setting_key' => $key,
                'setting_value' => $value,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
    
    /**
     * Reset một nhóm cài đặt
     */
    private function resetGroup($group)
    {
        $defaults = $this->defaultSettings[$group] ?? [];
        
        foreach ($defaults as $key => $value) {
            $this->saveSetting($group, $key, $value);
        }
    }
}