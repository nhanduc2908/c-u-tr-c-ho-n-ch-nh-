<?php
/**
 * AUTH MIDDLEWARE
 * 
 * Xác thực JWT token từ header Authorization
 * Nếu token không hợp lệ hoặc hết hạn, trả về lỗi 401
 * 
 * @package Middleware
 */

namespace Middleware;

use Core\JWT;
use Core\Database;

class AuthMiddleware
{
    /**
     * Xử lý xác thực
     * 
     * @return object|null Payload nếu hợp lệ, null nếu không
     */
    public static function handle()
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        $token = str_replace('Bearer ', '', $authHeader);
        
        if (!$token) {
            self::unauthorized('No token provided');
        }
        
        $payload = JWT::decode($token);
        if (!$payload) {
            self::unauthorized('Invalid or expired token');
        }
        
        // Kiểm tra session còn tồn tại trong database
        $db = Database::getInstance();
        $session = $db->fetchOne(
            "SELECT * FROM user_sessions WHERE token = ? AND expires_at > NOW()",
            [$token]
        );
        
        if (!$session) {
            self::unauthorized('Session expired');
        }
        
        // Kiểm tra user còn active không
        $user = $db->fetchOne("SELECT * FROM users WHERE id = ? AND is_active = 1", [$payload->user_id]);
        if (!$user) {
            self::unauthorized('Account disabled');
        }
        
        return $payload;
    }
    
    /**
     * Trả về lỗi 401 Unauthorized
     */
    private static function unauthorized($message = 'Unauthorized')
    {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized',
            'message' => $message
        ]);
        exit;
    }
}