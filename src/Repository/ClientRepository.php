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
     * @param array $data Client data (name, client_color, client_logo, active)
     * @return int|false Client ID or false on failure
     */
    public function create(array $data) {
        return $this->insert([
            'name'         => $data['name'],
            'client_color' => $data['client_color'] ?? null,
            'client_logo'  => $data['client_logo'] ?? null,
            'active'       => array_key_exists('active', $data) ? (int) $data['active'] : 1,
        ]);
    }

    /**
     * Update client information. Only whitelisted fields are persisted.
     */
    public function updateClient($id, array $data) {
        $updateData = [];

        foreach (['name', 'client_color', 'client_logo', 'active'] as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        if (empty($updateData)) {
            return false;
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
