<?php
/**
 * Middleware - Xác thực và xử lý request
 * 
 * @package Core
 */

namespace Core;

class Middleware
{
    /**
     * Xác thực JWT token
     * 
     * @return object|null Payload nếu hợp lệ
     */
    public static function auth()
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
        
        return $payload;
    }
    
    /**
     * Lấy user ID từ token
     * 
     * @return int|null
     */
    public static function getUserId()
    {
        $payload = self::auth();
        return $payload->user_id ?? null;
    }
    
    /**
     * Lấy thông tin user hiện tại
     * 
     * @return array|null
     */
    public static function getCurrentUser()
    {
        $userId = self::getUserId();
        if (!$userId) {
            return null;
        }
        
        $db = Database::getInstance();
        return $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    }
    
    /**
     * Trả về lỗi 401 Unauthorized
     * 
     * @param string $message
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