<?php
/**
 * Client Service
 * 
 * Handles business logic for client management.
 */

require_once __DIR__ . '/../Repository/autoload.php';

class ClientService {
    private $clientRepo;
    private $projectRepo;
    private $pdo;
    
    public function __construct($pdo = null) {
        $this->pdo = $pdo ?? get_db_connection();
        $this->clientRepo = new ClientRepository($this->pdo);
        $this->projectRepo = new ProjectRepository($this->pdo);
    }
    
    /**
     * Create a new client with validation
     * 
     * @param array $data Client data (name, logo_path, active)
     * @return array Result with 'success', 'message', and optionally 'client_id'
     */
    public function createClient(array $data) {
        try {
            // Validate required fields
            if (empty($data['name'])) {
                return [
                    'success' => false,
                    'message' => 'Client name is required'
                ];
            }
            
            // Validate name length
            if (strlen($data['name']) > 255) {
                return [
                    'success' => false,
                    'message' => 'Client name is too long (max 255 characters)'
                ];
            }
            
            $clientId = $this->clientRepo->create($data);
            
            if ($clientId) {
                return [
                    'success' => true,
                    'message' => 'Client created successfully',
                    'client_id' => $clientId
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to create client'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Create client error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while creating the client'
            ];
        }
    }
    
    /**
     * Update client with validation
     * 
     * @param int $clientId Client ID
     * @param array $data Client data to update
     * @return array Result with 'success' and 'message'
     */
    public function updateClient($clientId, array $data) {
        try {
            // Check if client exists
            $client = $this->clientRepo->findById($clientId);
            if (!$client) {
                return [
                    'success' => false,
                    'message' => 'Client not found'
                ];
            }
            
            // Validate name if provided
            if (isset($data['name'])) {
                if (empty($data['name'])) {
                    return [
                        'success' => false,
                        'message' => 'Client name cannot be empty'
                    ];
                }
                
                if (strlen($data['name']) > 255) {
                    return [
                        'success' => false,
                        'message' => 'Client name is too long (max 255 characters)'
                    ];
                }
            }
            
            $success = $this->clientRepo->updateClient($clientId, $data);
            
            if ($success) {
                return [
                    'success' => true,
                    'message' => 'Client updated successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to update client'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Update client error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while updating the client'
            ];
        }
    }
    
    /**
     * Delete or deactivate client
     * 
     * @param int $clientId Client ID
     * @param bool $forceDelete Force delete even if client has projects
     * @return array Result with 'success' and 'message'
     */
    public function deleteClient($clientId, $forceDelete = false) {
        try {
            // Check if client exists
            $client = $this->clientRepo->findById($clientId);
            if (!$client) {
                return [
                    'success' => false,
                    'message' => 'Client not found'
                ];
            }
            
            // Check if client has projects
            if ($this->clientRepo->hasProjects($clientId)) {
                if (!$forceDelete) {
                    // Deactivate instead of delete
                    $success = $this->clientRepo->deactivate($clientId);
                    
                    if ($success) {
                        return [
                            'success' => true,
                            'message' => 'Client has projects and was deactivated instead of deleted'
                        ];
                    } else {
                        return [
                            'success' => false,
                            'message' => 'Failed to deactivate client'
                        ];
                    }
                } else {
                    return [
                        'success' => false,
                        'message' => 'Cannot delete client with existing projects. Please delete projects first.'
                    ];
                }
            }
            
            // Delete client
            $success = $this->clientRepo->delete($clientId);
            
            if ($success) {
                return [
                    'success' => true,
                    'message' => 'Client deleted successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to delete client'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Delete client error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while deleting the client'
            ];
        }
    }
    
    /**
     * Get client with projects and summary
     * 
     * @param int $clientId Client ID
     * @return array|null Client data with projects and summary
     */
    public function getClientWithDetails($clientId) {
        $client = $this->clientRepo->findById($clientId);
        
        if (!$client) {
            return null;
        }
        
        // Get projects
        $client['projects'] = $this->projectRepo->getByClientId($clientId);
        
        // Get project and task counts
        $client['project_count'] = count($client['projects']);
        $client['active_project_count'] = count(array_filter(
            $client['projects'], 
            function($p) { return $p['status'] === 'active'; }
        ));
        
        return $client;
    }
    
    /**
     * Upload and save client logo
     * 
     * @param int $clientId Client ID
     * @param array $file $_FILES array element
     * @return array Result with 'success', 'message', and optionally 'logo_path'
     */
    public function uploadLogo($clientId, array $file) {
        try {
            // Check if client exists
            $client = $this->clientRepo->findById($clientId);
            if (!$client) {
                return [
                    'success' => false,
                    'message' => 'Client not found'
                ];
            }
            
            // Use file upload helper
            require_once __DIR__ . '/../../includes/file_upload.php';
            
            $uploadResult = handle_logo_upload($file);
            
            if (!$uploadResult['success']) {
                return $uploadResult;
            }
            
            // Update client with new logo path
            $success = $this->clientRepo->updateClient($clientId, [
                'logo_path' => $uploadResult['file_path']
            ]);
            
            if ($success) {
                // Delete old logo if exists
                if (!empty($client['logo_path']) && file_exists($client['logo_path'])) {
                    @unlink($client['logo_path']);
                }
                
                return [
                    'success' => true,
                    'message' => 'Logo uploaded successfully',
                    'logo_path' => $uploadResult['file_path']
                ];
            } else {
                // Clean up uploaded file if database update failed
                if (file_exists($uploadResult['file_path'])) {
                    @unlink($uploadResult['file_path']);
                }
                
                return [
                    'success' => false,
                    'message' => 'Failed to update client with new logo'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Upload logo error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while uploading the logo'
            ];
        }
    }
}
