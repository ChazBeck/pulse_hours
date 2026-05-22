<?php
/**
 * Project Template Service
 *
 * Business logic for managing project templates and their task templates.
 * All public methods return ['success' => bool, 'message' => string, ...].
 */

require_once __DIR__ . '/../Repository/autoload.php';

class ProjectTemplateService {
    private const NAME_MAX_LENGTH = 255;

    private $pdo;
    private $templateRepo;
    private $taskRepo;

    public function __construct($pdo = null) {
        $this->pdo = $pdo ?? get_db_connection();
        $this->templateRepo = new ProjectTemplateRepository($this->pdo);
        $this->taskRepo = new TaskTemplateRepository($this->pdo);
    }

    // ------------------------------------------------------------------
    // Read
    // ------------------------------------------------------------------

    /**
     * Fetch all templates with their tasks pre-grouped by template id.
     *
     * @return array{templates: array, tasksByTemplate: array}
     */
    public function getAllWithTasks() {
        $templates = $this->templateRepo->getAll();
        $tasksByTemplate = $this->taskRepo->getByProjectTemplateIds(
            array_column($templates, 'id')
        );

        return [
            'templates'       => $templates,
            'tasksByTemplate' => $tasksByTemplate,
        ];
    }

    // ------------------------------------------------------------------
    // Project template CRUD
    // ------------------------------------------------------------------

    public function createTemplate(array $data) {
        $name = trim($data['name'] ?? '');

        if ($name === '') {
            return $this->fail('Template name is required.');
        }
        if (strlen($name) > self::NAME_MAX_LENGTH) {
            return $this->fail('Template name is too long (max ' . self::NAME_MAX_LENGTH . ' characters).');
        }

        try {
            $id = $this->templateRepo->create([
                'name'        => $name,
                'description' => trim($data['description'] ?? ''),
                'active'      => !empty($data['active']) ? 1 : 0,
            ]);

            if (!$id) {
                return $this->fail('Failed to create template.');
            }
            return [
                'success'     => true,
                'message'     => 'Project template added successfully!',
                'template_id' => $id,
            ];
        } catch (Throwable $e) {
            return $this->logAndFail('create template', $e, 'Error adding template.');
        }
    }

    public function updateTemplate(array $data) {
        $id = (int) ($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');

        if ($id <= 0) {
            return $this->fail('Invalid template ID.');
        }
        if ($name === '') {
            return $this->fail('Template name is required.');
        }
        if (strlen($name) > self::NAME_MAX_LENGTH) {
            return $this->fail('Template name is too long (max ' . self::NAME_MAX_LENGTH . ' characters).');
        }
        if (!$this->templateRepo->findById($id)) {
            return $this->fail('Template not found.');
        }

        try {
            $this->templateRepo->updateTemplate($id, [
                'name'        => $name,
                'description' => trim($data['description'] ?? ''),
                'active'      => !empty($data['active']) ? 1 : 0,
            ]);
            return $this->ok('Template updated successfully!');
        } catch (Throwable $e) {
            return $this->logAndFail('update template', $e, 'Error updating template.');
        }
    }

    public function deleteTemplate($id) {
        $id = (int) $id;
        if ($id <= 0) {
            return $this->fail('Invalid template ID.');
        }
        if (!$this->templateRepo->findById($id)) {
            return $this->fail('Template not found.');
        }

        try {
            // task_templates has ON DELETE CASCADE so child rows are removed by FK.
            $this->templateRepo->delete($id);
            return $this->ok('Template deleted successfully!');
        } catch (Throwable $e) {
            return $this->logAndFail('delete template', $e, 'Error deleting template.');
        }
    }

    // ------------------------------------------------------------------
    // Task template CRUD
    // ------------------------------------------------------------------

    public function addTask(array $data) {
        $projectTemplateId = (int) ($data['project_template_id'] ?? 0);
        $name = trim($data['name'] ?? '');

        if ($projectTemplateId <= 0) {
            return $this->fail('Invalid project template.');
        }
        if ($name === '') {
            return $this->fail('Task name is required.');
        }
        if (!$this->templateRepo->findById($projectTemplateId)) {
            return $this->fail('Parent template not found.');
        }

        try {
            $id = $this->taskRepo->create([
                'project_template_id' => $projectTemplateId,
                'name'                => $name,
                'description'         => trim($data['description'] ?? ''),
                'sort_order'          => $this->taskRepo->getNextSortOrder($projectTemplateId),
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
            return $this->logAndFail('add task', $e, 'Error adding task.');
        }
    }

    public function updateTask(array $data) {
        $id = (int) ($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');

        if ($id <= 0) {
            return $this->fail('Invalid task ID.');
        }
        if ($name === '') {
            return $this->fail('Task name is required.');
        }
        if (!$this->taskRepo->findById($id)) {
            return $this->fail('Task not found.');
        }

        try {
            $this->taskRepo->updateTask($id, [
                'name'        => $name,
                'description' => trim($data['description'] ?? ''),
            ]);
            return $this->ok('Task updated successfully!');
        } catch (Throwable $e) {
            return $this->logAndFail('update task', $e, 'Error updating task.');
        }
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

    public function reorderTask($id, $direction) {
        $id = (int) $id;
        if ($id <= 0 || !in_array($direction, ['up', 'down'], true)) {
            return $this->fail('Invalid reorder request.');
        }

        try {
            $task = $this->taskRepo->findById($id);
            if (!$task) {
                return $this->fail('Task not found.');
            }

            $adjacent = $this->taskRepo->findAdjacent(
                $task['project_template_id'],
                $task['sort_order'],
                $direction
            );

            if (!$adjacent) {
                return [
                    'success' => true,
                    'message' => 'Task is already at the ' . ($direction === 'up' ? 'top' : 'bottom') . '.',
                    'type'    => 'info',
                ];
            }

            $this->taskRepo->swapSortOrder($task, $adjacent);
            return $this->ok('Task reordered successfully!');
        } catch (Throwable $e) {
            return $this->logAndFail('reorder task', $e, 'Error reordering task.');
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function ok($message) {
        return ['success' => true, 'message' => $message];
    }

    private function fail($message) {
        return ['success' => false, 'message' => $message];
    }

    private function logAndFail($context, Throwable $e, $userMessage) {
        error_log("ProjectTemplateService::{$context} failed: " . $e->getMessage());
        return $this->fail($userMessage);
    }
}
