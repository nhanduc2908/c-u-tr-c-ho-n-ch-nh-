<?php
/**
 * ATTACK DETECTOR SERVICE
 * 
 * Phát hiện các cuộc tấn công an ninh mạng
 * - SQL Injection
 * - XSS (Cross-Site Scripting)
 * - Path Traversal
 * - Command Injection
 * - Brute Force
 * 
 * @package Services
 */

namespace Services;

use Core\Database;
use Core\Logger;
use Core\Cache;

class AttackDetectorService
{
    private $db;
    private $cache;
    private $realtime;
    
    // Các pattern phát hiện tấn công
    private $attackPatterns = [
        'sql_injection' => [
            'pattern' => '/(\b(select|insert|update|delete|drop|union|alter|create|truncate)\b.*\b(from|into|set|where)\b)|(\b(and|or)\s+\d+\s*=\s*\d+\s*--)|(\b(and|or)\s+.*?\s*=\s*.*?\s*--)|(\'(\s|.)*?\')/i',
            'severity' => 'critical',
            'description' => 'SQL Injection attack detected'
        ],
        'xss' => [
            'pattern' => '/<script.*?>.*?<\/script>|<img.*?onerror=|<svg.*?onload=|<iframe.*?src=|javascript:|on\w+\s*=\s*["\'][^"\']*["\']/i',
            'severity' => 'high',
            'description' => 'XSS (Cross-Site Scripting) attack detected'
        ],
        'path_traversal' => [
            'pattern' => '/\.\.\/|\.\.\\\\|\.\.%2f|\.\.%5c|%2e%2e%2f|%2e%2e%5c/i',
            'severity' => 'high',
            'description' => 'Path Traversal attack detected'
        ],
        'command_injection' => [
            'pattern' => '/;.*\||\|\|.*;|`.*`|\$\(.*\)|\|\s*\w+\s*\||\&\&\s*\w+\s*&&/i',
            'severity' => 'critical',
            'description' => 'Command Injection attack detected'
        ],
        'xxe' => [
            'pattern' => '/<!DOCTYPE.*?SYSTEM.*?http|<!ENTITY.*?SYSTEM.*?http/i',
            'severity' => 'high',
            'description' => 'XXE (XML External Entity) attack detected'
        ],
        'ldap_injection' => [
            'pattern' => '/\*\(|\)\||\|\|\(|&\(|\)&|\(\)/i',
            'severity' => 'high',
            'description' => 'LDAP Injection attack detected'
        ],
        'nosql_injection' => [
            'pattern' => '/\{\$gt\:|\{\$ne\:|\{\$where\:|\{\$regex\:|\{\$or\:/i',
            'severity' => 'high',
            'description' => 'NoSQL Injection attack detected'
        ],
        'csrf' => [
            'pattern' => '/referer.*?=/i',
            'severity' => 'medium',
            'description' => 'Possible CSRF attack detected'
        ],
        'user_agent_anomaly' => [
            'pattern' => '/(sqlmap|nikto|nmap|dirbuster|gobuster|burp|w3af|acunetix|nessus|openvas)/i',
            'severity' => 'medium',
            'description' => 'Security scanner detected'
        ]
    ];
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->cache = Cache::getInstance();
        $this->realtime = new RealtimeService();
    }
    
    /**
     * Phân tích request để phát hiện tấn công
     * 
     * @param array $requestData Dữ liệu request (GET, POST, headers)
     * @param string $ip Địa chỉ IP
     * @return array Các tấn công phát hiện được
     */
    public function analyzeRequest($requestData, $ip = null)
    {
        $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $detectedAttacks = [];
        
        // Kết hợp tất cả dữ liệu cần kiểm tra
        $dataToCheck = '';
        
        // GET parameters
        if (!empty($_GET)) {
            $dataToCheck .= ' ' . json_encode($_GET);
        }
        
        // POST parameters
        if (!empty($_POST)) {
            $dataToCheck .= ' ' . json_encode($_POST);
        }
        
        // Request body (JSON)
        $input = file_get_contents('php://input');
        if ($input) {
            $dataToCheck .= ' ' . $input;
        }
        
        // Headers
        $headers = getallheaders();
        $dataToCheck .= ' ' . json_encode($headers);
        
        // User Agent
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $dataToCheck .= ' ' . $_SERVER['HTTP_USER_AGENT'];
        }
        
        // Request URI
        if (isset($_SERVER['REQUEST_URI'])) {
            $dataToCheck .= ' ' . $_SERVER['REQUEST_URI'];
        }
        
        // Kiểm tra từng pattern
        foreach ($this->attackPatterns as $attackType => $pattern) {
            if (preg_match($pattern['pattern'], $dataToCheck)) {
                $detectedAttacks[] = [
                    'type' => $attackType,
                    'severity' => $pattern['severity'],
                    'description' => $pattern['description'],
                    'ip' => $ip,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
        }
        
        // Ghi log và xử lý nếu phát hiện tấn công
        if (!empty($detectedAttacks)) {
            $this->handleAttack($detectedAttacks, $requestData, $ip);
        }
        
        // Kiểm tra brute force
        $this->checkBruteForce($ip);
        
        return $detectedAttacks;
    }
    
    /**
     * Xử lý khi phát hiện tấn công
     */
    private function handleAttack($attacks, $requestData, $ip)
    {
        foreach ($attacks as $attack) {
            // Ghi log
            Logger::attack($ip, $attack['type'], [
                'severity' => $attack['severity'],
                'description' => $attack['description'],
                'request_data' => $requestData
            ]);
            
            // Lưu vào database
            $this->db->insert('attack_logs', [
                'ip_address' => $ip,
                'attack_type' => $attack['type'],
                'severity' => $attack['severity'],
                'description' => $attack['description'],
                'request_data' => json_encode($requestData),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
                'detected_at' => date('Y-m-d H:i:s')
            ]);
            
            // Tạo cảnh báo nếu severity cao
            if (in_array($attack['severity'], ['critical', 'high'])) {
                $this->createAlert($attack, $ip);
            }
            
            // Gửi realtime notification
            $this->realtime->push('attack_detected', [
                'ip' => $ip,
                'type' => $attack['type'],
                'severity' => $attack['severity'],
                'description' => $attack['description'],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
        
        // Nếu là critical attack, có thể chặn IP
        $criticalAttacks = array_filter($attacks, function($a) {
            return $a['severity'] === 'critical';
        });
        
        if (!empty($criticalAttacks)) {
            $this->blockIp($ip, 'Critical attack detected');
        }
    }
    
    /**
     * Kiểm tra brute force (đăng nhập sai nhiều lần)
     */
    private function checkBruteForce($ip)
    {
        $maxAttempts = $_ENV['LOGIN_ATTEMPTS'] ?? 5;
        $windowMinutes = $_ENV['LOGIN_LOCKOUT_TIME'] ?? 15;
        
        $attempts = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM login_attempts 
             WHERE ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$ip, $windowMinutes]
        );
        
        if ($attempts >= $maxAttempts) {
            // Tạo cảnh báo brute force
            $this->db->insert('alerts', [
                'type' => 'brute_force',
                'severity' => 'high',
                'title' => 'Brute Force Attack Detected',
                'message' => "IP {$ip} has made {$attempts} failed login attempts in {$windowMinutes} minutes",
                'reference_id' => $ip,
                'reference_type' => 'ip',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Gửi realtime
            $this->realtime->push('brute_force_detected', [
                'ip' => $ip,
                'attempts' => $attempts,
                'window_minutes' => $windowMinutes
            ]);
        }
    }
    
    /**
     * Chặn IP tạm thời
     */
    private function blockIp($ip, $reason)
    {
        // Kiểm tra xem đã bị chặn chưa
        $blocked = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM blocked_ips WHERE ip_address = ? AND expires_at > NOW()",
            [$ip]
        );
        
        if (!$blocked) {
            $this->db->insert('blocked_ips', [
                'ip_address' => $ip,
                'reason' => $reason,
                'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            Logger::warning("IP blocked", ['ip' => $ip, 'reason' => $reason]);
        }
    }
    
    /**
     * Kiểm tra IP có bị chặn không
     */
    public function isIpBlocked($ip)
    {
        $blocked = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM blocked_ips WHERE ip_address = ? AND expires_at > NOW()",
            [$ip]
        );
        
        return $blocked > 0;
    }
    
    /**
     * Tạo cảnh báo
     */
    private function createAlert($attack, $ip)
    {
        $this->db->insert('alerts', [
            'type' => 'attack_detected',
            'severity' => $attack['severity'],
            'title' => ucfirst(str_replace('_', ' ', $attack['type'])),
            'message' => $attack['description'] . " from IP: {$ip}",
            'reference_id' => $ip,
            'reference_type' => 'ip',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Lấy thống kê tấn công
     */
    public function getAttackStats($days = 7)
    {
        $stats = $this->db->fetchAll(
            "SELECT attack_type, severity, COUNT(*) as count 
             FROM attack_logs 
             WHERE detected_at > DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY attack_type, severity",
            [$days]
        );
        
        $topIps = $this->db->fetchAll(
            "SELECT ip_address, COUNT(*) as count 
             FROM attack_logs 
             WHERE detected_at > DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY ip_address 
             ORDER BY count DESC 
             LIMIT 10",
            [$days]
        );
        
        return [
            'total_attacks' => $this->db->fetchColumn(
                "SELECT COUNT(*) FROM attack_logs WHERE detected_at > DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            ),
            'by_type' => $stats,
            'top_attackers' => $topIps
        ];
    }
}