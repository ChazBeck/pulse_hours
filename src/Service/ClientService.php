<?php
/**
 * Client Service
 *
 * Business logic for client CRUD, including logo upload handling and
 * filesystem cleanup on delete.
 */

require_once __DIR__ . '/../Repository/autoload.php';
require_once __DIR__ . '/../../includes/file_upload.php';

class ClientService {
    private const NAME_MAX_LENGTH = 255;
    private const DEFAULT_COLOR = '#3b82f6';

    private $pdo;
    private $clientRepo;
    private $projectRepo;

    public function __construct($pdo = null) {
        $this->pdo = $pdo ?? get_db_connection();
        $this->clientRepo = new ClientRepository($this->pdo);
        $this->projectRepo = new ProjectRepository($this->pdo);
    }

    // ------------------------------------------------------------------
    // Read
    // ------------------------------------------------------------------

    public function getAll() {
        return $this->clientRepo->findAll('name ASC');
    }

    public function getActive() {
        return $this->clientRepo->getActive();
    }

    public function getClientById($id) {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }
        return $this->clientRepo->findById($id) ?: null;
    }

    // ------------------------------------------------------------------
    // Write
    // ------------------------------------------------------------------

    /**
     * Create a client. If $logoFile is a $_FILES entry it is uploaded
     * and the resulting relative path is stored on the client.
     */
    public function createClient(array $data, array $logoFile = null) {
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            return $this->fail('Client name is required.');
        }
        if (strlen($name) > self::NAME_MAX_LENGTH) {
            return $this->fail('Client name is too long (max ' . self::NAME_MAX_LENGTH . ' characters).');
        }

        $logoPath = null;
        if ($this->hasUploadedFile($logoFile)) {
            $upload = handle_logo_upload($logoFile);
            if (!$upload['success']) {
                return $this->fail('Logo upload error: ' . $upload['error']);
            }
            $logoPath = $upload['relative_path'];
        }

        try {
            $id = $this->clientRepo->create([
                'name'         => $name,
                'client_color' => $this->normalizeColor($data['client_color'] ?? null),
                'client_logo'  => $logoPath,
                'active'       => !empty($data['active']) ? 1 : 0,
            ]);

            if (!$id) {
                if ($logoPath) delete_uploaded_file($logoPath);
                return $this->fail('Failed to create client.');
            }
            return [
                'success'   => true,
                'message'   => 'Client added successfully!',
                'client_id' => $id,
            ];
        } catch (Throwable $e) {
            if ($logoPath) delete_uploaded_file($logoPath);
            return $this->logAndFail('create client', $e, 'Error adding client.');
        }
    }

    public function updateClient($id, array $data, array $logoFile = null) {
        $id = (int) $id;
        if ($id <= 0) {
            return $this->fail('Invalid client ID.');
        }

        $client = $this->clientRepo->findById($id);
        if (!$client) {
            return $this->fail('Client not found.');
        }

        $name = trim($data['name'] ?? '');
        if ($name === '') {
            return $this->fail('Client name is required.');
        }
        if (strlen($name) > self::NAME_MAX_LENGTH) {
            return $this->fail('Client name is too long (max ' . self::NAME_MAX_LENGTH . ' characters).');
        }

        $logoPath = $client['client_logo'];
        $oldLogo = $client['client_logo'];

        if ($this->hasUploadedFile($logoFile)) {
            $upload = handle_logo_upload($logoFile);
            if (!$upload['success']) {
                return $this->fail('Logo upload error: ' . $upload['error']);
            }
            $logoPath = $upload['relative_path'];
        }

        try {
            $this->clientRepo->updateClient($id, [
                'name'         => $name,
                'client_color' => $this->normalizeColor($data['client_color'] ?? null),
                'client_logo'  => $logoPath,
                'active'       => !empty($data['active']) ? 1 : 0,
            ]);

            // Only delete the previous logo after the update commits
            if ($logoPath !== $oldLogo && !empty($oldLogo)) {
                delete_uploaded_file($oldLogo);
            }
            return $this->ok('Client updated successfully!');
        } catch (Throwable $e) {
            return $this->logAndFail('update client', $e, 'Error updating client.');
        }
    }

    public function deleteClient($id) {
        $id = (int) $id;
        if ($id <= 0) {
            return $this->fail('Invalid client ID.');
        }

        $client = $this->clientRepo->findById($id);
        if (!$client) {
            return $this->fail('Client not found.');
        }

        try {
            $this->clientRepo->delete($id);
            if (!empty($client['client_logo'])) {
                delete_uploaded_file($client['client_logo']);
            }
            return $this->ok('Client deleted successfully!');
        } catch (Throwable $e) {
            return $this->logAndFail('delete client', $e, 'Error deleting client.');
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function hasUploadedFile($file) {
        return is_array($file)
            && isset($file['error'])
            && $file['error'] !== UPLOAD_ERR_NO_FILE;
    }

    private function normalizeColor($color) {
        $color = trim((string) $color);
        if ($color === '') {
            return self::DEFAULT_COLOR;
        }
        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : self::DEFAULT_COLOR;
    }

    private function ok($message) {
        return ['success' => true, 'message' => $message];
    }

    private function fail($message) {
        return ['success' => false, 'message' => $message];
    }

    private function logAndFail($context, Throwable $e, $userMessage) {
        error_log("ClientService::{$context} failed: " . $e->getMessage());
        return $this->fail($userMessage);
    }
}
