<?php
/**
 * PSR-4 Autoloader
 * Tự động nạp class khi được gọi mà không cần require thủ công
 * 
 * @package Core
 */

namespace Core;

class Autoloader
{
    /**
     * Đăng ký autoloader vào SPL stack
     */
    public static function register()
    {
        spl_autoload_register([__CLASS__, 'load']);
    }
    
    /**
     * Load class dựa trên namespace
     * 
     * @param string $className Tên class đầy đủ namespace
     * @return bool
     */
    public static function load($className)
    {
        // Base directory cho các namespace
        $baseDir = __DIR__ . '/../';
        
        // Loại bỏ namespace gốc
        $className = ltrim($className, '\\');
        
        // Chuyển namespace thành đường dẫn
        $file = $baseDir . str_replace('\\', '/', $className) . '.php';
        
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
        
        return false;
    }
}

// Tự động đăng ký khi file được include
Autoloader::register();