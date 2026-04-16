<?php
/**
 * JWT CONFIGURATION
 * 
 * Cấu hình JSON Web Token cho xác thực API
 * 
 * @package Config
 */

return [
    // Secret key để ký token (QUAN TRỌNG: phải thay đổi trong production)
    'secret' => $_ENV['JWT_SECRET'] ?? 'your-super-secret-key-change-in-production',
    
    // Thời gian sống của token (giây)
    'ttl' => (int)($_ENV['JWT_TTL'] ?? 3600), // 1 hour
    
    // Thời gian sống của refresh token (giây)
    'refresh_ttl' => (int)($_ENV['JWT_REFRESH_TTL'] ?? 604800), // 7 days
    
    // Thuật toán mã hóa
    'algo' => 'HS256',
    
    // Claims mặc định
    'claims' => [
        'iss' => $_ENV['APP_URL'] ?? 'http://localhost', // Issuer
        'aud' => $_ENV['APP_URL'] ?? 'http://localhost', // Audience
    ],
    
    // Blacklist token khi logout
    'blacklist_enabled' => true,
    'blacklist_ttl' => 86400, // 24 hours
    
    // Leeway time cho clock skew (giây)
    'leeway' => 60,
    
    // Require confirmation (email verification)
    'require_confirmation' => filter_var($_ENV['JWT_REQUIRE_CONFIRMATION'] ?? false, FILTER_VALIDATE_BOOLEAN),
];