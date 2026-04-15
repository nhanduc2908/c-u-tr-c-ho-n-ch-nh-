<?php
/**
 * SYSTEM SETTINGS API ROUTES
 * 
 * Quản lý cài đặt hệ thống - CHỈ ADMIN
 * - Cấu hình chung
 * - Cấu hình bảo mật
 * - Cấu hình email
 * - Cấu hình backup
 * - Cấu hình đánh giá
 * 
 * @package API
 */

use Core\Router;

$router = new Router();

// Tất cả routes settings đều yêu cầu quyền ADMIN
$router->group([
    'prefix' => '/settings', 
    'middleware' => ['AuthMiddleware', 'PermissionMiddleware@adminOnly']
], function($router) {
    
    /**
     * GET /api/settings
     * 
     * Lấy tất cả cài đặt hệ thống
     * 
     * Headers: Authorization: Bearer <token>
     * 
     * Response:
     * {
     *     "success": true,
     *     "data": {
     *         "general": {...},
     *         "security": {...},
     *         "email": {...},
     *         "backup": {...},
     *         "assessment": {...}
     *     }
     * }
     */
    $router->get('', 'SettingController@index');
    
    /**
     * GET /api/settings/{key}
     * 
     * Lấy cài đặt theo nhóm
     * 
     * Groups: general, security, email, backup, assessment, notification, api
     */
    $router->get('/{key}', 'SettingController@get');
    
    /**
     * PUT /api/settings/general
     * 
     * Cập nhật cài đặt chung
     * 
     * Request body:
     * {
     *     "app_name": "Security Assessment Platform",
     *     "app_url": "https://security.example.com",
     *     "timezone": "Asia/Ho_Chi_Minh",
     *     "date_format": "Y-m-d H:i:s",
     *     "language": "vi",
     *     "maintenance_mode": false,
     *     "maintenance_message": "Đang bảo trì..."
     * }
     */
    $router->put('/general', 'SettingController@updateGeneral');
    
    /**
     * PUT /api/settings/security
     * 
     * Cập nhật cài đặt bảo mật
     * 
     * Request body:
     * {
     *     "session_lifetime": 120,
     *     "password_min_length": 8,
     *     "password_require_number": true,
     *     "password_require_uppercase": true,
     *     "login_attempts": 5,
     *     "lockout_time": 15,
     *     "two_factor_required": false,
     *     "jwt_ttl": 3600,
     *     "allowed_ips": ["192.168.1.0/24"],
     *     "rate_limit": 100
     * }
     */
    $router->put('/security', 'SettingController@updateSecurity');
    
    /**
     * PUT /api/settings/email
     * 
     * Cập nhật cài đặt email (SMTP)
     * 
     * Request body:
     * {
     *     "smtp_host": "smtp.gmail.com",
     *     "smtp_port": 587,
     *     "smtp_username": "noreply@security.com",
     *     "smtp_password": "********",
     *     "smtp_encryption": "tls",
     *     "from_address": "noreply@security.com",
     *     "from_name": "Security Platform",
     *     "test_email": "admin@security.com"
     * }
     */
    $router->put('/email', 'SettingController@updateEmail');
    
    /**
     * POST /api/settings/email/test
     * 
     * Gửi email test để kiểm tra cấu hình SMTP
     * 
     * Request body:
     * {
     *     "to_email": "admin@example.com"
     * }
     */
    $router->post('/email/test', 'SettingController@testEmail');
    
    /**
     * PUT /api/settings/backup
     * 
     * Cập nhật cài đặt backup
     * 
     * Request body:
     * {
     *     "auto_backup_enabled": true,
     *     "backup_time": "02:00",
     *     "backup_retention_days": 30,
     *     "backup_path": "/var/backups/security",
     *     "backup_include_files": true,
     *     "backup_destination": "local|s3|ftp"
     * }
     */
    $router->put('/backup', 'SettingController@updateBackup');
    
    /**
     * PUT /api/settings/assessment
     * 
     * Cập nhật cài đặt đánh giá
     * 
     * Request body:
     * {
     *     "default_score_threshold": 60,
     *     "auto_scan_enabled": true,
     *     "scan_interval": "daily",
     *     "scan_time": "01:00",
     *     "critical_alert_enabled": true,
     *     "report_auto_generate": true,
     *     "criteria_weights": {...}
     * }
     */
    $router->put('/assessment', 'SettingController@updateAssessment');
    
    /**
     * PUT /api/settings/notification
     * 
     * Cập nhật cài đặt thông báo
     * 
     * Request body:
     * {
     *     "email_notifications": true,
     *     "alert_notifications": true,
     *     "report_notifications": true,
     *     "webhook_url": "https://hooks.slack.com/...",
     *     "webhook_enabled": false
     * }
     */
    $router->put('/notification', 'SettingController@updateNotification');
    
    /**
     * PUT /api/settings/api
     * 
     * Cập nhật cài đặt API
     * 
     * Request body:
     * {
     *     "api_rate_limit": 100,
     *     "api_rate_window": 60,
     *     "allowed_origins": ["http://localhost:3000"],
     *     "api_version": "v1",
     *     "enable_api_docs": true
     * }
     */
    $router->put('/api', 'SettingController@updateApi');
    
    /**
     * POST /api/settings/reset/{group}
     * 
     * Đặt lại cài đặt về mặc định
     * 
     * Groups: general, security, email, backup, assessment, notification, api, all
     */
    $router->post('/reset/{group}', 'SettingController@reset');
    
    /**
     * GET /api/settings/backup/download
     * 
     * Tải file backup cài đặt
     */
    $router->get('/backup/download', 'SettingController@downloadBackup');
    
    /**
     * POST /api/settings/backup/restore
     * 
     * Khôi phục cài đặt từ file backup
     * 
     * Request: multipart/form-data - file: settings_backup.json
     */
    $router->post('/backup/restore', 'SettingController@restoreBackup');
    
    /**
     * GET /api/settings/health
     * 
     * Kiểm tra sức khỏe hệ thống
     * 
     * Response:
     * {
     *     "success": true,
     *     "data": {
     *         "database": "ok",
     *         "redis": "ok",
     *         "websocket": "ok",
     *         "storage": "ok",
     *         "version": "3.0.0"
     *     }
     * }
     */
    $router->get('/health', 'SettingController@healthCheck');
});