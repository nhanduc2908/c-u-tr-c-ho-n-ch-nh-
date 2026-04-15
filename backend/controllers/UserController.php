<?php
/**
 * USER CONTROLLER
 * 
 * Quản lý người dùng hệ thống - CHỈ ADMIN
 * - CRUD users
 * - Phân quyền user
 * - Reset mật khẩu
 * - Khóa/Mở khóa tài khoản
 * 
 * @package Controllers
 */

namespace Controllers;

use Core\Controller;
use Core\Database;
use Core\Validator;
use Core\Logger;
use Services\NotificationService;

class UserController extends Controller
{
    private $db;
    private $notification;
    
    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->notification = new NotificationService();
    }
    
    /**
     * GET /api/users
     * 
     * Lấy danh sách users (có phân trang, filter)
     */
    public function index()
    {
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $role = $_GET['role'] ?? null;
        $status = $_GET['status'] ?? null;
        $search = $_GET['search'] ?? null;
        
        $offset = ($page - 1) * $limit;
        $params = [];
        
        $sql = "SELECT u.id, u.username, u.email, u.full_name, u.avatar, u.is_active, 
                       u.last_login, u.last_ip, u.created_at, r.id as role_id, r.name as role_name
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE 1=1";
        
        if ($role) {
            $sql .= " AND r.name = ?";
            $params[] = $role;
        }
        
        if ($status === 'active') {
            $sql .= " AND u.is_active = 1";
        } elseif ($status === 'inactive') {
            $sql .= " AND u.is_active = 0";
        }
        
        if ($search) {
            $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        
        // Đếm tổng
        $countSql = str_replace("u.id, u.username, u.email, u.full_name, u.avatar, u.is_active, u.last_login, u.last_ip, u.created_at, r.id as role_id, r.name as role_name", 
                                "COUNT(*) as total", $sql);
        $total = $this->db->fetchColumn($countSql, $params);
        
        // Lấy dữ liệu
        $sql .= " ORDER BY u.created_at DESC LIMIT {$limit} OFFSET {$offset}";
        $users = $this->db->fetchAll($sql, $params);
        
        return $this->success([
            'data' => $users,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => (int)$total,
                'total_pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    /**
     * GET /api/users/{id}
     * 
     * Chi tiết user
     */
    public function show($id)
    {
        $user = $this->db->fetchOne(
            "SELECT u.*, r.name as role_name, r.id as role_id
             FROM users u
             JOIN roles r ON u.role_id = r.id
             WHERE u.id = ?",
            [$id]
        );
        
        if (!$user) {
            return $this->error('User not found', 404);
        }
        
        // Xóa password hash khỏi response
        unset($user['password_hash']);
        
        // Lấy thống kê của user
        $stats = $this->getUserStats($id);
        $user['stats'] = $stats;
        
        return $this->success($user);
    }
    
    /**
     * POST /api/users
     * 
     * Tạo user mới
     */
    public function store()
    {
        $data = $this->getRequestData();
        
        // Validate
        $this->validate($data, [
            'username' => 'required|min:3|max:50|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'role_id' => 'required|exists:roles,id',
            'full_name' => 'max:100'
        ]);
        
        // Tạo user
        $userId = $this->db->insert('users', [
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role_id' => $data['role_id'],
            'full_name' => $data['full_name'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Gửi email thông báo
        $this->notification->sendWelcomeEmail($data['email'], $data['username'], $data['password']);
        
        // Ghi log
        $this->logAction('USER_CREATE', "Created user: {$data['username']} (ID: {$userId})");
        
        return $this->success(['id' => $userId], 'User created successfully', 201);
    }
    
    /**
     * PUT /api/users/{id}
     * 
     * Cập nhật user
     */
    public function update($id)
    {
        $data = $this->getRequestData();
        
        // Kiểm tra user tồn tại
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
        if (!$user) {
            return $this->error('User not found', 404);
        }
        
        // Validate
        $rules = [
            'email' => 'email|unique:users,email,' . $id,
            'username' => 'min:3|max:50|unique:users,username,' . $id,
            'role_id' => 'exists:roles,id',
            'full_name' => 'max:100'
        ];
        $this->validate($data, $rules);
        
        // Chuẩn bị dữ liệu cập nhật
        $updateData = [];
        $allowedFields = ['username', 'email', 'full_name', 'role_id', 'is_active'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        
        // Cập nhật mật khẩu nếu có
        if (!empty($data['password'])) {
            $updateData['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        if (!empty($updateData)) {
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            $this->db->update('users', $updateData, 'id = ?', [$id]);
        }
        
        // Ghi log
        $this->logAction('USER_UPDATE', "Updated user ID: {$id}");
        
        return $this->success([], 'User updated successfully');
    }
    
    /**
     * DELETE /api/users/{id}
     * 
     * Xóa user
     */
    public function destroy($id)
    {
        $currentUserId = $this->getUserId();
        
        // Không cho xóa chính mình
        if ($currentUserId == $id) {
            return $this->error('Cannot delete your own account', 400);
        }
        
        // Kiểm tra user tồn tại
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
        if (!$user) {
            return $this->error('User not found', 404);
        }
        
        // Xóa user
        $this->db->delete('users', 'id = ?', [$id]);
        
        // Ghi log
        $this->logAction('USER_DELETE', "Deleted user: {$user['username']} (ID: {$id})");
        
        return $this->success([], 'User deleted successfully');
    }
    
    /**
     * POST /api/users/{id}/reset-password
     * 
     * Reset mật khẩu user
     */
    public function resetPassword($id)
    {
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
        if (!$user) {
            return $this->error('User not found', 404);
        }
        
        // Tạo mật khẩu ngẫu nhiên
        $newPassword = $this->generateRandomPassword();
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Cập nhật
        $this->db->update('users', ['password_hash' => $passwordHash], 'id = ?', [$id]);
        
        // Gửi email
        $this->notification->sendPasswordResetEmail($user['email'], $user['username'], $newPassword);
        
        // Ghi log
        $this->logAction('USER_RESET_PASSWORD', "Reset password for user ID: {$id}");
        
        return $this->success([
            'temporary_password' => $newPassword
        ], 'Password reset successfully. Temporary password has been sent to user email.');
    }
    
    /**
     * PUT /api/users/{id}/toggle-status
     * 
     * Khóa/Mở khóa user
     */
    public function toggleStatus($id)
    {
        $currentUserId = $this->getUserId();
        
        // Không cho khóa chính mình
        if ($currentUserId == $id) {
            return $this->error('Cannot change your own status', 400);
        }
        
        $user = $this->db->fetchOne("SELECT is_active FROM users WHERE id = ?", [$id]);
        if (!$user) {
            return $this->error('User not found', 404);
        }
        
        $newStatus = $user['is_active'] ? 0 : 1;
        $this->db->update('users', ['is_active' => $newStatus], 'id = ?', [$id]);
        
        $action = $newStatus ? 'unlocked' : 'locked';
        $this->logAction('USER_TOGGLE_STATUS', "{$action} user ID: {$id}");
        
        return $this->success([
            'is_active' => (bool)$newStatus
        ], "User has been " . ($newStatus ? 'activated' : 'deactivated'));
    }
    
    /**
     * GET /api/users/export
     * 
     * Export users ra file
     */
    public function export()
    {
        $format = $_GET['format'] ?? 'csv';
        
        $users = $this->db->fetchAll(
            "SELECT u.id, u.username, u.email, u.full_name, r.name as role, 
                    u.is_active, u.last_login, u.created_at
             FROM users u
             JOIN roles r ON u.role_id = r.id
             ORDER BY u.id"
        );
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="users_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Username', 'Email', 'Full Name', 'Role', 'Status', 'Last Login', 'Created At']);
        
        foreach ($users as $user) {
            fputcsv($output, [
                $user['id'],
                $user['username'],
                $user['email'],
                $user['full_name'],
                $user['role'],
                $user['is_active'] ? 'Active' : 'Inactive',
                $user['last_login'],
                $user['created_at']
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    // ============================================
    // PRIVATE METHODS
    // ============================================
    
    private function getUserStats($userId)
    {
        // Số lần đăng nhập
        $logins = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM audit_logs WHERE user_id = ? AND action = 'LOGIN_SUCCESS'",
            [$userId]
        );
        
        // Số lần đánh giá đã thực hiện
        $assessments = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM assessment_reports WHERE created_by = ?",
            [$userId]
        );
        
        // Số cảnh báo đã xử lý
        $alertsResolved = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM alerts WHERE resolved_by = ?",
            [$userId]
        );
        
        return [
            'total_logins' => (int)$logins,
            'total_assessments' => (int)$assessments,
            'alerts_resolved' => (int)$alertsResolved
        ];
    }
    
    private function generateRandomPassword($length = 12)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
        return substr(str_shuffle($chars), 0, $length);
    }
}