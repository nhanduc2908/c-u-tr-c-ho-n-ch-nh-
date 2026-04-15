<?php
/**
 * PROFILE CONTROLLER
 * 
 * Quản lý thông tin cá nhân của người dùng
 * - Xem và cập nhật thông tin
 * - Đổi mật khẩu
 * - Quản lý avatar
 * - Xem lịch sử hoạt động
 * - Quản lý phiên đăng nhập
 * - Xác thực 2 lớp
 * 
 * @package Controllers
 */

namespace Controllers;

use Core\Controller;
use Core\Database;
use Core\JWT;
use Core\Validator;
use Core\Logger;
use Services\FileUploadService;
use Services\NotificationService;

class ProfileController extends Controller
{
    private $db;
    private $uploadService;
    private $notification;
    
    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->uploadService = new FileUploadService();
        $this->notification = new NotificationService();
    }
    
    /**
     * GET /api/profile
     * 
     * Lấy thông tin cá nhân
     */
    public function show()
    {
        $userId = $this->getUserId();
        
        $profile = $this->db->fetchOne(
            "SELECT u.id, u.username, u.email, u.full_name, u.phone, u.address, 
                    u.avatar, u.role_id, r.name as role, u.is_active, 
                    u.last_login, u.last_ip, u.two_factor_enabled,
                    u.email_verified_at, u.created_at, u.updated_at
             FROM users u 
             JOIN roles r ON u.role_id = r.id 
             WHERE u.id = ?",
            [$userId]
        );
        
        if (!$profile) {
            return $this->error('User not found', 404);
        }
        
        // Thêm thông tin bổ sung
        $profile['avatar_url'] = $profile['avatar'] ? 
            $_ENV['APP_URL'] . '/uploads/avatars/' . $profile['avatar'] : null;
        $profile['email_verified'] = !is_null($profile['email_verified_at']);
        
        return $this->success($profile);
    }
    
    /**
     * PUT /api/profile
     * 
     * Cập nhật thông tin cá nhân
     */
    public function update()
    {
        $userId = $this->getUserId();
        $data = $this->getRequestData();
        
        // Validate dữ liệu
        $rules = [
            'full_name' => 'max:100',
            'phone' => 'max:20|regex:/^[0-9+\-\s]+$/',
            'address' => 'max:255'
        ];
        
        if (isset($data['email'])) {
            $rules['email'] = 'email|unique:users,email,' . $userId;
        }
        
        $this->validate($data, $rules);
        
        // Chuẩn bị dữ liệu cập nhật
        $updateData = [];
        $allowedFields = ['full_name', 'email', 'phone', 'address'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        
        if (empty($updateData)) {
            return $this->error('No data to update', 400);
        }
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        
        // Cập nhật
        $this->db->update('users', $updateData, 'id = ?', [$userId]);
        
        // Ghi log
        $this->logAction('PROFILE_UPDATE', "User ID: {$userId} updated profile");
        
        // Lấy thông tin mới
        $updated = $this->db->fetchOne(
            "SELECT u.id, u.username, u.email, u.full_name, u.phone, u.address, u.avatar, r.name as role
             FROM users u 
             JOIN roles r ON u.role_id = r.id 
             WHERE u.id = ?",
            [$userId]
        );
        
        return $this->success($updated, 'Profile updated successfully');
    }
    
    /**
     * PUT /api/profile/change-password
     * 
     * Đổi mật khẩu
     */
    public function changePassword()
    {
        $userId = $this->getUserId();
        $data = $this->getRequestData();
        
        // Validate
        $this->validate($data, [
            'current_password' => 'required',
            'new_password' => 'required|min:8',
            'new_password_confirmation' => 'required|same:new_password'
        ]);
        
        // Lấy user hiện tại
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        
        if (!$user) {
            return $this->error('User not found', 404);
        }
        
        // Kiểm tra mật khẩu hiện tại
        if (!password_verify($data['current_password'], $user['password_hash'])) {
            $this->logAction('PASSWORD_CHANGE_FAILED', "User ID: {$userId} - Incorrect current password");
            return $this->error('Current password is incorrect', 400);
        }
        
        // Kiểm tra mật khẩu mới không trùng mật khẩu cũ
        if (password_verify($data['new_password'], $user['password_hash'])) {
            return $this->error('New password cannot be the same as current password', 400);
        }
        
        // Cập nhật mật khẩu
        $newHash = password_hash($data['new_password'], PASSWORD_DEFAULT);
        $this->db->update('users', ['password_hash' => $newHash], 'id = ?', [$userId]);
        
        // Ghi log
        $this->logAction('PASSWORD_CHANGE', "User ID: {$userId} changed password");
        
        // Gửi email thông báo
        $this->notification->sendPasswordChangedEmail($user['email'], $user['username']);
        
        // Xóa tất cả session khác (trừ session hiện tại)
        $currentToken = str_replace('Bearer ', '', getallheaders()['Authorization'] ?? '');
        $this->db->delete('user_sessions', 'user_id = ? AND token != ?', [$userId, $currentToken]);
        
        return $this->success([], 'Password changed successfully');
    }
    
    /**
     * POST /api/profile/avatar
     * 
     * Upload avatar
     */
    public function uploadAvatar()
    {
        $userId = $this->getUserId();
        
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            return $this->error('No file uploaded or upload failed', 400);
        }
        
        $file = $_FILES['file'];
        
        // Validate file
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($file['type'], $allowedTypes)) {
            return $this->error('Invalid file type. Allowed: JPEG, PNG, GIF, WEBP', 400);
        }
        
        if ($file['size'] > $maxSize) {
            return $this->error('File too large. Max size: 2MB', 400);
        }
        
        // Tạo tên file an toàn
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
        $uploadPath = __DIR__ . '/../storage/uploads/avatars/';
        
        // Tạo thư mục nếu chưa tồn tại
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
        
        // Xóa avatar cũ nếu có
        $oldAvatar = $this->db->fetchColumn("SELECT avatar FROM users WHERE id = ?", [$userId]);
        if ($oldAvatar && file_exists($uploadPath . $oldAvatar)) {
            unlink($uploadPath . $oldAvatar);
        }
        
        // Upload file
        $targetPath = $uploadPath . $filename;
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // Resize image (optional)
            $this->resizeImage($targetPath, 200, 200);
            
            // Cập nhật database
            $this->db->update('users', ['avatar' => $filename], 'id = ?', [$userId]);
            
            $this->logAction('AVATAR_UPLOAD', "User ID: {$userId} uploaded new avatar");
            
            return $this->success([
                'avatar' => $filename,
                'avatar_url' => $_ENV['APP_URL'] . '/uploads/avatars/' . $filename
            ], 'Avatar uploaded successfully');
        }
        
        return $this->error('Failed to upload file', 500);
    }
    
    /**
     * DELETE /api/profile/avatar
     * 
     * Xóa avatar
     */
    public function deleteAvatar()
    {
        $userId = $this->getUserId();
        
        $avatar = $this->db->fetchColumn("SELECT avatar FROM users WHERE id = ?", [$userId]);
        
        if ($avatar) {
            $avatarPath = __DIR__ . '/../storage/uploads/avatars/' . $avatar;
            if (file_exists($avatarPath)) {
                unlink($avatarPath);
            }
            
            $this->db->update('users', ['avatar' => null], 'id = ?', [$userId]);
            $this->logAction('AVATAR_DELETE', "User ID: {$userId} deleted avatar");
        }
        
        return $this->success([], 'Avatar deleted successfully');
    }
    
    /**
     * GET /api/profile/activity
     * 
     * Lấy lịch sử hoạt động cá nhân
     */
    public function getActivity()
    {
        $userId = $this->getUserId();
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $type = $_GET['type'] ?? null;
        
        $offset = ($page - 1) * $limit;
        $params = [$userId];
        
        $sql = "SELECT * FROM audit_logs WHERE user_id = ?";
        
        if ($type) {
            $sql .= " AND action LIKE ?";
            $params[] = "%{$type}%";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";
        
        $activities = $this->db->fetchAll($sql, $params);
        
        // Đếm tổng
        $countSql = str_replace("SELECT *", "SELECT COUNT(*) as total", $sql);
        $total = $this->db->fetchColumn($countSql, $params);
        
        return $this->success([
            'data' => $activities,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => (int)$total,
                'total_pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    /**
     * GET /api/profile/sessions
     * 
     * Lấy danh sách phiên đăng nhập
     */
    public function getSessions()
    {
        $userId = $this->getUserId();
        $currentToken = str_replace('Bearer ', '', getallheaders()['Authorization'] ?? '');
        
        $sessions = $this->db->fetchAll(
            "SELECT id, ip_address, user_agent, created_at, expires_at,
                    CASE WHEN token = ? THEN 1 ELSE 0 END as is_current
             FROM user_sessions 
             WHERE user_id = ? 
             ORDER BY created_at DESC",
            [$currentToken, $userId]
        );
        
        foreach ($sessions as &$session) {
            $session['device'] = $this->parseUserAgent($session['user_agent']);
            $session['location'] = $this->getIpLocation($session['ip_address']);
        }
        
        return $this->success($sessions);
    }
    
    /**
     * DELETE /api/profile/sessions/{id}
     * 
     * Xóa một phiên đăng nhập
     */
    public function revokeSession($id)
    {
        $userId = $this->getUserId();
        $currentToken = str_replace('Bearer ', '', getallheaders()['Authorization'] ?? '');
        
        // Kiểm tra không xóa session hiện tại
        $session = $this->db->fetchOne(
            "SELECT token FROM user_sessions WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
        
        if (!$session) {
            return $this->error('Session not found', 404);
        }
        
        if ($session['token'] === $currentToken) {
            return $this->error('Cannot revoke current session', 400);
        }
        
        $this->db->delete('user_sessions', 'id = ? AND user_id = ?', [$id, $userId]);
        
        $this->logAction('SESSION_REVOKE', "User ID: {$userId} revoked session ID: {$id}");
        
        return $this->success([], 'Session revoked successfully');
    }
    
    /**
     * DELETE /api/profile/sessions
     * 
     * Xóa tất cả phiên đăng nhập khác
     */
    public function revokeAllOtherSessions()
    {
        $userId = $this->getUserId();
        $currentToken = str_replace('Bearer ', '', getallheaders()['Authorization'] ?? '');
        
        $this->db->delete('user_sessions', 'user_id = ? AND token != ?', [$userId, $currentToken]);
        
        $this->logAction('ALL_SESSIONS_REVOKE', "User ID: {$userId} revoked all other sessions");
        
        return $this->success([], 'All other sessions revoked successfully');
    }
    
    /**
     * POST /api/profile/two-factor/enable
     * 
     * Bật xác thực 2 lớp
     */
    public function enableTwoFactor()
    {
        $userId = $this->getUserId();
        
        // Tạo secret key
        $secret = $this->generateTwoFactorSecret();
        
        // Lưu secret
        $this->db->update('users', ['two_factor_secret' => $secret], 'id = ?', [$userId]);
        
        // Tạo QR code URL
        $user = $this->db->fetchOne("SELECT email, username FROM users WHERE id = ?", [$userId]);
        $qrUrl = "otpauth://totp/SecurityPlatform:{$user['email']}?secret={$secret}&issuer=SecurityPlatform";
        
        return $this->success([
            'secret' => $secret,
            'qr_code_url' => $qrUrl
        ], 'Scan QR code with Google Authenticator');
    }
    
    /**
     * POST /api/profile/two-factor/verify
     * 
     * Xác thực mã 2FA
     */
    public function verifyTwoFactor()
    {
        $userId = $this->getUserId();
        $data = $this->getRequestData();
        
        $this->validate($data, ['code' => 'required|numeric|digits:6']);
        
        $user = $this->db->fetchOne("SELECT two_factor_secret FROM users WHERE id = ?", [$userId]);
        
        if (!$this->verifyTwoFactorCode($data['code'], $user['two_factor_secret'])) {
            return $this->error('Invalid 2FA code', 400);
        }
        
        // Bật 2FA
        $this->db->update('users', ['two_factor_enabled' => 1], 'id = ?', [$userId]);
        
        $this->logAction('2FA_ENABLED', "User ID: {$userId} enabled two-factor authentication");
        
        return $this->success([], 'Two-factor authentication enabled');
    }
    
    /**
     * POST /api/profile/two-factor/disable
     * 
     * Tắt xác thực 2 lớp
     */
    public function disableTwoFactor()
    {
        $userId = $this->getUserId();
        $data = $this->getRequestData();
        
        $this->validate($data, ['code' => 'required|numeric|digits:6']);
        
        $user = $this->db->fetchOne("SELECT two_factor_secret FROM users WHERE id = ?", [$userId]);
        
        if (!$this->verifyTwoFactorCode($data['code'], $user['two_factor_secret'])) {
            return $this->error('Invalid 2FA code', 400);
        }
        
        // Tắt 2FA
        $this->db->update('users', [
            'two_factor_enabled' => 0,
            'two_factor_secret' => null
        ], 'id = ?', [$userId]);
        
        $this->logAction('2FA_DISABLED', "User ID: {$userId} disabled two-factor authentication");
        
        return $this->success([], 'Two-factor authentication disabled');
    }
    
    /**
     * GET /api/profile/notifications
     * 
     * Lấy danh sách thông báo
     */
    public function getNotifications()
    {
        $userId = $this->getUserId();
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $unreadOnly = isset($_GET['unread']) && $_GET['unread'] === 'true';
        
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        $params = [$userId];
        
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";
        
        $notifications = $this->db->fetchAll($sql, $params);
        
        // Đếm tổng
        $countSql = str_replace("SELECT *", "SELECT COUNT(*) as total", $sql);
        $total = $this->db->fetchColumn($countSql, $params);
        
        // Đếm số chưa đọc
        $unreadCount = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
        
        return $this->success([
            'data' => $notifications,
            'unread_count' => (int)$unreadCount,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => (int)$total,
                'total_pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    /**
     * PUT /api/profile/notifications/{id}/read
     * 
     * Đánh dấu thông báo đã đọc
     */
    public function markNotificationRead($id)
    {
        $userId = $this->getUserId();
        
        $this->db->update('notifications', 
            ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')], 
            'id = ? AND user_id = ?', 
            [$id, $userId]
        );
        
        return $this->success([], 'Notification marked as read');
    }
    
    /**
     * PUT /api/profile/notifications/read-all
     * 
     * Đánh dấu tất cả thông báo đã đọc
     */
    public function markAllNotificationsRead()
    {
        $userId = $this->getUserId();
        
        $this->db->update('notifications', 
            ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')], 
            'user_id = ? AND is_read = 0', 
            [$userId]
        );
        
        return $this->success([], 'All notifications marked as read');
    }
    
    /**
     * GET /api/profile/stats
     * 
     * Thống kê cá nhân
     */
    public function getStats()
    {
        $userId = $this->getUserId();
        
        // Thống kê đánh giá
        $assessments = $this->db->fetchOne(
            "SELECT COUNT(*) as total, 
                    AVG(total_score) as avg_score,
                    SUM(CASE WHEN total_score >= 80 THEN 1 ELSE 0 END) as good,
                    SUM(CASE WHEN total_score >= 60 AND total_score < 80 THEN 1 ELSE 0 END) as average,
                    SUM(CASE WHEN total_score < 60 THEN 1 ELSE 0 END) as poor
             FROM assessment_reports 
             WHERE created_by = ?",
            [$userId]
        );
        
        // Thống kê alerts
        $alerts = $this->db->fetchOne(
            "SELECT COUNT(*) as total,
                    SUM(CASE WHEN is_resolved = 1 THEN 1 ELSE 0 END) as resolved,
                    SUM(CASE WHEN is_resolved = 0 THEN 1 ELSE 0 END) as pending
             FROM alerts 
             WHERE created_by = ? OR assigned_to = ?",
            [$userId, $userId]
        );
        
        // Thống kê login
        $logins = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM audit_logs WHERE user_id = ? AND action = 'LOGIN_SUCCESS'",
            [$userId]
        );
        
        // Thời gian hoạt động gần nhất
        $lastActivity = $this->db->fetchColumn(
            "SELECT created_at FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 1",
            [$userId]
        );
        
        return $this->success([
            'assessments' => $assessments,
            'alerts' => $alerts,
            'total_logins' => (int)$logins,
            'last_activity' => $lastActivity,
            'member_since' => $this->db->fetchColumn(
                "SELECT created_at FROM users WHERE id = ?",
                [$userId]
            )
        ]);
    }
    
    // ============================================
    // PRIVATE HELPER METHODS
    // ============================================
    
    /**
     * Resize ảnh
     */
    private function resizeImage($path, $width, $height)
    {
        $imageInfo = getimagesize($path);
        $mime = $imageInfo['mime'];
        
        switch ($mime) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($path);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($path);
                break;
            default:
                return false;
        }
        
        $resized = imagescale($image, $width, $height);
        
        switch ($mime) {
            case 'image/jpeg':
                imagejpeg($resized, $path, 90);
                break;
            case 'image/png':
                imagepng($resized, $path, 9);
                break;
            case 'image/gif':
                imagegif($resized, $path);
                break;
        }
        
        imagedestroy($image);
        imagedestroy($resized);
        
        return true;
    }
    
    /**
     * Parse User Agent để lấy thông tin thiết bị
     */
    private function parseUserAgent($userAgent)
    {
        $device = 'Unknown';
        
        if (strpos($userAgent, 'Windows') !== false) $device = 'Windows PC';
        elseif (strpos($userAgent, 'Mac') !== false) $device = 'Mac';
        elseif (strpos($userAgent, 'Linux') !== false) $device = 'Linux';
        elseif (strpos($userAgent, 'iPhone') !== false) $device = 'iPhone';
        elseif (strpos($userAgent, 'iPad') !== false) $device = 'iPad';
        elseif (strpos($userAgent, 'Android') !== false) $device = 'Android Phone';
        
        if (strpos($userAgent, 'Chrome') !== false) $device .= ' - Chrome';
        elseif (strpos($userAgent, 'Firefox') !== false) $device .= ' - Firefox';
        elseif (strpos($userAgent, 'Safari') !== false) $device .= ' - Safari';
        elseif (strpos($userAgent, 'Edge') !== false) $device .= ' - Edge';
        
        return $device;
    }
    
    /**
     * Lấy vị trí từ IP (sử dụng API bên ngoài)
     */
    private function getIpLocation($ip)
    {
        // Trong thực tế có thể gọi API ip-api.com hoặc ip2location
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return 'Localhost';
        }
        return 'Unknown';
    }
    
    /**
     * Tạo secret key cho 2FA
     */
    private function generateTwoFactorSecret()
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $secret;
    }
    
    /**
     * Xác thực mã 2FA
     */
    private function verifyTwoFactorCode($code, $secret)
    {
        // TOTP verification - simplified
        // In production, use a proper library like robthree/twofactorauth
        $timeSlice = floor(time() / 30);
        
        for ($i = -1; $i <= 1; $i++) {
            $calculatedCode = $this->generateTOTP($secret, $timeSlice + $i);
            if ($calculatedCode == $code) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Tạo mã TOTP
     */
    private function generateTOTP($secret, $timeSlice)
    {
        // Simplified TOTP - in production use proper implementation
        return str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    }
}