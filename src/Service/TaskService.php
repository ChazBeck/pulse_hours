<?php
/**
 * Task Service
 *
 * Business logic for task CRUD operations and the supporting data
 * needed by the admin task pages.
 */

require_once __DIR__ . '/../Repository/autoload.php';
require_once __DIR__ . '/../../config/constants.php';

class TaskService {
    private const NAME_MAX_LENGTH = 255;

    private $pdo;
    private $taskRepo;
    private $clientRepo;
    private $projectRepo;

    public function __construct($pdo = null) {
        $this->pdo = $pdo ?? get_db_connection();
        $this->taskRepo = new TaskRepository($this->pdo);
        $this->clientRepo = new ClientRepository($this->pdo);
        $this->projectRepo = new ProjectRepository($this->pdo);
    }

    // ------------------------------------------------------------------
    // Read
    // ------------------------------------------------------------------

    public function getAllWithRelations() {
        return $this->taskRepo->getAllWithRelations();
    }

    public function getFilteredWithRelations(array $filters = []) {
        return $this->taskRepo->getFilteredWithRelations($filters);
    }

    public function getTaskById($id) {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }
        return $this->taskRepo->findById($id) ?: null;
    }

    /**
     * Fetch the supporting data both task pages need to render dropdowns
     * and filter forms: active clients and the projects users can pick
     * from. Returned in a single call so callers don't reach into the
     * repository layer.
     */
    public function getSelectOptions() {
        return [
            'clients'  => $this->clientRepo->getActive(),
            'projects' => $this->projectRepo->getEnabledForSelect(),
        ];
    }

    // ------------------------------------------------------------------
    // Write
    // ------------------------------------------------------------------

    public function createTask(array $data) {
        $errors = $this->validate($data, ['name', 'client_id']);
        if ($errors) {
            return $this->fail($errors);
        }

        try {
            $id = $this->taskRepo->create([
                'name'        => trim($data['name']),
                'client_id'   => (int) $data['client_id'],
                'project_id'  => $this->nullableInt($data['project_id'] ?? null),
                'description' => trim($data['description'] ?? ''),
                'status'      => $this->normalizeStatus($data['status'] ?? null),
            ]);

            if (!$id) {
                return $this->fail('Failed to add task.');
            }
            return [
                'success' => true,
                'message' => 'Task added successfully!',
                'task_id' => $id,
            ];
        } catch (Throwable $e) {
            return $this->logAndFail('create task', $e, 'Error adding task.');
        }
    }

    public function updateTask(array $data) {
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid task ID.');
        }
        if (!$this->taskRepo->findById($id)) {
            return $this->fail('Task not found.');
        }

        $errors = $this->validate($data, ['name', 'client_id']);
        if ($errors) {
            return $this->fail($errors);
        }

        try {
            $this->taskRepo->updateTask($id, [
                'name'        => trim($data['name']),
                'client_id'   => (int) $data['client_id'],
                'project_id'  => $this->nullableInt($data['project_id'] ?? null),
                'description' => trim($data['description'] ?? ''),
                'status'      => $this->normalizeStatus($data['status'] ?? null),
            ]);
            return $this->ok('Task updated successfully!');
        } catch (Throwable $e) {
            return $this->logAndFail('update task', $e, 'Error updating task.');
        }
    }

    /**
     * Update only a task's status. Used by the inline status selector on
     * the projects admin page.
     */
    public function updateStatus($taskId, $status) {
        $taskId = (int) $taskId;
        if ($taskId <= 0) {
            return $this->fail('Invalid task ID.');
        }
        if (!$this->taskRepo->findById($taskId)) {
            return $this->fail('Task not found.');
        }

        try {
            $this->taskRepo->updateTask($taskId, [
                'status' => $this->normalizeStatus($status),
            ]);
            return $this->ok('Task status updated successfully!');
        } catch (Throwable $e) {
            return $this->logAndFail('update task status', $e, 'Error updating task.');
        }
    }

    /**
     * Create a task scoped to a project. The project's client_id is
     * looked up automatically so callers don't need to supply it.
     */
    public function createForProject($projectId, array $data) {
        $projectId = (int) $projectId;
        if ($projectId <= 0) {
            return $this->fail('Invalid project ID.');
        }

        $project = $this->projectRepo->findById($projectId);
        if (!$project) {
            return $this->fail('Project not found.');
        }

        return $this->createTask([
            'name'        => $data['name'] ?? '',
            'description' => $data['description'] ?? '',
            'project_id'  => $projectId,
            'client_id'   => $project['client_id'],
            'status'      => TaskStatus::NOT_STARTED,
        ]);
    }

    public function deleteTask($id) {
        $id = (int) $id;
        if ($id <= 0) {
            return $this->fail('Invalid task ID.');
        }
        if (!$this->taskRepo->findById($id)) {
            return $this->fail('Task not found.');
        }

        try {
            $this->taskRepo->delete($id);
            return $this->ok('Task deleted successfully!');
        } catch (Throwable $e) {
            return $this->logAndFail('delete task', $e, 'Error deleting task.');
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function validate(array $data, array $required) {
        $name = trim($data['name'] ?? '');
        $clientId = (int) ($data['client_id'] ?? 0);

        if (in_array('name', $required, true) && $name === '') {
            return 'Task name is required.';
        }
        if (strlen($name) > self::NAME_MAX_LENGTH) {
            return 'Task name is too long (max ' . self::NAME_MAX_LENGTH . ' characters).';
        }
        if (in_array('client_id', $required, true) && $clientId <= 0) {
            return 'Please select a valid client.';
        }
        return null;
    }

    private function nullableInt($value) {
        if ($value === null || $value === '' || $value === '0') {
            return null;
        }
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }

    private function normalizeStatus($status) {
        $allowed = TaskStatus::all();
        if (in_array($status, $allowed, true)) {
            return $status;
        }
        return TaskStatus::NOT_STARTED;
    }

    private function ok($message) {
        return ['success' => true, 'message' => $message];
    }

    private function fail($message) {
        return ['success' => false, 'message' => $message];
    }

    private function logAndFail($context, Throwable $e, $userMessage) {
        error_log("TaskService::{$context} failed: " . $e->getMessage());
        return $this->fail($userMessage);
    }
}
