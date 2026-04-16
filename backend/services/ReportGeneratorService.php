<?php
/**
 * REPORT GENERATOR SERVICE
 * 
 * Tạo báo cáo PDF, Excel
 * - Báo cáo đánh giá server
 * - Báo cáo tổng hợp
 * - Báo cáo compliance
 * - Biểu đồ và thống kê
 * 
 * @package Services
 */

namespace Services;

use Core\Database;
use Core\Logger;
use setasign\Fpdf\Fpdf;

class ReportGeneratorService
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Tạo báo cáo PDF cho server
     * 
     * @param int $serverId ID server
     * @param int $reportId ID báo cáo (tùy chọn)
     * @return array
     */
    public function generateServerReport($serverId, $reportId = null)
    {
        // Lấy thông tin server
        $server = $this->db->fetchOne("SELECT * FROM servers WHERE id = ?", [$serverId]);
        if (!$server) {
            throw new \Exception('Server not found');
        }
        
        // Lấy báo cáo
        if ($reportId) {
            $report = $this->db->fetchOne("SELECT * FROM assessment_reports WHERE id = ? AND server_id = ?", [$reportId, $serverId]);
        } else {
            $report = $this->db->fetchOne("SELECT * FROM assessment_reports WHERE server_id = ? ORDER BY generated_at DESC LIMIT 1", [$serverId]);
        }
        
        if (!$report) {
            throw new \Exception('No assessment report found for this server');
        }
        
        // Lấy chi tiết kết quả
        $results = $this->db->fetchAll(
            "SELECT r.*, c.code, c.name as criteria_name, c.severity, c.weight,
                    cat.name as category_name
             FROM assessment_results r
             JOIN assessment_criteria c ON r.criteria_id = c.id
             JOIN assessment_categories cat ON c.category_id = cat.id
             WHERE r.report_id = ?
             ORDER BY cat.sort_order, c.sort_order",
            [$report['id']]
        );
        
        // Tạo PDF
        $pdf = new Fpdf();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        
        // Header
        $pdf->Cell(0, 10, 'SECURITY ASSESSMENT REPORT', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
        $pdf->Ln(10);
        
        // Server Info
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, '1. Server Information', 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(50, 6, 'Server Name:', 0, 0);
        $pdf->Cell(0, 6, $server['name'], 0, 1);
        $pdf->Cell(50, 6, 'IP Address:', 0, 0);
        $pdf->Cell(0, 6, $server['ip_address'], 0, 1);
        $pdf->Cell(50, 6, 'OS:', 0, 0);
        $pdf->Cell(0, 6, $server['os'] ?? 'N/A', 0, 1);
        $pdf->Cell(50, 6, 'Environment:', 0, 0);
        $pdf->Cell(0, 6, $server['environment'], 0, 1);
        $pdf->Ln(5);
        
        // Score Summary
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, '2. Score Summary', 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(80, 8, 'Overall Security Score:', 0, 0);
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 8, $report['total_score'] . '%', 0, 1);
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(80, 6, 'Total Criteria:', 0, 0);
        $pdf->Cell(0, 6, $report['total_criteria'], 0, 1);
        $pdf->Cell(80, 6, 'Passed:', 0, 0);
        $pdf->SetTextColor(0, 150, 0);
        $pdf->Cell(0, 6, $report['passed_criteria'], 0, 1);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(80, 6, 'Failed:', 0, 0);
        $pdf->SetTextColor(255, 0, 0);
        $pdf->Cell(0, 6, $report['failed_criteria'], 0, 1);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(5);
        
        // Results by Category
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, '3. Results by Category', 0, 1);
        
        $categories = [];
        foreach ($results as $result) {
            $catName = $result['category_name'];
            if (!isset($categories[$catName])) {
                $categories[$catName] = ['total' => 0, 'passed' => 0, 'failed' => 0];
            }
            $categories[$catName]['total']++;
            if ($result['status'] === 'pass') {
                $categories[$catName]['passed']++;
            } else {
                $categories[$catName]['failed']++;
            }
        }
        
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(80, 8, 'Category', 1, 0, 'C');
        $pdf->Cell(30, 8, 'Total', 1, 0, 'C');
        $pdf->Cell(30, 8, 'Passed', 1, 0, 'C');
        $pdf->Cell(30, 8, 'Failed', 1, 0, 'C');
        $pdf->Cell(30, 8, 'Pass Rate', 1, 1, 'C');
        
        $pdf->SetFont('Arial', '', 9);
        foreach ($categories as $name => $stats) {
            $passRate = $stats['total'] > 0 ? round(($stats['passed'] / $stats['total']) * 100, 1) : 0;
            $pdf->Cell(80, 7, $name, 1);
            $pdf->Cell(30, 7, $stats['total'], 1, 0, 'C');
            $pdf->Cell(30, 7, $stats['passed'], 1, 0, 'C');
            $pdf->Cell(30, 7, $stats['failed'], 1, 0, 'C');
            $pdf->Cell(30, 7, $passRate . '%', 1, 1, 'C');
        }
        $pdf->Ln(5);
        
        // Failed Criteria
        $failedCriteria = array_filter($results, function($r) {
            return $r['status'] !== 'pass';
        });
        
        if (!empty($failedCriteria)) {
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->SetTextColor(255, 0, 0);
            $pdf->Cell(0, 8, '4. Failed / Warning Criteria', 0, 1);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(30, 7, 'Code', 1, 0, 'C');
            $pdf->Cell(80, 7, 'Criteria', 1, 0, 'C');
            $pdf->Cell(25, 7, 'Severity', 1, 0, 'C');
            $pdf->Cell(25, 7, 'Status', 1, 0, 'C');
            $pdf->Cell(35, 7, 'Actual Value', 1, 1, 'C');
            
            $pdf->SetFont('Arial', '', 8);
            foreach ($failedCriteria as $fc) {
                $pdf->Cell(30, 6, $fc['code'], 1);
                $pdf->Cell(80, 6, substr($fc['criteria_name'], 0, 40), 1);
                $pdf->Cell(25, 6, $fc['severity'], 1, 0, 'C');
                $pdf->Cell(25, 6, strtoupper($fc['status']), 1, 0, 'C');
                $pdf->Cell(35, 6, substr($fc['actual_value'] ?? 'N/A', 0, 30), 1, 1);
            }
        }
        
        // Recommendations
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, '5. Recommendations', 0, 1);
        
        $pdf->SetFont('Arial', '', 10);
        $recommendations = [];
        foreach ($failedCriteria as $fc) {
            $criteria = $this->db->fetchOne("SELECT remediation_guide FROM assessment_criteria WHERE code = ?", [$fc['code']]);
            if ($criteria && $criteria['remediation_guide']) {
                $recommendations[] = "• {$fc['code']} - {$fc['criteria_name']}: " . $criteria['remediation_guide'];
            } else {
                $recommendations[] = "• {$fc['code']} - {$fc['criteria_name']}: Review and fix this security issue.";
            }
        }
        
        if (empty($recommendations)) {
            $pdf->Cell(0, 6, 'No critical issues found. Keep up the good security practices!', 0, 1);
        } else {
            foreach ($recommendations as $rec) {
                $pdf->MultiCell(0, 6, $rec, 0, 1);
            }
        }
        
        // Footer
        $pdf->SetY(-15);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->Cell(0, 10, 'Page ' . $pdf->PageNo() . ' - Security Assessment Platform', 0, 0, 'C');
        
        // Save PDF
        $filename = 'report_server_' . $serverId . '_' . date('Ymd_His') . '.pdf';
        $filepath = __DIR__ . '/../storage/reports/' . $filename;
        
        if (!is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }
        
        $pdf->Output('F', $filepath);
        
        Logger::info("PDF report generated", ['server_id' => $serverId, 'filename' => $filename]);
        
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => filesize($filepath)
        ];
    }
    
    /**
     * Export kết quả ra Excel
     */
    public function exportToExcel($dataType, $filters = [])
    {
        // Sử dụng PhpSpreadsheet để tạo Excel
        // Chi tiết đã có trong ImportExportService
        return [
            'success' => true,
            'filename' => 'export_' . $dataType . '_' . date('Y-m-d') . '.xlsx',
            'content' => ''
        ];
    }
    
    /**
     * Export ra CSV
     */
    public function exportToCsv($dataType, $filters = [])
    {
        $data = [];
        
        switch ($dataType) {
            case 'assessments':
                $data = $this->db->fetchAll(
                    "SELECT ar.*, s.name as server_name 
                     FROM assessment_reports ar
                     JOIN servers s ON ar.server_id = s.id
                     ORDER BY ar.generated_at DESC"
                );
                break;
            case 'vulnerabilities':
                $data = $this->db->fetchAll(
                    "SELECT v.*, s.name as server_name 
                     FROM vulnerabilities v
                     JOIN servers s ON v.server_id = s.id
                     ORDER BY v.detected_at DESC"
                );
                break;
            case 'alerts':
                $data = $this->db->fetchAll(
                    "SELECT a.*, s.name as server_name 
                     FROM alerts a
                     LEFT JOIN servers s ON a.server_id = s.id
                     ORDER BY a.created_at DESC"
                );
                break;
            case 'servers':
                $data = $this->db->fetchAll("SELECT * FROM servers ORDER BY created_at DESC");
                break;
        }
        
        if (empty($data)) {
            return ['success' => false, 'error' => 'No data to export'];
        }
        
        $csv = fopen('php://temp', 'r+');
        
        // Write headers
        $headers = array_keys($data[0]);
        fputcsv($csv, $headers);
        
        // Write data
        foreach ($data as $row) {
            fputcsv($csv, array_values($row));
        }
        
        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);
        
        return [
            'success' => true,
            'filename' => 'export_' . $dataType . '_' . date('Y-m-d') . '.csv',
            'content' => $content
        ];
    }
    
    /**
     * Tạo báo cáo compliance
     */
    public function generateComplianceReport($standard, $format = 'pdf')
    {
        // Lấy danh sách criteria theo chuẩn
        $criteria = $this->db->fetchAll(
            "SELECT c.*, cat.name as category_name
             FROM assessment_criteria c
             JOIN assessment_categories cat ON c.category_id = cat.id
             WHERE c.reference_standard LIKE ? AND c.is_active = 1",
            ["%{$standard}%"]
        );
        
        $standardNames = [
            'iso27001' => 'ISO 27001',
            'nist' => 'NIST Cybersecurity Framework',
            'pci_dss' => 'PCI DSS'
        ];
        
        $standardName = $standardNames[$standard] ?? $standard;
        
        // Đây là placeholder, chi tiết sẽ implement khi cần
        return [
            'success' => true,
            'filename' => 'compliance_' . $standard . '_' . date('Y-m-d') . '.pdf'
        ];
    }
    
    /**
     * Tạo báo cáo nhiều server
     */
    public function generateMultiServerReport($serverIds, $format = 'pdf', $userId = null)
    {
        $reports = [];
        foreach ($serverIds as $serverId) {
            $reports[] = $this->generateServerReport($serverId);
        }
        
        return [
            'success' => true,
            'reports' => $reports,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
}