<?php
/**
 * AUDIT LOGS API ROUTES
 * 
 * Quản lý và xem nhật ký hệ thống - CHỈ ADMIN
 * - Xem audit logs
 * - Lọc theo thời gian, hành động, người dùng
 * - Xuất logs ra file
 * - Xóa logs cũ
 * 
 * @package API
 */

use Core\Router;

$router = new Router();

// Tất cả routes audit đều yêu cầu quyền ADMIN
$router->group([
    'prefix' => '/audit', 
    'middleware' => ['AuthMiddleware', 'PermissionMiddleware@adminOnly']
], function($router) {
    
    /**
     * GET /api/audit/logs
     * 
     * Lấy danh sách audit logs (có phân trang, filter)
     * 
     * Headers: Authorization: Bearer <token>
     * 
     * Query params:
     * - page: 1
     * - limit: 50
     * - user_id: 1
     * - action: LOGIN_SUCCESS|LOGIN_FAILED|USER_CREATE|...
     * - from_date: 2024-01-01
     * - to_date: 2024-12-31
     * - search: keyword
     * 
     * Response:
     * {
     *     "success": true,
     *     "data": [...],
     *     "pagination": {...},
     *     "stats": {...}
     * }
     */
    $router->get('/logs', 'AuditController@index');
    
    /**
     * GET /api/audit/logs/{id}
     * 
     * Xem chi tiết một log
     */
    $router->get('/logs/{id}', 'AuditController@show');
    
    /**
     * GET /api/audit/stats
     * 
     * Thống kê audit logs
     * 
     * Response:
     * {
     *     "success": true,
     *     "data": {
     *         "total_logs": 1523,
     *         "by_action": {...},
     *         "by_user": {...},
     *         "by_date": {...}
     *     }
     * }
     */
    $router->get('/stats', 'AuditController@stats');
    
    /**
     * GET /api/audit/actions
     * 
     * Lấy danh sách các hành động (actions) có trong hệ thống
     */
    $router->get('/actions', 'AuditController@getActions');
    
    /**
     * GET /api/audit/export
     * 
     * Xuất audit logs ra file (CSV, Excel, JSON)
     * 
     * Query params:
     * - format: csv|excel|json
     * - from_date: 2024-01-01
     * - to_date: 2024-12-31
     */
    $router->get('/export', 'AuditController@export');
    
    /**
     * DELETE /api/audit/cleanup
     * 
     * Xóa audit logs cũ hơn số ngày chỉ định
     * 
     * Request body:
     * {
     *     "older_than_days": 90,
     *     "confirm": true
     * }
     */
    $router->delete('/cleanup', 'AuditController@cleanup');
    
    /**
     * GET /api/audit/realtime
     * 
     * Lấy audit logs realtime (polling)
     * 
     * Query params:
     * - last_id: 1000 (lấy logs mới hơn ID này)
     */
    $router->get('/realtime', 'AuditController@realtime');
    
    /**
     * GET /api/audit/user/{userId}
     * 
     * Lấy audit logs theo user cụ thể
     */
    $router->get('/user/{userId}', 'AuditController@getByUser');
    
    /**
     * GET /api/audit/ip/{ip}
     * 
     * Lấy audit logs theo IP
     */
    $router->get('/ip/{ip}', 'AuditController@getByIp');
});