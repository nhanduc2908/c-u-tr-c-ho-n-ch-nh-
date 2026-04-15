<?php
/**
 * ROLE MODEL
 * 
 * Quản lý vai trò người dùng
 * 
 * @package Models
 */

namespace Models;

use Core\Model;
use Core\Database;

class Role extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'id';
    protected $fillable = ['name', 'description'];
    protected $guarded = ['id', 'created_at'];
    protected $timestamps = true;
    
    /**
     * Lấy danh sách user thuộc role
     */
    public function getUsers()
    {
        $db = Database::getInstance();
        return $db->fetchAll("SELECT * FROM users WHERE role_id = ?", [$this->id]);
    }
    
    /**
     * Lấy danh sách permissions của role
     */
    public function getPermissions()
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT p.* FROM permissions p
             JOIN role_permission rp ON p.id = rp.permission_id
             WHERE rp.role_id = ?",
            [$this->id]
        );
    }
    
    /**
     * Gán permission cho role
     */
    public function assignPermission($permissionId)
    {
        $db = Database::getInstance();
        $exists = $db->fetchColumn(
            "SELECT COUNT(*) FROM role_permission WHERE role_id = ? AND permission_id = ?",
            [$this->id, $permissionId]
        );
        
        if (!$exists) {
            $db->insert('role_permission', [
                'role_id' => $this->id,
                'permission_id' => $permissionId
            ]);
        }
    }
    
    /**
     * Xóa permission khỏi role
     */
    public function removePermission($permissionId)
    {
        $db = Database::getInstance();
        $db->delete('role_permission', 'role_id = ? AND permission_id = ?', [$this->id, $permissionId]);
    }
    
    /**
     * Gán nhiều permissions
     */
    public function syncPermissions($permissionIds)
    {
        $db = Database::getInstance();
        
        // Xóa tất cả permissions cũ
        $db->delete('role_permission', 'role_id = ?', [$this->id]);
        
        // Thêm permissions mới
        foreach ($permissionIds as $permId) {
            $db->insert('role_permission', [
                'role_id' => $this->id,
                'permission_id' => $permId
            ]);
        }
    }
    
    /**
     * Kiểm tra role có permission không
     */
    public function hasPermission($permissionName)
    {
        $db = Database::getInstance();
        $count = $db->fetchColumn(
            "SELECT COUNT(*) FROM permissions p
             JOIN role_permission rp ON p.id = rp.permission_id
             WHERE rp.role_id = ? AND p.name = ?",
            [$this->id, $permissionName]
        );
        return $count > 0;
    }
}