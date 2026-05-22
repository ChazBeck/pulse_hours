<?php
/**
 * PHPUnit bootstrap.
 *
 * Defines DB constants from environment, loads the schema and pending
 * migrations into a fresh test database, then surfaces a connection
 * helper that tests use for state assertions.
 *
 * Environment variables (set by phpunit.xml or CI):
 *   DB_HOST  default 127.0.0.1
 *   DB_NAME  default plusehours_test
 *   DB_USER  default root
 *   DB_PASS  default ''
 */

// Constants must be defined before any of the app's config files load.
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'plusehours_test');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');
define('APP_ENV', 'development');
define('BASE_URL', '/');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../config/constants.php';

/**
 * Returns the PDO connection used by the application.
 */
function test_pdo() {
    return get_db_connection();
}

/**
 * Naive SQL splitter. Strips `-- ...` line comments, then splits on `;`
 * at statement boundaries. Good enough for this project's schema
 * (no stored procedures, no string literals containing `;`).
 *
 * @return string[] Non-empty trimmed statements.
 */
function test_split_sql(string $sql): array {
    $lines = explode("\n", $sql);
    $stripped = [];
    foreach ($lines as $line) {
        $trimmed = ltrim($line);
        if (strpos($trimmed, '--') === 0) continue;
        $stripped[] = $line;
    }
    $clean = implode("\n", $stripped);

    $parts = explode(';', $clean);
    $statements = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') continue;
        // setup_database.sql begins with `CREATE DATABASE plusehours; USE plusehours;`.
        // In tests the target database is provided by phpunit.xml env vars, so
        // these statements would silently switch us off the test database.
        $upper = strtoupper(substr($part, 0, 20));
        if (strpos($upper, 'CREATE DATABASE') === 0) continue;
        if (strpos($upper, 'USE ') === 0) continue;
        $statements[] = $part;
    }
    return $statements;
}

/**
 * Drop and re-create every application table from setup_database.sql,
 * then apply the PHP migrations the production DB already has.
 *
 * setup_database.sql has a definition-order issue (projects references
 * project_templates before it is declared) so foreign-key checks are
 * temporarily disabled while loading. This is the same workaround the
 * docker/local stack uses on first boot.
 */
function test_reset_schema() {
    $pdo = get_db_connection();

    // Wipe any prior state. Disable FK checks so the order of drops
    // does not matter.
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    // Load schema. FK checks off again because of the projects ->
    // project_templates declaration order. PDO::exec with multi-statement
    // SQL is unreliable across drivers, so split on `;` boundaries that
    // sit outside comments and execute each statement individually.
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (test_split_sql(file_get_contents(__DIR__ . '/../database/setup_database.sql')) as $stmt) {
        $pdo->exec($stmt);
    }
    foreach (test_split_sql(file_get_contents(__DIR__ . '/../database/add_login_attempts_table.sql')) as $stmt) {
        $pdo->exec($stmt);
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    // Schema-only migrations. We run their ALTERs directly so tests do
    // not depend on each script's CLI output.
    try {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER status");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') === false) throw $e;
    }
    try {
        $pdo->exec("ALTER TABLE clients ADD COLUMN is_internal TINYINT(1) NOT NULL DEFAULT 0 AFTER active");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') === false) throw $e;
    }
}

/**
 * Insert a minimal fixture set: one admin user, two clients, one
 * project template with three task templates, one project with two
 * tasks, and one pulse entry for the user. Returns the inserted ids
 * so tests can refer to them by name.
 */
function test_seed_fixtures() {
    $pdo = get_db_connection();

    // Wipe any rows the schema file may have seeded (e.g. the default
    // admin@plusehours.com user) so fixtures are deterministic.
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['hours', 'pulse', 'tasks', 'task_templates', 'projects', 'project_templates', 'users', 'clients', 'sessions', 'login_attempts'] as $t) {
        $pdo->exec("TRUNCATE TABLE `$t`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $pdo->exec("INSERT INTO users (email, password_hash, first_name, last_name, role, is_active)
                VALUES ('admin@plusehours.com', '\$2y\$10\$dummyhash', 'Admin', 'User', 'Admin', 1)");
    $userId = (int) $pdo->lastInsertId();

    $pdo->exec("INSERT INTO clients (name, client_color, active) VALUES
                ('Acme Corp', '#3b82f6', 1),
                ('Globex', '#10b981', 1)");
    $acmeId = (int) $pdo->query("SELECT id FROM clients WHERE name = 'Acme Corp'")->fetchColumn();
    $globexId = (int) $pdo->query("SELECT id FROM clients WHERE name = 'Globex'")->fetchColumn();

    $pdo->exec("INSERT INTO project_templates (name, description, active) VALUES
                ('Standard Report', 'Reusable template', 1)");
    $templateId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO task_templates (project_template_id, name, sort_order) VALUES
                ($templateId, 'Kickoff',  0),
                ($templateId, 'Draft',    1),
                ($templateId, 'Review',   2)");

    $pdo->exec("INSERT INTO projects (client_id, name, status, active) VALUES
                ($acmeId, '2025 Q1 Report', 'active', 1)");
    $projectId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO tasks (client_id, project_id, name, status) VALUES
                ($acmeId, $projectId, 'Write outline', 'in-progress'),
                ($acmeId, NULL, 'Client-level task', 'not-started')");

    $pdo->exec("INSERT INTO pulse (user_id, year_week, pulse, work_load) VALUES
                ($userId, '2026-21', 4, 6)");

    return [
        'admin_user_id' => $userId,
        'acme_id'       => $acmeId,
        'globex_id'     => $globexId,
        'template_id'   => $templateId,
        'project_id'    => $projectId,
    ];
}

// Build the schema once at suite start so tests that don't need data
// reset can still rely on the tables existing.
test_reset_schema();
