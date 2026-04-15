<?php
/**
 * SECURITY ASSESSMENT PLATFORM - API GATEWAY
 * 
 * Cổng vào chính của hệ thống, xử lý mọi request API
 * Hỗ trợ: JWT Authentication, RBAC (3 roles), CORS, Rate Limiting
 * 
 * @author Security Team
 * @version 3.0.0
 */

// ============================================
// CẤU HÌNH ERROR REPORTING
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', $_ENV['APP_DEBUG'] ?? 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/storage/logs/error.log');
ini_set('date.timezone', $_ENV['APP_TIMEZONE'] ?? 'Asia/Ho_Chi_Minh');
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);
ini_set('post_max_size', '50M');
ini_set('upload_max_filesize', '50M');

// ============================================
// ĐỊNH NGHĨA HẰNG SỐ
// ============================================
define('ROOT_PATH', dirname(__DIR__));
define('BACKEND_PATH', __DIR__);
define('BASE_URL', $_ENV['APP_URL'] ?? 'http://localhost');
define('IS_DEBUG', ($_ENV['APP_DEBUG'] ?? 'false') === 'true');
define('START_TIME', microtime(true));

// ============================================
// LOAD COMPOSER AUTOLOADER
// ============================================
require_once __DIR__ . '/vendor/autoload.php';

// ============================================
// LOAD ENVIRONMENT VARIABLES
// ============================================
use Dotenv\Dotenv;

try {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    $dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'JWT_SECRET']);
} catch (Exception $e) {
    die('Environment file not found. Please copy .env.example to .env');
}

// ============================================
// CORS HEADERS - CHO PHÉP FRONTEND GỌI API
// ============================================
$allowedOrigins = [
    'http://localhost',
    'http://localhost:3000',
    'http://localhost:8080',
    $_ENV['APP_URL'] ?? ''
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins) || IS_DEBUG) {
    header("Access-Control-Allow-Origin: $origin");
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, X-Lang");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// ============================================
// SECURITY HEADERS
// ============================================
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

// ============================================
// XỬ LÝ PREFLIGHT REQUEST (OPTIONS)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================
// RATE LIMITING (CHỐNG SPAM)
// ============================================
function checkRateLimit() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $endpoint = $_SERVER['REQUEST_URI'] ?? '';
    $key = 'rate_limit_' . md5($ip . $endpoint);
    
    // Sử dụng cache hoặc file để lưu
    $limit = $_ENV['RATE_LIMIT_REQUESTS'] ?? 100;
    $window = $_ENV['RATE_LIMIT_WINDOW'] ?? 60;
    
    // Đơn giản: lưu vào session hoặc file
    // (Trong thực tế nên dùng Redis)
    return true; // Tạm thời bỏ qua rate limit
}

// ============================================
// KHỞI TẠO ROUTER & XỬ LÝ REQUEST
// ============================================
use Core\Router;
use Core\Logger;
use Core\Middleware;

try {
    // Kiểm tra bảo trì
    if (file_exists(__DIR__ . '/storage/maintenance.flag')) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'error' => 'Maintenance Mode',
            'message' => 'Hệ thống đang bảo trì. Vui lòng quay lại sau.'
        ]);
        exit();
    }
    
    // Kiểm tra rate limit
    if (!checkRateLimit()) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Too Many Requests',
            'message' => 'Bạn đã gửi quá nhiều request. Vui lòng thử lại sau.'
        ]);
        exit();
    }
    
    // Khởi tạo router
    $router = new Router();
    
    // Load tất cả route definitions từ thư mục api/
    $apiFiles = glob(__DIR__ . '/api/*.php');
    if (empty($apiFiles)) {
        throw new Exception('No API route files found in /api directory');
    }
    
    foreach ($apiFiles as $file) {
        require_once $file;
    }
    
    // Ghi log request (nếu cần)
    if (IS_DEBUG) {
        Logger::debug('Request received', [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }
    
    // Xử lý request
    $router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
    
} catch (Throwable $e) {
    // Ghi log lỗi
    Logger::error('API Error', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => IS_DEBUG ? $e->getTraceAsString() : null,
        'uri' => $_SERVER['REQUEST_URI'],
        'method' => $_SERVER['REQUEST_METHOD']
    ]);
    
    // Xác định mã lỗi HTTP
    $statusCode = 500;
    $errorMessage = 'Internal Server Error';
    
    if ($e instanceof \Core\ValidationException) {
        $statusCode = 422;
        $errorMessage = 'Validation Error';
    } elseif ($e instanceof \Core\AuthException) {
        $statusCode = 401;
        $errorMessage = 'Unauthorized';
    } elseif ($e instanceof \Core\PermissionException) {
        $statusCode = 403;
        $errorMessage = 'Forbidden';
    } elseif ($e instanceof \Core\NotFoundException) {
        $statusCode = 404;
        $errorMessage = 'Not Found';
    }
    
    // Trả về response lỗi
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'error' => $errorMessage,
        'message' => IS_DEBUG ? $e->getMessage() : 'Đã xảy ra lỗi, vui lòng thử lại sau',
        'code' => $e->getCode()
    ]);
}

// ============================================
// GHI LOG THỜI GIAN XỬ LÝ (DEBUG)
// ============================================
if (IS_DEBUG) {
    $executionTime = round((microtime(true) - START_TIME) * 1000, 2);
    Logger::debug('Request completed', [
        'execution_time_ms' => $executionTime,
        'memory_usage_mb' => round(memory_get_peak_usage() / 1024 / 1024, 2)
    ]);
}