<?php
/**
 * ALERTS MANAGEMENT ROUTES
 * Quản lý cảnh báo an ninh
 * 
 * @package API
 */

use Core\Router;

$router = new Router();

// Routes yêu cầu xác thực (tất cả role đều xem được)
$router->group(['prefix' => '/alerts', 'middleware' => ['AuthMiddleware']], function($router) {
    
    // Lấy danh sách alerts (có phân trang, filter)
    $router->get('', 'AlertController@index');
    
    // Chi tiết alert
    $router->get('/{id}', 'AlertController@show');
    
    // Thống kê alerts theo severity
    $router->get('/stats/summary', 'AlertController@stats');
    
    // Real-time alerts (polling)
    $router->get('/realtime', 'AlertController@realtime');
});

// Routes yêu cầu quyền ADMIN hoặc SECURITY_OFFICER (xử lý alerts)
$router->group([
    'prefix' => '/alerts',
    'middleware' => ['AuthMiddleware', 'PermissionMiddleware@officerOrAbove']
], function($router) {
    
    // Xác nhận đã thấy alert
    $router->put('/{id}/acknowledge', 'AlertController@acknowledge');
    
    // Đánh dấu đã giải quyết
    $router->put('/{id}/resolve', 'AlertController@resolve');
    
    // Thêm ghi chú cho alert
    $router->post('/{id}/note', 'AlertController@addNote');
    
    // Gán alert cho người xử lý
    $router->put('/{id}/assign', 'AlertController@assign');
    
    // Bulk acknowledge
    $router->post('/bulk-acknowledge', 'AlertController@bulkAcknowledge');
    
    // Bulk resolve
    $router->post('/bulk-resolve', 'AlertController@bulkResolve');
});

// Routes yêu cầu quyền ADMIN (xóa, cấu hình)
$router->group([
    'prefix' => '/alerts',
    'middleware' => ['AuthMiddleware', 'PermissionMiddleware@adminOnly']
], function($router) {
    
    // Xóa alert
    $router->delete('/{id}', 'AlertController@destroy');
    
    // Xóa tất cả alerts cũ
    $router->delete('/cleanup', 'AlertController@cleanup');
    
    // Cấu hình alert rules
    $router->put('/rules', 'AlertController@updateRules');
});
