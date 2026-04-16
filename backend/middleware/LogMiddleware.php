<?php
/**
 * LOG MIDDLEWARE
 * 
 * Ghi log tất cả request vào hệ thống
 * Phục vụ debug và audit
 * 
 * @package Middleware
 */

namespace Middleware;

use Core\Logger;
use Core\Database;

class LogMiddleware
{
    /**
     * Ghi log request
     */
    public static function handle()
    {
        $startTime = microtime(true);
        
        // Lấy thông tin request
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Lấy user ID nếu có token
        $userId = self::getUserIdFromToken();
        
        // Ghi log
        if (($method !== 'GET' || strpos($uri, '/api/dashboard') === false) && $_ENV['APP_DEBUG'] ?? false) {
            Logger::debug("Request: {$method} {$uri}", [
                'ip' => $ip,
                'user_id' => $userId,
                'user_agent' => substr($userAgent, 0, 100)
            ]);
        }
        
        // Lưu vào database cho các request quan trọng
        $importantMethods = ['POST', 'PUT', 'DELETE', 'PATCH'];
        if (in_array($method, $importantMethods)) {
            self::logToDatabase($userId, $method, $uri, $ip, $userAgent);
        }
        
        // Register shutdown function để ghi thời gian xử lý
        register_shutdown_function(function() use ($startTime, $method, $uri) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            if ($executionTime > 1000) {
                Logger::warning("Slow request: {$method} {$uri} took {$executionTime}ms");
            }
        });
        
        return true;
    }
    
    /**
     * Lấy user ID từ JWT token
     */
    private static function getUserIdFromToken()
    {
        $headers = getallheaders();
        $token = str_replace('Bearer ', '', $headers['Authorization'] ?? $headers['authorization'] ?? '');
        
        if (!$token) {
            return null;
        }
        
        try {
            $payload = \Core\JWT::decode($token);
            return $payload->user_id ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Ghi log vào database
     */
    private static function logToDatabase($userId, $method, $uri, $ip, $userAgent)
    {
        $db = Database::getInstance();
        
        // Bỏ qua log cho một số endpoint để tránh tràn
        $excludePaths = ['/api/auth/login', '/api/auth/refresh'];
        if (in_array($uri, $excludePaths)) {
            return;
        }
        
        $db->insert('api_logs', [
            'user_id' => $userId,
            'method' => $method,
            'endpoint' => $uri,
            'ip_address' => $ip,
            'user_agent' => substr($userAgent, 0, 255),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Dọn dẹp log cũ
     */
    public static function cleanup()
    {
        $db = Database::getInstance();
        $db->delete('api_logs', 'created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
    }
}