<?php
/**
 * Hours Repository
 * 
 * Handles all database operations for hours table.
 */

require_once __DIR__ . '/BaseRepository.php';

class HoursRepository extends BaseRepository {
    protected $table = 'hours';
    
    /**
     * Get hours for a specific user and week
     * 
     * @param int $userId User ID
     * @param string $yearWeek Year-week string (e.g., "2026-01")
     * @return array Array of hours records with task/project/client data
     */
    public function getByUserAndWeek($userId, $yearWeek) {
        $stmt = $this->pdo->prepare("
            SELECT h.*, 
                   t.name as task_name,
                   p.name as project_name,
                   c.name as client_name,
                   c.id as client_id,
                   p.id as project_id
            FROM hours h
            JOIN tasks t ON h.task_id = t.id
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN clients c ON t.client_id = c.id
            WHERE h.user_id = ? AND h.year_week = ?
            ORDER BY h.date_worked ASC, c.name ASC, p.name ASC, t.name ASC
        ");
        $stmt->execute([$userId, $yearWeek]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get all hours with full details (for admin views)
     * 
     * @param array $filters Optional filters (user_id, year_week, client_id, project_id)
     * @return array Array of hours records with complete information
     */
    public function getAllWithDetails(array $filters = []) {
        $sql = "
            SELECT h.*, 
                   u.first_name, u.last_name, u.email,
                   t.name as task_name,
                   p.name as project_name,
                   c.name as client_name
            FROM hours h
            JOIN users u ON h.user_id = u.id
            JOIN tasks t ON h.task_id = t.id
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN clients c ON t.client_id = c.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND h.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['year_week'])) {
            $sql .= " AND h.year_week = ?";
            $params[] = $filters['year_week'];
        }
        
        if (!empty($filters['client_id'])) {
            $sql .= " AND c.id = ?";
            $params[] = $filters['client_id'];
        }
        
        if (!empty($filters['project_id'])) {
            $sql .= " AND p.id = ?";
            $params[] = $filters['project_id'];
        }
        
        $sql .= " ORDER BY h.date_worked DESC, u.last_name ASC, u.first_name ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get existing hours for a specific date and task
     * 
     * @param int $userId User ID
     * @param int $taskId Task ID
     * @param string $date Date (YYYY-MM-DD)
     * @return array|false Existing hours record or false
     */
    public function getByUserTaskDate($userId, $taskId, $date) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM hours 
            WHERE user_id = ? AND task_id = ? AND date_worked = ?
        ");
        $stmt->execute([$userId, $taskId, $date]);
        return $stmt->fetch();
    }
    
    /**
     * Log hours (insert or update existing)
     * 
     * @param array $data Hours data
     * @return int|bool Hours ID (new or existing) or false on failure
     */
    public function logHours(array $data) {
        // Check if hours already exist for this user/task/date
        $existing = $this->getByUserTaskDate(
            $data['user_id'],
            $data['task_id'],
            $data['date_worked']
        );
        
        if ($existing) {
            // Update existing record
            $success = $this->update($existing['id'], [
                'hours' => $data['hours'],
                'notes' => $data['notes'] ?? null,
                'year_week' => $data['year_week']
            ]);
            return $success ? $existing['id'] : false;
        } else {
            // Insert new record
            return $this->insert([
                'user_id' => $data['user_id'],
                'task_id' => $data['task_id'],
                'date_worked' => $data['date_worked'],
                'hours' => $data['hours'],
                'notes' => $data['notes'] ?? null,
                'year_week' => $data['year_week']
            ]);
        }
    }
    
    /**
     * Get total hours for a user in a week
     * 
     * @param int $userId User ID
     * @param string $yearWeek Year-week string
     * @return float Total hours
     */
    public function getTotalByUserAndWeek($userId, $yearWeek) {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(hours), 0) as total
            FROM hours
            WHERE user_id = ? AND year_week = ?
        ");
        $stmt->execute([$userId, $yearWeek]);
        return (float) $stmt->fetch()['total'];
    }
    
    /**
     * Get total hours by project
     * 
     * @param int $projectId Project ID
     * @return float Total hours
     */
    public function getTotalByProject($projectId) {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(h.hours), 0) as total
            FROM hours h
            JOIN tasks t ON h.task_id = t.id
            WHERE t.project_id = ?
        ");
        $stmt->execute([$projectId]);
        return (float) $stmt->fetch()['total'];
    }
    
    /**
     * Get hours summary by client for a date range
     * 
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @return array Array of client summaries with total hours
     */
    public function getSummaryByClient($startDate, $endDate) {
        $stmt = $this->pdo->prepare("
            SELECT c.id, c.name, c.logo_path,
                   SUM(h.hours) as total_hours
            FROM hours h
            JOIN tasks t ON h.task_id = t.id
            LEFT JOIN clients c ON t.client_id = c.id
            WHERE h.date_worked BETWEEN ? AND ?
            GROUP BY c.id, c.name, c.logo_path
            ORDER BY total_hours DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll();
    }
    
    /**
     * Delete hours by ID (with ownership check for non-admins)
     * 
     * @param int $id Hours ID
     * @param int|null $userId User ID for ownership check (null for admin)
     * @return bool Success status
     */
    public function deleteHours($id, $userId = null) {
        if ($userId !== null) {
            // Check ownership
            $stmt = $this->pdo->prepare("SELECT user_id FROM hours WHERE id = ?");
            $stmt->execute([$id]);
            $record = $stmt->fetch();
            
            if (!$record || $record['user_id'] != $userId) {
                return false; // Not owner
            }
        }
        
        return $this->delete($id);
    }
}
