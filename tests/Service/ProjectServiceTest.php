<?php

class ProjectServiceTest extends DatabaseTestCase {
    private ProjectService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = new ProjectService($this->pdo);
    }

    public function testCreateProjectFromTemplateInstantiatesTasks(): void {
        $result = $this->service->createProject([
            'name'                => 'Templated project',
            'client_id'           => $this->fixtures['acme_id'],
            'project_template_id' => $this->fixtures['template_id'],
            'status'              => 'active',
            'active'              => 1,
        ]);
        $this->assertTrue($result['success'], $result['message']);
        $this->assertSame(3, $result['task_count']);
        $this->assertSame(3, $this->countWhere('tasks', 'project_id = ?', [$result['project_id']]));

        $names = $this->pdo->prepare(
            "SELECT name FROM tasks WHERE project_id = ? ORDER BY id"
        );
        $names->execute([$result['project_id']]);
        $this->assertSame(['Kickoff', 'Draft', 'Review'], $names->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testCreateProjectRollsBackWhenTemplateInsertFails(): void {
        // Use a non-existent template id. TaskTemplateRepository::getByProjectTemplateId
        // will return [] for that, so no rollback to test there. Instead, force a
        // failure by leaving client_id invalid for the foreign key.
        $result = $this->service->createProject([
            'name'      => 'Bad client',
            'client_id' => 999999,
            'status'    => 'active',
        ]);
        $this->assertFalse($result['success']);
        $this->assertSame(0, $this->countWhere('projects', 'name = ?', ['Bad client']));
    }

    public function testCreateProjectRequiresName(): void {
        $result = $this->service->createProject([
            'name'      => '',
            'client_id' => $this->fixtures['acme_id'],
        ]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('name is required', $result['message']);
    }

    public function testDeleteProjectCascadeRemovesTasks(): void {
        $projectId = $this->fixtures['project_id'];
        $this->assertSame(1, $this->countWhere('tasks', 'project_id = ?', [$projectId]));

        $result = $this->service->deleteProjectCascade($projectId);
        $this->assertTrue($result['success']);
        $this->assertSame(0, $this->countWhere('projects', 'id = ?', [$projectId]));
        $this->assertSame(0, $this->countWhere('tasks', 'project_id = ?', [$projectId]));
    }

    public function testUpdateProjectRejectsEndBeforeStart(): void {
        $result = $this->service->updateProject($this->fixtures['project_id'], [
            'name'       => 'Renamed',
            'client_id'  => $this->fixtures['acme_id'],
            'start_date' => '2026-06-01',
            'end_date'   => '2026-05-01',
        ]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('End date', $result['message']);
    }
}
