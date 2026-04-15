<?php
/**
 * Validator - Validate dữ liệu đầu vào
 * 
 * @package Core
 */

namespace Core;

class Validator
{
    /**
     * @var array Danh sách lỗi
     */
    private $errors = [];
    
    /**
     * Validate dữ liệu theo rules
     * 
     * @param array $data Dữ liệu cần validate
     * @param array $rules Rules validation
     * @return bool
     */
    public function validate($data, $rules)
    {
        $this->errors = [];
        
        foreach ($rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $data[$field] ?? null;
            
            foreach ($rules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Áp dụng một rule validation
     * 
     * @param string $field Tên field
     * @param mixed $value Giá trị
     * @param string $rule Rule (vd: 'required', 'email', 'min:5')
     */
    private function applyRule($field, $value, $rule)
    {
        // Rule có tham số: min:5
        if (strpos($rule, ':') !== false) {
            list($ruleName, $parameter) = explode(':', $rule, 2);
        } else {
            $ruleName = $rule;
            $parameter = null;
        }
        
        $method = 'validate' . ucfirst($ruleName);
        
        if (method_exists($this, $method)) {
            if (!$this->$method($value, $parameter)) {
                $this->addError($field, $ruleName, $parameter);
            }
        }
    }
    
    /**
     * Rule: required
     */
    private function validateRequired($value)
    {
        return !is_null($value) && $value !== '';
    }
    
    /**
     * Rule: email
     */
    private function validateEmail($value)
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Rule: min
     */
    private function validateMin($value, $min)
    {
        if (is_string($value)) {
            return strlen($value) >= (int)$min;
        }
        if (is_numeric($value)) {
            return $value >= (int)$min;
        }
        return false;
    }
    
    /**
     * Rule: max
     */
    private function validateMax($value, $max)
    {
        if (is_string($value)) {
            return strlen($value) <= (int)$max;
        }
        if (is_numeric($value)) {
            return $value <= (int)$max;
        }
        return false;
    }
    
    /**
     * Rule: numeric
     */
    private function validateNumeric($value)
    {
        return is_numeric($value);
    }
    
    /**
     * Rule: integer
     */
    private function validateInteger($value)
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }
    
    /**
     * Rule: boolean
     */
    private function validateBoolean($value)
    {
        return is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false', true, false]);
    }
    
    /**
     * Rule: array
     */
    private function validateArray($value)
    {
        return is_array($value);
    }
    
    /**
     * Rule: in
     */
    private function validateIn($value, $allowedValues)
    {
        $allowed = explode(',', $allowedValues);
        return in_array($value, $allowed);
    }
    
    /**
     * Rule: exists (kiểm tra tồn tại trong database)
     * Format: exists:table,column
     */
    private function validateExists($value, $params)
    {
        list($table, $column) = explode(',', $params);
        $db = Database::getInstance();
        $result = $db->fetchColumn("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?", [$value]);
        return $result > 0;
    }
    
    /**
     * Rule: unique (kiểm tra không trùng trong database)
     * Format: unique:table,column,exceptId
     */
    private function validateUnique($value, $params)
    {
        $parts = explode(',', $params);
        $table = $parts[0];
        $column = $parts[1] ?? $column ?? null;
        $exceptId = $parts[2] ?? null;
        
        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = ?";
        $params = [$value];
        
        if ($exceptId) {
            $sql .= " AND id != ?";
            $params[] = $exceptId;
        }
        
        $db = Database::getInstance();
        $result = $db->fetchColumn($sql, $params);
        return $result == 0;
    }
    
    /**
     * Thêm lỗi vào danh sách
     */
    private function addError($field, $rule, $parameter = null)
    {
        $messages = [
            'required' => 'The :field field is required',
            'email' => 'The :field must be a valid email address',
            'min' => 'The :field must be at least :param characters',
            'max' => 'The :field may not be greater than :param characters',
            'numeric' => 'The :field must be a number',
            'integer' => 'The :field must be an integer',
            'boolean' => 'The :field must be true or false',
            'array' => 'The :field must be an array',
            'in' => 'The selected :field is invalid',
            'exists' => 'The selected :field does not exist',
            'unique' => 'The :field has already been taken',
        ];
        
        $message = $messages[$rule] ?? "The :field failed validation for {$rule}";
        $message = str_replace(':field', $field, $message);
        $message = str_replace(':param', $parameter, $message);
        
        $this->errors[$field][] = $message;
    }
    
    /**
     * Lấy danh sách lỗi
     * 
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }
}