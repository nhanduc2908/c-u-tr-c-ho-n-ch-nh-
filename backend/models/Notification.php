<?php
/**
 * NOTIFICATION MODEL
 * 
 * Quản lý thông báo người dùng
 * 
 * @package Models
 */

namespace Models;

use Core\Model;
use Core\Database;

class Notification extends Model
{
    protected $table = 'notifications';
    protected $primaryKey = 'id';
    protected $fillable = [
        'user_id', 'type', 'title', 'message', 'data', 
        'is_read', 'read_at', 'reference_id', 'reference_type'
    ];
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $timestamps = true;
    
    /**
     * Gửi thông báo cho user
     */
    public static function send($userId, $title, $message, $type = 'info', $data = null)
    {
        $db = Database::getInstance();
        return $db->insert('notifications', [
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data ? json_encode($data) : null,
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Gửi thông báo cho nhiều user
     */
    public static function sendBulk($userIds, $title, $message, $type = 'info', $data = null)
    {
        $db = Database::getInstance();
        $count = 0;
        
        foreach ($userIds as $userId) {
            $db->insert('notifications', [
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data ? json_encode($data) : null,
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            $count++;
        }
        
        return $count;
    }
    
    /**
     * Đánh dấu đã đọc
     */
    public function markAsRead()
    {
        $db = Database::getInstance();
        $db->update('notifications', [
            'is_read' => 1,
            'read_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$this->id]);
    }
    
    /**
     * Lấy thông báo chưa đọc của user
     */
    public static function getUnread($userId)
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT * FROM notifications 
             WHERE user_id = ? AND is_read = 0 
             ORDER BY created_at DESC",
            [$userId]
        );
    }
    
    /**
     * Đánh dấu tất cả đã đọc
     */
    public static function markAllAsRead($userId)
    {
        $db = Database::getInstance();
        return $db->update('notifications', 
            ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
            'user_id = ? AND is_read = 0',
            [$userId]
        );
    }
    
    /**
     * Xóa thông báo cũ
     */
    public static function deleteOld($olderThanDays = 30)
    {
        $db = Database::getInstance();
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$olderThanDays} days"));
        return $db->delete('notifications', 'created_at < ? AND is_read = 1', [$cutoffDate]);
    }
}
