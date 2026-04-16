<?php
/**
 * PERMISSION MODEL
 * 
 * Quản lý quyền chi tiết
 * 
 * @package Models
 */

namespace Models;

use Core\Model;
use Core\Database;

class Permission extends Model
{
    protected $table = 'permissions';
    protected $primaryKey = 'id';
    protected $fillable = ['name', 'module', 'description'];
    protected $guarded = ['id'];
    protected $timestamps = false;
    
    /**
     * Lấy danh sách roles có permission này
     */
    public function getRoles()
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT r.* FROM roles r
             JOIN role_permission rp ON r.id = rp.role_id
             WHERE rp.permission_id = ?",
            [$this->id]
        );
    }
    
    /**
     * Lấy permissions theo module
     */
    public static function getByModule($module)
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT * FROM permissions WHERE module = ? ORDER BY name",
            [$module]
        );
    }
    
    /**
     * Lấy tất cả permissions group theo module
     */
    public static function getGrouped()
    {
        $db = Database::getInstance();
        $permissions = $db->fetchAll("SELECT * FROM permissions ORDER BY module, name");
        
        $grouped = [];
        foreach ($permissions as $perm) {
            $module = $perm['module'] ?? 'other';
            if (!isset($grouped[$module])) {
                $grouped[$module] = [];
            }
            $grouped[$module][] = $perm;
        }
        
        return $grouped;
    }
}