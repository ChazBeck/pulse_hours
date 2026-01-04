<?php
/**
 * FilterBuilder
 * 
 * Utility class for building dynamic WHERE clauses for SQL queries.
 * Helps eliminate duplicate filter logic across multiple pages.
 * 
 * Usage:
 *   $filter = new FilterBuilder();
 *   $filter->addFilter('u.id = ?', $_GET['user'] ?? null)
 *          ->addFilter('c.id = ?', $_GET['client'] ?? null)
 *          ->addFilter('h.year_week = ?', $_GET['week'] ?? null);
 *   
 *   $where = $filter->buildWhere();  // "WHERE u.id = ? AND c.id = ?"
 *   $params = $filter->getParams();  // [user_value, client_value]
 */
class FilterBuilder {
    private $clauses = [];
    private $params = [];
    
    /**
     * Add a filter condition
     * 
     * @param string $condition SQL condition with placeholder (e.g., "u.id = ?")
     * @param mixed $value Filter value (null or empty values are ignored)
     * @return self For method chaining
     */
    public function addFilter($condition, $value) {
        // Only add filter if value is not empty
        if ($value !== null && $value !== '') {
            $this->clauses[] = $condition;
            $this->params[] = $value;
        }
        return $this;
    }
    
    /**
     * Add multiple filters at once from an array
     * 
     * @param array $filters Associative array of condition => value
     * @return self For method chaining
     */
    public function addFilters(array $filters) {
        foreach ($filters as $condition => $value) {
            $this->addFilter($condition, $value);
        }
        return $this;
    }
    
    /**
     * Build the WHERE clause
     * 
     * @param string $operator Logical operator to join conditions (default: "AND")
     * @return string WHERE clause or empty string if no filters
     */
    public function buildWhere($operator = 'AND') {
        if (empty($this->clauses)) {
            return '';
        }
        
        return 'WHERE ' . implode(" $operator ", $this->clauses);
    }
    
    /**
     * Get the parameter values for prepared statement
     * 
     * @return array Array of parameter values
     */
    public function getParams() {
        return $this->params;
    }
    
    /**
     * Check if any filters have been added
     * 
     * @return bool True if filters exist
     */
    public function hasFilters() {
        return !empty($this->clauses);
    }
    
    /**
     * Get count of active filters
     * 
     * @return int Number of filter conditions
     */
    public function count() {
        return count($this->clauses);
    }
    
    /**
     * Clear all filters
     * 
     * @return self For method chaining
     */
    public function clear() {
        $this->clauses = [];
        $this->params = [];
        return $this;
    }
    
    /**
     * Build complete SQL condition (without WHERE keyword)
     * Useful for adding to existing WHERE clauses
     * 
     * @param string $operator Logical operator to join conditions (default: "AND")
     * @return string SQL condition or empty string if no filters
     */
    public function buildCondition($operator = 'AND') {
        if (empty($this->clauses)) {
            return '';
        }
        
        return implode(" $operator ", $this->clauses);
    }
}
