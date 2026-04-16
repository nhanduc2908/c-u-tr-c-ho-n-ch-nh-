<?php
/**
 * RATE LIMIT MIDDLEWARE
 * 
 * Giới hạn số lượng request trong một khoảng thời gian
 * Chống spam và DoS attack
 * 
 * @package Middleware
 */

namespace Middleware;

use Core\Database;

class RateLimitMiddleware
{
    /**
     * Xử lý rate limiting
     * 
     * @param int $maxRequests Số request tối đa
     * @param int $windowSeconds Thời gian window (giây)
     * @return bool
     */
    public static function handle($maxRequests = null, $windowSeconds = null)
    {
        $maxRequests = $maxRequests ?? ($_ENV['RATE_LIMIT_REQUESTS'] ?? 100);
        $windowSeconds = $windowSeconds ?? ($_ENV['RATE_LIMIT_WINDOW'] ?? 60);
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $endpoint = $_SERVER['REQUEST_URI'] ?? '/';
        $key = md5($ip . $endpoint);
        
        $db = Database::getInstance();
        
        // Tạo bảng rate_limits nếu chưa có
        $db->query("CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            `key` VARCHAR(100) NOT NULL,
            requests INT DEFAULT 1,
            first_request TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_request TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_key (`key`)
        )");
        
        // Kiểm tra tồn tại
        $record = $db->fetchOne("SELECT * FROM rate_limits WHERE `key` = ?", [$key]);
        
        if (!$record) {
            // Tạo mới
            $db->insert('rate_limits', [
                'key' => $key,
                'requests' => 1,
                'first_request' => date('Y-m-d H:i:s'),
                'last_request' => date('Y-m-d H:i:s')
            ]);
            return true;
        }
        
        // Kiểm tra window
        $firstRequest = strtotime($record['first_request']);
        $now = time();
        
        if ($now - $firstRequest > $windowSeconds) {
            // Reset window
            $db->update('rate_limits', [
                'requests' => 1,
                'first_request' => date('Y-m-d H:i:s'),
                'last_request' => date('Y-m-d H:i:s')
            ], '`key` = ?', [$key]);
            return true;
        }
        
        // Tăng số request
        $newCount = $record['requests'] + 1;
        
        if ($newCount > $maxRequests) {
            $resetTime = $firstRequest + $windowSeconds - $now;
            self::tooManyRequests($resetTime);
        }
        
        $db->update('rate_limits', [
            'requests' => $newCount,
            'last_request' => date('Y-m-d H:i:s')
        ], '`key` = ?', [$key]);
        
        // Thêm headers
        header('X-RateLimit-Limit: ' . $maxRequests);
        header('X-RateLimit-Remaining: ' . ($maxRequests - $newCount));
        header('X-RateLimit-Reset: ' . ($firstRequest + $windowSeconds));
        
        return true;
    }
    
    /**
     * Trả về lỗi 429 Too Many Requests
     */
    private static function tooManyRequests($retryAfter)
    {
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: ' . $retryAfter);
        echo json_encode([
            'success' => false,
            'error' => 'Too Many Requests',
            'message' => "Rate limit exceeded. Please try again after {$retryAfter} seconds.",
            'retry_after' => $retryAfter
        ]);
        exit;
    }
    
    /**
     * Dọn dẹp rate limits cũ
     */
    public static function cleanup()
    {
        $db = Database::getInstance();
        $db->delete('rate_limits', 'first_request < DATE_SUB(NOW(), INTERVAL 1 HOUR)');
    }
}