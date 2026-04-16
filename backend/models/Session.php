<?php
/**
 * SESSION MODEL
 * 
 * Quản lý phiên đăng nhập
 * 
 * @package Models
 */

namespace Models;

use Core\Model;
use Core\Database;

class Session extends Model
{
    protected $table = 'user_sessions';
    protected $primaryKey = 'id';
    protected $fillable = [
        'user_id', 'token', 'ip_address', 'user_agent', 'expires_at'
    ];
    protected $guarded = ['id', 'created_at'];
    protected $timestamps = false;
    
    /**
     * Lấy thông tin user
     */
    public function getUser()
    {
        $db = Database::getInstance();
        return $db->fetchOne("SELECT id, username, email FROM users WHERE id = ?", [$this->user_id]);
    }
    
    /**
     * Kiểm tra session còn hiệu lực
     */
    public function isValid()
    {
        return strtotime($this->expires_at) > time();
    }
    
    /**
     * Lấy session theo token
     */
    public static function findByToken($token)
    {
        $db = Database::getInstance();
        return $db->fetchOne("SELECT * FROM user_sessions WHERE token = ?", [$token]);
    }
    
    /**
     * Xóa session cũ
     */
    public static function deleteExpired()
    {
        $db = Database::getInstance();
        return $db->delete('user_sessions', 'expires_at < NOW()');
    }
    
    /**
     * Xóa tất cả session của user
     */
    public static function deleteByUser($userId, $exceptToken = null)
    {
        $db = Database::getInstance();
        if ($exceptToken) {
            return $db->delete('user_sessions', 'user_id = ? AND token != ?', [$userId, $exceptToken]);
        }
        return $db->delete('user_sessions', 'user_id = ?', [$userId]);
    }
}