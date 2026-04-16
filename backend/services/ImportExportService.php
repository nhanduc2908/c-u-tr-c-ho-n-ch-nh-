<?php
/**
 * IMPORT EXPORT SERVICE
 * 
 * Import/Export dữ liệu từ/ra Excel, CSV, JSON
 * - Import 280 tiêu chí từ Excel
 * - Export tiêu chí ra Excel
 * - Import/Export kết quả đánh giá
 * 
 * @package Services
 */

namespace Services;

use Core\Database;
use Core\Logger;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ImportExportService
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Import tiêu chí từ Excel
     * 
     * @param string $filePath Đường dẫn file
     * @param string $extension Đuôi file (xlsx, xls, csv)
     * @return array Kết quả import
     */
    public function importCriteria($filePath, $extension)
    {
        $result = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => [],
            'total' => 0
        ];
        
        try {
            // Đọc file
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            if (empty($rows)) {
                throw new \Exception('File is empty');
            }
            
            // Lấy header
            $header = array_shift($rows);
            $header = array_map('trim', $header);
            
            // Map header sang field
            $fieldMap = $this->mapCriteriaHeaders($header);
            
            if (empty($fieldMap['code']) || empty($fieldMap['name']) || empty($fieldMap['category_id'])) {
                throw new \Exception('Missing required columns: code, name, category_id');
            }
            
            $result['total'] = count($rows);
            
            foreach ($rows as $rowIndex => $row) {
                $data = [];
                foreach ($fieldMap as $field => $colIndex) {
                    if ($colIndex !== null && isset($row[$colIndex])) {
                        $data[$field] = trim($row[$colIndex]);
                    }
                }
                
                // Validate dữ liệu
                if (empty($data['code']) || empty($data['name']) || empty($data['category_id'])) {
                    $result['skipped']++;
                    $result['errors'][] = "Row " . ($rowIndex + 2) . ": Missing required fields";
                    continue;
                }
                
                // Kiểm tra category tồn tại
                $categoryExists = $this->db->fetchColumn(
                    "SELECT COUNT(*) FROM assessment_categories WHERE id = ? OR code = ?",
                    [$data['category_id'], $data['category_id']]
                );
                
                if (!$categoryExists) {
                    $result['skipped']++;
                    $result['errors'][] = "Row " . ($rowIndex + 2) . ": Category not found: {$data['category_id']}";
                    continue;
                }
                
                // Lấy category_id thực tế
                $categoryId = $this->db->fetchColumn(
                    "SELECT id FROM assessment_categories WHERE id = ? OR code = ? LIMIT 1",
                    [$data['category_id'], $data['category_id']]
                );
                $data['category_id'] = $categoryId;
                
                // Kiểm tra code đã tồn tại
                $exists = $this->db->fetchColumn(
                    "SELECT COUNT(*) FROM assessment_criteria WHERE code = ?",
                    [$data['code']]
                );
                
                if ($exists) {
                    // Cập nhật nếu đã tồn tại
                    unset($data['code']);
                    $data['updated_at'] = date('Y-m-d H:i:s');
                    $this->db->update('assessment_criteria', $data, 'code = ?', [$data['code']]);
                    $result['imported']++;
                } else {
                    // Thêm mới
                    $data['created_at'] = date('Y-m-d H:i:s');
                    $data['updated_at'] = date('Y-m-d H:i:s');
                    $data['is_active'] = $data['is_active'] ?? 1;
                    $data['weight'] = $data['weight'] ?? 1;
                    $data['severity'] = $data['severity'] ?? 'medium';
                    
                    $this->db->insert('assessment_criteria', $data);
                    $result['imported']++;
                }
            }
            
            Logger::info("Criteria import completed", $result);
            
        } catch (\Exception $e) {
            Logger::error("Import failed", ['error' => $e->getMessage()]);
            throw $e;
        }
        
        return $result;
    }
    
    /**
     * Export tiêu chí ra Excel
     * 
     * @param array $criteria Danh sách tiêu chí
     * @param string $format Định dạng (xlsx, csv)
     * @return array Kết quả export
     */
    public function exportCriteria($criteria, $format = 'xlsx')
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set headers
        $headers = [
            'A' => 'code',
            'B' => 'category_id',
            'C' => 'category_name',
            'D' => 'name',
            'E' => 'description',
            'F' => 'severity',
            'G' => 'weight',
            'H' => 'check_method',
            'I' => 'expected_value',
            'J' => 'remediation_guide',
            'K' => 'reference_standard',
            'L' => 'is_active'
        ];
        
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', strtoupper(str_replace('_', ' ', $header)));
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0E0E0');
            $col++;
        }
        
        // Set data
        $row = 2;
        foreach ($criteria as $item) {
            $sheet->setCellValue('A' . $row, $item['code']);
            $sheet->setCellValue('B' . $row, $item['category_id']);
            $sheet->setCellValue('C' . $row, $item['category_name'] ?? '');
            $sheet->setCellValue('D' . $row, $item['name']);
            $sheet->setCellValue('E' . $row, $item['description'] ?? '');
            $sheet->setCellValue('F' . $row, $item['severity'] ?? 'medium');
            $sheet->setCellValue('G' . $row, $item['weight'] ?? 1);
            $sheet->setCellValue('H' . $row, $item['check_method'] ?? 'auto');
            $sheet->setCellValue('I' . $row, $item['expected_value'] ?? '');
            $sheet->setCellValue('J' . $row, $item['remediation_guide'] ?? '');
            $sheet->setCellValue('K' . $row, $item['reference_standard'] ?? '');
            $sheet->setCellValue('L' . $row, $item['is_active'] ? 'Yes' : 'No');
            $row++;
        }
        
        // Auto size columns
        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Xuất file
        if ($format === 'csv') {
            $writer = IOFactory::createWriter($spreadsheet, 'Csv');
            $filename = 'criteria_export_' . date('Y-m-d') . '.csv';
            $mime = 'text/csv';
        } else {
            $writer = new Xlsx($spreadsheet);
            $filename = 'criteria_export_' . date('Y-m-d') . '.xlsx';
            $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }
        
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();
        
        return [
            'content' => $content,
            'filename' => $filename,
            'mime' => $mime
        ];
    }
    
    /**
     * Map header Excel sang field database
     */
    private function mapCriteriaHeaders($header)
    {
        $map = [
            'code' => null,
            'category_id' => null,
            'category_name' => null,
            'name' => null,
            'description' => null,
            'severity' => null,
            'weight' => null,
            'check_method' => null,
            'expected_value' => null,
            'remediation_guide' => null,
            'reference_standard' => null,
            'is_active' => null
        ];
        
        foreach ($header as $index => $col) {
            $colLower = strtolower(trim($col));
            $colLower = str_replace(' ', '_', $colLower);
            
            if (isset($map[$colLower])) {
                $map[$colLower] = $index;
            } elseif (strpos($colLower, 'code') !== false) {
                $map['code'] = $index;
            } elseif (strpos($colLower, 'category') !== false) {
                $map['category_id'] = $index;
            } elseif (strpos($colLower, 'name') !== false && !isset($map['name'])) {
                $map['name'] = $index;
            }
        }
        
        return $map;
    }
    
    /**
     * Lấy template import
     */
    public function getImportTemplate($format = 'xlsx')
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set headers
        $headers = ['code', 'category_id', 'name', 'description', 'severity', 'weight', 'check_method', 'expected_value', 'remediation_guide'];
        
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', strtoupper($header));
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $col++;
        }
        
        // Add example row
        $sheet->setCellValue('A2', 'SEC-001');
        $sheet->setCellValue('B2', '1');
        $sheet->setCellValue('C2', 'Example criteria');
        $sheet->setCellValue('D2', 'Description here');
        $sheet->setCellValue('E2', 'high');
        $sheet->setCellValue('F2', '5');
        $sheet->setCellValue('G2', 'auto');
        $sheet->setCellValue('H2', 'expected value');
        $sheet->setCellValue('I2', 'remediation steps');
        
        // Auto size
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        if ($format === 'csv') {
            $writer = IOFactory::createWriter($spreadsheet, 'Csv');
            $filename = 'criteria_import_template.csv';
            $mime = 'text/csv';
        } else {
            $writer = new Xlsx($spreadsheet);
            $filename = 'criteria_import_template.xlsx';
            $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }
        
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();
        
        return [
            'content' => $content,
            'filename' => $filename,
            'mime' => $mime
        ];
    }
    
    /**
     * Export kết quả đánh giá
     */
    public function exportAssessmentResults($reportId)
    {
        $report = $this->db->fetchOne("SELECT * FROM assessment_reports WHERE id = ?", [$reportId]);
        if (!$report) {
            throw new \Exception('Report not found');
        }
        
        $details = $this->db->fetchAll(
            "SELECT c.code, c.name as criteria_name, c.severity, r.status, 
                    r.score_obtained, r.actual_value, r.notes
             FROM assessment_results r
             JOIN assessment_criteria c ON r.criteria_id = c.id
             WHERE r.report_id = ?",
            [$reportId]
        );
        
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Summary
        $sheet->setCellValue('A1', 'ASSESSMENT REPORT');
        $sheet->setCellValue('A2', 'Server:');
        $sheet->setCellValue('B2', $this->db->fetchColumn("SELECT name FROM servers WHERE id = ?", [$report['server_id']]));
        $sheet->setCellValue('A3', 'Date:');
        $sheet->setCellValue('B3', $report['generated_at']);
        $sheet->setCellValue('A4', 'Total Score:');
        $sheet->setCellValue('B4', $report['total_score'] . '%');
        
        // Headers
        $headers = ['Code', 'Criteria', 'Severity', 'Status', 'Score', 'Actual Value', 'Notes'];
        $col = 'A';
        $row = 7;
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $col++;
        }
        
        // Data
        $row = 8;
        foreach ($details as $detail) {
            $sheet->setCellValue('A' . $row, $detail['code']);
            $sheet->setCellValue('B' . $row, $detail['criteria_name']);
            $sheet->setCellValue('C' . $row, $detail['severity']);
            $sheet->setCellValue('D' . $row, strtoupper($detail['status']));
            $sheet->setCellValue('E' . $row, $detail['score_obtained']);
            $sheet->setCellValue('F' . $row, $detail['actual_value'] ?? '');
            $sheet->setCellValue('G' . $row, $detail['notes'] ?? '');
            $row++;
        }
        
        // Auto size
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        $writer = new Xlsx($spreadsheet);
        $filename = 'assessment_report_' . $reportId . '_' . date('Y-m-d') . '.xlsx';
        
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();
        
        return [
            'content' => $content,
            'filename' => $filename,
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
    }
}