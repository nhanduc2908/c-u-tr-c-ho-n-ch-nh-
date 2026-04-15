<?php
/**
 * CRITERIA MANAGEMENT ROUTES (280 TIÊU CHÍ)
 * Quản lý 280 tiêu chí đánh giá bảo mật
 * 
 * @package API
 */

use Core\Router;

$router = new Router();

// Routes công khai (tất cả role đều xem được)
$router->group(['prefix' => '/criteria', 'middleware' => ['AuthMiddleware']], function($router) {
    
    // Lấy danh sách criteria (có phân trang, filter theo category, severity)
    $router->get('', 'CriteriaController@index');
    
    // Chi tiết 1 criteria
    $router->get('/{id}', 'CriteriaController@show');
    
    // Lấy criteria theo category
    $router->get('/category/{categoryId}', 'CriteriaController@getByCategory');
    
    // Lấy criteria theo severity
    $router->get('/severity/{severity}', 'CriteriaController@getBySeverity');
    
    // Thống kê số lượng criteria
    $router->get('/stats/summary', 'CriteriaController@stats');
});

// Routes yêu cầu quyền ADMIN hoặc SECURITY_OFFICER (xem + chỉnh sửa cơ bản)
$router->group([
    'prefix' => '/criteria',
    'middleware' => ['AuthMiddleware', 'PermissionMiddleware@officerOrAbove']
], function($router) {
    
    // Cập nhật một số field (weight, severity, is_active)
    $router->patch('/{id}', 'CriteriaController@partialUpdate');
    
    // Bulk update (cập nhật nhiều criteria cùng lúc)
    $router->post('/bulk-update', 'CriteriaController@bulkUpdate');
});

// Routes yêu cầu quyền ADMIN (full quyền)
$router->group([
    'prefix' => '/criteria',
    'middleware' => ['AuthMiddleware', 'PermissionMiddleware@adminOnly']
], function($router) {
    
    // Thêm criteria mới
    $router->post('', 'CriteriaController@store');
    
    // Cập nhật criteria
    $router->put('/{id}', 'CriteriaController@update');
    
    // Xóa criteria
    $router->delete('/{id}', 'CriteriaController@destroy');
    
    // Import criteria từ Excel
    $router->post('/import', 'CriteriaController@import');
    
    // Export criteria ra Excel
    $router->get('/export', 'CriteriaController@export');
    
    // Import từ JSON
    $router->post('/import-json', 'CriteriaController@importJson');
    
    // Export ra JSON
    $router->get('/export-json', 'CriteriaController@exportJson');
    
    // Clone criteria
    $router->post('/{id}/clone', 'CriteriaController@clone');
    
    // Active/Inactive nhiều criteria
    $router->post('/bulk-status', 'CriteriaController@bulkStatus');
});