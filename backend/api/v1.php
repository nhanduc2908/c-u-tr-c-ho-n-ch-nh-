<?php
/**
 * API VERSION 1 - ROUTES
 * 
 * Tổng hợp tất cả routes của API version 1
 * 
 * @package API
 */

use Core\Router;

$router = new Router();

// ============================================
// API Version 1 - Prefix /v1
// ============================================
$router->group(['prefix' => '/v1'], function($router) {
    
    // Auth routes
    require_once __DIR__ . '/auth.php';
    
    // User management
    require_once __DIR__ . '/users.php';
    
    // Role management
    require_once __DIR__ . '/roles.php';
    
    // Server management
    require_once __DIR__ . '/servers.php';
    
    // Categories (17 domains)
    require_once __DIR__ . '/categories.php';
    
    // Criteria (280 criteria)
    require_once __DIR__ . '/criteria.php';
    
    // Assessments
    require_once __DIR__ . '/assessments.php';
    
    // Dashboard
    require_once __DIR__ . '/dashboard.php';
    
    // Vulnerabilities
    require_once __DIR__ . '/vulnerabilities.php';
    
    // Alerts
    require_once __DIR__ . '/alerts.php';
    
    // Reports
    require_once __DIR__ . '/reports.php';
    
    // Backup
    require_once __DIR__ . '/backup.php';
    
    // Profile
    require_once __DIR__ . '/profile.php';
    
    // Audit
    require_once __DIR__ . '/audit.php';
    
    // Settings
    require_once __DIR__ . '/settings.php';
    
    // Health check
    require_once __DIR__ . '/health.php';
    
    // Notifications
    require_once __DIR__ . '/notifications.php';
    
    // Search
    require_once __DIR__ . '/search.php';
    
    // Export
    require_once __DIR__ . '/export.php';
    
    // Import
    require_once __DIR__ . '/import.php';
    
    // Stats
    require_once __DIR__ . '/stats.php';
    
    // Compliance
    require_once __DIR__ . '/compliance.php';
    
    // Schedules
    require_once __DIR__ . '/schedules.php';
    
    // Queue
    require_once __DIR__ . '/queue.php';
    
    // Cache
    require_once __DIR__ . '/cache.php';
    
    // Logs
    require_once __DIR__ . '/logs.php';
});