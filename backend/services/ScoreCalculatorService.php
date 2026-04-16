<?php
/**
 * SCORE CALCULATOR SERVICE
 * 
 * Tính điểm đánh giá bảo mật dựa trên:
 * - Trọng số của từng tiêu chí
 * - Trọng số của từng lĩnh vực
 * - Kết quả pass/fail/warning
 * 
 * @package Services
 */

namespace Services;

use Core\Database;

class ScoreCalculatorService
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Tính điểm cho một server
     * 
     * @param int $serverId ID server
     * @param array $results Kết quả đánh giá (criteria_id => status)
     * @return array Điểm chi tiết
     */
    public function calculateServerScore($serverId, $results)
    {
        // Lấy tất cả categories và criteria
        $categories = $this->db->fetchAll(
            "SELECT * FROM assessment_categories WHERE is_active = 1 ORDER BY sort_order"
        );
        
        $allCriteria = $this->db->fetchAll(
            "SELECT * FROM assessment_criteria WHERE is_active = 1"
        );
        
        $criteriaMap = [];
        foreach ($allCriteria as $c) {
            $criteriaMap[$c['id']] = $c;
        }
        
        // Tính điểm theo từng category
        $categoryScores = [];
        $totalWeightedScore = 0;
        $totalWeight = 0;
        
        foreach ($categories as $category) {
            $categoryScore = $this->calculateCategoryScore($category, $results, $criteriaMap);
            $categoryScores[] = $categoryScore;
            
            $totalWeightedScore += $categoryScore['weighted_score'];
            $totalWeight += $category['weight_percent'];
        }
        
        // Tổng điểm
        $overallScore = $totalWeight > 0 ? round(($totalWeightedScore / $totalWeight) * 100, 1) : 0;
        
        // Xếp hạng
        $rating = $this->getRating($overallScore);
        
        return [
            'overall_score' => $overallScore,
            'rating' => $rating,
            'category_scores' => $categoryScores,
            'total_criteria' => count($results),
            'passed' => $this->countStatus($results, 'pass'),
            'failed' => $this->countStatus($results, 'fail'),
            'warning' => $this->countStatus($results, 'warning')
        ];
    }
    
    /**
     * Tính điểm cho một category
     */
    private function calculateCategoryScore($category, $results, $criteriaMap)
    {
        $categoryCriteria = array_filter($criteriaMap, function($c) use ($category) {
            return $c['category_id'] == $category['id'];
        });
        
        $totalCriteriaWeight = 0;
        $earnedWeight = 0;
        
        foreach ($categoryCriteria as $criteria) {
            $status = $results[$criteria['id']] ?? 'pending';
            $weight = $criteria['weight'];
            
            $totalCriteriaWeight += $weight;
            
            switch ($status) {
                case 'pass':
                    $earnedWeight += $weight;
                    break;
                case 'warning':
                    $earnedWeight += $weight * 0.5;
                    break;
                case 'fail':
                default:
                    $earnedWeight += 0;
                    break;
            }
        }
        
        $categoryScore = $totalCriteriaWeight > 0 
            ? round(($earnedWeight / $totalCriteriaWeight) * 100, 1) 
            : 0;
        
        $weightedScore = ($categoryScore * $category['weight_percent']) / 100;
        
        return [
            'category_id' => $category['id'],
            'category_code' => $category['code'],
            'category_name' => $category['name'],
            'score' => $categoryScore,
            'weight' => $category['weight_percent'],
            'weighted_score' => $weightedScore,
            'total_criteria' => count($categoryCriteria),
            'rating' => $this->getRating($categoryScore)
        ];
    }
    
    /**
     * Đếm số lượng theo status
     */
    private function countStatus($results, $status)
    {
        $count = 0;
        foreach ($results as $result) {
            if ($result === $status) {
                $count++;
            }
        }
        return $count;
    }
    
    /**
     * Lấy xếp hạng dựa trên điểm
     */
    public function getRating($score)
    {
        if ($score >= 90) {
            return ['level' => 'excellent', 'label' => 'Xuất sắc', 'color' => '#22c55e'];
        } elseif ($score >= 75) {
            return ['level' => 'good', 'label' => 'Tốt', 'color' => '#3b82f6'];
        } elseif ($score >= 60) {
            return ['level' => 'average', 'label' => 'Trung bình', 'color' => '#eab308'];
        } elseif ($score >= 40) {
            return ['level' => 'poor', 'label' => 'Yếu', 'color' => '#f97316'];
        } else {
            return ['level' => 'critical', 'label' => 'Nguy kịch', 'color' => '#ef4444'];
        }
    }
    
    /**
     * Tính điểm xu hướng (so sánh với lần đánh giá trước)
     */
    public function calculateTrend($currentScore, $previousScore)
    {
        $diff = $currentScore - $previousScore;
        
        if ($diff > 5) {
            return ['direction' => 'up', 'label' => 'Cải thiện', 'icon' => '📈', 'color' => '#22c55e'];
        } elseif ($diff < -5) {
            return ['direction' => 'down', 'label' => 'Suy giảm', 'icon' => '📉', 'color' => '#ef4444'];
        } else {
            return ['direction' => 'stable', 'label' => 'Ổn định', 'icon' => '➡️', 'color' => '#6b7280'];
        }
    }
    
    /**
     * Tính điểm compliance theo chuẩn
     */
    public function calculateComplianceScore($results, $standard)
    {
        // Lấy danh sách criteria theo chuẩn
        $criteria = $this->db->fetchAll(
            "SELECT * FROM assessment_criteria 
             WHERE reference_standard LIKE ? AND is_active = 1",
            ["%{$standard}%"]
        );
        
        $total = count($criteria);
        $passed = 0;
        
        foreach ($criteria as $c) {
            if (isset($results[$c['id']]) && $results[$c['id']] === 'pass') {
                $passed++;
            }
        }
        
        $score = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
        
        return [
            'standard' => $standard,
            'score' => $score,
            'total' => $total,
            'passed' => $passed,
            'rating' => $this->getRating($score)
        ];
    }
}