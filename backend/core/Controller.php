<?php
/**
 * Base Controller
 * Cung cấp các method dùng chung cho tất cả controllers
 * 
 * @package Core
 */

namespace Core;

class Controller
{
    /**
     * @var Database Database instance
     */
    protected $db;
    
    /**
     * @var Logger Logger instance
     */
    protected $logger;
    
    /**
     * @var Validator Validator instance
     */
    protected $validator;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = Logger::getInstance();
        $this->validator = new Validator();
    }
    
    /**
     * Trả về JSON response thành công
     * 
     * @param mixed $data Dữ liệu trả về
     * @param string $message Thông báo
     * @param int $statusCode HTTP status code
     */
    protected function success($data = [], $message = 'Success', $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Trả về JSON response lỗi
     * 
     * @param string $message Thông báo lỗi
     * @param int $statusCode HTTP status code
     * @param array $errors Chi tiết lỗi
     */
    protected function error($message, $statusCode = 400, $errors = [])
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Lấy dữ liệu từ request (JSON hoặc form)
     * 
     * @return array
     */
    protected function getRequestData()
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true);
            return is_array($input) ? $input : [];
        }
        
        return $_REQUEST;
    }
    
    /**
     * Validate dữ liệu đầu vào
     * 
     * @param array $data Dữ liệu cần validate
     * @param array $rules Rules validation
     * @return bool
     */
    protected function validate($data, $rules)
    {
        if (!$this->validator->validate($data, $rules)) {
            $this->error('Validation failed', 422, $this->validator->getErrors());
        }
        return true;
    }
    
    /**
     * Lấy user ID từ JWT token
     * 
     * @return int|null
     */
    protected function getUserId()
    {
        $headers = getallheaders();
        $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
        
        if (!$token) {
            return null;
        }
        
        $payload = JWT::decode($token);
        return $payload->user_id ?? null;
    }
    
    /**
     * Lấy thông tin user hiện tại
     * 
     * @return array|null
     */
    protected function getCurrentUser()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return null;
        }
        
        return $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    }
    
    /**
     * Lấy role của user hiện tại
     * 
     * @return string|null
     */
    protected function getCurrentRole()
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return null;
        }
        
        $role = $this->db->fetchOne("SELECT name FROM roles WHERE id = ?", [$user['role_id']]);
        return $role['name'] ?? null;
    }
    
    /**
     * Kiểm tra user có quyền không
     * 
     * @param string $permission Tên permission
     * @return bool
     */
    protected function hasPermission($permission)
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return false;
        }
        
        $rbac = new RBAC();
        return $rbac->hasPermission($userId, $permission);
    }
    
    /**
     * Ghi log hành động
     * 
     * @param string $action Tên hành động
     * @param string|null $details Chi tiết
     */
    protected function logAction($action, $details = null)
    {
        $userId = $this->getUserId();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $this->db->insert('audit_logs', [
            'user_id' => $userId,
            'action' => $action,
            'details' => $details,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $this->logger->info($action, ['user_id' => $userId, 'details' => $details]);
    }
    
    /**
     * Paginate results
     * 
     * @param string $sql SQL query
     * @param array $params Parameters
     * @param int $page Current page
     * @param int $limit Items per page
     * @return array
     */
    protected function paginate($sql, $params = [], $page = 1, $limit = 15)
    {
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);
        $offset = ($page - 1) * $limit;
        
        // Đếm tổng số records
        $countSql = preg_replace('/SELECT.*?FROM/i', 'SELECT COUNT(*) as total FROM', $sql, 1);
        $total = $this->db->fetchColumn($countSql, $params);
        
        // Thêm LIMIT và OFFSET
        $sql .= " LIMIT {$limit} OFFSET {$offset}";
        $data = $this->db->fetchAll($sql, $params);
        
        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => (int)$total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }
}