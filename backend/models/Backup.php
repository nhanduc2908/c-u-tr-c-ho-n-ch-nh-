<?php
/**
 * BACKUP MODEL
 * 
 * Quản lý backup hệ thống
 * 
 * @package Models
 */

namespace Models;

use Core\Model;
use Core\Database;

class Backup extends Model
{
    protected $table = 'backups';
    protected $primaryKey = 'id';
    protected $fillable = [
        'backup_name', 'backup_type', 'file_path', 'file_size',
        'status', 'restored_at', 'created_by'
    ];
    protected $guarded = ['id', 'created_at'];
    protected $timestamps = false;
    
    /**
     * Lấy người tạo backup
     */
    public function getCreator()
    {
        if (!$this->created_by) return null;
        
        $db = Database::getInstance();
        return $db->fetchOne("SELECT id, username, email FROM users WHERE id = ?", [$this->created_by]);
    }
    
    /**
     * Lấy danh sách backup theo loại
     */
    public static function getByType($type)
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT * FROM backups 
             WHERE backup_type = ? 
             ORDER BY created_at DESC",
            [$type]
        );
    }
    
    /**
     * Lấy backup mới nhất
     */
    public static function getLatest()
    {
        $db = Database::getInstance();
        return $db->fetchOne(
            "SELECT * FROM backups ORDER BY created_at DESC LIMIT 1"
        );
    }
    
    /**
     * Đánh dấu đã restore
     */
    public function markAsRestored()
    {
        $db = Database::getInstance();
        $db->update('backups', ['restored_at' => date('Y-m-d H:i:s')], 'id = ?', [$this->id]);
    }
    
    /**
     * Xóa backup cũ
     */
    public static function deleteOld($olderThanDays = 30)
    {
        $db = Database::getInstance();
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$olderThanDays} days"));
        
        $backups = $db->fetchAll("SELECT id, file_path FROM backups WHERE created_at < ?", [$cutoffDate]);
        
        foreach ($backups as $backup) {
            if (file_exists($backup['file_path'])) {
                unlink($backup['file_path']);
            }
            $db->delete('backups', 'id = ?', [$backup['id']]);
        }
        
        return count($backups);
    }
}