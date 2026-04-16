<?php
/**
 * SECURITY HEADERS MIDDLEWARE
 * 
 * Thêm các header bảo mật vào response
 * Bảo vệ chống XSS, clickjacking, MIME sniffing, v.v.
 * 
 * @package Middleware
 */

namespace Middleware;

class SecurityHeadersMiddleware
{
    /**
     * Thêm security headers
     */
    public static function handle()
    {
        // Content Security Policy (CSP)
        $csp = "default-src 'self'; "
              . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; "
              . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
              . "font-src 'self' https://cdn.jsdelivr.net; "
              . "img-src 'self' data: https:; "
              . "connect-src 'self' ws: wss:;";
        
        if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
            header("Content-Security-Policy: {$csp}");
        }
        
        // X-Content-Type-Options: ngăn MIME sniffing
        header("X-Content-Type-Options: nosniff");
        
        // X-Frame-Options: ngăn clickjacking
        header("X-Frame-Options: DENY");
        
        // X-XSS-Protection: bảo vệ XSS (cũ)
        header("X-XSS-Protection: 1; mode=block");
        
        // Referrer-Policy
        header("Referrer-Policy: strict-origin-when-cross-origin");
        
        // Permissions-Policy: hạn chế tính năng trình duyệt
        header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()");
        
        // Strict-Transport-Security (HSTS) - chỉ trên HTTPS
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
        }
        
        // Cache-Control cho API
        header("Cache-Control: no-store, no-cache, must-revalidate, private");
        header("Pragma: no-cache");
        
        // X-Powered-By: ẩn thông tin server
        header_remove('X-Powered-By');
        
        // Server: ẩn thông tin server
        header_remove('Server');
        
        return true;
    }
    
    /**
     * Thêm header cho file upload
     */
    public static function uploadHeaders()
    {
        header("X-Content-Type-Options: nosniff");
        return true;
    }
    
    /**
     * Kiểm tra HTTPS (chuyển hướng nếu cần)
     */
    public static function enforceHttps()
    {
        if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
            if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
                $redirectUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                header("Location: {$redirectUrl}", true, 301);
                exit();
            }
        }
        return true;
    }
}
