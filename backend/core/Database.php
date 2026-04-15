<?php
/**
 * Database Connection - Singleton Pattern
 * Quản lý kết nối PDO và các truy vấn cơ bản
 * 
 * @package Core
 */

namespace Core;

use PDO;
use PDOException;

class Database
{
    /**
     * @var Database|null Singleton instance
     */
    private static $instance = null;
    
    /**
     * @var PDO PDO connection
     */
    private $connection;
    
    /**
     * @var array Query log
     */
    private $queryLog = [];
    
    /**
     * Private constructor (Singleton pattern)
     */
    private function __construct()
    {
        try {
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $port = $_ENV['DB_PORT'] ?? 3306;
            $dbname = $_ENV['DB_NAME'] ?? 'security_db';
            $charset = 'utf8mb4';
            
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
            
            $this->connection = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false
            ]);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get singleton instance
     * 
     * @return Database
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    /**
     * Get PDO connection
     * 
     * @return PDO
     */
    public function getConnection()
    {
        return $this->connection;
    }
    
    /**
     * Execute query with parameters
     * 
     * @param string $sql SQL query
     * @param array $params Parameters
     * @return \PDOStatement
     */
    public function query($sql, $params = [])
    {
        $startTime = microtime(true);
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        
        // Log query if debug mode
        if (($_ENV['APP_DEBUG'] ?? false) === 'true') {
            $this->queryLog[] = [
                'sql' => $sql,
                'params' => $params,
                'time' => round((microtime(true) - $startTime) * 1000, 2)
            ];
        }
        
        return $stmt;
    }
    
    /**
     * Fetch all rows
     * 
     * @param string $sql SQL query
     * @param array $params Parameters
     * @return array
     */
    public function fetchAll($sql, $params = [])
    {
        return $this->query($sql, $params)->fetchAll();
    }
    
    /**
     * Fetch single row
     * 
     * @param string $sql SQL query
     * @param array $params Parameters
     * @return array|false
     */
    public function fetchOne($sql, $params = [])
    {
        return $this->query($sql, $params)->fetch();
    }
    
    /**
     * Fetch single column value
     * 
     * @param string $sql SQL query
     * @param array $params Parameters
     * @return mixed
     */
    public function fetchColumn($sql, $params = [])
    {
        return $this->query($sql, $params)->fetchColumn();
    }
    
    /**
     * Insert data into table
     * 
     * @param string $table Table name
     * @param array $data Associative array column => value
     * @return int Last insert ID
     */
    public function insert($table, $data)
    {
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        
        $sql = "INSERT INTO {$table} (" . implode(',', $fields) . ") VALUES ({$placeholders})";
        
        $this->query($sql, $data);
        return (int)$this->connection->lastInsertId();
    }
    
    /**
     * Insert multiple rows
     * 
     * @param string $table Table name
     * @param array $rows Array of associative arrays
     * @return int Number of affected rows
     */
    public function insertMultiple($table, $rows)
    {
        if (empty($rows)) {
            return 0;
        }
        
        $fields = array_keys($rows[0]);
        $placeholders = '(' . implode(',', array_fill(0, count($fields), '?')) . ')';
        $placeholders = implode(',', array_fill(0, count($rows), $placeholders));
        
        $sql = "INSERT INTO {$table} (" . implode(',', $fields) . ") VALUES {$placeholders}";
        
        $params = [];
        foreach ($rows as $row) {
            foreach ($fields as $field) {
                $params[] = $row[$field] ?? null;
            }
        }
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Update data in table
     * 
     * @param string $table Table name
     * @param array $data Associative array column => value
     * @param string $where WHERE clause (without WHERE keyword)
     * @param array $whereParams Parameters for WHERE clause
     * @return int Number of affected rows
     */
    public function update($table, $data, $where, $whereParams = [])
    {
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "{$key} = :{$key}";
        }
        
        $sql = "UPDATE {$table} SET " . implode(',', $set) . " WHERE {$where}";
        
        $params = array_merge($data, $whereParams);
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Delete from table
     * 
     * @param string $table Table name
     * @param string $where WHERE clause
     * @param array $params Parameters
     * @return int Number of affected rows
     */
    public function delete($table, $where, $params = [])
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction()
    {
        $this->connection->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit()
    {
        $this->connection->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback()
    {
        $this->connection->rollBack();
    }
    
    /**
     * Get query log
     * 
     * @return array
     */
    public function getQueryLog()
    {
        return $this->queryLog;
    }
    
    /**
     * Escape string for safe SQL (use prepared statements instead)
     * 
     * @param string $value
     * @return string
     */
    public function escape($value)
    {
        return $this->connection->quote($value);
    }
}