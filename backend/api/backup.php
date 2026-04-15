<?php
/**
 * BACKUP & RESTORE ROUTES
 * Quản lý backup và restore - CHỈ ADMIN
 * 
 * @package API
 */

use Core\Router;

$router = new Router();

$router->group([
    'prefix' => '/backup',
    'middleware' => ['AuthMiddleware', 'PermissionMiddleware@adminOnly']
], function($router) {
    
    // Lấy danh sách backups
    $router->get('', 'BackupController@index');
    
    // Chi tiết backup
    $router->get('/{id}', 'BackupController@show');
    
    // Tạo backup mới
    $router->post('/create', 'BackupController@create');
    
    // Tải backup
    $router->get('/{id}/download', 'BackupController@download');
    
    // Khôi phục từ backup
    $router->post('/{id}/restore', 'BackupController@restore');
    
    // Xóa backup
    $router->delete('/{id}', 'BackupController@destroy');
    
    // Thông tin dung lượng backup
    $router->get('/storage/info', 'BackupController@storageInfo');
    
    // Cấu hình backup tự động
    $router->put('/config', 'BackupController@updateConfig');
    
    // Chạy backup tự động ngay lập tức
    $router->post('/run-auto', 'BackupController@runAutoBackup');
    
    // Xóa backups cũ (theo retention policy)
    $router->delete('/cleanup', 'BackupController@cleanup');
});
