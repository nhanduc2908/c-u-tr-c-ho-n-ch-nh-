<?php
/**
 * SETTING SEEDER
 * 
 * Tạo cài đặt mặc định cho hệ thống
 * 
 * @package Seeds
 */

use Core\Database;

class SettingSeeder
{
    public function run()
    {
        $db = Database::getInstance();
        
        // Xóa dữ liệu cũ
        $db->query("TRUNCATE TABLE system_settings");
        
        $settings = [
            // General settings
            ['setting_group' => 'general', 'setting_key' => 'app_name', 'setting_value' => 'Security Assessment Platform'],
            ['setting_group' => 'general', 'setting_key' => 'timezone', 'setting_value' => 'Asia/Ho_Chi_Minh'],
            ['setting_group' => 'general', 'setting_key' => 'date_format', 'setting_value' => 'Y-m-d H:i:s'],
            ['setting_group' => 'general', 'setting_key' => 'language', 'setting_value' => 'vi'],
            ['setting_group' => 'general', 'setting_key' => 'maintenance_mode', 'setting_value' => '0'],
            
            // Security settings
            ['setting_group' => 'security', 'setting_key' => 'session_lifetime', 'setting_value' => '120'],
            ['setting_group' => 'security', 'setting_key' => 'password_min_length', 'setting_value' => '8'],
            ['setting_group' => 'security', 'setting_key' => 'login_attempts', 'setting_value' => '5'],
            ['setting_group' => 'security', 'setting_key' => 'lockout_time', 'setting_value' => '15'],
            ['setting_group' => 'security', 'setting_key' => 'two_factor_required', 'setting_value' => '0'],
            ['setting_group' => 'security', 'setting_key' => 'jwt_ttl', 'setting_value' => '3600'],
            ['setting_group' => 'security', 'setting_key' => 'rate_limit', 'setting_value' => '100'],
            
            // Assessment settings
            ['setting_group' => 'assessment', 'setting_key' => 'default_score_threshold', 'setting_value' => '60'],
            ['setting_group' => 'assessment', 'setting_key' => 'auto_scan_enabled', 'setting_value' => '1'],
            ['setting_group' => 'assessment', 'setting_key' => 'scan_interval', 'setting_value' => 'daily'],
            ['setting_group' => 'assessment', 'setting_key' => 'scan_time', 'setting_value' => '01:00'],
            ['setting_group' => 'assessment', 'setting_key' => 'critical_alert_enabled', 'setting_value' => '1'],
            ['setting_group' => 'assessment', 'setting_key' => 'report_auto_generate', 'setting_value' => '1'],
            
            // Backup settings
            ['setting_group' => 'backup', 'setting_key' => 'auto_backup_enabled', 'setting_value' => '1'],
            ['setting_group' => 'backup', 'setting_key' => 'backup_time', 'setting_value' => '02:00'],
            ['setting_group' => 'backup', 'setting_key' => 'backup_retention_days', 'setting_value' => '30'],
            ['setting_group' => 'backup', 'setting_key' => 'backup_path', 'setting_value' => '/var/backups/security'],
            
            // Notification settings
            ['setting_group' => 'notification', 'setting_key' => 'email_notifications', 'setting_value' => '1'],
            ['setting_group' => 'notification', 'setting_key' => 'alert_notifications', 'setting_value' => '1'],
            ['setting_group' => 'notification', 'setting_key' => 'report_notifications', 'setting_value' => '1'],
            ['setting_group' => 'notification', 'setting_key' => 'webhook_enabled', 'setting_value' => '0'],
        ];
        
        foreach ($settings as $setting) {
            $db->insert('system_settings', [
                'setting_group' => $setting['setting_group'],
                'setting_key' => $setting['setting_key'],
                'setting_value' => $setting['setting_value'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        echo "      - Created " . count($settings) . " system settings\n";
    }
}
