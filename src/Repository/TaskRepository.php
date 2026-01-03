<?php
/**
 * Task Repository
 * 
 * Handles all database operations for tasks table.
 */

require_once __DIR__ . '/BaseRepository.php';

class TaskRepository extends BaseRepository {
    protected $table = 'tasks';
    
    /**
     * Get all tasks with project and client information
     * 
     * @return array Array of task records with related data
     */
    public function getAllWithRelations() {
        $stmt = $this->pdo->query("
            SELECT t.*, 
                   p.name as project_name, 
                   c.name as client_name,
                   c.id as client_id
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN clients c ON t.client_id = c.id
            ORDER BY c.name ASC, p.name ASC, t.name ASC
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * Get tasks by project ID
     * 
     * @param int $projectId Project ID
     * @return array Array of task records
     */
    public function getByProjectId($projectId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM tasks 
            WHERE project_id = ? 
            ORDER BY name ASC
        ");
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get tasks by client ID (client-level tasks without project)
     * 
     * @param int $clientId Client ID
     * @return array Array of task records
     */
    public function getByClientId($clientId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM tasks 
            WHERE client_id = ? AND project_id IS NULL
            ORDER BY name ASC
        ");
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get task by ID with relations
     * 
     * @param int $id Task ID
     * @return array|false Task data with project and client info
     */
    public function getByIdWithRelations($id) {
        $stmt = $this->pdo->prepare("
            SELECT t.*, 
                   p.name as project_name, 
                   c.name as client_name,
                   c.id as client_id
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN clients c ON t.client_id = c.id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Create a new task
     * 
     * @param array $data Task data
     * @return int|false Task ID or false on failure
     */
    public function create(array $data) {
        return $this->insert([
            'name' => $data['name'],
            'project_id' => $data['project_id'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'status' => $data['status'] ?? 'not-started',
            'description' => $data['description'] ?? null,
            'estimated_hours' => $data['estimated_hours'] ?? null
        ]);
    }
    
    /**
     * Update task information
     * 
     * @param int $id Task ID
     * @param array $data Task data to update
     * @return bool Success status
     */
    public function updateTask($id, array $data) {
        $updateData = [];
        
        $fields = ['name', 'project_id', 'client_id', 'status', 'description', 'estimated_hours'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        
        return $this->update($id, $updateData);
    }
    
    /**
     * Get tasks assigned to a user
     * 
     * @param int $userId User ID
     * @return array Array of task records
     */
    public function getByUserId($userId) {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT t.*, 
                   p.name as project_name, 
                   c.name as client_name
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN clients c ON t.client_id = c.id
            INNER JOIN user_tasks ut ON t.id = ut.task_id
            WHERE ut.user_id = ?
            ORDER BY c.name ASC, p.name ASC, t.name ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Assign task to user
     * 
     * @param int $taskId Task ID
     * @param int $userId User ID
     * @return bool Success status
     */
    public function assignToUser($taskId, $userId) {
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO user_tasks (user_id, task_id) 
            VALUES (?, ?)
        ");
        return $stmt->execute([$userId, $taskId]);
    }
    
    /**
     * Unassign task from user
     * 
     * @param int $taskId Task ID
     * @param int $userId User ID
     * @return bool Success status
     */
    public function unassignFromUser($taskId, $userId) {
        $stmt = $this->pdo->prepare("
            DELETE FROM user_tasks 
            WHERE user_id = ? AND task_id = ?
        ");
        return $stmt->execute([$userId, $taskId]);
    }
    
    /**
     * Check if task has hours logged
     * 
     * @param int $taskId Task ID
     * @return bool True if task has hours
     */
    public function hasHours($taskId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count FROM hours WHERE task_id = ?
        ");
        $stmt->execute([$taskId]);
        return $stmt->fetch()['count'] > 0;
    }
}
