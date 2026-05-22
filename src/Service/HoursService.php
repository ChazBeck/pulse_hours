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
    private $pulseRepo;
    private $pdo;

    public function __construct($pdo = null) {
        $this->pdo = $pdo ?? get_db_connection();
        $this->hoursRepo = new HoursRepository($this->pdo);
        $this->taskRepo = new TaskRepository($this->pdo);
        $this->userRepo = new UserRepository($this->pdo);
        $this->pulseRepo = new PulseRepository($this->pdo);
    }

    /**
     * Fetch the year_week from a user's most recently submitted pulse,
     * ordered by submission timestamp. Returns null if the user has
     * never submitted one.
     */
    public function getCurrentYearWeekForUser($userId) {
        $stmt = $this->pdo->prepare(
            "SELECT year_week FROM pulse
             WHERE user_id = ?
             ORDER BY date_created DESC
             LIMIT 1"
        );
        $stmt->execute([(int) $userId]);
        $row = $stmt->fetch();
        return $row ? $row['year_week'] : null;
    }

    /**
     * Build the nested client -> project -> task structure used by
     * the user-facing hours entry form, including any existing hours
     * already logged for the supplied week.
     *
     * @return array<int, array> Client rows with nested projects and
     *   client-level tasks. Only clients with at least one active,
     *   non-completed task are included.
     */
    public function getEntryFormData($userId, $yearWeek) {
        $userId = (int) $userId;
        $clientStmt = $this->pdo->query("
            SELECT c.id as client_id, c.name as client_name, c.client_logo
            FROM clients c
            WHERE c.active = 1
            ORDER BY c.name ASC
        ");
        $clients = $clientStmt->fetchAll();

        $projectStmt = $this->pdo->prepare("
            SELECT p.id as project_id, p.name as project_name, p.active as project_active
            FROM projects p
            WHERE p.client_id = ? AND p.active = 1
            ORDER BY p.name ASC
        ");
        $projectTasksStmt = $this->pdo->prepare("
            SELECT t.id as task_id, t.name as task_name, t.status as task_status
            FROM tasks t
            WHERE t.project_id = ? AND t.status != 'completed'
            ORDER BY t.sort_order ASC, t.name ASC
        ");
        $clientTasksStmt = $this->pdo->prepare("
            SELECT t.id as task_id, t.name as task_name, t.status as task_status
            FROM tasks t
            WHERE t.client_id = ? AND t.project_id IS NULL AND t.status != 'completed'
            ORDER BY t.sort_order ASC, t.name ASC
        ");
        $hoursStmt = $this->pdo->prepare("
            SELECT date_worked, hours
            FROM hours
            WHERE user_id = ? AND task_id = ? AND year_week = ?
            ORDER BY date_worked DESC
        ");

        $clientData = [];
        foreach ($clients as $client) {
            $clientId = $client['client_id'];

            $projectStmt->execute([$clientId]);
            $projects = $projectStmt->fetchAll();

            $clientProjects = [];
            foreach ($projects as $project) {
                $projectTasksStmt->execute([$project['project_id']]);
                $tasks = $projectTasksStmt->fetchAll();

                foreach ($tasks as &$task) {
                    $hoursStmt->execute([$userId, $task['task_id'], $yearWeek]);
                    $task['existing_hours'] = $hoursStmt->fetchAll();
                }
                unset($task);

                $project['tasks'] = $tasks;
                $clientProjects[] = $project;
            }

            $clientTasksStmt->execute([$clientId]);
            $clientLevelTasks = $clientTasksStmt->fetchAll();
            foreach ($clientLevelTasks as &$task) {
                $hoursStmt->execute([$userId, $task['task_id'], $yearWeek]);
                $task['existing_hours'] = $hoursStmt->fetchAll();
            }
            unset($task);

            $hasTasks = !empty($clientLevelTasks);
            foreach ($clientProjects as $p) {
                if (!empty($p['tasks'])) {
                    $hasTasks = true;
                    break;
                }
            }

            if ($hasTasks) {
                $client['projects'] = $clientProjects;
                $client['client_tasks'] = $clientLevelTasks;
                $clientData[] = $client;
            }
        }

        return $clientData;
    }

    /**
     * Upsert today's hours for a user across a set of task ids.
     * Skips zero or empty entries. All writes share a transaction so
     * a validation failure rolls everything back.
     *
     * @param array<int, mixed> $hoursByTaskId Map of task_id => hours string
     */
    public function submitTodayHours($userId, $yearWeek, array $hoursByTaskId) {
        $userId = (int) $userId;
        $yearWeek = (string) $yearWeek;
        $dateWorked = date('Y-m-d');

        try {
            $this->pdo->beginTransaction();

            $taskStmt = $this->pdo->prepare("SELECT project_id FROM tasks WHERE id = ?");
            $existingStmt = $this->pdo->prepare(
                "SELECT id FROM hours WHERE user_id = ? AND task_id = ? AND date_worked = ?"
            );
            $updateStmt = $this->pdo->prepare(
                "UPDATE hours SET hours = ?, year_week = ? WHERE id = ?"
            );
            $insertStmt = $this->pdo->prepare(
                "INSERT INTO hours (user_id, project_id, task_id, date_worked, year_week, hours)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            foreach ($hoursByTaskId as $taskId => $rawHours) {
                $rawHours = trim((string) $rawHours);
                if ($rawHours === '' || $rawHours === '0' || $rawHours === '0.00') {
                    continue;
                }
                if (!is_numeric($rawHours) || (float) $rawHours <= 0) {
                    $this->pdo->rollBack();
                    return ['success' => false, 'message' => 'Hours must be a positive number.'];
                }

                $taskStmt->execute([(int) $taskId]);
                $task = $taskStmt->fetch();
                if (!$task) {
                    $this->pdo->rollBack();
                    return ['success' => false, 'message' => 'Invalid task ID: ' . (int) $taskId];
                }

                $existingStmt->execute([$userId, (int) $taskId, $dateWorked]);
                $existing = $existingStmt->fetch();

                if ($existing) {
                    $updateStmt->execute([$rawHours, $yearWeek, $existing['id']]);
                } else {
                    $insertStmt->execute([
                        $userId,
                        $task['project_id'],
                        (int) $taskId,
                        $dateWorked,
                        $yearWeek,
                        $rawHours,
                    ]);
                }
            }

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Hours saved.'];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('HoursService::submitTodayHours failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error saving hours.'];
        }
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
     * Update a single hours entry. The year_week is derived from the
     * supplied date_worked so it stays consistent with the date.
     */
    public function updateEntry($id, array $data) {
        $id = (int) $id;
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Invalid entry ID.'];
        }

        $rawHours = $data['hours'] ?? '';
        if ($rawHours === '' || $rawHours === null) {
            $hours = 0.0;
        } elseif (!is_numeric($rawHours)) {
            return ['success' => false, 'message' => 'Hours must be a number.'];
        } else {
            $hours = (float) $rawHours;
        }

        if ($hours < 0) {
            return ['success' => false, 'message' => 'Hours must be non-negative.'];
        }

        $dateWorked = $data['date_worked'] ?? '';
        $ts = strtotime($dateWorked);
        if (!$ts) {
            return ['success' => false, 'message' => 'Invalid date.'];
        }

        try {
            $this->hoursRepo->update($id, [
                'hours'       => $hours,
                'date_worked' => $dateWorked,
                'year_week'   => date('o-W', $ts),
            ]);
            return ['success' => true, 'message' => 'Entry updated successfully.'];
        } catch (Throwable $e) {
            error_log('HoursService::updateEntry failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error updating entry.'];
        }
    }

    /**
     * Admin-only delete that skips the per-user ownership check.
     */
    public function deleteEntryAsAdmin($id) {
        $id = (int) $id;
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Invalid entry ID.'];
        }
        try {
            $this->hoursRepo->delete($id);
            return ['success' => true, 'message' => 'Entry deleted successfully.'];
        } catch (Throwable $e) {
            error_log('HoursService::deleteEntryAsAdmin failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error deleting entry.'];
        }
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
