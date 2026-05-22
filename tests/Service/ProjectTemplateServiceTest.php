<?php

class ProjectTemplateServiceTest extends DatabaseTestCase {
    private ProjectTemplateService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = new ProjectTemplateService($this->pdo);
    }

    public function testCreateTemplateValidatesName(): void {
        $result = $this->service->createTemplate(['name' => '']);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('name is required', $result['message']);
    }

    public function testAddTaskAutoIncrementsSortOrder(): void {
        $tid = $this->fixtures['template_id'];

        $this->service->addTask(['project_template_id' => $tid, 'name' => 'New A']);
        $this->service->addTask(['project_template_id' => $tid, 'name' => 'New B']);

        $row = $this->fetchOne(
            "SELECT MAX(sort_order) AS max_order, COUNT(*) AS total
             FROM task_templates WHERE project_template_id = ?",
            [$tid]
        );
        $this->assertSame(5, (int) $row['total'], 'seed has 3 tasks + 2 added = 5');
        $this->assertSame(4, (int) $row['max_order'], 'next sort_order after seed (max 2) should be 3 then 4');
    }

    public function testReorderTaskSwapsSortOrder(): void {
        $tid = $this->fixtures['template_id'];
        $kickoff = $this->fetchOne(
            "SELECT id FROM task_templates WHERE project_template_id = ? AND name = 'Kickoff'",
            [$tid]
        );

        $result = $this->service->reorderTask($kickoff['id'], 'down');
        $this->assertTrue($result['success'], $result['message']);

        $rows = $this->pdo->query("
            SELECT name, sort_order FROM task_templates
            WHERE project_template_id = $tid ORDER BY sort_order
        ")->fetchAll();
        $this->assertSame('Draft',   $rows[0]['name']);
        $this->assertSame('Kickoff', $rows[1]['name']);
        $this->assertSame('Review',  $rows[2]['name']);
    }

    public function testReorderTaskAtBoundaryReturnsInfo(): void {
        $tid = $this->fixtures['template_id'];
        $kickoff = $this->fetchOne(
            "SELECT id FROM task_templates WHERE project_template_id = ? AND name = 'Kickoff'",
            [$tid]
        );

        $result = $this->service->reorderTask($kickoff['id'], 'up');
        $this->assertTrue($result['success']);
        $this->assertSame('info', $result['type'] ?? null);
        $this->assertStringContainsString('top', $result['message']);
    }

    public function testDeleteTemplateCascadesToTasks(): void {
        $tid = $this->fixtures['template_id'];
        $this->assertSame(3, $this->countWhere('task_templates', 'project_template_id = ?', [$tid]));

        $result = $this->service->deleteTemplate($tid);
        $this->assertTrue($result['success']);
        $this->assertSame(0, $this->countWhere('project_templates', 'id = ?', [$tid]));
        $this->assertSame(0, $this->countWhere('task_templates', 'project_template_id = ?', [$tid]));
    }
}
