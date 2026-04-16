<?php
/**
 * SERVER MODEL
 * 
 * Quản lý thông tin server
 * 
 * @package Models
 */

namespace Models;

use Core\Model;
use Core\Database;

class Server extends Model
{
    protected $table = 'servers';
    protected $primaryKey = 'id';
    protected $fillable = [
        'name', 'ip_address', 'hostname', 'os', 'environment',
        'status', 'ssh_port', 'ssh_username', 'ssh_key_path',
        'last_scan_at', 'created_by'
    ];
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $timestamps = true;
    
    /**
     * Lấy kết quả đánh giá mới nhất
     */
    public function getLatestAssessment()
    {
        $db = Database::getInstance();
        return $db->fetchOne(
            "SELECT * FROM assessment_reports WHERE server_id = ? ORDER BY generated_at DESC LIMIT 1",
            [$this->id]
        );
    }
    
    /**
     * Lấy lịch sử đánh giá
     */
    public function getAssessmentHistory($limit = 10)
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT * FROM assessment_reports 
             WHERE server_id = ? 
             ORDER BY generated_at DESC 
             LIMIT ?",
            [$this->id, $limit]
        );
    }
    
    /**
     * Lấy điểm trung bình
     */
    public function getAverageScore()
    {
        $db = Database::getInstance();
        return $db->fetchColumn(
            "SELECT AVG(total_score) FROM assessment_reports WHERE server_id = ?",
            [$this->id]
        );
    }
    
    /**
     * Lấy danh sách lỗ hổng
     */
    public function getVulnerabilities()
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT * FROM vulnerabilities WHERE server_id = ? ORDER BY severity DESC, detected_at DESC",
            [$this->id]
        );
    }
    
    /**
     * Lấy số lượng lỗ hổng theo severity
     */
    public function getVulnerabilityStats()
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT severity, COUNT(*) as count 
             FROM vulnerabilities 
             WHERE server_id = ? 
             GROUP BY severity",
            [$this->id]
        );
    }
    
    /**
     * Cập nhật thời gian quét cuối
     */
    public function updateLastScan()
    {
        $db = Database::getInstance();
        $db->update('servers', ['last_scan_at' => date('Y-m-d H:i:s')], 'id = ?', [$this->id]);
    }
    
    /**
     * Kiểm tra kết nối SSH
     */
    public function testSshConnection()
    {
        // Sẽ được implement trong service
        return false;
    }
    
    /**
     * Lấy người tạo server
     */
    public function getCreator()
    {
        $db = Database::getInstance();
        return $db->fetchOne("SELECT id, username, email FROM users WHERE id = ?", [$this->created_by]);
    }
}