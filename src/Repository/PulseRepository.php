<?php
/**
 * Pulse Repository
 * 
 * Handles all database operations for pulse table (pulse and workload data).
 */

require_once __DIR__ . '/BaseRepository.php';

class PulseRepository extends BaseRepository {
    protected $table = 'pulse';
    
    /**
     * Get average pulse or workload for multiple weeks
     * 
     * @param string $metric Metric to average ('pulse' or 'work_load')
     * @param array $yearWeeks Array of year-week strings (e.g., ["2026-01", "2026-02"])
     * @return array Array of averages indexed by year_week
     */
    public function getAverageByWeeks($metric, array $yearWeeks) {
        if (empty($yearWeeks)) {
            return [];
        }
        
        // Validate metric
        if (!in_array($metric, ['pulse', 'work_load'])) {
            throw new InvalidArgumentException("Invalid metric: $metric");
        }
        
        $placeholders = implode(',', array_fill(0, count($yearWeeks), '?'));
        $stmt = $this->pdo->prepare("
            SELECT year_week, AVG($metric) as average, COUNT(*) as count
            FROM pulse
            WHERE year_week IN ($placeholders)
            GROUP BY year_week
            ORDER BY year_week ASC
        ");
        $stmt->execute($yearWeeks);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Index by year_week for easy lookup
        $data = [];
        foreach ($results as $row) {
            $data[$row['year_week']] = round($row['average'], 2);
        }
        
        return $data;
    }
    
    /**
     * Get individual user pulse or workload data for multiple weeks
     * 
     * @param string $metric Metric to retrieve ('pulse' or 'work_load')
     * @param array $yearWeeks Array of year-week strings
     * @return array Array of user data organized by user_id
     */
    public function getByUserAndWeeks($metric, array $yearWeeks) {
        if (empty($yearWeeks)) {
            return [];
        }
        
        // Validate metric
        if (!in_array($metric, ['pulse', 'work_load'])) {
            throw new InvalidArgumentException("Invalid metric: $metric");
        }
        
        $placeholders = implode(',', array_fill(0, count($yearWeeks), '?'));
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, p.year_week, p.$metric as value
            FROM pulse p
            JOIN users u ON p.user_id = u.id
            WHERE p.year_week IN ($placeholders)
            ORDER BY u.last_name, u.first_name, p.year_week ASC
        ");
        $stmt->execute($yearWeeks);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organize by user
        $userData = [];
        foreach ($results as $row) {
            $userId = $row['id'];
            if (!isset($userData[$userId])) {
                $userData[$userId] = [
                    'name' => $row['first_name'] . ' ' . $row['last_name'],
                    'data' => []
                ];
            }
            $userData[$userId]['data'][$row['year_week']] = (float)$row['value'];
        }
        
        // Fill in data for all weeks for each user
        foreach ($userData as $userId => &$user) {
            $filledData = [];
            foreach ($yearWeeks as $yw) {
                $filledData[] = $user['data'][$yw] ?? null;
            }
            $user['chartData'] = $filledData;
        }
        
        return $userData;
    }
    
    /**
     * Get pulse entry for a specific user and week
     * 
     * @param int $userId User ID
     * @param string $yearWeek Year-week string (e.g., "2026-01")
     * @return array|false Pulse record or false if not found
     */
    public function getByUserAndWeek($userId, $yearWeek) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM pulse
            WHERE user_id = ? AND year_week = ?
        ");
        $stmt->execute([$userId, $yearWeek]);
        return $stmt->fetch();
    }
    
    /**
     * Save or update pulse check-in
     * 
     * @param int $userId User ID
     * @param string $yearWeek Year-week string
     * @param int $pulse Pulse rating (1-5)
     * @param int $workLoad Workload rating (1-10)
     * @param string|null $notes Optional notes
     * @return int|bool Pulse entry ID or false on failure
     */
    public function savePulse($userId, $yearWeek, $pulse, $workLoad, $notes = null) {
        // Check if entry already exists
        $existing = $this->getByUserAndWeek($userId, $yearWeek);
        
        $data = [
            'pulse' => $pulse,
            'work_load' => $workLoad,
            'notes' => $notes
        ];
        
        if ($existing) {
            // Update existing
            $success = $this->update($existing['id'], $data);
            return $success ? $existing['id'] : false;
        } else {
            // Insert new
            $data['user_id'] = $userId;
            $data['year_week'] = $yearWeek;
            return $this->insert($data);
        }
    }
    
    /**
     * Get pulse history for a user
     * 
     * @param int $userId User ID
     * @param int $limit Number of weeks to retrieve
     * @return array Array of pulse records
     */
    public function getUserHistory($userId, $limit = 8) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM pulse
            WHERE user_id = ?
            ORDER BY year_week DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get team pulse summary for a specific week
     * 
     * @param string $yearWeek Year-week string
     * @return array Summary with average pulse and workload
     */
    public function getTeamSummary($yearWeek) {
        $stmt = $this->pdo->prepare("
            SELECT 
                AVG(pulse) as avg_pulse,
                AVG(work_load) as avg_workload,
                COUNT(*) as response_count
            FROM pulse
            WHERE year_week = ?
        ");
        $stmt->execute([$yearWeek]);
        return $stmt->fetch();
    }
}
