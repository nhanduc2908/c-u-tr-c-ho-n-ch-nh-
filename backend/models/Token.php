<?php
/**
 * TOKEN MODEL
 * 
 * Quản lý refresh token và reset token
 * 
 * @package Models
 */

namespace Models;

use Core\Model;
use Core\Database;

class Token extends Model
{
    protected $table = 'password_resets';
    protected $primaryKey = 'id';
    protected $fillable = ['email', 'token', 'expires_at'];
    protected $guarded = ['id', 'created_at'];
    protected $timestamps = false;
    
    /**
     * Tạo reset token
     */
    public static function createResetToken($email)
    {
        $db = Database::getInstance();
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);
        
        // Xóa token cũ
        $db->delete('password_resets', 'email = ?', [$email]);
        
        // Tạo token mới
        $db->insert('password_resets', [
            'email' => $email,
            'token' => password_hash($token, PASSWORD_DEFAULT),
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return $token;
    }
    
    /**
     * Xác thực reset token
     */
    public static function verifyResetToken($email, $token)
    {
        $db = Database::getInstance();
        $record = $db->fetchOne(
            "SELECT * FROM password_resets 
             WHERE email = ? AND expires_at > NOW() 
             ORDER BY created_at DESC LIMIT 1",
            [$email]
        );
        
        if (!$record) {
            return false;
        }
        
        return password_verify($token, $record['token']);
    }
    
    /**
     * Xóa reset token
     */
    public static function deleteResetToken($email)
    {
        $db = Database::getInstance();
        return $db->delete('password_resets', 'email = ?', [$email]);
    }
    
    /**
     * Xóa token cũ
     */
    public static function deleteExpired()
    {
        $db = Database::getInstance();
        return $db->delete('password_resets', 'expires_at < NOW()');
    }
}
