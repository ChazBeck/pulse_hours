<?php
/**
 * Task Template Repository
 *
 * Database operations for the task_templates table.
 */

require_once __DIR__ . '/BaseRepository.php';

class TaskTemplateRepository extends BaseRepository {
    protected $table = 'task_templates';

    /**
     * Get tasks for a single project template, ordered by sort_order.
     */
    public function getByProjectTemplateId($projectTemplateId) {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table}
             WHERE project_template_id = ?
             ORDER BY sort_order ASC"
        );
        $stmt->execute([$projectTemplateId]);
        return $stmt->fetchAll();
    }

    /**
     * Get tasks for many project templates in a single query,
     * grouped by project_template_id.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function getByProjectTemplateIds(array $projectTemplateIds) {
        if (empty($projectTemplateIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($projectTemplateIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table}
             WHERE project_template_id IN ($placeholders)
             ORDER BY project_template_id, sort_order ASC"
        );
        $stmt->execute(array_values($projectTemplateIds));

        $grouped = [];
        foreach ($stmt->fetchAll() as $task) {
            $grouped[$task['project_template_id']][] = $task;
        }
        return $grouped;
    }

    /**
     * Compute the next sort_order for a new task within a project template.
     */
    public function getNextSortOrder($projectTemplateId) {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(MAX(sort_order), -1) + 1
             FROM {$this->table}
             WHERE project_template_id = ?"
        );
        $stmt->execute([$projectTemplateId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Create a task template. Caller is expected to supply sort_order
     * (typically via getNextSortOrder).
     */
    public function create(array $data) {
        return $this->insert([
            'project_template_id' => $data['project_template_id'],
            'name'                => $data['name'],
            'description'         => $data['description'] ?? null,
            'sort_order'          => $data['sort_order'] ?? 0,
        ]);
    }

    /**
     * Update a task template. Only whitelisted fields are persisted.
     */
    public function updateTask($id, array $data) {
        $updateData = [];
        foreach (['name', 'description'] as $field) {
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
     * Find the immediately adjacent task in the same template,
     * either above (direction='up') or below (direction='down').
     */
    public function findAdjacent($projectTemplateId, $sortOrder, $direction) {
        $comparator = $direction === 'up' ? '<' : '>';
        $order = $direction === 'up' ? 'DESC' : 'ASC';

        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table}
             WHERE project_template_id = ?
               AND sort_order $comparator ?
             ORDER BY sort_order $order
             LIMIT 1"
        );
        $stmt->execute([$projectTemplateId, $sortOrder]);
        return $stmt->fetch();
    }

    /**
     * Atomically swap sort_order values for two tasks.
     */
    public function swapSortOrder(array $taskA, array $taskB) {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE {$this->table} SET sort_order = ? WHERE id = ?"
            );
            $stmt->execute([$taskB['sort_order'], $taskA['id']]);
            $stmt->execute([$taskA['sort_order'], $taskB['id']]);
            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
