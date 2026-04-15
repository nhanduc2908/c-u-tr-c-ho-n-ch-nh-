<?php
/**
 * ROLE & PERMISSION MANAGEMENT ROUTES
 * Quản lý vai trò và quyền - CHỈ ADMIN
 * 
 * @package API
 */

use Core\Router;

$router = new Router();

$router->group([
    'prefix' => '/roles',
    'middleware' => ['AuthMiddleware', 'PermissionMiddleware@adminOnly']
], function($router) {
    
    // Danh sách roles
    $router->get('', 'RoleController@index');
    
    // Chi tiết role
    $router->get('/{id}', 'RoleController@show');
    
    // Tạo role mới
    $router->post('', 'RoleController@store');
    
    // Cập nhật role
    $router->put('/{id}', 'RoleController@update');
    
    // Xóa role
    $router->delete('/{id}', 'RoleController@destroy');
    
    // Danh sách permissions
    $router->get('/permissions', 'RoleController@permissions');
    
    // Gán permission cho role
    $router->post('/{id}/permissions', 'RoleController@assignPermissions');
});