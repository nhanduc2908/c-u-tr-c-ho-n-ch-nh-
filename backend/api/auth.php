<?php
/**
 * AUTHENTICATION API ROUTES
 * 
 * Các endpoint xác thực người dùng:
 * - Đăng nhập
 * - Đăng xuất  
 * - Refresh token
 * - Lấy thông tin user hiện tại
 * - Đổi mật khẩu
 * - Quên mật khẩu
 * - Đặt lại mật khẩu
 * 
 * @package API
 * @version 1.0
 */

use Core\Router;

$router = new Router();

// ============================================
// PUBLIC ROUTES (Không cần xác thực)
// ============================================

/**
 * POST /api/auth/login
 * 
 * Đăng nhập hệ thống
 * 
 * Request body:
 * {
 *     "username": "admin",
 *     "password": "123456"
 * }
 * 
 * Response:
 * {
 *     "success": true,
 *     "message": "Login successful",
 *     "data": {
 *         "token": "eyJhbGciOiJIUzI1NiIs...",
 *         "user": {
 *             "id": 1,
 *             "username": "admin",
 *             "email": "admin@security.com",
 *             "full_name": "Administrator",
 *             "role": "admin"
 *         }
 *     }
 * }
 */
$router->post('/auth/login', 'AuthController@login');

/**
 * POST /api/auth/forgot-password
 * 
 * Gửi email đặt lại mật khẩu
 * 
 * Request body:
 * {
 *     "email": "user@example.com"
 * }
 */
$router->post('/auth/forgot-password', 'AuthController@forgotPassword');

/**
 * POST /api/auth/reset-password
 * 
 * Đặt lại mật khẩu với token
 * 
 * Request body:
 * {
 *     "token": "reset_token_here",
 *     "password": "new_password",
 *     "password_confirmation": "new_password"
 * }
 */
$router->post('/auth/reset-password', 'AuthController@resetPassword');

/**
 * POST /api/auth/verify-email
 * 
 * Xác thực email
 * 
 * Request body:
 * {
 *     "token": "verification_token_here"
 * }
 */
$router->post('/auth/verify-email', 'AuthController@verifyEmail');

/**
 * POST /api/auth/resend-verification
 * 
 * Gửi lại email xác thực
 * 
 * Request body:
 * {
 *     "email": "user@example.com"
 * }
 */
$router->post('/auth/resend-verification', 'AuthController@resendVerification');


// ============================================
// PROTECTED ROUTES (Yêu cầu xác thực)
// ============================================

$router->group(['prefix' => '/auth', 'middleware' => ['AuthMiddleware']], function($router) {
    
    /**
     * POST /api/auth/logout
     * 
     * Đăng xuất (xóa token)
     * 
     * Headers:
     * Authorization: Bearer <token>
     * 
     * Response:
     * {
     *     "success": true,
     *     "message": "Logout successful"
     * }
     */
    $router->post('/logout', 'AuthController@logout');
    
    /**
     * POST /api/auth/refresh
     * 
     * Refresh token (lấy token mới)
     * 
     * Headers:
     * Authorization: Bearer <old_token>
     * 
     * Response:
     * {
     *     "success": true,
     *     "data": {
     *         "token": "new_token_here"
     *     }
     * }
     */
    $router->post('/refresh', 'AuthController@refresh');
    
    /**
     * GET /api/auth/me
     * 
     * Lấy thông tin user hiện tại
     * 
     * Headers:
     * Authorization: Bearer <token>
     * 
     * Response:
     * {
     *     "success": true,
     *     "data": {
     *         "id": 1,
     *         "username": "admin",
     *         "email": "admin@security.com",
     *         "full_name": "Administrator",
     *         "role": "admin",
     *         "role_id": 1,
     *         "is_active": true,
     *         "last_login": "2024-01-15 10:30:00",
     *         "created_at": "2024-01-01 00:00:00"
     *     }
     * }
     */
    $router->get('/me', 'AuthController@me');
    
    /**
     * PUT /api/auth/change-password
     * 
     * Đổi mật khẩu
     * 
     * Headers:
     * Authorization: Bearer <token>
     * 
     * Request body:
     * {
     *     "current_password": "old_password",
     *     "new_password": "new_password",
     *     "new_password_confirmation": "new_password"
     * }
     */
    $router->put('/change-password', 'AuthController@changePassword');
    
    /**
     * PUT /api/auth/update-profile
     * 
     * Cập nhật thông tin cá nhân
     * 
     * Headers:
     * Authorization: Bearer <token>
     * 
     * Request body:
     * {
     *     "full_name": "Nguyen Van A",
     *     "email": "newemail@example.com"
     * }
     */
    $router->put('/update-profile', 'AuthController@updateProfile');
    
    /**
     * POST /api/auth/upload-avatar
     * 
     * Upload avatar
     * 
     * Headers:
     * Authorization: Bearer <token>
     * 
     * Request: multipart/form-data
     * - file: avatar image
     */
    $router->post('/upload-avatar', 'AuthController@uploadAvatar');
    
    /**
     * GET /api/auth/permissions
     * 
     * Lấy danh sách permissions của user hiện tại
     * 
     * Headers:
     * Authorization: Bearer <token>
     * 
     * Response:
     * {
     *     "success": true,
     *     "data": {
     *         "role": "admin",
     *         "permissions": ["*"],
     *         "menu": [...]
     *     }
     * }
     */
    $router->get('/permissions', 'AuthController@getPermissions');
    
    /**
     * POST /api/auth/two-factor/enable
     * 
     * Bật xác thực 2 lớp
     * 
     * Headers:
     * Authorization: Bearer <token>
     */
    $router->post('/two-factor/enable', 'AuthController@enableTwoFactor');
    
    /**
     * POST /api/auth/two-factor/verify
     * 
     * Xác thực mã 2FA
     * 
     * Headers:
     * Authorization: Bearer <token>
     * 
     * Request body:
     * {
     *     "code": "123456"
     * }
     */
    $router->post('/two-factor/verify', 'AuthController@verifyTwoFactor');
    
    /**
     * POST /api/auth/two-factor/disable
     * 
     * Tắt xác thực 2 lớp
     * 
     * Headers:
     * Authorization: Bearer <token>
     */
    $router->post('/two-factor/disable', 'AuthController@disableTwoFactor');
    
    /**
     * GET /api/auth/sessions
     * 
     * Lấy danh sách các phiên đăng nhập
     * 
     * Headers:
     * Authorization: Bearer <token>
     */
    $router->get('/sessions', 'AuthController@getSessions');
    
    /**
     * DELETE /api/auth/sessions/{id}
     * 
     * Xóa một phiên đăng nhập (đăng xuất từ xa)
     * 
     * Headers:
     * Authorization: Bearer <token>
     */
    $router->delete('/sessions/{id}', 'AuthController@revokeSession');
    
    /**
     * DELETE /api/auth/sessions
     * 
     * Xóa tất cả phiên đăng nhập khác (đăng xuất tất cả thiết bị khác)
     * 
     * Headers:
     * Authorization: Bearer <token>
     */
    $router->delete('/sessions', 'AuthController@revokeAllSessions');
});