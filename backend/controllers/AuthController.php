<?php
/**
 * AUTHENTICATION CONTROLLER
 * 
 * Xử lý các request liên quan đến xác thực người dùng
 * Hỗ trợ: Login, Logout, Refresh Token, Change Password, 2FA
 * 
 * @package Controllers
 */

namespace Controllers;

use Core\Controller;
use Core\Database;
use Core\JWT;
use Core\Validator;
use Core\Logger;
use Services\NotificationService;

class AuthController extends Controller
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
     * POST /api/auth/login
     * 
     * Đăng nhập hệ thống
     * Hỗ trợ đăng nhập bằng username hoặc email
     */
    public function login()
    {
        $data = $this->getRequestData();
        
        // Validate input
        $this->validate($data, [
            'username' => 'required',
            'password' => 'required'
        ]);
        
        $username = $data['username'];
        $password = $data['password'];
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Kiểm tra số lần đăng nhập sai
        $this->checkLoginAttempts($username, $ip);
        
        // Tìm user theo username hoặc email
        $user = $this->db->fetchOne(
            "SELECT u.*, r.name as role_name 
             FROM users u 
             JOIN roles r ON u.role_id = r.id 
             WHERE u.username = ? OR u.email = ?",
            [$username, $username]
        );
        
        // Kiểm tra user tồn tại
        if (!$user) {
            $this->logFailedLogin($username, $ip, 'User not found');
            $this->incrementLoginAttempts($username, $ip);
            return $this->error('Invalid credentials', 401);
        }
        
        // Kiểm tra password
        if (!password_verify($password, $user['password_hash'])) {
            $this->logFailedLogin($username, $ip, 'Invalid password');
            $this->incrementLoginAttempts($username, $ip);
            return $this->error('Invalid credentials', 401);
        }
        
        // Kiểm tra tài khoản có active không
        if (!$user['is_active']) {
            $this->logFailedLogin($username, $ip, 'Account disabled');
            return $this->error('Your account has been disabled. Please contact administrator.', 403);
        }
        
        // Kiểm tra email đã xác thực chưa (nếu bật)
        if (($_ENV['EMAIL_VERIFICATION'] ?? 'false') === 'true' && !$user['email_verified_at']) {
            return $this->error('Please verify your email address before logging in.', 403);
        }
        
        // Kiểm tra 2FA
        if ($user['two_factor_enabled']) {
            // Lưu user ID tạm thời để xác thực 2FA
            $_SESSION['2fa_user_id'] = $user['id'];
            return $this->success([
                'requires_two_factor' => true,
                'message' => 'Please enter your 2FA code'
            ], '2FA required');
        }
        
        // Cập nhật last_login
        $this->db->update('users', 
            ['last_login' => date('Y-m-d H:i:s'), 'last_ip' => $ip], 
            'id = ?', 
            [$user['id']]
        );
        
        // Xóa login attempts
        $this->clearLoginAttempts($username, $ip);
        
        // Tạo JWT token
        $token = JWT::encode([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role_name'],
            'email' => $user['email']
        ]);
        
        // Lưu session
        $this->saveSession($user['id'], $token, $ip, $userAgent);
        
        // Ghi log thành công
        $this->logAction('LOGIN_SUCCESS', "User {$user['username']} logged in from {$ip}");
        Logger::auth($user['username'], 'success', $ip);
        
        // Gửi thông báo đăng nhập (nếu email khác với IP thường)
        $this->sendLoginNotification($user, $ip);
        
        // Trả về response
        return $this->success([
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'avatar' => $user['avatar'],
                'role' => $user['role_name'],
                'role_id' => $user['role_id'],
                'permissions' => $this->getUserPermissions($user['id'])
            ]
        ], 'Login successful');
    }
    
    /**
     * POST /api/auth/logout
     * 
     * Đăng xuất
     */
    public function logout()
    {
        $headers = getallheaders();
        $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
        
        $userId = $this->getUserId();
        
        if ($userId && $token) {
            // Xóa session
            $this->db->delete('user_sessions', 'token = ?', [$token]);
            $this->logAction('LOGOUT', "User ID: {$userId} logged out");
        }
        
        return $this->success([], 'Logout successful');
    }
    
    /**
     * POST /api/auth/refresh
     * 
     * Refresh token
     */
    public function refresh()
    {
        $headers = getallheaders();
        $oldToken = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
        
        if (!$oldToken) {
            return $this->error('No token provided', 401);
        }
        
        // Kiểm tra token còn hiệu lực không
        $payload = JWT::decode($oldToken);
        if (!$payload) {
            return $this->error('Invalid or expired token', 401);
        }
        
        // Kiểm tra session còn tồn tại không
        $session = $this->db->fetchOne(
            "SELECT * FROM user_sessions WHERE token = ? AND expires_at > NOW()",
            [$oldToken]
        );
        
        if (!$session) {
            return $this->error('Session expired', 401);
        }
        
        // Tạo token mới
        $newToken = JWT::refresh($oldToken);
        
        // Cập nhật session
        $this->db->update('user_sessions', 
            ['token' => $newToken, 'expires_at' => date('Y-m-d H:i:s', time() + 604800)], 
            'id = ?', 
            [$session['id']]
        );
        
        return $this->success(['token' => $newToken], 'Token refreshed');
    }
    
    /**
     * GET /api/auth/me
     * 
     * Lấy thông tin user hiện tại
     */
    public function me()
    {
        $userId = $this->getUserId();
        
        if (!$userId) {
            return $this->error('Unauthorized', 401);
        }
        
        $user = $this->db->fetchOne(
            "SELECT u.id, u.username, u.email, u.full_name, u.avatar, u.is_active, 
                    u.last_login, u.created_at, r.name as role, r.id as role_id
             FROM users u 
             JOIN roles r ON u.role_id = r.id 
             WHERE u.id = ?",
            [$userId]
        );
        
        if (!$user) {
            return $this->error('User not found', 404);
        }
        
        return $this->success($user);
    }
    
    /**
     * PUT /api/auth/change-password
     * 
     * Đổi mật khẩu
     */
    public function changePassword()
    {
        $userId = $this->getUserId();
        $data = $this->getRequestData();
        
        $this->validate($data, [
            'current_password' => 'required',
            'new_password' => 'required|min:8',
            'new_password_confirmation' => 'required|same:new_password'
        ]);
        
        // Lấy user hiện tại
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        
        // Kiểm tra mật khẩu hiện tại
        if (!password_verify($data['current_password'], $user['password_hash'])) {
            return $this->error('Current password is incorrect', 400);
        }
        
        // Kiểm tra mật khẩu mới không được trùng mật khẩu cũ
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
     * POST /api/auth/forgot-password
     * 
     * Quên mật khẩu - gửi email reset
     */
    public function forgotPassword()
    {
        $data = $this->getRequestData();
        $this->validate($data, ['email' => 'required|email']);
        
        $user = $this->db->fetchOne("SELECT * FROM users WHERE email = ?", [$data['email']]);
        
        if (!$user) {
            // Không tiết lộ email có tồn tại hay không (bảo mật)
            return $this->success([], 'If your email is registered, you will receive a password reset link.');
        }
        
        // Tạo reset token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour
        
        // Lưu token
        $this->db->insert('password_resets', [
            'email' => $user['email'],
            'token' => password_hash($token, PASSWORD_DEFAULT),
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Gửi email
        $resetLink = $_ENV['APP_URL'] . "/reset-password?token={$token}&email=" . urlencode($user['email']);
        $this->notification->sendPasswordResetEmail($user['email'], $user['username'], $resetLink);
        
        $this->logAction('FORGOT_PASSWORD', "Password reset requested for email: {$data['email']}");
        
        return $this->success([], 'Password reset link sent to your email.');
    }
    
    /**
     * POST /api/auth/reset-password
     * 
     * Đặt lại mật khẩu với token
     */
    public function resetPassword()
    {
        $data = $this->getRequestData();
        
        $this->validate($data, [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8',
            'password_confirmation' => 'required|same:password'
        ]);
        
        // Tìm reset record
        $reset = $this->db->fetchOne(
            "SELECT * FROM password_resets WHERE email = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1",
            [$data['email']]
        );
        
        if (!$reset || !password_verify($data['token'], $reset['token'])) {
            return $this->error('Invalid or expired reset token', 400);
        }
        
        // Cập nhật mật khẩu
        $newHash = password_hash($data['password'], PASSWORD_DEFAULT);
        $this->db->update('users', ['password_hash' => $newHash], 'email = ?', [$data['email']]);
        
        // Xóa tất cả reset tokens
        $this->db->delete('password_resets', 'email = ?', [$data['email']]);
        
        // Xóa tất cả sessions
        $user = $this->db->fetchOne("SELECT id FROM users WHERE email = ?", [$data['email']]);
        if ($user) {
            $this->db->delete('user_sessions', 'user_id = ?', [$user['id']]);
        }
        
        $this->logAction('RESET_PASSWORD', "Password reset for email: {$data['email']}");
        
        return $this->success([], 'Password reset successfully. Please login with your new password.');
    }
    
    /**
     * GET /api/auth/permissions
     * 
     * Lấy permissions của user hiện tại
     */
    public function getPermissions()
    {
        $userId = $this->getUserId();
        
        if (!$userId) {
            return $this->error('Unauthorized', 401);
        }
        
        $rbac = new \Core\RBAC();
        $role = $rbac->getUserRole($userId);
        $permissions = $rbac->getRolePermissions($role);
        $menu = $rbac->getMenuByRole($role);
        
        return $this->success([
            'role' => $role,
            'permissions' => $permissions,
            'menu' => $menu
        ]);
    }
    
    /**
     * POST /api/auth/two-factor/enable
     * 
     * Bật xác thực 2 lớp
     */
    public function enableTwoFactor()
    {
        $userId = $this->getUserId();
        
        // Tạo secret key
        $secret = $this->generateTwoFactorSecret();
        
        // Lưu secret tạm thời
        $this->db->update('users', ['two_factor_secret' => $secret], 'id = ?', [$userId]);
        
        // Tạo QR code URL
        $user = $this->db->fetchOne("SELECT email FROM users WHERE id = ?", [$userId]);
        $qrUrl = "otpauth://totp/SecurityPlatform:{$user['email']}?secret={$secret}&issuer=SecurityPlatform";
        
        return $this->success([
            'secret' => $secret,
            'qr_code_url' => $qrUrl
        ], 'Scan QR code with Google Authenticator');
    }
    
    /**
     * POST /api/auth/two-factor/verify
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
        
        return $this->success([], 'Two-factor authentication enabled');
    }
    
    /**
     * POST /api/auth/two-factor/disable
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
        
        return $this->success([], 'Two-factor authentication disabled');
    }
    
    // ============================================
    // PRIVATE HELPER METHODS
    // ============================================
    
    /**
     * Kiểm tra số lần đăng nhập sai
     */
    private function checkLoginAttempts($username, $ip)
    {
        $maxAttempts = $_ENV['LOGIN_ATTEMPTS'] ?? 5;
        $lockoutTime = $_ENV['LOGIN_LOCKOUT_TIME'] ?? 15;
        
        $attempts = $this->db->fetchOne(
            "SELECT COUNT(*) as count, MAX(created_at) as last_attempt 
             FROM login_attempts 
             WHERE (username = ? OR ip = ?) 
             AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$username, $ip, $lockoutTime]
        );
        
        if ($attempts && $attempts['count'] >= $maxAttempts) {
            $waitTime = $lockoutTime - (time() - strtotime($attempts['last_attempt'])) / 60;
            return $this->error("Too many login attempts. Please try again in " . ceil($waitTime) . " minutes.", 429);
        }
    }
    
    /**
     * Tăng số lần đăng nhập sai
     */
    private function incrementLoginAttempts($username, $ip)
    {
        $this->db->insert('login_attempts', [
            'username' => $username,
            'ip' => $ip,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Xóa số lần đăng nhập sai
     */
    private function clearLoginAttempts($username, $ip)
    {
        $this->db->delete('login_attempts', 'username = ? OR ip = ?', [$username, $ip]);
    }
    
    /**
     * Lưu session đăng nhập
     */
    private function saveSession($userId, $token, $ip, $userAgent)
    {
        $this->db->insert('user_sessions', [
            'user_id' => $userId,
            'token' => $token,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'expires_at' => date('Y-m-d H:i:s', time() + 604800), // 7 days
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Ghi log đăng nhập thất bại
     */
    private function logFailedLogin($username, $ip, $reason)
    {
        Logger::auth($username, 'failed', $ip);
        $this->db->insert('audit_logs', [
            'user_id' => null,
            'action' => 'LOGIN_FAILED',
            'details' => "Username: {$username}, IP: {$ip}, Reason: {$reason}",
            'ip_address' => $ip,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Lấy danh sách permissions của user
     */
    private function getUserPermissions($userId)
    {
        $rbac = new \Core\RBAC();
        $role = $rbac->getUserRole($userId);
        return $rbac->getRolePermissions($role);
    }
    
    /**
     * Gửi thông báo đăng nhập từ IP lạ
     */
    private function sendLoginNotification($user, $ip)
    {
        // Kiểm tra IP có phải IP thường xuyên không
        $recentSessions = $this->db->fetchAll(
            "SELECT ip_address FROM user_sessions WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY ip_address",
            [$user['id']]
        );
        
        $knownIps = array_column($recentSessions, 'ip_address');
        
        if (!in_array($ip, $knownIps)) {
            $this->notification->sendNewLoginEmail($user['email'], $user['username'], $ip, date('Y-m-d H:i:s'));
        }
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
        // TOTP verification
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
        // Simplified TOTP - in production use a proper library
        return str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    }
}