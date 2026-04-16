<?php
/**
 * CORS MIDDLEWARE
 * 
 * Xử lý Cross-Origin Resource Sharing
 * Cho phép frontend gọi API từ domain khác
 * 
 * @package Middleware
 */

namespace Middleware;

class CorsMiddleware
{
    /**
     * Xử lý CORS headers
     */
    public static function handle()
    {
        // Danh sách allowed origins
        $allowedOrigins = [
            'http://localhost',
            'http://localhost:3000',
            'http://localhost:8080',
            'http://localhost:5500',
            $_ENV['APP_URL'] ?? ''
        ];
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array($origin, $allowedOrigins) || ($_ENV['APP_DEBUG'] ?? false)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header("Access-Control-Allow-Credentials: true");
        }
        
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, X-Lang, X-Device");
        header("Access-Control-Expose-Headers: Content-Length, X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset");
        header("Access-Control-Max-Age: 86400");
        
        // Xử lý preflight request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
        
        return true;
    }
}