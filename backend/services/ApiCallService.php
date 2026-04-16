<?php
/**
 * API CALL SERVICE
 * 
 * Gọi các API bên ngoài
 * - HTTP requests (GET, POST, PUT, DELETE)
 * - Xử lý response
 * - Cache kết quả
 * 
 * @package Services
 */

namespace Services;

use Core\Logger;
use Core\Cache;

class ApiCallService
{
    private $cache;
    private $defaultTimeout = 30;
    private $defaultUserAgent = 'SecurityAssessmentPlatform/1.0';
    
    public function __construct()
    {
        $this->cache = Cache::getInstance();
    }
    
    /**
     * Gửi request GET
     * 
     * @param string $url URL
     * @param array $params Query parameters
     * @param array $headers Headers
     * @param int $timeout Timeout (giây)
     * @return array
     */
    public function get($url, $params = [], $headers = [], $timeout = null)
    {
        if (!empty($params)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
        }
        
        return $this->request('GET', $url, null, $headers, $timeout);
    }
    
    /**
     * Gửi request POST
     * 
     * @param string $url URL
     * @param mixed $data Dữ liệu gửi đi
     * @param array $headers Headers
     * @param int $timeout Timeout
     * @return array
     */
    public function post($url, $data = [], $headers = [], $timeout = null)
    {
        return $this->request('POST', $url, $data, $headers, $timeout);
    }
    
    /**
     * Gửi request PUT
     */
    public function put($url, $data = [], $headers = [], $timeout = null)
    {
        return $this->request('PUT', $url, $data, $headers, $timeout);
    }
    
    /**
     * Gửi request DELETE
     */
    public function delete($url, $headers = [], $timeout = null)
    {
        return $this->request('DELETE', $url, null, $headers, $timeout);
    }
    
    /**
     * Gửi request với cache
     * 
     * @param string $method HTTP method
     * @param string $url URL
     * @param mixed $data Dữ liệu
     * @param array $headers Headers
     * @param int $ttl Cache TTL (giây)
     * @return array
     */
    public function getCached($method, $url, $data = null, $headers = [], $ttl = 3600)
    {
        $cacheKey = 'api_' . md5($method . $url . json_encode($data) . json_encode($headers));
        
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return json_decode($cached, true);
        }
        
        $result = $this->request($method, $url, $data, $headers);
        
        if ($result['success'] && isset($result['body'])) {
            $this->cache->set($cacheKey, json_encode($result), $ttl);
        }
        
        return $result;
    }
    
    /**
     * Gửi request HTTP
     * 
     * @param string $method HTTP method
     * @param string $url URL
     * @param mixed $data Dữ liệu
     * @param array $headers Headers
     * @param int $timeout Timeout
     * @return array
     */
    private function request($method, $url, $data = null, $headers = [], $timeout = null)
    {
        $timeout = $timeout ?? $this->defaultTimeout;
        $startTime = microtime(true);
        
        $ch = curl_init();
        
        // Cấu hình CURL
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->defaultUserAgent);
        
        // Method
        switch (strtoupper($method)) {
            case 'GET':
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->preparePostData($data, $headers));
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->preparePostData($data, $headers));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            default:
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->preparePostData($data, $headers));
                }
        }
        
        // Headers
        $defaultHeaders = [
            'Accept: application/json',
            'User-Agent: ' . $this->defaultUserAgent
        ];
        
        $allHeaders = array_merge($defaultHeaders, $headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
        
        // Thực thi
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);
        
        curl_close($ch);
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        // Parse response
        $body = $response;
        $contentType = $info['content_type'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $body = json_decode($response, true);
        }
        
        $success = $httpCode >= 200 && $httpCode < 300;
        
        // Ghi log
        Logger::debug("API call", [
            'method' => $method,
            'url' => $url,
            'http_code' => $httpCode,
            'duration_ms' => $duration,
            'success' => $success
        ]);
        
        if (!$success && $error) {
            Logger::error("API call failed", [
                'url' => $url,
                'error' => $error,
                'http_code' => $httpCode
            ]);
        }
        
        return [
            'success' => $success,
            'http_code' => $httpCode,
            'body' => $body,
            'error' => $error,
            'duration_ms' => $duration,
            'info' => $info
        ];
    }
    
    /**
     * Chuẩn bị dữ liệu POST
     */
    private function preparePostData($data, &$headers)
    {
        if (is_array($data)) {
            // Kiểm tra nếu cần gửi JSON
            $hasJsonHeader = false;
            foreach ($headers as $header) {
                if (stripos($header, 'Content-Type: application/json') !== false) {
                    $hasJsonHeader = true;
                    break;
                }
            }
            
            if ($hasJsonHeader) {
                return json_encode($data);
            }
            
            return http_build_query($data);
        }
        
        return $data;
    }
    
    /**
     * Gọi API NVD để lấy thông tin CVE
     * 
     * @param string $cveCode Mã CVE (vd: CVE-2024-1234)
     * @return array|null
     */
    public function getCveInfo($cveCode)
    {
        $apiKey = $_ENV['NVD_API_KEY'] ?? '';
        $url = "https://services.nvd.nist.gov/rest/json/cves/2.0?cveId={$cveCode}";
        
        if ($apiKey) {
            $url .= "&apiKey={$apiKey}";
        }
        
        $result = $this->get($url, [], ['Accept: application/json'], 10);
        
        if ($result['success'] && isset($result['body']['vulnerabilities'][0]['cve'])) {
            return $result['body']['vulnerabilities'][0]['cve'];
        }
        
        return null;
    }
    
    /**
     * Gọi API Shodan để lấy thông tin server
     * 
     * @param string $ip Địa chỉ IP
     * @return array|null
     */
    public function getShodanInfo($ip)
    {
        $apiKey = $_ENV['SHODAN_API_KEY'] ?? '';
        if (!$apiKey) {
            return null;
        }
        
        $url = "https://api.shodan.io/shodan/host/{$ip}?key={$apiKey}";
        $result = $this->get($url, [], [], 10);
        
        if ($result['success']) {
            return $result['body'];
        }
        
        return null;
    }
    
    /**
     * Kiểm tra website có an toàn không qua VirusTotal
     * 
     * @param string $url URL cần kiểm tra
     * @return array|null
     */
    public function checkUrlSafety($url)
    {
        $apiKey = $_ENV['VIRUSTOTAL_API_KEY'] ?? '';
        if (!$apiKey) {
            return null;
        }
        
        $apiUrl = "https://www.virustotal.com/api/v3/urls";
        $result = $this->post($apiUrl, ['url' => $url], [
            'x-apikey: ' . $apiKey,
            'Content-Type: application/x-www-form-urlencoded'
        ], 15);
        
        if ($result['success']) {
            return $result['body'];
        }
        
        return null;
    }
    
    /**
     * Lấy thông tin IP từ ip-api.com
     * 
     * @param string $ip Địa chỉ IP
     * @return array|null
     */
    public function getIpInfo($ip)
    {
        $url = "http://ip-api.com/json/{$ip}";
        $result = $this->get($url, [], [], 5);
        
        if ($result['success'] && isset($result['body']['status']) && $result['body']['status'] === 'success') {
            return [
                'country' => $result['body']['country'],
                'city' => $result['body']['city'],
                'isp' => $result['body']['isp'],
                'org' => $result['body']['org'],
                'lat' => $result['body']['lat'],
                'lon' => $result['body']['lon']
            ];
        }
        
        return null;
    }
}