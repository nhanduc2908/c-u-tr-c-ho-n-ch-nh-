<?php
/**
 * CATEGORY MODEL
 * 
 * Quản lý 17 lĩnh vực đánh giá
 * 
 * @package Models
 */

namespace Models;

use Core\Model;
use Core\Database;

class Category extends Model
{
    protected $table = 'assessment_categories';
    protected $primaryKey = 'id';
    protected $fillable = [
        'code', 'name', 'description', 'weight_percent',
        'expected_score', 'sort_order', 'is_active'
    ];
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $timestamps = true;
    
    /**
     * Lấy danh sách tiêu chí thuộc category
     */
    public function getCriteria()
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT * FROM assessment_criteria 
             WHERE category_id = ? AND is_active = 1 
             ORDER BY sort_order, code",
            [$this->id]
        );
    }
    
    /**
     * Lấy số lượng tiêu chí
     */
    public function getCriteriaCount()
    {
        $db = Database::getInstance();
        return $db->fetchColumn(
            "SELECT COUNT(*) FROM assessment_criteria WHERE category_id = ? AND is_active = 1",
            [$this->id]
        );
    }
    
    /**
     * Lấy điểm trung bình của category cho một server
     */
    public function getAverageScoreForServer($serverId)
    {
        $db = Database::getInstance();
        return $db->fetchColumn(
            "SELECT AVG(r.score_obtained) 
             FROM assessment_results r
             JOIN assessment_criteria c ON r.criteria_id = c.id
             WHERE c.category_id = ? AND r.server_id = ?
             ORDER BY r.created_at DESC
             LIMIT 1",
            [$this->id, $serverId]
        );
    }
    
    /**
     * Lấy tất cả categories có tiêu chí active
     */
    public static function getActiveWithCriteria()
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT c.*, COUNT(cr.id) as criteria_count
             FROM assessment_categories c
             LEFT JOIN assessment_criteria cr ON c.id = cr.category_id AND cr.is_active = 1
             WHERE c.is_active = 1
             GROUP BY c.id
             ORDER BY c.sort_order"
        );
    }
}