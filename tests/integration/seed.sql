INSERT INTO users (id, email, password_hash, first_name, last_name, role, is_active) VALUES
  (101, 'charlie@veerless.com', 'x', 'Charlie', 'Beck', 'Admin', 1),
  (102, 'gone@veerless.com', 'x', 'Gone', 'User', 'User', 0);
INSERT INTO clients (id, name, active) VALUES (501, 'Northwind', 1), (503, 'Dormant', 0);
UPDATE clients SET is_internal = 0 WHERE id IN (501, 503);
INSERT INTO projects (id, client_id, name, active) VALUES (601, 501, 'Retainer', 1), (602, 501, 'Old project', 0);
-- task 12's own client_id disagrees with its project's (nothing keeps them in step)
INSERT INTO tasks (id, client_id, project_id, name, status, sort_order) VALUES
  (10, 501, 601, 'Weekly sync', 'in-progress', 0),
  (11, 501, NULL, 'Reporting', 'not-started', 0),
  (12, 503, 601, 'Mismatched', 'in-progress', 1),
  (13, 501, 601, 'Done', 'completed', 0),
  (14, 501, 602, 'Under inactive project', 'in-progress', 0),
  (15, 503, NULL, 'Dormant client task', 'in-progress', 0);
-- the internal Veerless client (created by migrate_add_is_internal.php) needs a task to be offered
INSERT INTO tasks (id, client_id, project_id, name, status) SELECT 20, id, NULL, 'Business development', 'in-progress' FROM clients WHERE name = 'Veerless' AND is_internal = 1;
