<?php
/**
 * Hours Service
 * 
 * Handles business logic for hours logging and tracking.
 */

require_once __DIR__ . '/../Repository/autoload.php';

class HoursService {
    private $hoursRepo;
    private $taskRepo;
    private $userRepo;
    private $pdo;
    
    public function __construct($pdo = null) {
        $this->pdo = $pdo ?? get_db_connection();
        $this->hoursRepo = new HoursRepository($this->pdo);
        $this->taskRepo = new TaskRepository($this->pdo);
        $this->userRepo = new UserRepository($this->pdo);
    }
    
    /**
     * Submit hours for a user and week
     * 
     * @param int $userId User ID
     * @param string $yearWeek Year-week string
     * @param array $hoursData Array of hours data indexed by "date_taskId"
     * @param array $notesData Array of notes data indexed by "date_taskId"
     * @return array Result with 'success' and 'message'
     */
    public function submitHoursForWeek($userId, $yearWeek, array $hoursData, array $notesData) {
        try {
            $this->pdo->beginTransaction();
            
            $processedCount = 0;
            $errors = [];
            
            foreach ($hoursData as $key => $hours) {
                // Parse key format: "YYYY-MM-DD_taskId"
                list($date, $taskId) = explode('_', $key);
                
                $hours = floatval($hours);
                $notes = $notesData[$key] ?? '';
                
                // Skip if hours is 0 or empty
                if ($hours <= 0) {
                    continue;
                }
                
                // Validate task exists
                $task = $this->taskRepo->findById($taskId);
                if (!$task) {
                    $errors[] = "Task ID $taskId not found for date $date";
                    continue;
                }
                
                // Log hours
                $result = $this->hoursRepo->logHours([
                    'user_id' => $userId,
                    'task_id' => $taskId,
                    'date_worked' => $date,
                    'hours' => $hours,
                    'notes' => $notes,
                    'year_week' => $yearWeek
                ]);
                
                if ($result) {
                    $processedCount++;
                } else {
                    $errors[] = "Failed to log hours for task $taskId on $date";
                }
            }
            
            $this->pdo->commit();
            
            if (count($errors) > 0) {
                return [
                    'success' => false,
                    'message' => 'Some hours could not be saved: ' . implode(', ', $errors),
                    'processed' => $processedCount
                ];
            }
            
            return [
                'success' => true,
                'message' => "Successfully logged $processedCount hour entries",
                'processed' => $processedCount
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Hours submission error: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'An error occurred while saving hours. Please try again.'
            ];
        }
    }
    
    /**
     * Get hours structure for a user and week
     * Organizes hours by client -> project -> task with existing hours
     * 
     * @param int $userId User ID
     * @param string $yearWeek Year-week string
     * @return array Hierarchical structure of clients/projects/tasks with hours
     */
    public function getHoursStructureForWeek($userId, $yearWeek) {
        // Get all hours for this user and week
        $existingHours = $this->hoursRepo->getByUserAndWeek($userId, $yearWeek);
        
        // Index by task_id and date for easy lookup
        $hoursIndex = [];
        foreach ($existingHours as $hour) {
            $key = $hour['task_id'] . '_' . $hour['date_worked'];
            $hoursIndex[$key] = $hour;
        }
        
        // Get user's assigned tasks
        $tasks = $this->taskRepo->getByUserId($userId);
        
        // Organize by client -> project -> task
        $structure = [];
        
        foreach ($tasks as $task) {
            $clientId = $task['client_id'];
            $clientName = $task['client_name'] ?? 'No Client';
            $projectId = $task['project_id'];
            $projectName = $task['project_name'] ?? 'Client-Level Task';
            
            // Initialize client if not exists
            if (!isset($structure[$clientId])) {
                $structure[$clientId] = [
                    'id' => $clientId,
                    'name' => $clientName,
                    'projects' => []
                ];
            }
            
            // Initialize project if not exists
            if (!isset($structure[$clientId]['projects'][$projectId])) {
                $structure[$clientId]['projects'][$projectId] = [
                    'id' => $projectId,
                    'name' => $projectName,
                    'tasks' => []
                ];
            }
            
            // Add task
            $structure[$clientId]['projects'][$projectId]['tasks'][] = [
                'id' => $task['id'],
                'name' => $task['name'],
                'status' => $task['status'],
                'existing_hours' => $hoursIndex // Pass index for lookup
            ];
        }
        
        return [
            'structure' => $structure,
            'total_hours' => $this->hoursRepo->getTotalByUserAndWeek($userId, $yearWeek)
        ];
    }
    
    /**
     * Delete hours entry with ownership check
     * 
     * @param int $hoursId Hours ID
     * @param int $userId User ID (for ownership check)
     * @param bool $isAdmin Whether user is admin
     * @return array Result with 'success' and 'message'
     */
    public function deleteHours($hoursId, $userId, $isAdmin = false) {
        try {
            if ($isAdmin) {
                // Admin can delete any hours
                $success = $this->hoursRepo->delete($hoursId);
            } else {
                // Non-admin can only delete their own hours
                $success = $this->hoursRepo->deleteHours($hoursId, $userId);
            }
            
            if ($success) {
                return [
                    'success' => true,
                    'message' => 'Hours entry deleted successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Hours entry not found or you do not have permission to delete it'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Delete hours error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while deleting hours'
            ];
        }
    }
    
    /**
     * Get hours summary for reporting
     * 
     * @param array $filters Filters (user_id, year_week, client_id, project_id, date_range)
     * @return array Summary data
     */
    public function getHoursSummary(array $filters = []) {
        $hours = $this->hoursRepo->getAllWithDetails($filters);
        
        $summary = [
            'total_hours' => 0,
            'total_entries' => count($hours),
            'by_user' => [],
            'by_client' => [],
            'by_project' => [],
            'entries' => $hours
        ];
        
        foreach ($hours as $entry) {
            $summary['total_hours'] += $entry['hours'];
            
            // Group by user
            $userName = $entry['first_name'] . ' ' . $entry['last_name'];
            if (!isset($summary['by_user'][$userName])) {
                $summary['by_user'][$userName] = 0;
            }
            $summary['by_user'][$userName] += $entry['hours'];
            
            // Group by client
            $clientName = $entry['client_name'] ?? 'No Client';
            if (!isset($summary['by_client'][$clientName])) {
                $summary['by_client'][$clientName] = 0;
            }
            $summary['by_client'][$clientName] += $entry['hours'];
            
            // Group by project
            $projectName = $entry['project_name'] ?? 'Client-Level';
            if (!isset($summary['by_project'][$projectName])) {
                $summary['by_project'][$projectName] = 0;
            }
            $summary['by_project'][$projectName] += $entry['hours'];
        }
        
        return $summary;
    }
}
