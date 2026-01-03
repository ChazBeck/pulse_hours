<?php
/**
 * Base Repository Class
 * 
 * Provides common database operations and connection management.
 * All repository classes should extend this base class.
 */

abstract class BaseRepository {
    protected $pdo;
    protected $table;
    
    public function __construct($pdo = null) {
        $this->pdo = $pdo ?? get_db_connection();
    }
    
    /**
     * Find a record by ID
     * 
     * @param int $id Record ID
     * @return array|false Record data or false if not found
     */
    public function findById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Find all records
     * 
     * @param string $orderBy Order by clause (e.g., "name ASC")
     * @return array Array of records
     */
    public function findAll($orderBy = 'id ASC') {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table} ORDER BY {$orderBy}");
        return $stmt->fetchAll();
    }
    
    /**
     * Insert a new record
     * 
     * @param array $data Associative array of column => value
     * @return int|false Last insert ID or false on failure
     */
    public function insert(array $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->table} ($columns) 
            VALUES ($placeholders)
        ");
        
        if ($stmt->execute(array_values($data))) {
            return $this->pdo->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Update a record by ID
     * 
     * @param int $id Record ID
     * @param array $data Associative array of column => value
     * @return bool Success status
     */
    public function update($id, array $data) {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "$column = ?";
        }
        $setClause = implode(', ', $setParts);
        
        $values = array_values($data);
        $values[] = $id;
        
        $stmt = $this->pdo->prepare("
            UPDATE {$this->table} 
            SET $setClause 
            WHERE id = ?
        ");
        
        return $stmt->execute($values);
    }
    
    /**
     * Delete a record by ID
     * 
     * @param int $id Record ID
     * @return bool Success status
     */
    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Count total records
     * 
     * @param string $where Optional WHERE clause (without WHERE keyword)
     * @param array $params Parameters for WHERE clause
     * @return int Record count
     */
    public function count($where = '', array $params = []) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        if ($where) {
            $sql .= " WHERE $where";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetch()['count'];
    }
}
