<?php
/**
 * Client Repository
 * 
 * Handles all database operations for clients table.
 */

require_once __DIR__ . '/BaseRepository.php';

class ClientRepository extends BaseRepository {
    protected $table = 'clients';
    
    /**
     * Get all active clients
     * 
     * @return array Array of active client records
     */
    public function getActive() {
        $stmt = $this->pdo->query("
            SELECT * FROM clients 
            WHERE active = 1 
            ORDER BY name ASC
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * Get client by ID with active status
     * 
     * @param int $id Client ID
     * @param bool $activeOnly Only return if client is active
     * @return array|false Client data or false if not found
     */
    public function getById($id, $activeOnly = false) {
        $sql = "SELECT * FROM clients WHERE id = ?";
        if ($activeOnly) {
            $sql .= " AND active = 1";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Create a new client
     * 
     * @param array $data Client data (name, logo_path, active)
     * @return int|false Client ID or false on failure
     */
    public function create(array $data) {
        return $this->insert([
            'name' => $data['name'],
            'logo_path' => $data['logo_path'] ?? null,
            'active' => $data['active'] ?? 1
        ]);
    }
    
    /**
     * Update client information
     * 
     * @param int $id Client ID
     * @param array $data Client data to update
     * @return bool Success status
     */
    public function updateClient($id, array $data) {
        $updateData = [];
        
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['logo_path'])) {
            $updateData['logo_path'] = $data['logo_path'];
        }
        if (isset($data['active'])) {
            $updateData['active'] = $data['active'];
        }
        
        return $this->update($id, $updateData);
    }
    
    /**
     * Deactivate a client
     * 
     * @param int $id Client ID
     * @return bool Success status
     */
    public function deactivate($id) {
        return $this->update($id, ['active' => 0]);
    }
    
    /**
     * Activate a client
     * 
     * @param int $id Client ID
     * @return bool Success status
     */
    public function activate($id) {
        return $this->update($id, ['active' => 1]);
    }
    
    /**
     * Check if client has projects
     * 
     * @param int $clientId Client ID
     * @return bool True if client has projects
     */
    public function hasProjects($clientId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count FROM projects WHERE client_id = ?
        ");
        $stmt->execute([$clientId]);
        return $stmt->fetch()['count'] > 0;
    }
}
