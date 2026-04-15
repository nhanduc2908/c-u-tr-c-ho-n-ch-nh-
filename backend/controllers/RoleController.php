<?php
/**
 * ROLE CONTROLLER
 * 
 * Quản lý vai trò và quyền - CHỈ ADMIN
 * - CRUD roles
 * - Quản lý permissions
 * - Gán permission cho role
 * 
 * @package Controllers
 */

namespace Controllers;

use Core\Controller;
use Core\Database;

class RoleController extends Controller
{
    private $db;
    
    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
    }
    
    /**
     * GET /api/roles
     * 
     * Lấy danh sách roles
     */
    public function index()
    {
        $roles = $this->db->fetchAll(
            "SELECT r.*, COUNT(u.id) as user_count 
             FROM roles r
             LEFT JOIN users u ON r.id = u.role_id
             GROUP BY r.id
             ORDER BY r.id"
        );
        
        return $this->success($roles);
    }
    
    /**
     * GET /api/roles/{id}
     * 
     * Chi tiết role
     */
    public function show($id)
    {
        $role = $this->db->fetchOne("SELECT * FROM roles WHERE id = ?", [$id]);
        
        if (!$role) {
            return $this->error('Role not found', 404);
        }
        
        // Lấy permissions của role
        $permissions = $this->db->fetchAll(
            "SELECT p.* FROM permissions p
             JOIN role_permission rp ON p.id = rp.permission_id
             WHERE rp.role_id = ?",
            [$id]
        );
        
        $role['permissions'] = $permissions;
        
        return $this->success($role);
    }
    
    /**
     * POST /api/roles
     * 
     * Tạo role mới
     */
    public function store()
    {
        $data = $this->getRequestData();
        
        $this->validate($data, [
            'name' => 'required|unique:roles,name',
            'description' => 'max:255'
        ]);
        
        $roleId = $this->db->insert('roles', [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $this->logAction('ROLE_CREATE', "Created role: {$data['name']} (ID: {$roleId})");
        
        return $this->success(['id' => $roleId], 'Role created successfully', 201);
    }
    
    /**
     * PUT /api/roles/{id}
     * 
     * Cập nhật role
     */
    public function update($id)
    {
        $data = $this->getRequestData();
        
        $role = $this->db->fetchOne("SELECT * FROM roles WHERE id = ?", [$id]);
        if (!$role) {
            return $this->error('Role not found', 404);
        }
        
        // Không cho sửa role admin nếu chỉ có 1 admin
        if ($role['name'] === 'admin') {
            $adminCount = $this->db->fetchColumn("SELECT COUNT(*) FROM users WHERE role_id = ?", [$id]);
            if ($adminCount <= 1 && isset($data['name']) && $data['name'] !== 'admin') {
                return $this->error('Cannot rename admin role when only one admin exists', 400);
            }
        }
        
        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        
        if (!empty($updateData)) {
            $this->db->update('roles', $updateData, 'id = ?', [$id]);
        }
        
        $this->logAction('ROLE_UPDATE', "Updated role ID: {$id}");
        
        return $this->success([], 'Role updated successfully');
    }
    
    /**
     * DELETE /api/roles/{id}
     * 
     * Xóa role
     */
    public function destroy($id)
    {
        $role = $this->db->fetchOne("SELECT * FROM roles WHERE id = ?", [$id]);
        if (!$role) {
            return $this->error('Role not found', 404);
        }
        
        // Không cho xóa role admin
        if ($role['name'] === 'admin') {
            return $this->error('Cannot delete admin role', 400);
        }
        
        // Kiểm tra có user nào đang dùng role này không
        $userCount = $this->db->fetchColumn("SELECT COUNT(*) FROM users WHERE role_id = ?", [$id]);
        if ($userCount > 0) {
            return $this->error("Cannot delete role because it is assigned to {$userCount} user(s)", 400);
        }
        
        // Xóa role_permission trước
        $this->db->delete('role_permission', 'role_id = ?', [$id]);
        
        // Xóa role
        $this->db->delete('roles', 'id = ?', [$id]);
        
        $this->logAction('ROLE_DELETE', "Deleted role: {$role['name']} (ID: {$id})");
        
        return $this->success([], 'Role deleted successfully');
    }
    
    /**
     * GET /api/roles/permissions
     * 
     * Lấy danh sách tất cả permissions
     */
    public function permissions()
    {
        $permissions = $this->db->fetchAll(
            "SELECT * FROM permissions ORDER BY module, name"
        );
        
        // Nhóm theo module
        $grouped = [];
        foreach ($permissions as $perm) {
            $module = $perm['module'] ?? 'other';
            if (!isset($grouped[$module])) {
                $grouped[$module] = [];
            }
            $grouped[$module][] = $perm;
        }
        
        return $this->success([
            'all' => $permissions,
            'grouped' => $grouped
        ]);
    }
    
    /**
     * POST /api/roles/{id}/permissions
     * 
     * Gán permission cho role
     */
    public function assignPermissions($id)
    {
        $data = $this->getRequestData();
        
        $role = $this->db->fetchOne("SELECT * FROM roles WHERE id = ?", [$id]);
        if (!$role) {
            return $this->error('Role not found', 404);
        }
        
        // Xóa permissions cũ
        $this->db->delete('role_permission', 'role_id = ?', [$id]);
        
        // Thêm permissions mới
        if (isset($data['permission_ids']) && is_array($data['permission_ids'])) {
            foreach ($data['permission_ids'] as $permId) {
                $this->db->insert('role_permission', [
                    'role_id' => $id,
                    'permission_id' => $permId
                ]);
            }
        }
        
        $this->logAction('ROLE_PERMISSIONS', "Updated permissions for role ID: {$id}");
        
        return $this->success([], 'Permissions assigned successfully');
    }
}