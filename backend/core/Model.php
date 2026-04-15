<?php
/**
 * Base Model - ORM cơ bản
 * Cung cấp các method CRUD cho các model con
 * 
 * @package Core
 */

namespace Core;

abstract class Model
{
    /**
     * @var string Tên bảng trong database
     */
    protected $table;
    
    /**
     * @var string Khóa chính của bảng
     */
    protected $primaryKey = 'id';
    
    /**
     * @var array Các field có thể fill (mass assignment)
     */
    protected $fillable = [];
    
    /**
     * @var array Các field không được fill
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];
    
    /**
     * @var bool Có tự động timestamps không
     */
    protected $timestamps = true;
    
    /**
     * @var Database Database instance
     */
    protected $db;
    
    /**
     * @var array Dữ liệu hiện tại của model
     */
    protected $attributes = [];
    
    /**
     * @var array Dữ liệu cũ trước khi update
     */
    protected $original = [];
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        
        if (!$this->table) {
            // Tự động tạo tên bảng từ tên class
            $className = (new \ReflectionClass($this))->getShortName();
            $this->table = strtolower(preg_replace('/(?<!^)([A-Z])/', '_$1', $className)) . 's';
        }
    }
    
    /**
     * Tìm record theo ID
     * 
     * @param int $id
     * @return static|null
     */
    public function find($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $result = $this->db->fetchOne($sql, [$id]);
        
        if ($result) {
            $this->attributes = $result;
            $this->original = $result;
            return $this;
        }
        
        return null;
    }
    
    /**
     * Lấy tất cả records
     * 
     * @return array
     */
    public function all()
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY {$this->primaryKey} DESC";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Lấy records với điều kiện
     * 
     * @param array $conditions
     * @return array
     */
    public function where($conditions)
    {
        $sql = "SELECT * FROM {$this->table} WHERE ";
        $params = [];
        $clauses = [];
        
        foreach ($conditions as $field => $value) {
            $clauses[] = "{$field} = ?";
            $params[] = $value;
        }
        
        $sql .= implode(' AND ', $clauses);
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Tạo record mới
     * 
     * @param array $data
     * @return int|false
     */
    public function create($data)
    {
        $data = $this->filterFillable($data);
        
        if ($this->timestamps) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        $id = $this->db->insert($this->table, $data);
        
        if ($id) {
            $this->attributes = $data;
            $this->attributes[$this->primaryKey] = $id;
            $this->original = $this->attributes;
        }
        
        return $id;
    }
    
    /**
     * Cập nhật record hiện tại
     * 
     * @param array $data
     * @return int
     */
    public function update($data)
    {
        if (empty($this->attributes[$this->primaryKey])) {
            return false;
        }
        
        $data = $this->filterFillable($data);
        
        if ($this->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        $affected = $this->db->update(
            $this->table,
            $data,
            "{$this->primaryKey} = ?",
            [$this->attributes[$this->primaryKey]]
        );
        
        if ($affected) {
            $this->attributes = array_merge($this->attributes, $data);
            $this->original = $this->attributes;
        }
        
        return $affected;
    }
    
    /**
     * Xóa record hiện tại
     * 
     * @return int
     */
    public function delete()
    {
        if (empty($this->attributes[$this->primaryKey])) {
            return false;
        }
        
        return $this->db->delete(
            $this->table,
            "{$this->primaryKey} = ?",
            [$this->attributes[$this->primaryKey]]
        );
    }
    
    /**
     * Lọc dữ liệu chỉ lấy các field trong fillable
     * 
     * @param array $data
     * @return array
     */
    protected function filterFillable($data)
    {
        if (!empty($this->fillable)) {
            return array_intersect_key($data, array_flip($this->fillable));
        }
        
        return array_diff_key($data, array_flip($this->guarded));
    }
    
    /**
     * Magic method để truy cập attributes
     */
    public function __get($name)
    {
        return $this->attributes[$name] ?? null;
    }
    
    /**
     * Magic method để set attributes
     */
    public function __set($name, $value)
    {
        $this->attributes[$name] = $value;
    }
    
    /**
     * Lấy tất cả attributes
     * 
     * @return array
     */
    public function toArray()
    {
        return $this->attributes;
    }
    
    /**
     * Chuyển thành JSON
     * 
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->attributes, JSON_UNESCAPED_UNICODE);
    }
}