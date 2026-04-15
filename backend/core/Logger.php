<?php
/**
 * Logger - Ghi log hệ thống
 * Hỗ trợ ghi log ra file và database
 * 
 * @package Core
 */

namespace Core;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

class Logger
{
    /**
     * @var Logger|null Singleton instance
     */
    private static $instance = null;
    
    /**
     * @var MonologLogger Monolog instance
     */
    private $logger;
    
    /**
     * Private constructor
     */
    private function __construct()
    {
        $logPath = __DIR__ . '/../storage/logs/app.log';
        $logLevel = $this->getLogLevel();
        
        $this->logger = new MonologLogger('security-platform');
        
        // Rotating file handler (tự động xoay vòng file log)
        $handler = new RotatingFileHandler($logPath, 30, $logLevel);
        $this->logger->pushHandler($handler);
    }
    
    /**
     * Get singleton instance
     * 
     * @return Logger
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Logger();
        }
        return self::$instance;
    }
    
    /**
     * Get log level from environment
     * 
     * @return int
     */
    private function getLogLevel()
    {
        $level = $_ENV['LOG_LEVEL'] ?? 'debug';
        
        $levels = [
            'debug' => MonologLogger::DEBUG,
            'info' => MonologLogger::INFO,
            'notice' => MonologLogger::NOTICE,
            'warning' => MonologLogger::WARNING,
            'error' => MonologLogger::ERROR,
            'critical' => MonologLogger::CRITICAL,
            'alert' => MonologLogger::ALERT,
            'emergency' => MonologLogger::EMERGENCY,
        ];
        
        return $levels[$level] ?? MonologLogger::DEBUG;
    }
    
    /**
     * Log debug message
     * 
     * @param string $message
     * @param array $context
     */
    public static function debug($message, $context = [])
    {
        self::getInstance()->logger->debug($message, $context);
    }
    
    /**
     * Log info message
     * 
     * @param string $message
     * @param array $context
     */
    public static function info($message, $context = [])
    {
        self::getInstance()->logger->info($message, $context);
    }
    
    /**
     * Log notice message
     * 
     * @param string $message
     * @param array $context
     */
    public static function notice($message, $context = [])
    {
        self::getInstance()->logger->notice($message, $context);
    }
    
    /**
     * Log warning message
     * 
     * @param string $message
     * @param array $context
     */
    public static function warning($message, $context = [])
    {
        self::getInstance()->logger->warning($message, $context);
    }
    
    /**
     * Log error message
     * 
     * @param string $message
     * @param array $context
     */
    public static function error($message, $context = [])
    {
        self::getInstance()->logger->error($message, $context);
    }
    
    /**
     * Log critical message
     * 
     * @param string $message
     * @param array $context
     */
    public static function critical($message, $context = [])
    {
        self::getInstance()->logger->critical($message, $context);
    }
    
    /**
     * Log authentication event
     * 
     * @param string $username
     * @param string $status (success/failed)
     * @param string $ip
     */
    public static function auth($username, $status, $ip)
    {
        self::info("Authentication {$status}", [
            'username' => $username,
            'ip' => $ip,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Log scan event
     * 
     * @param int $serverId
     * @param string $status
     * @param array $result
     */
    public static function scan($serverId, $status, $result = [])
    {
        self::info("Scan {$status}", [
            'server_id' => $serverId,
            'result' => $result,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Log attack detection
     * 
     * @param string $ip
     * @param string $attackType
     * @param array $details
     */
    public static function attack($ip, $attackType, $details = [])
    {
        self::warning("Attack detected", [
            'ip' => $ip,
            'type' => $attackType,
            'details' => $details,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}