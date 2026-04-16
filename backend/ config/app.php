<?php
/**
 * APPLICATION CONFIGURATION
 * 
 * Cấu hình chung của ứng dụng
 * 
 * @package Config
 */

return [
    // Thông tin ứng dụng
    'name' => $_ENV['APP_NAME'] ?? 'Security Assessment Platform',
    'version' => '3.0.0',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Asia/Ho_Chi_Minh',
    'locale' => $_ENV['APP_LOCALE'] ?? 'vi',
    'fallback_locale' => 'en',
    
    // Mã hóa
    'key' => $_ENV['APP_KEY'] ?? '',
    'cipher' => 'AES-256-CBC',
    
    // Session
    'session' => [
        'lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 120),
        'driver' => $_ENV['SESSION_DRIVER'] ?? 'file',
        'prefix' => 'security_',
        'secure' => filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'http_only' => true,
        'same_site' => 'lax',
    ],
    
    // Bảo mật
    'security' => [
        'bcrypt_rounds' => (int)($_ENV['BCRYPT_ROUNDS'] ?? 12),
        'login_attempts' => (int)($_ENV['LOGIN_ATTEMPTS'] ?? 5),
        'lockout_time' => (int)($_ENV['LOGIN_LOCKOUT_TIME'] ?? 15),
        'password_min_length' => (int)($_ENV['PASSWORD_MIN_LENGTH'] ?? 8),
        'password_require_number' => filter_var($_ENV['PASSWORD_REQUIRE_NUMBER'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'password_require_uppercase' => filter_var($_ENV['PASSWORD_REQUIRE_UPPERCASE'] ?? true, FILTER_VALIDATE_BOOLEAN),
    ],
    
    // CORS
    'cors' => [
        'allowed_origins' => explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? 'http://localhost'),
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept'],
        'max_age' => 86400,
    ],
    
    // Rate Limiting
    'rate_limit' => [
        'enabled' => filter_var($_ENV['RATE_LIMIT_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'max_requests' => (int)($_ENV['RATE_LIMIT_REQUESTS'] ?? 100),
        'decay_minutes' => (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 1),
    ],
    
    // Logging
    'logging' => [
        'channel' => $_ENV['LOG_CHANNEL'] ?? 'file',
        'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
        'path' => __DIR__ . '/../storage/logs/app.log',
        'max_files' => 30,
    ],
    
    // Upload
    'upload' => [
        'max_size' => (int)($_ENV['UPLOAD_MAX_SIZE'] ?? 5242880),
        'allowed_extensions' => explode(',', $_ENV['UPLOAD_ALLOWED_EXTENSIONS'] ?? 'jpg,jpeg,png,gif,pdf,doc,docx,xlsx'),
        'allowed_mimes' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ],
    ],
    
    // Maintenance mode
    'maintenance' => [
        'enabled' => file_exists(__DIR__ . '/../storage/maintenance.flag'),
        'message' => 'Hệ thống đang bảo trì. Vui lòng quay lại sau.',
    ],
];