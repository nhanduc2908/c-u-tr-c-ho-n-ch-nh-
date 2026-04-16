<?php
/**
 * ASSESSMENT REPORT MODEL
 * 
 * Báo cáo tổng hợp đánh giá
 * 
 * @package Models
 */

namespace Models;

use Core\Model;
use Core\Database;

class AssessmentReport extends Model
{
    protected $table = 'assessment_reports';
    protected $primaryKey = 'id';
    protected $fillable = [
        'server_id', 'report_name', 'total_score', 'total_criteria',
        'passed_criteria', 'failed_criteria', 'warning_criteria',
        'not_applicable_criteria', 'score_by_category', 'status',
        'file_path', 'generated_by', 'approved_by', 'approved_at'
    ];
    protected $guarded = ['id', 'generated_at', 'updated_at'];
    protected $timestamps = true;
    
    /**
     * Lấy thông tin server
     */
    public function getServer()
    {
        $db = Database::getInstance();
        return $db->fetchOne("SELECT * FROM servers WHERE id = ?", [$this->server_id]);
    }
    
    /**
     * Lấy chi tiết kết quả từng tiêu chí
     */
    public function getDetails()
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT r.*, c.code, c.name as criteria_name, c.severity, c.weight,
                    cat.name as category_name
             FROM assessment_results r
             JOIN assessment_criteria c ON r.criteria_id = c.id
             JOIN assessment_categories cat ON c.category_id = cat.id
             WHERE r.report_id = ?
             ORDER BY cat.sort_order, c.sort_order",
            [$this->id]
        );
    }
    
    /**
     * Lấy thống kê theo category
     */
    public function getCategoryStats()
    {
        $details = $this->getDetails();
        $stats = [];
        
        foreach ($details as $detail) {
            $catId = $detail['category_id'];
            if (!isset($stats[$catId])) {
                $stats[$catId] = [
                    'category_name' => $detail['category_name'],
                    'total' => 0,
                    'passed' => 0,
                    'failed' => 0,
                    'score' => 0
                ];
            }
            $stats[$catId]['total']++;
            if ($detail['status'] === 'pass') {
                $stats[$catId]['passed']++;
            } else {
                $stats[$catId]['failed']++;
            }
            $stats[$catId]['score'] += $detail['score_obtained'];
        }
        
        foreach ($stats as &$stat) {
            $stat['score'] = $stat['total'] > 0 ? round($stat['score'] / $stat['total'], 1) : 0;
            $stat['pass_rate'] = $stat['total'] > 0 ? round(($stat['passed'] / $stat['total']) * 100, 1) : 0;
        }
        
        return array_values($stats);
    }
    
    /**
     * Lấy người tạo báo cáo
     */
    public function getGenerator()
    {
        $db = Database::getInstance();
        return $db->fetchOne("SELECT id, username, email FROM users WHERE id = ?", [$this->generated_by]);
    }
    
    /**
     * Phê duyệt báo cáo
     */
    public function approve($userId)
    {
        $db = Database::getInstance();
        $db->update('assessment_reports', [
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$this->id]);
    }
}