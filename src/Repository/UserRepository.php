<?php
/**
 * User Repository
 * 
 * Handles all database operations for users table.
 */

require_once __DIR__ . '/BaseRepository.php';

class UserRepository extends BaseRepository {
    protected $table = 'users';
    
    /**
     * Find user by email
     * 
     * @param string $email User email
     * @return array|false User data or false if not found
     */
    public function findByEmail($email) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM users WHERE email = ?
        ");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }
    
    /**
     * Get all active users
     * 
     * @return array Array of active user records
     */
    public function getActive() {
        $stmt = $this->pdo->query("
            SELECT id, email, first_name, last_name, role, is_active, created_at, last_login
            FROM users 
            WHERE is_active = 1 
            ORDER BY last_name ASC, first_name ASC
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * Get all users (including inactive)
     * 
     * @return array Array of all user records
     */
    public function getAll() {
        $stmt = $this->pdo->query("
            SELECT id, email, first_name, last_name, role, is_active, created_at, last_login
            FROM users 
            ORDER BY last_name ASC, first_name ASC
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * Get user by ID (without password hash)
     * 
     * @param int $id User ID
     * @return array|false User data or false if not found
     */
    public function getById($id) {
        $stmt = $this->pdo->prepare("
            SELECT id, email, first_name, last_name, role, is_active, created_at, last_login
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Create a new user
     * 
     * @param array $data User data (email, password, first_name, last_name, role)
     * @return int|false User ID or false on failure
     */
    public function create(array $data) {
        return $this->insert([
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'role' => $data['role'] ?? 'User',
            'is_active' => $data['is_active'] ?? 1
        ]);
    }
    
    /**
     * Update user information
     * 
     * @param int $id User ID
     * @param array $data User data to update
     * @return bool Success status
     */
    public function updateUser($id, array $data) {
        $updateData = [];
        
        if (isset($data['email'])) {
            $updateData['email'] = $data['email'];
        }
        if (isset($data['first_name'])) {
            $updateData['first_name'] = $data['first_name'];
        }
        if (isset($data['last_name'])) {
            $updateData['last_name'] = $data['last_name'];
        }
        if (isset($data['role'])) {
            $updateData['role'] = $data['role'];
        }
        if (isset($data['is_active'])) {
            $updateData['is_active'] = $data['is_active'];
        }
        
        return $this->update($id, $updateData);
    }
    
    /**
     * Update user password
     * 
     * @param int $id User ID
     * @param string $newPassword New password (plain text, will be hashed)
     * @return bool Success status
     */
    public function updatePassword($id, $newPassword) {
        return $this->update($id, [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)
        ]);
    }
    
    /**
     * Deactivate a user
     * 
     * @param int $id User ID
     * @return bool Success status
     */
    public function deactivate($id) {
        return $this->update($id, ['is_active' => 0]);
    }
    
    /**
     * Activate a user
     * 
     * @param int $id User ID
     * @return bool Success status
     */
    public function activate($id) {
        return $this->update($id, ['is_active' => 1]);
    }
    
    /**
     * Check if email already exists
     * 
     * @param string $email Email to check
     * @param int|null $excludeUserId User ID to exclude from check (for updates)
     * @return bool True if email exists
     */
    public function emailExists($email, $excludeUserId = null) {
        $sql = "SELECT COUNT(*) as count FROM users WHERE email = ?";
        $params = [$email];
        
        if ($excludeUserId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeUserId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch()['count'] > 0;
    }
    
    /**
     * Get users assigned to a task
     * 
     * @param int $taskId Task ID
     * @return array Array of user records
     */
    public function getByTaskId($taskId) {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.email, u.first_name, u.last_name, u.role
            FROM users u
            INNER JOIN user_tasks ut ON u.id = ut.user_id
            WHERE ut.task_id = ? AND u.is_active = 1
            ORDER BY u.last_name ASC, u.first_name ASC
        ");
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();
    }
}
