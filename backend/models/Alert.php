<?php
/**
 * ALERT MODEL
 * 
 * Quản lý cảnh báo an ninh
 * 
 * @package Models
 */

namespace Models;

use Core\Model;
use Core\Database;

class Alert extends Model
{
    protected $table = 'alerts';
    protected $primaryKey = 'id';
    protected $fillable = [
        'server_id', 'type', 'severity', 'title', 'message',
        'is_read', 'is_resolved', 'reference_id', 'reference_type',
        'assigned_to', 'resolution_note', 'resolved_by', 'resolved_at',
        'acknowledged_by', 'acknowledged_at'
    ];
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $timestamps = true;
    
    /**
     * Lấy thông tin server
     */
    public function getServer()
    {
        if (!$this->server_id) return null;
        
        $db = Database::getInstance();
        return $db->fetchOne("SELECT * FROM servers WHERE id = ?", [$this->server_id]);
    }
    
    /**
     * Lấy người xử lý
     */
    public function getResolver()
    {
        if (!$this->resolved_by) return null;
        
        $db = Database::getInstance();
        return $db->fetchOne("SELECT id, username, email FROM users WHERE id = ?", [$this->resolved_by]);
    }
    
    /**
     * Đánh dấu đã đọc
     */
    public function acknowledge($userId)
    {
        $db = Database::getInstance();
        $db->update('alerts', [
            'is_read' => 1,
            'acknowledged_by' => $userId,
            'acknowledged_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$this->id]);
    }
    
    /**
     * Đánh dấu đã giải quyết
     */
    public function resolve($userId, $note = null)
    {
        $db = Database::getInstance();
        $db->update('alerts', [
            'is_resolved' => 1,
            'resolution_note' => $note,
            'resolved_by' => $userId,
            'resolved_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$this->id]);
    }
    
    /**
     * Gán cho người xử lý
     */
    public function assignTo($userId)
    {
        $db = Database::getInstance();
        $db->update('alerts', ['assigned_to' => $userId], 'id = ?', [$this->id]);
    }
    
    /**
     * Lấy các alert chưa giải quyết
     */
    public static function getUnresolved()
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT a.*, s.name as server_name
             FROM alerts a
             LEFT JOIN servers s ON a.server_id = s.id
             WHERE a.is_resolved = 0
             ORDER BY 
                CASE a.severity 
                    WHEN 'critical' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'medium' THEN 3 
                    WHEN 'low' THEN 4 
                END,
                a.created_at DESC"
        );
    }
}