<?php
/**
 * REALTIME SERVICE
 * 
 * Đẩy dữ liệu realtime qua WebSocket (Node.js)
 * Sử dụng HTTP request để gọi WebSocket server
 * 
 * @package Services
 */

namespace Services;

use Core\Logger;

class RealtimeService
{
    private $socketHost;
    private $socketPort;
    
    public function __construct()
    {
        $this->socketHost = $_ENV['SOCKET_HOST'] ?? 'localhost';
        $this->socketPort = $_ENV['SOCKET_PORT'] ?? 3000;
    }
    
    /**
     * Đẩy event đến tất cả client
     * 
     * @param string $event Tên event
     * @param mixed $data Dữ liệu gửi đi
     * @return bool
     */
    public function push($event, $data)
    {
        return $this->sendToSocket('global', $event, $data);
    }
    
    /**
     * Đẩy event đến một user cụ thể
     * 
     * @param int $userId ID user
     * @param string $event Tên event
     * @param mixed $data Dữ liệu
     * @return bool
     */
    public function pushToUser($userId, $event, $data)
    {
        return $this->sendToSocket("user:{$userId}", $event, $data);
    }
    
    /**
     * Đẩy event đến một server cụ thể
     * 
     * @param int $serverId ID server
     * @param string $event Tên event
     * @param mixed $data Dữ liệu
     * @return bool
     */
    public function pushToServer($serverId, $event, $data)
    {
        return $this->sendToSocket("server:{$serverId}", $event, $data);
    }
    
    /**
     * Đẩy event đến room admin
     */
    public function pushToAdmin($event, $data)
    {
        return $this->sendToSocket('admin', $event, $data);
    }
    
    /**
     * Gửi dữ liệu đến Socket.io server
     * 
     * @param string $room Tên room
     * @param string $event Tên event
     * @param mixed $data Dữ liệu
     * @return bool
     */
    private function sendToSocket($room, $event, $data)
    {
        $url = "http://{$this->socketHost}:{$this->socketPort}/api/push";
        
        $payload = json_encode([
            'room' => $room,
            'event' => $event,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1); // Không chờ response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            Logger::warning("Failed to push realtime event", [
                'room' => $room,
                'event' => $event,
                'http_code' => $httpCode
            ]);
            return false;
        }
        
        return true;
    }
    
    /**
     * Thông báo scan bắt đầu
     */
    public function scanStarted($serverId, $serverName, $userId)
    {
        return $this->pushToServer($serverId, 'scan_started', [
            'server_id' => $serverId,
            'server_name' => $serverName,
            'user_id' => $userId,
            'started_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Thông báo scan progress
     */
    public function scanProgress($serverId, $current, $total, $criteriaName)
    {
        $percent = round(($current / $total) * 100, 1);
        
        return $this->pushToServer($serverId, 'scan_progress', [
            'server_id' => $serverId,
            'current' => $current,
            'total' => $total,
            'percent' => $percent,
            'criteria_name' => $criteriaName
        ]);
    }
    
    /**
     * Thông báo scan hoàn thành
     */
    public function scanCompleted($serverId, $score, $passed, $failed, $warning)
    {
        return $this->pushToServer($serverId, 'scan_completed', [
            'server_id' => $serverId,
            'score' => $score,
            'passed' => $passed,
            'failed' => $failed,
            'warning' => $warning,
            'completed_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Thông báo alert mới
     */
    public function newAlert($alert)
    {
        return $this->pushToAdmin('new_alert', $alert);
    }
    
    /**
     * Thông báo assessment mới
     */
    public function newAssessment($assessment)
    {
        return $this->pushToAdmin('new_assessment', $assessment);
    }
    
    /**
     * Thông báo dashboard update
     */
    public function dashboardUpdate($data)
    {
        return $this->push('dashboard_update', $data);
    }
    
    /**
     * Thông báo realtime metrics
     */
    public function metrics($metrics)
    {
        return $this->push('metrics', $metrics);
    }
}