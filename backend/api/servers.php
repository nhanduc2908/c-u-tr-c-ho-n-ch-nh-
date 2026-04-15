<?php
/**
 * SERVER MANAGEMENT ROUTES
 * Quản lý server - ADMIN và SECURITY_OFFICER được phép
 * 
 * @package API
 */

use Core\Router;

$router = new Router();

// Routes yêu cầu xác thực (cả 3 role đều xem được)
$router->group(['prefix' => '/servers', 'middleware' => ['AuthMiddleware']], function($router) {
    
    // Lấy danh sách server (tất cả role đều xem được)
    $router->get('', 'ServerController@index');
    
    // Chi tiết server
    $router->get('/{id}', 'ServerController@show');
    
    // Thống kê server
    $router->get('/stats/summary', 'ServerController@stats');
});

// Routes yêu cầu quyền ADMIN hoặc SECURITY_OFFICER
$router->group([
    'prefix' => '/servers',
    'middleware' => ['AuthMiddleware', 'PermissionMiddleware@officerOrAbove']
], function($router) {
    
    // Thêm server mới
    $router->post('', 'ServerController@store');
    
    // Cập nhật server
    $router->put('/{id}', 'ServerController@update');
    
    // Xóa server
    $router->delete('/{id}', 'ServerController@destroy');
    
    // Quét server (chạy assessment)
    $router->post('/{id}/scan', 'ServerController@scan');
    
    // Kiểm tra kết nối server
    $router->post('/{id}/test-connection', 'ServerController@testConnection');
    
    // Đồng bộ server
    $router->post('/sync', 'ServerController@sync');
});