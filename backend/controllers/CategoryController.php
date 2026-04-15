<?php
/**
 * CATEGORY CONTROLLER
 * 
 * Quản lý 17 lĩnh vực đánh giá
 * - CRUD categories
 * - Sắp xếp categories
 * - Lấy criteria theo category
 * 
 * @package Controllers
 */

namespace Controllers;

use Core\Controller;
use Core\Database;

class CategoryController extends Controller
{
    private $db;
    
    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
    }
    
    /**
     * GET /api/categories
     * 
     * Lấy danh sách 17 lĩnh vực
     */
    public function index()
    {
        $categories = $this->db->fetchAll(
            "SELECT c.*, 
                    (SELECT COUNT(*) FROM assessment_criteria WHERE category_id = c.id AND is_active = 1) as criteria_count
             FROM assessment_categories c
             WHERE c.is_active = 1
             ORDER BY c.sort_order, c.id"
        );
        
        return $this->success($categories);
    }
    
    /**
     * GET /api/categories/{id}
     * 
     * Chi tiết category
     */
    public function show($id)
    {
        $category = $this->db->fetchOne(
            "SELECT * FROM assessment_categories WHERE id = ?",
            [$id]
        );
        
        if (!$category) {
            return $this->error('Category not found', 404);
        }
        
        // Lấy danh sách criteria thuộc category
        $criteria = $this->db->fetchAll(
            "SELECT * FROM assessment_criteria 
             WHERE category_id = ? AND is_active = 1 
             ORDER BY sort_order, code",
            [$id]
        );
        
        $category['criteria'] = $criteria;
        $category['criteria_count'] = count($criteria);
        
        return $this->success($category);
    }
    
    /**
     * GET /api/categories/{id}/criteria
     * 
     * Lấy criteria theo category
     */
    public function getCriteria($id)
    {
        $category = $this->db->fetchOne("SELECT id FROM assessment_categories WHERE id = ?", [$id]);
        if (!$category) {
            return $this->error('Category not found', 404);
        }
        
        $criteria = $this->db->fetchAll(
            "SELECT * FROM assessment_criteria 
             WHERE category_id = ? AND is_active = 1 
             ORDER BY sort_order, code",
            [$id]
        );
        
        return $this->success($criteria);
    }
    
    /**
     * POST /api/categories
     * 
     * Thêm category mới (CHỈ ADMIN)
     */
    public function store()
    {
        $this->requireAdmin();
        
        $data = $this->getRequestData();
        
        $this->validate($data, [
            'code' => 'required|max:20|unique:assessment_categories,code',
            'name' => 'required|max:100',
            'weight_percent' => 'numeric|min:0|max:100',
            'sort_order' => 'integer'
        ]);
        
        $categoryId = $this->db->insert('assessment_categories', [
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'weight_percent' => $data['weight_percent'] ?? 0,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $this->logAction('CATEGORY_CREATE', "Created category: {$data['name']} (ID: {$categoryId})");
        
        return $this->success(['id' => $categoryId], 'Category created successfully', 201);
    }
    
    /**
     * PUT /api/categories/{id}
     * 
     * Cập nhật category (CHỈ ADMIN)
     */
    public function update($id)
    {
        $this->requireAdmin();
        
        $category = $this->db->fetchOne("SELECT * FROM assessment_categories WHERE id = ?", [$id]);
        if (!$category) {
            return $this->error('Category not found', 404);
        }
        
        $data = $this->getRequestData();
        
        $allowedFields = ['code', 'name', 'description', 'weight_percent', 'sort_order', 'is_active'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));
        
        if (!empty($updateData)) {
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            $this->db->update('assessment_categories', $updateData, 'id = ?', [$id]);
        }
        
        $this->logAction('CATEGORY_UPDATE', "Updated category ID: {$id}");
        
        return $this->success([], 'Category updated successfully');
    }
    
    /**
     * DELETE /api/categories/{id}
     * 
     * Xóa category (CHỈ ADMIN)
     */
    public function destroy($id)
    {
        $this->requireAdmin();
        
        $category = $this->db->fetchOne("SELECT * FROM assessment_categories WHERE id = ?", [$id]);
        if (!$category) {
            return $this->error('Category not found', 404);
        }
        
        // Kiểm tra có criteria nào đang dùng không
        $criteriaCount = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM assessment_criteria WHERE category_id = ?",
            [$id]
        );
        
        if ($criteriaCount > 0) {
            return $this->error("Cannot delete category because it has {$criteriaCount} criteria", 400);
        }
        
        $this->db->delete('assessment_categories', 'id = ?', [$id]);
        
        $this->logAction('CATEGORY_DELETE', "Deleted category: {$category['name']} (ID: {$id})");
        
        return $this->success([], 'Category deleted successfully');
    }
    
    /**
     * POST /api/categories/reorder
     * 
     * Sắp xếp lại categories (CHỈ ADMIN)
     */
    public function reorder()
    {
        $this->requireAdmin();
        
        $data = $this->getRequestData();
        
        if (!isset($data['order']) || !is_array($data['order'])) {
            return $this->error('Invalid order data', 400);
        }
        
        foreach ($data['order'] as $index => $categoryId) {
            $this->db->update('assessment_categories', 
                ['sort_order' => $index + 1], 
                'id = ?', 
                [$categoryId]
            );
        }
        
        $this->logAction('CATEGORY_REORDER', "Reordered categories");
        
        return $this->success([], 'Categories reordered successfully');
    }
    
    /**
     * Kiểm tra quyền ADMIN
     */
    private function requireAdmin()
    {
        $role = $this->getCurrentRole();
        if ($role !== 'admin') {
            $this->error('Admin only', 403);
        }
    }
}
