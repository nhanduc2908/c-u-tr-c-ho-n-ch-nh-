<?php
/**
 * PROFILE API ROUTES
 * 
 * Quản lý thông tin cá nhân của người dùng
 * - Xem thông tin profile
 * - Cập nhật thông tin cá nhân
 * - Đổi mật khẩu
 * - Upload/Đổi avatar
 * - Xem lịch sử hoạt động cá nhân
 * - Quản lý phiên đăng nhập
 * 
 * @package API
 */

use Core\Router;

$router = new Router();

// Tất cả routes profile đều yêu cầu xác thực
$router->group(['prefix' => '/profile', 'middleware' => ['AuthMiddleware']], function($router) {
    
    /**
     * GET /api/profile
     * 
     * Lấy thông tin cá nhân
     * 
     * Headers: Authorization: Bearer <token>
     * 
     * Response:
     * {
     *     "success": true,
     *     "data": {
     *         "id": 1,
     *         "username": "admin",
     *         "email": "admin@security.com",
     *         "full_name": "Administrator",
     *         "avatar": "/uploads/avatars/admin.jpg",
     *         "role": "admin",
     *         "role_id": 1,
     *         "is_active": true,
     *         "last_login": "2024-01-15 10:30:00",
     *         "last_ip": "192.168.1.1",
     *         "created_at": "2024-01-01 00:00:00",
     *         "two_factor_enabled": false
     *     }
     * }
     */
    $router->get('', 'ProfileController@show');
    
    /**
     * PUT /api/profile
     * 
     * Cập nhật thông tin cá nhân
     * 
     * Headers: Authorization: Bearer <token>
     * 
     * Request body:
     * {
     *     "full_name": "Nguyen Van A",
     *     "email": "newemail@example.com",
     *     "phone": "0912345678",
     *     "address": "Hanoi, Vietnam"
     * }
     */
    $router->put('', 'ProfileController@update');
    
    /**
     * PUT /api/profile/change-password
     * 
     * Đổi mật khẩu
     * 
     * Headers: Authorization: Bearer <token>
     * 
     * Request body:
     * {
     *     "current_password": "old_password",
     *     "new_password": "new_password",
     *     "new_password_confirmation": "new_password"
     * }
     */
    $router->put('/change-password', 'ProfileController@changePassword');
    
    /**
     * POST /api/profile/avatar
     * 
     * Upload avatar
     * 
     * Headers: Authorization: Bearer <token>
     * Content-Type: multipart/form-data
     * 
     * Request: file (image/jpeg, image/png, image/gif)
     */
    $router->post('/avatar', 'ProfileController@uploadAvatar');
    
    /**
     * DELETE /api/profile/avatar
     * 
     * Xóa avatar
     * 
     * Headers: Authorization: Bearer <token>
     */
    $router->delete('/avatar', 'ProfileController@deleteAvatar');
    
    /**
     * GET /api/profile/activity
     * 
     * Lấy lịch sử hoạt động cá nhân
     * 
     * Headers: Authorization: Bearer <token>
     * 
     * Query params:
     * - page: 1
     * - limit: 20
     * - type: login|scan|assessment|alert
     */
    $router->get('/activity', 'ProfileController@getActivity');
    
    /**
     * GET /api/profile/sessions
     * 
     * Lấy danh sách các phiên đăng nhập
     * 
     * Headers: Authorization: Bearer <token>
     */
    $router->get('/sessions', 'ProfileController@getSessions');
    
    /**
     * DELETE /api/profile/sessions/{id}
     * 
     * Xóa một phiên đăng nhập (đăng xuất từ xa)
     * 
     * Headers: Authorization: Bearer <token>
     */
    $router->delete('/sessions/{id}', 'ProfileController@revokeSession');
    
    /**
     * DELETE /api/profile/sessions
     * 
     * Xóa tất cả phiên đăng nhập khác (đăng xuất các thiết bị khác)
     * 
     * Headers: Authorization: Bearer <token>
     */
    $router->delete('/sessions', 'ProfileController@revokeAllOtherSessions');
    
    /**
     * POST /api/profile/two-factor/enable
     * 
     * Bật xác thực 2 lớp
     * 
     * Headers: Authorization: Bearer <token>
     */
    $router->post('/two-factor/enable', 'ProfileController@enableTwoFactor');
    
    /**
     * POST /api/profile/two-factor/verify
     * 
     * Xác thực mã 2FA
     * 
     * Headers: Authorization: Bearer <token>
     * 
     * Request body:
     * {
     *     "code": "123456"
     * }
     */
    $router->post('/two-factor/verify', 'ProfileController@verifyTwoFactor');
    
    /**
     * POST /api/profile/two-factor/disable
     * 
     * Tắt xác thực 2 lớp
     * 
     * Headers: Authorization: Bearer <token>
     * 
     * Request body:
     * {
     *     "code": "123456"
     * }
     */
    $router->post('/two-factor/disable', 'ProfileController@disableTwoFactor');
    
    /**
     * GET /api/profile/notifications
     * 
     * Lấy danh sách thông báo
     * 
     * Headers: Authorization: Bearer <token>
     */
    $router->get('/notifications', 'ProfileController@getNotifications');
    
    /**
     * PUT /api/profile/notifications/{id}/read
     * 
     * Đánh dấu thông báo đã đọc
     * 
     * Headers: Authorization: Bearer <token>
     */
    $router->put('/notifications/{id}/read', 'ProfileController@markNotificationRead');
    
    /**
     * PUT /api/profile/notifications/read-all
     * 
     * Đánh dấu tất cả thông báo đã đọc
     * 
     * Headers: Authorization: Bearer <token>
     */
    $router->put('/notifications/read-all', 'ProfileController@markAllNotificationsRead');
    
    /**
     * GET /api/profile/stats
     * 
     * Thống kê cá nhân
     * 
     * Headers: Authorization: Bearer <token>
     */
    $router->get('/stats', 'ProfileController@getStats');
});