<?php
/**
 * ASSESSMENT ROUTES
 * Đánh giá server, chấm điểm, lưu kết quả
 * 
 * @package API
 */

use Core\Router;

$router = new Router();

// Routes yêu cầu xác thực
$router->group(['prefix' => '/assessments', 'middleware' => ['AuthMiddleware']], function($router) {
    
    // Lấy danh sách assessment reports
    $router->get('', 'AssessmentController@index');
    
    // Chi tiết assessment report
    $router->get('/{id}', 'AssessmentController@show');
    
    // Kết quả assessment của một server
    $router->get('/server/{serverId}', 'AssessmentController@getByServer');
    
    // Chi tiết kết quả từng criteria
    $router->get('/{reportId}/details', 'AssessmentController@getDetails');
});

// Routes yêu cầu quyền ADMIN hoặc SECURITY_OFFICER (chạy assessment)
$router->group([
    'prefix' => '/assessments',
    'middleware' => ['AuthMiddleware', 'PermissionMiddleware@officerOrAbove']
], function($router) {
    
    // Chạy assessment mới (quét server)
    $router->post('/run', 'AssessmentController@run');
    
    // Chạy assessment cho một server
    $router->post('/server/{serverId}/run', 'AssessmentController@runForServer');
    
    // Đánh giá thủ công (cập nhật kết quả)
    $router->put('/{resultId}/manual', 'AssessmentController@manualUpdate');
    
    // Upload bằng chứng cho assessment
    $router->post('/{resultId}/evidence', 'AssessmentController@uploadEvidence');
    
    // Phê duyệt assessment
    $router->post('/{reportId}/approve', 'AssessmentController@approve');
    
    // Hủy assessment đang chạy
    $router->post('/{reportId}/cancel', 'AssessmentController@cancel');
    
    // So sánh kết quả giữa 2 lần assessment
    $router->get('/compare/{reportId1}/{reportId2}', 'AssessmentController@compare');
});

// Routes yêu cầu quyền ADMIN (xóa, cấu hình)
$router->group([
    'prefix' => '/assessments',
    'middleware' => ['AuthMiddleware', 'PermissionMiddleware@adminOnly']
], function($router) {
    
    // Xóa assessment report
    $router->delete('/{id}', 'AssessmentController@destroy');
    
    // Cấu hình assessment (weight, threshold)
    $router->put('/config', 'AssessmentController@updateConfig');
    
    // Xóa tất cả kết quả cũ
    $router->delete('/cleanup', 'AssessmentController@cleanup');
});
