<?php
/**
 * PERMISSION MIDDLEWARE
 * 
 * Kiểm tra quyền truy cập dựa trên role (3 loại tài khoản)
 * - adminOnly: Chỉ ADMIN mới được truy cập
 * - officerOrAbove: ADMIN hoặc SECURITY_OFFICER
 * - authenticatedOnly: Bất kỳ user đã đăng nhập
 * - hasPermission: Kiểm tra quyền cụ thể
 * 
 * @package Middleware
 */

namespace Middleware;

use Core\RBAC;
use Core\JWT;
use Core\Database;

class PermissionMiddleware
{
    /**
     * Chỉ ADMIN mới được truy cập
     */
    public static function adminOnly()
    {
        $user = self::getAuthenticatedUser();
        if (!$user) {
            self::deny('Unauthorized');
        }
        
        $rbac = new RBAC();
        $role = $rbac->getUserRole($user['id']);
        
        if ($role !== RBAC::ROLE_ADMIN) {
            self::deny('Admin only access');
        }
        
        return $user;
    }
    
    /**
     * ADMIN hoặc SECURITY_OFFICER
     */
    public static function officerOrAbove()
    {
        $user = self::getAuthenticatedUser();
        if (!$user) {
            self::deny('Unauthorized');
        }
        
        $rbac = new RBAC();
        $role = $rbac->getUserRole($user['id']);
        
        if (!in_array($role, [RBAC::ROLE_ADMIN, RBAC::ROLE_SECURITY_OFFICER])) {
            self::deny('Access denied. Requires Admin or Security Officer');
        }
        
        return $user;
    }
    
    /**
     * Bất kỳ user đã đăng nhập (cả 3 role)
     */
    public static function authenticatedOnly()
    {
        $user = self::getAuthenticatedUser();
        if (!$user) {
            self::deny('Unauthorized');
        }
        return $user;
    }
    
    /**
     * Kiểm tra permission cụ thể
     * 
     * @param string $permissionName Tên permission cần kiểm tra
     */
    public static function hasPermission($permissionName)
    {
        $user = self::getAuthenticatedUser();
        if (!$user) {
            self::deny('Unauthorized');
        }
        
        $rbac = new RBAC();
        if (!$rbac->hasPermission($user['id'], $permissionName)) {
            self::deny("Permission denied: {$permissionName}");
        }
        
        return $user;
    }
    
    /**
     * Lấy user từ JWT token
     */
    private static function getAuthenticatedUser()
    {
        $headers = getallheaders();
        $token = str_replace('Bearer ', '', $headers['Authorization'] ?? $headers['authorization'] ?? '');
        
        if (!$token) {
            return null;
        }
        
        $payload = JWT::decode($token);
        if (!$payload) {
            return null;
        }
        
        $db = Database::getInstance();
        return $db->fetchOne("SELECT * FROM users WHERE id = ?", [$payload->user_id]);
    }
    
    /**
     * Trả về lỗi 403 Forbidden
     */
    private static function deny($message)
    {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Forbidden',
            'message' => $message
        ]);
        exit;
    }
}