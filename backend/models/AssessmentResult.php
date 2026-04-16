<?php
/**
 * ASSESSMENT RESULT MODEL
 * 
 * Kết quả đánh giá từng tiêu chí
 * 
 * @package Models
 */

namespace Models;

use Core\Model;
use Core\Database;

class AssessmentResult extends Model
{
    protected $table = 'assessment_results';
    protected $primaryKey = 'id';
    protected $fillable = [
        'report_id', 'server_id', 'criteria_id', 'status',
        'actual_value', 'score_obtained', 'max_score',
        'evidence_path', 'notes', 'checked_by', 'checked_at'
    ];
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $timestamps = true;
    
    /**
     * Lấy thông tin tiêu chí
     */
    public function getCriteria()
    {
        $db = Database::getInstance();
        return $db->fetchOne("SELECT * FROM assessment_criteria WHERE id = ?", [$this->criteria_id]);
    }
    
    /**
     * Lấy thông tin server
     */
    public function getServer()
    {
        $db = Database::getInstance();
        return $db->fetchOne("SELECT * FROM servers WHERE id = ?", [$this->server_id]);
    }
    
    /**
     * Lấy thông tin báo cáo
     */
    public function getReport()
    {
        $db = Database::getInstance();
        return $db->fetchOne("SELECT * FROM assessment_reports WHERE id = ?", [$this->report_id]);
    }
    
    /**
     * Lấy người kiểm tra
     */
    public function getChecker()
    {
        if (!$this->checked_by) return null;
        
        $db = Database::getInstance();
        return $db->fetchOne("SELECT id, username, email FROM users WHERE id = ?", [$this->checked_by]);
    }
    
    /**
     * Đánh dấu là đã kiểm tra thủ công
     */
    public function markAsChecked($userId, $status, $notes = null)
    {
        $db = Database::getInstance();
        $db->update('assessment_results', [
            'status' => $status,
            'score_obtained' => $status === 'pass' ? 100 : 0,
            'notes' => $notes,
            'checked_by' => $userId,
            'checked_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$this->id]);
    }
}