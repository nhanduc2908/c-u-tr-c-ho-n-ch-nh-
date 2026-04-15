<?php
/**
 * AUTHENTICATION ROUTES
 * Đăng nhập, đăng xuất, refresh token, lấy thông tin user
 * 
 * @package API
 */

use Core\Router;

$router = new Router();

// Public routes (không cần xác thực)
$router->post('/auth/login', 'AuthController@login');

// Protected routes (cần xác thực)
$router->group(['prefix' => '/auth', 'middleware' => ['AuthMiddleware']], function($router) {
    $router->post('/logout', 'AuthController@logout');
    $router->post('/refresh', 'AuthController@refresh');
    $router->get('/me', 'AuthController@me');
    $router->put('/change-password', 'AuthController@changePassword');
});