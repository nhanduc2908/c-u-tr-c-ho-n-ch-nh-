<?php
/**
 * REPORTS ROUTES
 * Tạo và xuất báo cáo PDF/Excel
 * 
 * @package API
 */

use Core\Router;

$router = new Router();

$router->group(['prefix' => '/reports', 'middleware' => ['AuthMiddleware']], function($router) {
    
    // Lấy danh sách reports đã tạo
    $router->get('', 'ReportController@index');
    
    // Chi tiết report
    $router->get('/{id}', 'ReportController@show');
    
    // Tạo báo cáo mới (PDF)
    $router->post('/generate', 'ReportController@generate');
    
    // Tải báo cáo
    $router->get('/{id}/download', 'ReportController@download');
    
    // Xem báo cáo (preview)
    $router->get('/{id}/preview', 'ReportController@preview');
    
    // Xuất báo cáo Excel
    $router->post('/export-excel', 'ReportController@exportExcel');
    
    // Xuất báo cáo CSV
    $router->post('/export-csv', 'ReportController@exportCsv');
    
    // Gửi báo cáo qua email
    $router->post('/{id}/email', 'ReportController@sendEmail');
    
    // Lịch sử báo cáo
    $router->get('/history', 'ReportController@history');
});

// Routes yêu cầu quyền ADMIN hoặc SECURITY_OFFICER
$router->group([
    'prefix' => '/reports',
    'middleware' => ['AuthMiddleware', 'PermissionMiddleware@officerOrAbove']
], function($router) {
    
    // Tạo báo cáo tổng hợp nhiều server
    $router->post('/generate-multi', 'ReportController@generateMultiServer');
    
    // Tạo báo cáo compliance
    $router->post('/generate-compliance', 'ReportController@generateCompliance');
    
    // Lên lịch tạo báo cáo tự động
    $router->post('/schedule', 'ReportController@schedule');
});

// Routes yêu cầu quyền ADMIN
$router->group([
    'prefix' => '/reports',
    'middleware' => ['AuthMiddleware', 'PermissionMiddleware@adminOnly']
], function($router) {
    
    // Xóa report
    $router->delete('/{id}', 'ReportController@destroy');
    
    // Xóa tất cả reports cũ
    $router->delete('/cleanup', 'ReportController@cleanup');
    
    // Cấu hình mẫu báo cáo
    $router->put('/templates', 'ReportController@updateTemplates');
});