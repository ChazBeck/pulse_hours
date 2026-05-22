<?php
/**
 * Project Template Repository
 *
 * Database operations for the project_templates table.
 */

require_once __DIR__ . '/BaseRepository.php';

class ProjectTemplateRepository extends BaseRepository {
    protected $table = 'project_templates';

    /**
     * Get all project templates, optionally filtered to active only.
     */
    public function getAll($activeOnly = false) {
        $sql = "SELECT * FROM {$this->table}";
        if ($activeOnly) {
            $sql .= " WHERE active = 1";
        }
        $sql .= " ORDER BY name ASC";

        return $this->pdo->query($sql)->fetchAll();
    }

    /**
     * Create a project template.
     */
    public function create(array $data) {
        return $this->insert([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'active'      => $data['active'] ?? 1,
        ]);
    }

    /**
     * Update a project template. Only whitelisted fields are persisted.
     */
    public function updateTemplate($id, array $data) {
        $updateData = [];
        foreach (['name', 'description', 'active'] as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }
        if (empty($updateData)) {
            return false;
        }
        return $this->update($id, $updateData);
    }
}
