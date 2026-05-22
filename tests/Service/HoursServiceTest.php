<?php

class HoursServiceTest extends DatabaseTestCase {
    private HoursService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = new HoursService($this->pdo);
    }

    public function testGetCurrentYearWeekFromPulse(): void {
        $week = $this->service->getCurrentYearWeekForUser($this->fixtures['admin_user_id']);
        $this->assertSame('2026-21', $week);
    }

    public function testGetCurrentYearWeekReturnsNullForUserWithoutPulse(): void {
        $this->pdo->exec("INSERT INTO users (email, password_hash, first_name, last_name, role, is_active)
                          VALUES ('no-pulse@example.com', 'x', 'No', 'Pulse', 'User', 1)");
        $userId = (int) $this->pdo->lastInsertId();
        $this->assertNull($this->service->getCurrentYearWeekForUser($userId));
    }

    public function testSubmitTodayHoursInsertsRowAndSkipsEmpties(): void {
        $taskId = (int) $this->fetchOne("SELECT id FROM tasks WHERE name = 'Client-level task'")['id'];

        $result = $this->service->submitTodayHours(
            $this->fixtures['admin_user_id'],
            '2026-21',
            [$taskId => '3.5', 999 => '', 1000 => '0']
        );
        $this->assertTrue($result['success']);

        $row = $this->fetchOne('SELECT hours, year_week FROM hours WHERE task_id = ?', [$taskId]);
        $this->assertSame('3.50', $row['hours']);
        $this->assertSame('2026-21', $row['year_week']);
    }

    public function testSubmitTodayHoursRollsBackOnInvalidValue(): void {
        $taskId = (int) $this->fetchOne("SELECT id FROM tasks WHERE name = 'Client-level task'")['id'];

        $result = $this->service->submitTodayHours(
            $this->fixtures['admin_user_id'],
            '2026-21',
            [$taskId => '-2']
        );
        $this->assertFalse($result['success']);
        $this->assertSame(0, $this->countWhere('hours', 'task_id = ?', [$taskId]));
    }

    public function testUpdateEntryDerivesYearWeekFromDate(): void {
        $taskId = (int) $this->fetchOne("SELECT id FROM tasks WHERE name = 'Client-level task'")['id'];
        $this->pdo->prepare("INSERT INTO hours (user_id, task_id, date_worked, year_week, hours)
                             VALUES (?, ?, '2026-05-20', '2026-21', 2.5)")
                  ->execute([$this->fixtures['admin_user_id'], $taskId]);
        $hoursId = (int) $this->pdo->lastInsertId();

        $result = $this->service->updateEntry($hoursId, [
            'hours'       => '4.25',
            'date_worked' => '2026-06-02',
        ]);
        $this->assertTrue($result['success'], $result['message']);
        $row = $this->fetchOne('SELECT hours, date_worked, year_week FROM hours WHERE id = ?', [$hoursId]);
        $this->assertSame('4.25', $row['hours']);
        $this->assertSame('2026-06-02', $row['date_worked']);
        $this->assertSame('2026-23', $row['year_week'], 'year_week must follow date_worked');
    }

    public function testDeleteEntryAsAdminRemovesRow(): void {
        $taskId = (int) $this->fetchOne("SELECT id FROM tasks WHERE name = 'Client-level task'")['id'];
        $this->pdo->prepare("INSERT INTO hours (user_id, task_id, date_worked, year_week, hours)
                             VALUES (?, ?, '2026-05-20', '2026-21', 1.0)")
                  ->execute([$this->fixtures['admin_user_id'], $taskId]);
        $hoursId = (int) $this->pdo->lastInsertId();

        $result = $this->service->deleteEntryAsAdmin($hoursId);
        $this->assertTrue($result['success']);
        $this->assertSame(0, $this->countWhere('hours', 'id = ?', [$hoursId]));
    }
}
