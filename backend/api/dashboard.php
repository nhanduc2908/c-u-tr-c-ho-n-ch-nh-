<?php
/**
 * DASHBOARD ROUTES
 * Lấy dữ liệu thống kê cho dashboard
 * Tất cả role đều xem được (nhưng dữ liệu có thể khác nhau)
 * 
 * @package API
 */

use Core\Router;

$router = new Router();

$router->group(['prefix' => '/dashboard', 'middleware' => ['AuthMiddleware']], function($router) {
    
    // Tổng quan stats (số server, criteria, alerts, score)
    $router->get('/stats', 'DashboardController@stats');
    
    // Dữ liệu cho biểu đồ (vulnerabilities by severity)
    $router->get('/charts', 'DashboardController@charts');
    
    // Xu hướng điểm bảo mật theo thời gian
    $router->get('/trends', 'DashboardController@trends');
    
    // Top 5 server có điểm thấp nhất
    $router->get('/lowest-score', 'DashboardController@lowestScore');
    
    // Top 5 alerts mới nhất
    $router->get('/recent-alerts', 'DashboardController@recentAlerts');
    
    // Hoạt động gần đây (audit log)
    $router->get('/recent-activities', 'DashboardController@recentActivities');
    
    // Thống kê theo category (17 lĩnh vực)
    $router->get('/category-stats', 'DashboardController@categoryStats');
    
    // Compliance summary (ISO 27001, NIST)
    $router->get('/compliance', 'DashboardController@complianceSummary');
    
    // Export dashboard data ra Excel
    $router->get('/export', 'DashboardController@export');
});
