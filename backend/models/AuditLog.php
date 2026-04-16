<?php
/**
 * AUDIT LOG MODEL
 * 
 * Ghi lại mọi hành động trong hệ thống
 * 
 * @package Models
 */

namespace Models;

use Core\Model;
use Core\Database;

class AuditLog extends Model
{
    protected $table = 'audit_logs';
    protected $primaryKey = 'id';
    protected $fillable = [
        'user_id', 'action', 'details', 'ip_address', 'user_agent'
    ];
    protected $guarded = ['id', 'created_at'];
    protected $timestamps = false;
    
    /**
     * Lấy thông tin user
     */
    public function getUser()
    {
        if (!$this->user_id) return null;
        
        $db = Database::getInstance();
        return $db->fetchOne("SELECT id, username, email FROM users WHERE id = ?", [$this->user_id]);
    }
    
    /**
     * Ghi log hành động
     */
    public static function log($userId, $action, $details = null, $ip = null, $userAgent = null)
    {
        $db = Database::getInstance();
        return $db->insert('audit_logs', [
            'user_id' => $userId,
            'action' => $action,
            'details' => $details,
            'ip_address' => $ip ?? $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Lấy log theo user
     */
    public static function getByUser($userId, $limit = 50)
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT * FROM audit_logs 
             WHERE user_id = ? 
             ORDER BY created_at DESC 
             LIMIT ?",
            [$userId, $limit]
        );
    }
    
    /**
     * Lấy log theo action
     */
    public static function getByAction($action, $limit = 50)
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT al.*, u.username 
             FROM audit_logs al
             LEFT JOIN users u ON al.user_id = u.id
             WHERE al.action = ?
             ORDER BY al.created_at DESC
             LIMIT ?",
            [$action, $limit]
        );
    }
    
    /**
     * Xóa log cũ
     */
    public static function cleanup($olderThanDays = 90)
    {
        $db = Database::getInstance();
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$olderThanDays} days"));
        return $db->delete('audit_logs', 'created_at < ?', [$cutoffDate]);
    }
}