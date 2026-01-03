<?php
/**
 * Project Repository
 * 
 * Handles all database operations for projects table.
 */

require_once __DIR__ . '/BaseRepository.php';

class ProjectRepository extends BaseRepository {
    protected $table = 'projects';
    
    /**
     * Get all projects with client information
     * 
     * @param bool $activeOnly Only return active projects
     * @return array Array of project records with client data
     */
    public function getAllWithClient($activeOnly = false) {
        $sql = "
            SELECT p.*, c.name as client_name, c.logo_path as client_logo
            FROM projects p
            LEFT JOIN clients c ON p.client_id = c.id
        ";
        
        if ($activeOnly) {
            $sql .= " WHERE p.status = 'active'";
        }
        
        $sql .= " ORDER BY c.name ASC, p.name ASC";
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Get projects by client ID
     * 
     * @param int $clientId Client ID
     * @param bool $activeOnly Only return active projects
     * @return array Array of project records
     */
    public function getByClientId($clientId, $activeOnly = false) {
        $sql = "SELECT * FROM projects WHERE client_id = ?";
        
        if ($activeOnly) {
            $sql .= " AND status = 'active'";
        }
        
        $sql .= " ORDER BY name ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get project by ID with client information
     * 
     * @param int $id Project ID
     * @return array|false Project data with client info or false if not found
     */
    public function getByIdWithClient($id) {
        $stmt = $this->pdo->prepare("
            SELECT p.*, c.name as client_name, c.logo_path as client_logo
            FROM projects p
            LEFT JOIN clients c ON p.client_id = c.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Create a new project
     * 
     * @param array $data Project data
     * @return int|false Project ID or false on failure
     */
    public function create(array $data) {
        return $this->insert([
            'name' => $data['name'],
            'client_id' => $data['client_id'] ?? null,
            'status' => $data['status'] ?? 'active',
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'budget_hours' => $data['budget_hours'] ?? null,
            'description' => $data['description'] ?? null
        ]);
    }
    
    /**
     * Update project information
     * 
     * @param int $id Project ID
     * @param array $data Project data to update
     * @return bool Success status
     */
    public function updateProject($id, array $data) {
        $updateData = [];
        
        $fields = ['name', 'client_id', 'status', 'start_date', 'end_date', 'budget_hours', 'description'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        
        return $this->update($id, $updateData);
    }
    
    /**
     * Get projects with task counts
     * 
     * @param int|null $clientId Optional client ID to filter
     * @return array Array of projects with task counts
     */
    public function getWithTaskCounts($clientId = null) {
        $sql = "
            SELECT p.*, c.name as client_name,
                   COUNT(DISTINCT t.id) as task_count
            FROM projects p
            LEFT JOIN clients c ON p.client_id = c.id
            LEFT JOIN tasks t ON p.id = t.project_id
        ";
        
        $params = [];
        if ($clientId !== null) {
            $sql .= " WHERE p.client_id = ?";
            $params[] = $clientId;
        }
        
        $sql .= " GROUP BY p.id ORDER BY c.name ASC, p.name ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Check if project has tasks
     * 
     * @param int $projectId Project ID
     * @return bool True if project has tasks
     */
    public function hasTasks($projectId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count FROM tasks WHERE project_id = ?
        ");
        $stmt->execute([$projectId]);
        return $stmt->fetch()['count'] > 0;
    }
    
    /**
     * Get active projects
     * 
     * @return array Array of active projects
     */
    public function getActive() {
        $stmt = $this->pdo->query("
            SELECT * FROM projects 
            WHERE status = 'active' 
            ORDER BY name ASC
        ");
        return $stmt->fetchAll();
    }
}
