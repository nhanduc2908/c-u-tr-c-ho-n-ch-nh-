<?php
/**
 * CRITERIA MODEL
 * 
 * Quản lý 280 tiêu chí đánh giá
 * 
 * @package Models
 */

namespace Models;

use Core\Model;
use Core\Database;

class Criteria extends Model
{
    protected $table = 'assessment_criteria';
    protected $primaryKey = 'id';
    protected $fillable = [
        'code', 'category_id', 'name', 'description', 'check_method',
        'check_command', 'api_endpoint', 'sql_query', 'expected_value',
        'severity', 'weight', 'is_auto_check', 'requires_manual',
        'requires_evidence', 'reference_standard', 'remediation_guide',
        'sort_order', 'is_active'
    ];
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $timestamps = true;
    
    /**
     * Lấy category của tiêu chí
     */
    public function getCategory()
    {
        $db = Database::getInstance();
        return $db->fetchOne("SELECT * FROM assessment_categories WHERE id = ?", [$this->category_id]);
    }
    
    /**
     * Lấy kết quả đánh giá gần nhất của tiêu chí cho một server
     */
    public function getLatestResultForServer($serverId)
    {
        $db = Database::getInstance();
        return $db->fetchOne(
            "SELECT * FROM assessment_results 
             WHERE criteria_id = ? AND server_id = ? 
             ORDER BY created_at DESC 
             LIMIT 1",
            [$this->id, $serverId]
        );
    }
    
    /**
     * Lấy lịch sử đánh giá của tiêu chí
     */
    public function getResultHistory($serverId = null, $limit = 10)
    {
        $db = Database::getInstance();
        $sql = "SELECT r.*, s.name as server_name
                FROM assessment_results r
                JOIN servers s ON r.server_id = s.id
                WHERE r.criteria_id = ?";
        $params = [$this->id];
        
        if ($serverId) {
            $sql .= " AND r.server_id = ?";
            $params[] = $serverId;
        }
        
        $sql .= " ORDER BY r.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        return $db->fetchAll($sql, $params);
    }
    
    /**
     * Lấy thống kê pass/fail của tiêu chí
     */
    public function getPassFailStats()
    {
        $db = Database::getInstance();
        return $db->fetchOne(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pass' THEN 1 ELSE 0 END) as passed,
                SUM(CASE WHEN status = 'fail' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'warning' THEN 1 ELSE 0 END) as warnings
             FROM assessment_results
             WHERE criteria_id = ?",
            [$this->id]
        );
    }
    
    /**
     * Lấy tất cả tiêu chí active
     */
    public static function getActive()
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT c.*, cat.name as category_name
             FROM assessment_criteria c
             JOIN assessment_categories cat ON c.category_id = cat.id
             WHERE c.is_active = 1
             ORDER BY cat.sort_order, c.sort_order"
        );
    }
    
    /**
     * Lấy tiêu chí theo severity
     */
    public static function getBySeverity($severity)
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT * FROM assessment_criteria 
             WHERE severity = ? AND is_active = 1 
             ORDER BY code",
            [$severity]
        );
    }
}