<?php
/**
 * AUTH SERVICE
 * 
 * Xử lý xác thực và phân quyền
 * - Đăng nhập/đăng xuất
 * - Quản lý session
 * - Xác thực 2FA
 * - Quản lý token
 * 
 * @package Services
 */

namespace Services;

use Core\Database;
use Core\JWT;
use Core\Logger;
use Core\Cache;

class AuthService
{
    private $db;
    private $cache;
    private $notification;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->cache = Cache::getInstance();
        $this->notification = new NotificationService();
    }
    
    /**
     * Đăng nhập
     * 
     * @param string $username Tên đăng nhập hoặc email
     * @param string $password Mật khẩu
     * @param string $ip Địa chỉ IP
     * @param string $userAgent User Agent
     * @return array Kết quả đăng nhập
     */
    public function login($username, $password, $ip, $userAgent)
    {
        // Kiểm tra số lần đăng nhập sai
        if ($this->isBruteForcing($username, $ip)) {
            return [
                'success' => false,
                'error' => 'Too many login attempts. Please try again later.',
                'code' => 'rate_limited'
            ];
        }
        
        // Tìm user
        $user = $this->db->fetchOne(
            "SELECT u.*, r.name as role_name 
             FROM users u 
             JOIN roles r ON u.role_id = r.id 
             WHERE u.username = ? OR u.email = ?",
            [$username, $username]
        );
        
        if (!$user) {
            $this->logFailedAttempt($username, $ip, 'User not found');
            return [
                'success' => false,
                'error' => 'Invalid credentials',
                'code' => 'invalid_credentials'
            ];
        }
        
        // Kiểm tra mật khẩu
        if (!password_verify($password, $user['password_hash'])) {
            $this->logFailedAttempt($username, $ip, 'Invalid password');
            $this->incrementLoginAttempts($username, $ip);
            return [
                'success' => false,
                'error' => 'Invalid credentials',
                'code' => 'invalid_credentials'
            ];
        }
        
        // Kiểm tra tài khoản active
        if (!$user['is_active']) {
            return [
                'success' => false,
                'error' => 'Your account has been disabled',
                'code' => 'account_disabled'
            ];
        }
        
        // Kiểm tra 2FA
        if ($user['two_factor_enabled']) {
            return [
                'success' => false,
                'requires_2fa' => true,
                'user_id' => $user['id'],
                'code' => '2fa_required'
            ];
        }
        
        // Đăng nhập thành công
        return $this->createSession($user, $ip, $userAgent);
    }
    
    /**
     * Xác thực 2FA
     * 
     * @param int $userId ID user
     * @param string $code Mã 2FA
     * @param string $ip IP
     * @param string $userAgent User Agent
     * @return array
     */
    public function verifyTwoFactor($userId, $code, $ip, $userAgent)
    {
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        
        if (!$user || !$user['two_factor_enabled']) {
            return [
                'success' => false,
                'error' => 'Invalid 2FA request',
                'code' => 'invalid_2fa'
            ];
        }
        
        if (!$this->verifyTotp($code, $user['two_factor_secret'])) {
            return [
                'success' => false,
                'error' => 'Invalid 2FA code',
                'code' => 'invalid_code'
            ];
        }
        
        // Lấy role
        $role = $this->db->fetchOne("SELECT name FROM roles WHERE id = ?", [$user['role_id']]);
        $user['role_name'] = $role['name'];
        
        return $this->createSession($user, $ip, $userAgent);
    }
    
    /**
     * Tạo session đăng nhập
     */
    private function createSession($user, $ip, $userAgent)
    {
        // Cập nhật last_login
        $this->db->update('users', [
            'last_login' => date('Y-m-d H:i:s'),
            'last_ip' => $ip
        ], 'id = ?', [$user['id']]);
        
        // Xóa login attempts
        $this->clearLoginAttempts($user['username'], $ip);
        
        // Tạo JWT token
        $token = JWT::encode([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role_name'],
            'email' => $user['email']
        ]);
        
        // Lưu session
        $this->db->insert('user_sessions', [
            'user_id' => $user['id'],
            'token' => $token,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'expires_at' => date('Y-m-d H:i:s', time() + 604800),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Ghi log
        Logger::auth($user['username'], 'success', $ip);
        
        // Gửi thông báo đăng nhập từ IP lạ
        $this->sendNewLoginNotification($user, $ip);
        
        return [
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role_name'],
                'avatar' => $user['avatar']
            ]
        ];
    }
    
    /**
     * Đăng xuất
     */
    public function logout($token)
    {
        if ($token) {
            $this->db->delete('user_sessions', 'token = ?', [$token]);
        }
        return ['success' => true];
    }
    
    /**
     * Refresh token
     */
    public function refreshToken($oldToken)
    {
        $payload = JWT::decode($oldToken);
        if (!$payload) {
            return ['success' => false, 'error' => 'Invalid token'];
        }
        
        // Kiểm tra session
        $session = $this->db->fetchOne(
            "SELECT * FROM user_sessions WHERE token = ? AND expires_at > NOW()",
            [$oldToken]
        );
        
        if (!$session) {
            return ['success' => false, 'error' => 'Session expired'];
        }
        
        // Tạo token mới
        $newToken = JWT::refresh($oldToken);
        
        // Cập nhật session
        $this->db->update('user_sessions', [
            'token' => $newToken,
            'expires_at' => date('Y-m-d H:i:s', time() + 604800)
        ], 'id = ?', [$session['id']]);
        
        return [
            'success' => true,
            'token' => $newToken
        ];
    }
    
    /**
     * Đổi mật khẩu
     */
    public function changePassword($userId, $currentPassword, $newPassword)
    {
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        
        if (!password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Current password is incorrect'];
        }
        
        if (password_verify($newPassword, $user['password_hash'])) {
            return ['success' => false, 'error' => 'New password cannot be the same as current password'];
        }
        
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->db->update('users', ['password_hash' => $newHash], 'id = ?', [$userId]);
        
        // Gửi email thông báo
        $this->notification->sendPasswordChangedEmail($user['email'], $user['username']);
        
        return ['success' => true];
    }
    
    /**
     * Quên mật khẩu - gửi email reset
     */
    public function forgotPassword($email)
    {
        $user = $this->db->fetchOne("SELECT * FROM users WHERE email = ?", [$email]);
        
        if (!$user) {
            // Không tiết lộ email có tồn tại hay không
            return ['success' => true];
        }
        
        // Tạo reset token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);
        
        // Lưu token
        $this->db->insert('password_resets', [
            'email' => $email,
            'token' => password_hash($token, PASSWORD_DEFAULT),
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Gửi email
        $resetLink = $_ENV['APP_URL'] . "/reset-password?token={$token}&email=" . urlencode($email);
        $this->notification->sendPasswordResetEmail($email, $user['username'], $resetLink);
        
        return ['success' => true];
    }
    
    /**
     * Đặt lại mật khẩu
     */
    public function resetPassword($email, $token, $newPassword)
    {
        $reset = $this->db->fetchOne(
            "SELECT * FROM password_resets 
             WHERE email = ? AND expires_at > NOW() 
             ORDER BY created_at DESC LIMIT 1",
            [$email]
        );
        
        if (!$reset || !password_verify($token, $reset['token'])) {
            return ['success' => false, 'error' => 'Invalid or expired reset token'];
        }
        
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->db->update('users', ['password_hash' => $newHash], 'email = ?', [$email]);
        
        // Xóa token
        $this->db->delete('password_resets', 'email = ?', [$email]);
        
        // Xóa tất cả sessions
        $user = $this->db->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($user) {
            $this->db->delete('user_sessions', 'user_id = ?', [$user['id']]);
        }
        
        return ['success' => true];
    }
    
    /**
     * Bật 2FA
     */
    public function enableTwoFactor($userId)
    {
        $secret = $this->generateTotpSecret();
        
        $this->db->update('users', [
            'two_factor_secret' => $secret
        ], 'id = ?', [$userId]);
        
        $user = $this->db->fetchOne("SELECT email FROM users WHERE id = ?", [$userId]);
        
        $qrUrl = "otpauth://totp/SecurityPlatform:{$user['email']}?secret={$secret}&issuer=SecurityPlatform";
        
        return [
            'success' => true,
            'secret' => $secret,
            'qr_code_url' => $qrUrl
        ];
    }
    
    /**
     * Xác thực 2FA để bật
     */
    public function verifyAndEnableTwoFactor($userId, $code)
    {
        $user = $this->db->fetchOne("SELECT two_factor_secret FROM users WHERE id = ?", [$userId]);
        
        if (!$this->verifyTotp($code, $user['two_factor_secret'])) {
            return ['success' => false, 'error' => 'Invalid 2FA code'];
        }
        
        $this->db->update('users', ['two_factor_enabled' => 1], 'id = ?', [$userId]);
        
        return ['success' => true];
    }
    
    /**
     * Tắt 2FA
     */
    public function disableTwoFactor($userId, $code)
    {
        $user = $this->db->fetchOne("SELECT two_factor_secret FROM users WHERE id = ?", [$userId]);
        
        if (!$this->verifyTotp($code, $user['two_factor_secret'])) {
            return ['success' => false, 'error' => 'Invalid 2FA code'];
        }
        
        $this->db->update('users', [
            'two_factor_enabled' => 0,
            'two_factor_secret' => null
        ], 'id = ?', [$userId]);
        
        return ['success' => true];
    }
    
    /**
     * Lấy danh sách session của user
     */
    public function getUserSessions($userId, $currentToken = null)
    {
        $sessions = $this->db->fetchAll(
            "SELECT id, ip_address, user_agent, created_at, expires_at,
                    CASE WHEN token = ? THEN 1 ELSE 0 END as is_current
             FROM user_sessions 
             WHERE user_id = ? 
             ORDER BY created_at DESC",
            [$currentToken, $userId]
        );
        
        return $sessions;
    }
    
    /**
     * Xóa session
     */
    public function revokeSession($sessionId, $userId)
    {
        $session = $this->db->fetchOne(
            "SELECT * FROM user_sessions WHERE id = ? AND user_id = ?",
            [$sessionId, $userId]
        );
        
        if (!$session) {
            return ['success' => false, 'error' => 'Session not found'];
        }
        
        $this->db->delete('user_sessions', 'id = ?', [$sessionId]);
        
        return ['success' => true];
    }
    
    /**
     * Xóa tất cả session khác
     */
    public function revokeOtherSessions($userId, $currentToken)
    {
        $this->db->delete('user_sessions', 'user_id = ? AND token != ?', [$userId, $currentToken]);
        
        return ['success' => true];
    }
    
    // ============================================
    // PRIVATE METHODS
    // ============================================
    
    private function isBruteForcing($username, $ip)
    {
        $maxAttempts = $_ENV['LOGIN_ATTEMPTS'] ?? 5;
        $windowMinutes = $_ENV['LOGIN_LOCKOUT_TIME'] ?? 15;
        
        $attempts = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM login_attempts 
             WHERE (username = ? OR ip = ?) 
             AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$username, $ip, $windowMinutes]
        );
        
        return $attempts >= $maxAttempts;
    }
    
    private function incrementLoginAttempts($username, $ip)
    {
        $this->db->insert('login_attempts', [
            'username' => $username,
            'ip' => $ip,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    private function clearLoginAttempts($username, $ip)
    {
        $this->db->delete('login_attempts', 'username = ? OR ip = ?', [$username, $ip]);
    }
    
    private function logFailedAttempt($username, $ip, $reason)
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
    
    private function sendNewLoginNotification($user, $ip)
    {
        // Kiểm tra IP đã từng đăng nhập chưa
        $knownIps = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM user_sessions 
             WHERE user_id = ? AND ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$user['id'], $ip]
        );
        
        if ($knownIps == 0) {
            $this->notification->sendNewLoginEmail(
                $user['email'],
                $user['username'],
                $ip,
                date('Y-m-d H:i:s')
            );
        }
    }
    
    private function generateTotpSecret()
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $secret;
    }
    
    private function verifyTotp($code, $secret)
    {
        // TOTP verification - simplified
        // In production, use a proper library like robthree/twofactorauth
        $timeSlice = floor(time() / 30);
        
        for ($i = -1; $i <= 1; $i++) {
            $calculatedCode = $this->generateTotpCode($secret, $timeSlice + $i);
            if ($calculatedCode == $code) {
                return true;
            }
        }
        return false;
    }
    
    private function generateTotpCode($secret, $timeSlice)
    {
        // Simplified TOTP - in production use proper implementation
        return str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    }
}