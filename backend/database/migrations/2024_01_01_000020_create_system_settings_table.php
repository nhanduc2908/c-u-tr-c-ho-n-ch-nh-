<?php
/**
 * MIGRATION: Create system_settings table
 * 
 * Bảng lưu cấu hình hệ thống
 * Key-value store cho các setting
 * 
 * @package Migrations
 */

use Core\Database;

class CreateSystemSettingsTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS system_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            setting_group VARCHAR(50) NOT NULL COMMENT 'Nhóm cài đặt',
            setting_key VARCHAR(100) NOT NULL COMMENT 'Key',
            setting_value TEXT COMMENT 'Value',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_group_key (setting_group, setting_key),
            INDEX idx_setting_group (setting_group)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng cài đặt hệ thống'";
        
        $db->query($sql);
        
        // Insert default settings
        $defaultSettings = [
            ['general', 'app_name', 'Security Assessment Platform'],
            ['general', 'app_version', '3.0.0'],
            ['general', 'maintenance_mode', '0'],
            ['security', 'session_lifetime', '120'],
            ['security', 'password_min_length', '8'],
            ['security', 'login_attempts', '5'],
            ['security', 'lockout_time', '15'],
            ['backup', 'auto_backup_enabled', '1'],
            ['backup', 'backup_retention_days', '30'],
            ['backup', 'backup_time', '02:00'],
            ['assessment', 'default_score_threshold', '60'],
            ['assessment', 'auto_scan_enabled', '1'],
            ['notification', 'email_notifications', '1'],
            ['notification', 'alert_notifications', '1']
        ];
        
        foreach ($defaultSettings as $setting) {
            $db->query(
                "INSERT IGNORE INTO system_settings (setting_group, setting_key, setting_value) VALUES (?, ?, ?)",
                $setting
            );
        }
        
        echo "✓ Created system_settings table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS system_settings");
        echo "✓ Dropped system_settings table\n";
    }
}