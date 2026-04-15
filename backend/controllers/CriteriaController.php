<?php
/**
 * CRITERIA CONTROLLER
 * 
 * Quản lý 280 tiêu chí đánh giá bảo mật
 * - CRUD criteria
 * - Import/Export Excel
 * - Filter theo category, severity
 * - Bulk operations
 * 
 * @package Controllers
 */

namespace Controllers;

use Core\Controller;
use Core\Database;
use Core\Logger;
use Middleware\PermissionMiddleware;
use Services\ImportExportService;

class CriteriaController extends Controller
{
    private $db;
    private $importExport;
    
    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->importExport = new ImportExportService();
    }
    
    /**
     * GET /api/criteria
     * 
     * Lấy danh sách 280 tiêu chí (có phân trang, filter)
     * Tất cả role đều xem được
     */
    public function index()
    {
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 50);
        $categoryId = $_GET['category_id'] ?? null;
        $severity = $_GET['severity'] ?? null;
        $search = $_GET['search'] ?? null;
        $isActive = $_GET['is_active'] ?? null;
        
        $offset = ($page - 1) * $limit;
        $params = [];
        
        $sql = "SELECT c.*, cat.name as category_name, cat.code as category_code,
                       cat.weight_percent as category_weight
                FROM assessment_criteria c
                JOIN assessment_categories cat ON c.category_id = cat.id
                WHERE 1=1";
        
        if ($categoryId) {
            $sql .= " AND c.category_id = ?";
            $params[] = $categoryId;
        }
        
        if ($severity) {
            $sql .= " AND c.severity = ?";
            $params[] = $severity;
        }
        
        if ($search) {
            $sql .= " AND (c.code LIKE ? OR c.name LIKE ? OR c.description LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        
        if ($isActive !== null) {
            $sql .= " AND c.is_active = ?";
            $params[] = $isActive;
        }
        
        // Đếm tổng số records
        $countSql = str_replace(
            "c.*, cat.name as category_name, cat.code as category_code, cat.weight_percent as category_weight",
            "COUNT(*) as total",
            $sql
        );
        $total = $this->db->fetchColumn($countSql, $params);
        
        // Lấy dữ liệu
        $sql .= " ORDER BY cat.sort_order, c.category_id, c.sort_order, c.code 
                  LIMIT {$limit} OFFSET {$offset}";
        $criteria = $this->db->fetchAll($sql, $params);
        
        // Thống kê theo category và severity
        $stats = $this->getStats();
        
        return $this->success([
            'data' => $criteria,
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit),
            'stats' => $stats
        ]);
    }
    
    /**
     * GET /api/criteria/{id}
     * 
     * Chi tiết 1 tiêu chí
     */
    public function show($id)
    {
        $criteria = $this->db->fetchOne(
            "SELECT c.*, cat.name as category_name, cat.code as category_code,
                    cat.weight_percent as category_weight
             FROM assessment_criteria c
             JOIN assessment_categories cat ON c.category_id = cat.id
             WHERE c.id = ?",
            [$id]
        );
        
        if (!$criteria) {
            return $this->error('Criteria not found', 404);
        }
        
        // Lấy lịch sử đánh giá của tiêu chí này
        $assessmentHistory = $this->db->fetchAll(
            "SELECT ar.server_id, s.name as server_name, ar.status, ar.score_obtained, ar.created_at
             FROM assessment_results ar
             JOIN servers s ON ar.server_id = s.id
             WHERE ar.criteria_id = ?
             ORDER BY ar.created_at DESC
             LIMIT 10",
            [$id]
        );
        
        $criteria['assessment_history'] = $assessmentHistory;
        
        return $this->success($criteria);
    }
    
    /**
     * GET /api/criteria/category/{categoryId}
     * 
     * Lấy tiêu chí theo category
     */
    public function getByCategory($categoryId)
    {
        $category = $this->db->fetchOne("SELECT * FROM assessment_categories WHERE id = ?", [$categoryId]);
        if (!$category) {
            return $this->error('Category not found', 404);
        }
        
        $criteria = $this->db->fetchAll(
            "SELECT * FROM assessment_criteria 
             WHERE category_id = ? AND is_active = 1 
             ORDER BY sort_order, code",
            [$categoryId]
        );
        
        return $this->success([
            'category' => $category,
            'criteria' => $criteria,
            'total' => count($criteria)
        ]);
    }
    
    /**
     * GET /api/criteria/severity/{severity}
     * 
     * Lấy tiêu chí theo severity
     */
    public function getBySeverity($severity)
    {
        $allowedSeverities = ['critical', 'high', 'medium', 'low', 'info'];
        if (!in_array($severity, $allowedSeverities)) {
            return $this->error('Invalid severity', 400);
        }
        
        $criteria = $this->db->fetchAll(
            "SELECT c.*, cat.name as category_name
             FROM assessment_criteria c
             JOIN assessment_categories cat ON c.category_id = cat.id
             WHERE c.severity = ? AND c.is_active = 1
             ORDER BY c.category_id, c.code",
            [$severity]
        );
        
        return $this->success($criteria);
    }
    
    /**
     * GET /api/criteria/stats/summary
     * 
     * Thống kê số lượng tiêu chí
     */
    public function stats()
    {
        $stats = $this->getStats();
        return $this->success($stats);
    }
    
    /**
     * POST /api/criteria
     * 
     * Thêm tiêu chí mới (CHỈ ADMIN)
     */
    public function store()
    {
        PermissionMiddleware::adminOnly();
        
        $data = $this->getRequestData();
        
        $this->validate($data, [
            'code' => 'required|max:20|unique:assessment_criteria,code',
            'name' => 'required|max:255',
            'category_id' => 'required|exists:assessment_categories,id',
            'severity' => 'in:critical,high,medium,low,info',
            'weight' => 'integer|min:1|max:10',
            'check_method' => 'in:auto,manual,api,command,sql'
        ]);
        
        // Kiểm tra code đã tồn tại
        $exists = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM assessment_criteria WHERE code = ?",
            [$data['code']]
        );
        
        if ($exists > 0) {
            return $this->error('Criteria code already exists', 400);
        }
        
        // Set defaults
        $insertData = [
            'code' => $data['code'],
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'check_method' => $data['check_method'] ?? 'auto',
            'check_command' => $data['check_command'] ?? null,
            'api_endpoint' => $data['api_endpoint'] ?? null,
            'expected_value' => $data['expected_value'] ?? null,
            'severity' => $data['severity'] ?? 'medium',
            'weight' => $data['weight'] ?? 1,
            'is_auto_check' => $data['is_auto_check'] ?? 1,
            'requires_manual' => $data['requires_manual'] ?? 0,
            'reference_standard' => $data['reference_standard'] ?? null,
            'remediation_guide' => $data['remediation_guide'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $id = $this->db->insert('assessment_criteria', $insertData);
        
        $this->logAction('CRITERIA_CREATE', "Created criteria: {$data['code']} (ID: {$id})");
        
        return $this->success(['id' => $id], 'Criteria created successfully', 201);
    }
    
    /**
     * PUT /api/criteria/{id}
     * 
     * Cập nhật tiêu chí (CHỈ ADMIN)
     */
    public function update($id)
    {
        PermissionMiddleware::adminOnly();
        
        $criteria = $this->db->fetchOne("SELECT * FROM assessment_criteria WHERE id = ?", [$id]);
        if (!$criteria) {
            return $this->error('Criteria not found', 404);
        }
        
        $data = $this->getRequestData();
        
        // Validate
        if (isset($data['code'])) {
            $exists = $this->db->fetchColumn(
                "SELECT COUNT(*) FROM assessment_criteria WHERE code = ? AND id != ?",
                [$data['code'], $id]
            );
            if ($exists > 0) {
                return $this->error('Criteria code already exists', 400);
            }
        }
        
        // Không cho phép cập nhật id, created_at
        unset($data['id'], $data['created_at']);
        
        $allowedFields = [
            'code', 'category_id', 'name', 'description', 'check_method',
            'check_command', 'api_endpoint', 'expected_value', 'severity',
            'weight', 'is_auto_check', 'requires_manual', 'reference_standard',
            'remediation_guide', 'sort_order', 'is_active'
        ];
        
        $updateData = array_intersect_key($data, array_flip($allowedFields));
        
        if (!empty($updateData)) {
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            $this->db->update('assessment_criteria', $updateData, 'id = ?', [$id]);
        }
        
        $this->logAction('CRITERIA_UPDATE', "Updated criteria ID: {$id}");
        
        return $this->success([], 'Criteria updated successfully');
    }
    
    /**
     * PATCH /api/criteria/{id}
     * 
     * Cập nhật một phần tiêu chí (CHỦ ADMIN hoặc SECURITY_OFFICER)
     */
    public function partialUpdate($id)
    {
        PermissionMiddleware::officerOrAbove();
        
        $criteria = $this->db->fetchOne("SELECT * FROM assessment_criteria WHERE id = ?", [$id]);
        if (!$criteria) {
            return $this->error('Criteria not found', 404);
        }
        
        $data = $this->getRequestData();
        
        // Chỉ cho phép cập nhật một số field
        $allowedFields = ['weight', 'severity', 'is_active', 'remediation_guide'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));
        
        if (!empty($updateData)) {
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            $this->db->update('assessment_criteria', $updateData, 'id = ?', [$id]);
        }
        
        $this->logAction('CRITERIA_PARTIAL_UPDATE', "Partially updated criteria ID: {$id}");
        
        return $this->success([], 'Criteria updated successfully');
    }
    
    /**
     * DELETE /api/criteria/{id}
     * 
     * Xóa tiêu chí (CHỈ ADMIN)
     */
    public function destroy($id)
    {
        PermissionMiddleware::adminOnly();
        
        $criteria = $this->db->fetchOne("SELECT * FROM assessment_criteria WHERE id = ?", [$id]);
        if (!$criteria) {
            return $this->error('Criteria not found', 404);
        }
        
        // Kiểm tra có đang được sử dụng trong assessment không
        $used = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM assessment_results WHERE criteria_id = ?",
            [$id]
        );
        
        if ($used > 0) {
            return $this->error(
                "Cannot delete criteria because it has {$used} assessment result(s). " .
                "Please archive it instead by setting is_active = 0",
                400
            );
        }
        
        $this->db->delete('assessment_criteria', 'id = ?', [$id]);
        
        $this->logAction('CRITERIA_DELETE', "Deleted criteria: {$criteria['code']} (ID: {$id})");
        
        return $this->success([], 'Criteria deleted successfully');
    }
    
    /**
     * POST /api/criteria/import
     * 
     * Import tiêu chí từ Excel (CHỈ ADMIN)
     */
    public function import()
    {
        PermissionMiddleware::adminOnly();
        
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            return $this->error('No file uploaded or upload failed', 400);
        }
        
        $file = $_FILES['file'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        $allowedExtensions = ['xlsx', 'xls', 'csv'];
        if (!in_array($extension, $allowedExtensions)) {
            return $this->error('Invalid file type. Allowed: xlsx, xls, csv', 400);
        }
        
        try {
            $result = $this->importExport->importCriteria($file['tmp_name'], $extension);
            
            $this->logAction('CRITERIA_IMPORT', "Imported {$result['imported']} criteria, {$result['skipped']} skipped");
            
            return $this->success([
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
                'total' => $result['total']
            ], "Imported {$result['imported']} criteria successfully");
            
        } catch (\Exception $e) {
            Logger::error('Import failed', ['error' => $e->getMessage()]);
            return $this->error('Import failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/criteria/export
     * 
     * Export tiêu chí ra Excel (CHỈ ADMIN)
     */
    public function export()
    {
        PermissionMiddleware::adminOnly();
        
        $format = $_GET['format'] ?? 'xlsx';
        $categoryId = $_GET['category_id'] ?? null;
        
        $sql = "SELECT c.*, cat.name as category_name, cat.code as category_code
                FROM assessment_criteria c
                JOIN assessment_categories cat ON c.category_id = cat.id
                WHERE 1=1";
        
        $params = [];
        if ($categoryId) {
            $sql .= " AND c.category_id = ?";
            $params[] = $categoryId;
        }
        
        $sql .= " ORDER BY cat.sort_order, c.category_id, c.sort_order, c.code";
        
        $criteria = $this->db->fetchAll($sql, $params);
        
        try {
            $result = $this->importExport->exportCriteria($criteria, $format);
            
            $this->logAction('CRITERIA_EXPORT', "Exported " . count($criteria) . " criteria to {$format}");
            
            // Xuất file
            header('Content-Type: ' . $result['mime']);
            header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
            header('Content-Length: ' . strlen($result['content']));
            echo $result['content'];
            exit;
            
        } catch (\Exception $e) {
            return $this->error('Export failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * POST /api/criteria/import-json
     * 
     * Import tiêu chí từ JSON (CHỈ ADMIN)
     */
    public function importJson()
    {
        PermissionMiddleware::adminOnly();
        
        $data = $this->getRequestData();
        
        if (!isset($data['criteria']) || !is_array($data['criteria'])) {
            return $this->error('Invalid JSON data', 400);
        }
        
        $imported = 0;
        $errors = [];
        
        foreach ($data['criteria'] as $item) {
            // Validate required fields
            if (empty($item['code']) || empty($item['name']) || empty($item['category_id'])) {
                $errors[] = "Missing required fields for item: " . json_encode($item);
                continue;
            }
            
            // Check if exists
            $exists = $this->db->fetchColumn(
                "SELECT COUNT(*) FROM assessment_criteria WHERE code = ?",
                [$item['code']]
            );
            
            if ($exists) {
                $errors[] = "Criteria code {$item['code']} already exists";
                continue;
            }
            
            $insertData = [
                'code' => $item['code'],
                'category_id' => $item['category_id'],
                'name' => $item['name'],
                'description' => $item['description'] ?? null,
                'severity' => $item['severity'] ?? 'medium',
                'weight' => $item['weight'] ?? 1,
                'check_method' => $item['check_method'] ?? 'auto',
                'is_active' => $item['is_active'] ?? 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->insert('assessment_criteria', $insertData);
            $imported++;
        }
        
        $this->logAction('CRITERIA_IMPORT_JSON', "Imported {$imported} criteria from JSON");
        
        return $this->success([
            'imported' => $imported,
            'errors' => $errors
        ], "Imported {$imported} criteria from JSON");
    }
    
    /**
     * GET /api/criteria/export-json
     * 
     * Export tiêu chí ra JSON (CHỈ ADMIN)
     */
    public function exportJson()
    {
        PermissionMiddleware::adminOnly();
        
        $criteria = $this->db->fetchAll(
            "SELECT c.*, cat.name as category_name
             FROM assessment_criteria c
             JOIN assessment_categories cat ON c.category_id = cat.id
             ORDER BY cat.sort_order, c.code"
        );
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="criteria_export_' . date('Y-m-d') . '.json"');
        echo json_encode($criteria, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * POST /api/criteria/bulk-update
     * 
     * Cập nhật nhiều tiêu chí cùng lúc (CHỦ ADMIN)
     */
    public function bulkUpdate()
    {
        PermissionMiddleware::adminOnly();
        
        $data = $this->getRequestData();
        
        if (!isset($data['criteria_ids']) || !is_array($data['criteria_ids'])) {
            return $this->error('criteria_ids array required', 400);
        }
        
        $allowedFields = ['severity', 'weight', 'is_active'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));
        
        if (empty($updateData)) {
            return $this->error('No fields to update', 400);
        }
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        
        $updated = 0;
        foreach ($data['criteria_ids'] as $id) {
            $this->db->update('assessment_criteria', $updateData, 'id = ?', [$id]);
            $updated++;
        }
        
        $this->logAction('CRITERIA_BULK_UPDATE', "Bulk updated {$updated} criteria");
        
        return $this->success(['updated' => $updated], "Updated {$updated} criteria");
    }
    
    /**
     * POST /api/criteria/bulk-status
     * 
     * Active/Inactive nhiều tiêu chí (CHỈ ADMIN)
     */
    public function bulkStatus()
    {
        PermissionMiddleware::adminOnly();
        
        $data = $this->getRequestData();
        
        $this->validate($data, [
            'criteria_ids' => 'required|array',
            'is_active' => 'required|boolean'
        ]);
        
        $isActive = $data['is_active'] ? 1 : 0;
        $updated = 0;
        
        foreach ($data['criteria_ids'] as $id) {
            $this->db->update('assessment_criteria', 
                ['is_active' => $isActive, 'updated_at' => date('Y-m-d H:i:s')], 
                'id = ?', 
                [$id]
            );
            $updated++;
        }
        
        $action = $isActive ? 'activated' : 'deactivated';
        $this->logAction('CRITERIA_BULK_STATUS', "Bulk {$action} {$updated} criteria");
        
        return $this->success(['updated' => $updated], "{$action} {$updated} criteria");
    }
    
    /**
     * POST /api/criteria/{id}/clone
     * 
     * Clone tiêu chí (CHỈ ADMIN)
     */
    public function clone($id)
    {
        PermissionMiddleware::adminOnly();
        
        $original = $this->db->fetchOne("SELECT * FROM assessment_criteria WHERE id = ?", [$id]);
        if (!$original) {
            return $this->error('Criteria not found', 404);
        }
        
        // Tạo code mới
        $newCode = $original['code'] . '_copy';
        $counter = 1;
        while ($this->db->fetchColumn("SELECT COUNT(*) FROM assessment_criteria WHERE code = ?", [$newCode]) > 0) {
            $newCode = $original['code'] . '_copy' . $counter;
            $counter++;
        }
        
        // Clone data
        unset($original['id']);
        $original['code'] = $newCode;
        $original['name'] = $original['name'] . ' (Copy)';
        $original['created_at'] = date('Y-m-d H:i:s');
        $original['updated_at'] = date('Y-m-d H:i:s');
        
        $newId = $this->db->insert('assessment_criteria', $original);
        
        $this->logAction('CRITERIA_CLONE', "Cloned criteria ID: {$id} to new ID: {$newId}");
        
        return $this->success(['id' => $newId, 'code' => $newCode], 'Criteria cloned successfully');
    }
    
    /**
     * GET /api/criteria/template/download
     * 
     * Tải template import Excel
     */
    public function downloadTemplate()
    {
        $format = $_GET['format'] ?? 'xlsx';
        
        $template = $this->importExport->getImportTemplate($format);
        
        header('Content-Type: ' . $template['mime']);
        header('Content-Disposition: attachment; filename="' . $template['filename'] . '"');
        echo $template['content'];
        exit;
    }
    
    /**
     * Lấy thống kê số lượng tiêu chí
     */
    private function getStats()
    {
        // Tổng số tiêu chí
        $total = $this->db->fetchColumn("SELECT COUNT(*) FROM assessment_criteria");
        $active = $this->db->fetchColumn("SELECT COUNT(*) FROM assessment_criteria WHERE is_active = 1");
        $inactive = $total - $active;
        
        // Theo category
        $byCategory = $this->db->fetchAll(
            "SELECT cat.id, cat.code, cat.name, COUNT(c.id) as total
             FROM assessment_categories cat
             LEFT JOIN assessment_criteria c ON cat.id = c.category_id AND c.is_active = 1
             GROUP BY cat.id
             ORDER BY cat.sort_order"
        );
        
        // Theo severity
        $bySeverity = $this->db->fetchAll(
            "SELECT severity, COUNT(*) as total 
             FROM assessment_criteria 
             WHERE is_active = 1
             GROUP BY severity"
        );
        
        // Theo check method
        $byMethod = $this->db->fetchAll(
            "SELECT check_method, COUNT(*) as total 
             FROM assessment_criteria 
             WHERE is_active = 1
             GROUP BY check_method"
        );
        
        return [
            'total' => (int)$total,
            'active' => (int)$active,
            'inactive' => (int)$inactive,
            'by_category' => $byCategory,
            'by_severity' => $bySeverity,
            'by_method' => $byMethod
        ];
    }
}