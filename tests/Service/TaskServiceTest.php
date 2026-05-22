<?php

class TaskServiceTest extends DatabaseTestCase {
    private TaskService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = new TaskService($this->pdo);
    }

    public function testCreateTaskRequiresClient(): void {
        $result = $this->service->createTask(['name' => 'X']);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('valid client', $result['message']);
    }

    public function testCreateTaskNormalizesUnknownStatus(): void {
        $result = $this->service->createTask([
            'name'      => 'Smoke',
            'client_id' => $this->fixtures['acme_id'],
            'status'    => 'BOGUS',
        ]);
        $this->assertTrue($result['success'], $result['message']);
        $row = $this->fetchOne('SELECT status FROM tasks WHERE id = ?', [$result['task_id']]);
        $this->assertSame('not-started', $row['status']);
    }

    public function testUpdateStatusOnly(): void {
        $task = $this->fetchOne("SELECT id FROM tasks WHERE name = 'Write outline'");
        $result = $this->service->updateStatus($task['id'], 'completed');
        $this->assertTrue($result['success']);
        $row = $this->fetchOne('SELECT status, name FROM tasks WHERE id = ?', [$task['id']]);
        $this->assertSame('completed', $row['status']);
        $this->assertSame('Write outline', $row['name'], 'name must be untouched by status-only update');
    }

    public function testCreateForProjectResolvesClientIdAutomatically(): void {
        $projectId = $this->fixtures['project_id'];
        $result = $this->service->createForProject($projectId, ['name' => 'Manual task']);
        $this->assertTrue($result['success'], $result['message']);

        $row = $this->fetchOne('SELECT client_id, status FROM tasks WHERE id = ?', [$result['task_id']]);
        $this->assertSame($this->fixtures['acme_id'], (int) $row['client_id'],
            'client_id must be inherited from the parent project, not omitted');
        $this->assertSame('not-started', $row['status']);
    }

    public function testGetFilteredWithRelationsRespectsStatusFilter(): void {
        $rows = $this->service->getFilteredWithRelations(['status' => 'in-progress']);
        $this->assertCount(1, $rows);
        $this->assertSame('Write outline', $rows[0]['task_name']);
    }
}
