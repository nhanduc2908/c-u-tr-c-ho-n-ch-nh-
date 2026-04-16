<?php
/**
 * MAIL CONFIGURATION
 * 
 * Cấu hình gửi email (SMTP)
 * 
 * @package Config
 */

return [
    // Default mailer
    'default' => $_ENV['MAIL_MAILER'] ?? 'smtp',
    
    // Mailers
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com',
            'port' => (int)($_ENV['MAIL_PORT'] ?? 587),
            'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
            'username' => $_ENV['MAIL_USERNAME'] ?? '',
            'password' => $_ENV['MAIL_PASSWORD'] ?? '',
            'timeout' => null,
            'auth_mode' => null,
        ],
        'sendmail' => [
            'transport' => 'sendmail',
            'path' => '/usr/sbin/sendmail -bs',
        ],
        'mail' => [
            'transport' => 'mail',
        ],
    ],
    
    // From address
    'from' => [
        'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@security.com',
        'name' => $_ENV['MAIL_FROM_NAME'] ?? 'Security Assessment Platform',
    ],
    
    // Email templates
    'templates' => [
        'welcome' => [
            'subject' => 'Chào mừng đến với Security Assessment Platform',
            'view' => 'emails/welcome',
        ],
        'password_reset' => [
            'subject' => 'Đặt lại mật khẩu - Security Assessment Platform',
            'view' => 'emails/password-reset',
        ],
        'password_changed' => [
            'subject' => 'Mật khẩu đã thay đổi - Security Assessment Platform',
            'view' => 'emails/password-changed',
        ],
        'new_login' => [
            'subject' => 'Đăng nhập mới - Security Assessment Platform',
            'view' => 'emails/new-login',
        ],
        'vulnerability_alert' => [
            'subject' => '⚠️ Cảnh báo lỗ hổng mới - Security Assessment Platform',
            'view' => 'emails/vulnerability-alert',
        ],
        'assessment_report' => [
            'subject' => 'Báo cáo đánh giá bảo mật - Security Assessment Platform',
            'view' => 'emails/assessment-report',
        ],
    ],
    
    // Thời gian chờ (giây)
    'timeout' => 60,
    
    // Gửi mail dạng HTML
    'html' => true,
    
    // Charset
    'charset' => 'UTF-8',
];