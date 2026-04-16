<?php
/**
 * DATABASE CONFIGURATION
 * 
 * Cấu hình kết nối database
 * 
 * @package Config
 */

return [
    // Default database connection
    'default' => $_ENV['DB_CONNECTION'] ?? 'mysql',
    
    // Database connections
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => $_ENV['DB_PORT'] ?? 3306,
            'database' => $_ENV['DB_NAME'] ?? 'security_db',
            'username' => $_ENV['DB_USER'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => $_ENV['DB_PREFIX'] ?? '',
            'strict' => true,
            'engine' => 'InnoDB',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ],
    ],
    
    // Migration repository
    'migrations' => 'migrations',
    
    // Redis cache (optional)
    'redis' => [
        'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
        'port' => (int)($_ENV['REDIS_PORT'] ?? 6379),
        'password' => $_ENV['REDIS_PASSWORD'] ?? null,
        'database' => (int)($_ENV['REDIS_DB'] ?? 0),
    ],
    
    // Backup
    'backup' => [
        'path' => __DIR__ . '/../storage/backups/',
        'retention_days' => (int)($_ENV['BACKUP_RETENTION_DAYS'] ?? 30),
        'auto_backup' => filter_var($_ENV['AUTO_BACKUP_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'backup_time' => $_ENV['BACKUP_TIME'] ?? '02:00',
    ],
];