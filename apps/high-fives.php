<?php
/**
 * High Fives Page
 *
 * The step between the Pulse check-in and the hours page: give one or more
 * teammates a High Five for something great they did this week, or skip
 * straight on to hours. Rules live in includes/high_fives.php.
 */

require __DIR__ . '/../sso/sso_include.php';
/* SSO enforced in sso_include.php */

require_once __DIR__ . '/../includes/date_helpers.php';
require_once __DIR__ . '/../includes/high_fives.php';
require_once __DIR__ . '/../includes/high_fives_zoom.php';

// $user provided by sso_include.php
$pdo = get_db_connection();

// Until database/migrate_add_high_fives.php has run, behave as before: the
// check-in leads straight to the hours page.
if (!high_fives_enabled($pdo)) {
    header('Location: ' . url('/apps/hours.php'));
    exit();
}

// The week of the user's latest check-in, as on the hours page
$stmt = $pdo->prepare("SELECT year_week FROM pulse WHERE user_id = ? ORDER BY date_created DESC LIMIT 1");
$stmt->execute([$user['id']]);
$pulse_entry = $stmt->fetch();

if (!$pulse_entry) {
    header('Location: ' . url('/apps/pulse.php'));
    exit();
}

$target_year_week = $pulse_entry['year_week'];
$week_label = format_week_range($target_year_week);

// Back returns to the check-in on the same week
$back_week = '';
if ($target_year_week === get_year_week(time())) {
    $back_week = 'this_week';
} elseif ($target_year_week === get_year_week(strtotime('-1 week'))) {
    $back_week = 'last_week';
}
$back_url = url('/apps/pulse.php') . ($back_week ? '?week=' . $back_week : '');

$error_message = '';
$success_message = '';
$selected_ids = [];
$message = '';

// ============================================================================
// Form Processing
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $selected_ids = array_map('intval', (array) ($_POST['recipients'] ?? []));
    $message = (string) ($_POST['message'] ?? '');
    $is_empty = !$selected_ids && trim($message) === '';

    if (!auth_verify_csrf($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid form submission (CSRF token mismatch)';
    } elseif ($action === 'next' && $is_empty) {
        // Nothing to give this week
        header('Location: ' . url('/apps/hours.php'));
        exit();
    } elseif ($action === 'next' || $action === 'another') {
        try {
            $zoom = high_fives_zoom_config();
            $id = high_fives_give($pdo, (int) $user['id'], $target_year_week, $selected_ids, $message, $zoom !== null);
            header('Location: ' . url($action === 'next' ? '/apps/hours.php' : '/apps/high-fives.php?sent=' . $id));
            if ($zoom) {
                // Let the browser move on, then post: a slow Zoom never holds up the click.
                // The session is released first, or the next page would wait on its lock.
                // A failure is recorded and retried by scripts/post_high_fives.php.
                session_write_close();
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
                try {
                    high_fives_zoom_post($pdo, $id, $zoom);
                } catch (Throwable $e) {
                    error_log('high-fives.php Zoom post: ' . $e->getMessage());
                }
            }
            exit();
        } catch (InvalidArgumentException $e) {
            $error_message = $e->getMessage();
        } catch (Throwable $e) {
            error_log('high-fives.php give: ' . $e->getMessage());
            $error_message = 'Could not save your High Five. Please try again.';
        }
    }
}

$teammates = high_fives_teammates($pdo, (int) $user['id']);
$given = high_fives_given($pdo, (int) $user['id'], $target_year_week);

if (isset($_GET['sent'])) {
    foreach ($given as $hf) {
        if ((int) $hf['id'] === (int) $_GET['sent']) {
            $success_message = '🙌 High Five sent to ' . $hf['recipients'] . '!';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>High Fives - PluseHours</title>
    <?php require_once __DIR__ . '/../includes/head.php'; ?>
    <link rel="stylesheet" href="<?= url('assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/admin-nav-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/pulse-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/high-fives-styles.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/../_header.php'; ?>

    <?php if ($user && $user['role'] === 'Admin'): ?>
        <?php include __DIR__ . '/admin/_admin_nav.php'; ?>
    <?php endif; ?>

    <main class="admin-content">
        <div class="pulse-container">
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <div class="pulse-card">
                <form method="POST" action="" id="highFiveForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token()) ?>">

                    <div class="hf-intro">
                        <h2>Any High Fives this week?</h2>
                        <p>Give a teammate a shout-out for something great they did. Week of <?= htmlspecialchars($week_label) ?>.</p>
                    </div>

                    <div class="question-section">
                        <h3>Who's it for?</h3>
                        <?php if (count($teammates) > 12): ?>
                            <input type="text" id="hfFilter" class="hf-filter" placeholder="Type a name to filter…" autocomplete="off">
                        <?php endif; ?>
                        <div class="hf-pills" id="hfPills">
                            <?php foreach ($teammates as $t): ?>
                                <?php $name = $t['first_name'] . ' ' . $t['last_name']; ?>
                                <label class="hf-pill" data-name="<?= htmlspecialchars(strtolower($name)) ?>">
                                    <input type="checkbox" name="recipients[]" value="<?= (int) $t['id'] ?>"
                                        <?= in_array((int) $t['id'], $selected_ids, true) ? 'checked' : '' ?>>
                                    <span><?= htmlspecialchars($name) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="question-section">
                        <h3><label for="hfMessage">What did they do?</label></h3>
                        <textarea id="hfMessage" name="message" class="hf-message" rows="4"
                            maxlength="<?= HIGH_FIVE_MAX_LENGTH ?>"><?= htmlspecialchars($message) ?></textarea>
                    </div>

                    <div class="hf-actions">
                        <a href="<?= htmlspecialchars($back_url) ?>" class="btn btn-secondary">Back</a>
                        <button type="submit" name="action" value="another" class="btn btn-secondary">Give another High Five</button>
                        <button type="submit" name="action" value="next" class="btn btn-primary">Next</button>
                    </div>
                </form>
            </div>

            <?php if ($given): ?>
                <div class="hf-given">
                    <h3>Your High Fives this week</h3>
                    <ul>
                        <?php foreach ($given as $hf): ?>
                            <li>
                                <strong>🙌 <?= htmlspecialchars($hf['recipients']) ?></strong>
                                <p><?= nl2br(htmlspecialchars($hf['message'])) ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php if (count($teammates) > 12): ?>
    <script>
        // Narrow the pills as you type; Enter must not submit the form
        (function () {
            var filter = document.getElementById('hfFilter');
            var pills = document.querySelectorAll('#hfPills .hf-pill');
            filter.addEventListener('input', function () {
                var q = filter.value.trim().toLowerCase();
                pills.forEach(function (p) {
                    var picked = p.querySelector('input').checked;
                    p.hidden = q !== '' && !picked && p.dataset.name.indexOf(q) === -1;
                });
            });
            filter.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') e.preventDefault();
            });
        })();
    </script>
    <?php endif; ?>
</body>
</html>
