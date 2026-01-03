<?php
/**
 * Project Service
 * 
 * Handles business logic for project management.
 */

require_once __DIR__ . '/../Repository/autoload.php';

class ProjectService {
    private $projectRepo;
    private $taskRepo;
    private $clientRepo;
    private $pdo;
    
    public function __construct($pdo = null) {
        $this->pdo = $pdo ?? get_db_connection();
        $this->projectRepo = new ProjectRepository($this->pdo);
        $this->taskRepo = new TaskRepository($this->pdo);
        $this->clientRepo = new ClientRepository($this->pdo);
    }
    
    /**
     * Create a new project with validation
     * 
     * @param array $data Project data
     * @return array Result with 'success', 'message', and optionally 'project_id'
     */
    public function createProject(array $data) {
        try {
            // Validate required fields
            if (empty($data['name'])) {
                return [
                    'success' => false,
                    'message' => 'Project name is required'
                ];
            }
            
            // Validate client exists if provided
            if (!empty($data['client_id'])) {
                $client = $this->clientRepo->findById($data['client_id']);
                if (!$client) {
                    return [
                        'success' => false,
                        'message' => 'Invalid client ID'
                    ];
                }
            }
            
            // Validate dates
            if (!empty($data['start_date']) && !empty($data['end_date'])) {
                if (strtotime($data['end_date']) < strtotime($data['start_date'])) {
                    return [
                        'success' => false,
                        'message' => 'End date must be after start date'
                    ];
                }
            }
            
            $projectId = $this->projectRepo->create($data);
            
            if ($projectId) {
                return [
                    'success' => true,
                    'message' => 'Project created successfully',
                    'project_id' => $projectId
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to create project'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Create project error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while creating the project'
            ];
        }
    }
    
    /**
     * Update project with validation
     * 
     * @param int $projectId Project ID
     * @param array $data Project data to update
     * @return array Result with 'success' and 'message'
     */
    public function updateProject($projectId, array $data) {
        try {
            // Check if project exists
            $project = $this->projectRepo->findById($projectId);
            if (!$project) {
                return [
                    'success' => false,
                    'message' => 'Project not found'
                ];
            }
            
            // Validate client if provided
            if (isset($data['client_id']) && !empty($data['client_id'])) {
                $client = $this->clientRepo->findById($data['client_id']);
                if (!$client) {
                    return [
                        'success' => false,
                        'message' => 'Invalid client ID'
                    ];
                }
            }
            
            // Validate dates
            if (isset($data['start_date']) && isset($data['end_date'])) {
                if (!empty($data['start_date']) && !empty($data['end_date'])) {
                    if (strtotime($data['end_date']) < strtotime($data['start_date'])) {
                        return [
                            'success' => false,
                            'message' => 'End date must be after start date'
                        ];
                    }
                }
            }
            
            $success = $this->projectRepo->updateProject($projectId, $data);
            
            if ($success) {
                return [
                    'success' => true,
                    'message' => 'Project updated successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to update project'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Update project error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while updating the project'
            ];
        }
    }
    
    /**
     * Delete project with cascade handling
     * 
     * @param int $projectId Project ID
     * @param bool $forceCascade Force delete even if project has tasks/hours
     * @return array Result with 'success' and 'message'
     */
    public function deleteProject($projectId, $forceCascade = false) {
        try {
            $this->pdo->beginTransaction();
            
            // Check if project exists
            $project = $this->projectRepo->findById($projectId);
            if (!$project) {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Project not found'
                ];
            }
            
            // Check if project has tasks
            if ($this->projectRepo->hasTasks($projectId)) {
                if (!$forceCascade) {
                    $this->pdo->rollBack();
                    return [
                        'success' => false,
                        'message' => 'Cannot delete project with existing tasks. Please delete tasks first or use force delete.'
                    ];
                }
                
                // Delete all tasks associated with project
                // Note: This should cascade to hours via foreign keys or be handled explicitly
                $tasks = $this->taskRepo->getByProjectId($projectId);
                foreach ($tasks as $task) {
                    if ($this->taskRepo->hasHours($task['id'])) {
                        $this->pdo->rollBack();
                        return [
                            'success' => false,
                            'message' => 'Cannot delete project because tasks have logged hours'
                        ];
                    }
                    $this->taskRepo->delete($task['id']);
                }
            }
            
            // Delete the project
            $success = $this->projectRepo->delete($projectId);
            
            if ($success) {
                $this->pdo->commit();
                return [
                    'success' => true,
                    'message' => 'Project deleted successfully'
                ];
            } else {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Failed to delete project'
                ];
            }
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Delete project error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while deleting the project'
            ];
        }
    }
    
    /**
     * Get project with tasks and hours summary
     * 
     * @param int $projectId Project ID
     * @return array|null Project data with tasks and summary
     */
    public function getProjectWithDetails($projectId) {
        $project = $this->projectRepo->getByIdWithClient($projectId);
        
        if (!$project) {
            return null;
        }
        
        // Get tasks
        $project['tasks'] = $this->taskRepo->getByProjectId($projectId);
        
        // Get hours summary
        $hoursRepo = new HoursRepository($this->pdo);
        $project['total_hours'] = $hoursRepo->getTotalByProject($projectId);
        
        // Calculate budget remaining if budget exists
        if ($project['budget_hours']) {
            $project['hours_remaining'] = $project['budget_hours'] - $project['total_hours'];
            $project['budget_percentage'] = ($project['total_hours'] / $project['budget_hours']) * 100;
        }
        
        return $project;
    }
    
    /**
     * Create project from template
     * 
     * @param int $templateId Template project ID
     * @param array $data New project data (name, client_id, etc.)
     * @return array Result with 'success', 'message', and optionally 'project_id'
     */
    public function createFromTemplate($templateId, array $data) {
        try {
            $this->pdo->beginTransaction();
            
            // Get template project
            $template = $this->projectRepo->findById($templateId);
            if (!$template) {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Template project not found'
                ];
            }
            
            // Create new project
            $newProjectData = array_merge([
                'name' => $data['name'],
                'client_id' => $data['client_id'] ?? $template['client_id'],
                'status' => 'active',
                'description' => $template['description']
            ], $data);
            
            $projectId = $this->projectRepo->create($newProjectData);
            
            if (!$projectId) {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Failed to create project from template'
                ];
            }
            
            // Copy tasks from template
            $templateTasks = $this->taskRepo->getByProjectId($templateId);
            foreach ($templateTasks as $task) {
                $this->taskRepo->create([
                    'name' => $task['name'],
                    'project_id' => $projectId,
                    'client_id' => $data['client_id'] ?? $template['client_id'],
                    'status' => 'not-started',
                    'description' => $task['description'],
                    'estimated_hours' => $task['estimated_hours']
                ]);
            }
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Project created from template successfully',
                'project_id' => $projectId
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Create from template error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while creating project from template'
            ];
        }
    }
}
