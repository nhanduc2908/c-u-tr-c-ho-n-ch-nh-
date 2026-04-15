<?php
/**
 * USER MODEL
 * 
 * Quản lý thông tin người dùng
 * 
 * @package Models
 */

namespace Models;

use Core\Model;
use Core\Database;

class User extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $fillable = [
        'username', 'email', 'password_hash', 'full_name', 'avatar',
        'phone', 'address', 'role_id', 'is_active', 'last_login',
        'last_ip', 'two_factor_enabled', 'two_factor_secret',
        'email_verified_at'
    ];
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $timestamps = true;
    
    /**
     * Xác thực mật khẩu
     */
    public function verifyPassword($password)
    {
        return password_verify($password, $this->password_hash);
    }
    
    /**
     * Mã hóa mật khẩu
     */
    public function setPassword($password)
    {
        $this->password_hash = password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Lấy role của user
     */
    public function getRole()
    {
        $db = Database::getInstance();
        return $db->fetchOne("SELECT * FROM roles WHERE id = ?", [$this->role_id]);
    }
    
    /**
     * Lấy danh sách permissions của user
     */
    public function getPermissions()
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT p.* FROM permissions p
             JOIN role_permission rp ON p.id = rp.permission_id
             WHERE rp.role_id = ?",
            [$this->role_id]
        );
    }
    
    /**
     * Kiểm tra user có permission không
     */
    public function hasPermission($permissionName)
    {
        $db = Database::getInstance();
        $count = $db->fetchColumn(
            "SELECT COUNT(*) FROM permissions p
             JOIN role_permission rp ON p.id = rp.permission_id
             WHERE rp.role_id = ? AND p.name = ?",
            [$this->role_id, $permissionName]
        );
        return $count > 0;
    }
    
    /**
     * Kiểm tra user có phải admin không
     */
    public function isAdmin()
    {
        $role = $this->getRole();
        return $role && $role['name'] === 'admin';
    }
    
    /**
     * Cập nhật last_login
     */
    public function updateLastLogin($ip)
    {
        $db = Database::getInstance();
        $db->update('users', [
            'last_login' => date('Y-m-d H:i:s'),
            'last_ip' => $ip
        ], 'id = ?', [$this->id]);
    }
    
    /**
     * Lấy danh sách server được gán (cho viewer)
     */
    public function getAssignedServers()
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT s.* FROM servers s
             JOIN user_server us ON s.id = us.server_id
             WHERE us.user_id = ?",
            [$this->id]
        );
    }
    
    /**
     * Gán server cho user
     */
    public function assignServer($serverId)
    {
        $db = Database::getInstance();
        $exists = $db->fetchColumn(
            "SELECT COUNT(*) FROM user_server WHERE user_id = ? AND server_id = ?",
            [$this->id, $serverId]
        );
        
        if (!$exists) {
            $db->insert('user_server', [
                'user_id' => $this->id,
                'server_id' => $serverId,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
    
    /**
     * Xóa gán server
     */
    public function removeServer($serverId)
    {
        $db = Database::getInstance();
        $db->delete('user_server', 'user_id = ? AND server_id = ?', [$this->id, $serverId]);
    }
}