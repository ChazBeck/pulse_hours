<?php
/**
 * Base class for tests that need a clean database for each test.
 *
 * setUp drops and recreates every table, applies migrations, and
 * loads the fixture seed. Tests get the seed ids via $this->fixtures.
 */

use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase {
    /** @var PDO */
    protected $pdo;

    /** @var array<string, int> */
    protected $fixtures;

    protected function setUp(): void {
        parent::setUp();
        test_reset_schema();
        $this->pdo = test_pdo();
        $this->fixtures = test_seed_fixtures();
    }

    protected function rowExists(string $sql, array $params = []): bool {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    protected function fetchOne(string $sql, array $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    protected function countWhere(string $table, string $where, array $params = []): int {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}
