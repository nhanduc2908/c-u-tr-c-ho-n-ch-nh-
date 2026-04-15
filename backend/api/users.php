<?php
/**
 * USER MANAGEMENT ROUTES
 * CRUD người dùng - CHỈ ADMIN mới được truy cập
 * 
 * @package API
 */

use Core\Router;

$router = new Router();

// Tất cả routes trong group này đều yêu cầu quyền ADMIN
$router->group([
    'prefix' => '/users', 
    'middleware' => ['AuthMiddleware', 'PermissionMiddleware@adminOnly']
], function($router) {
    
    // Lấy danh sách users (có phân trang, filter)
    $router->get('', 'UserController@index');
    
    // Lấy chi tiết 1 user
    $router->get('/{id}', 'UserController@show');
    
    // Tạo user mới
    $router->post('', 'UserController@store');
    
    // Cập nhật user
    $router->put('/{id}', 'UserController@update');
    
    // Xóa user
    $router->delete('/{id}', 'UserController@destroy');
    
    // Reset mật khẩu user
    $router->post('/{id}/reset-password', 'UserController@resetPassword');
    
    // Khóa/Mở khóa user
    $router->put('/{id}/toggle-status', 'UserController@toggleStatus');
});