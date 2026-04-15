<?php
/**
 * CATEGORIES MANAGEMENT ROUTES (17 LĨNH VỰC)
 * Quản lý danh mục đánh giá - CHỈ ADMIN
 * 
 * @package API
 */

use Core\Router;

$router = new Router();

// Routes công khai (tất cả role đều xem được)
$router->group(['prefix' => '/categories', 'middleware' => ['AuthMiddleware']], function($router) {
    
    // Lấy danh sách categories (17 lĩnh vực)
    $router->get('', 'CategoryController@index');
    
    // Chi tiết category
    $router->get('/{id}', 'CategoryController@show');
    
    // Lấy criteria theo category
    $router->get('/{id}/criteria', 'CategoryController@getCriteria');
});

// Routes yêu cầu quyền ADMIN
$router->group([
    'prefix' => '/categories',
    'middleware' => ['AuthMiddleware', 'PermissionMiddleware@adminOnly']
], function($router) {
    
    // Thêm category mới
    $router->post('', 'CategoryController@store');
    
    // Cập nhật category
    $router->put('/{id}', 'CategoryController@update');
    
    // Xóa category
    $router->delete('/{id}', 'CategoryController@destroy');
    
    // Sắp xếp lại categories
    $router->post('/reorder', 'CategoryController@reorder');
});
