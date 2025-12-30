<?php
/**
 * Pulse Check-in Page
 * 
 * Allows users to submit weekly pulse check-ins with mood and workload ratings.
 * Accessible to all logged-in users (not admin-only).
 */

require __DIR__ . '/../auth/include/auth_include.php';
auth_init();
auth_require_login(); // Only require login, not admin

$user = auth_get_user();
$pdo = get_db_connection();

require_once __DIR__ . '/../includes/date_helpers.php';

// Success/error messages
$success_message = '';
$error_message = '';
$existing_entry = null;

/**
 * Get smart default week selection based on current day
 * @return string 'this_week' or 'last_week'
 */
function get_default_week_selection() {
    $day_of_week = date('N'); // 1 (Monday) through 7 (Sunday)
    
    // Thursday (4) or Friday (5) → default "This Week"
    if ($day_of_week == 4 || $day_of_week == 5) {
        return 'this_week';
    }
    // Saturday (6), Sunday (7), Monday (1), Tuesday (2), Wednesday (3) → default "Last Week"
    else {
        return 'last_week';
    }
}

// ============================================================================
// Calculate Current and Last Week
// ============================================================================

$current_timestamp = time();
$this_week = get_year_week($current_timestamp);
$last_week = get_year_week(strtotime('-1 week', $current_timestamp));

$this_week_label = format_week_range($this_week);
$last_week_label = format_week_range($last_week);

// ============================================================================
// Form Processing
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_week = $_POST['week_selection'] ?? '';
    $pulse = isset($_POST['pulse']) ? (int)$_POST['pulse'] : 0;
    $work_load = isset($_POST['work_load']) ? (int)$_POST['work_load'] : 0;
    
    // Determine year_week based on selection
    $year_week = ($selected_week === 'this_week') ? $this_week : $last_week;
    
    // Validation
    if ($pulse < 1 || $pulse > 5) {
        $error_message = 'Please select a valid pulse rating (1-5).';
    } elseif ($work_load < 1 || $work_load > 10) {
        $error_message = 'Please select a valid workload rating (1-10).';
    } else {
        try {
            // Check if entry already exists
            $stmt = $pdo->prepare("
                SELECT id FROM pulse 
                WHERE user_id = ? AND year_week = ?
            ");
            $stmt->execute([$user['id'], $year_week]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing entry
                $stmt = $pdo->prepare("
                    UPDATE pulse 
                    SET pulse = ?, work_load = ?, date_created = CURRENT_TIMESTAMP
                    WHERE user_id = ? AND year_week = ?
                ");
                $stmt->execute([$pulse, $work_load, $user['id'], $year_week]);
            } else {
                // Insert new entry
                $stmt = $pdo->prepare("
                    INSERT INTO pulse (user_id, year_week, pulse, work_load)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$user['id'], $year_week, $pulse, $work_load]);
            }
            
            // Redirect to hours entry page
            header('Location: ' . url('/apps/hours.php'));
            exit;
            
        } catch (PDOException $e) {
            error_log("Database error in pulse.php: " . $e->getMessage());
            $error_message = 'An error occurred while saving your check-in. Please try again.';
        }
    }
}

// ============================================================================
// Check for Existing Entries
// ============================================================================

$default_selection = get_default_week_selection();
$selected_week = $_POST['week_selection'] ?? $_GET['week'] ?? $default_selection;
$current_year_week = ($selected_week === 'this_week') ? $this_week : $last_week;

try {
    $stmt = $pdo->prepare("
        SELECT pulse, work_load, date_created 
        FROM pulse 
        WHERE user_id = ? AND year_week = ?
    ");
    $stmt->execute([$user['id'], $current_year_week]);
    $existing_entry = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Database error in pulse.php (fetch): " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pulse Check-in - PluseHours</title>
    <link rel="stylesheet" href="<?= url('assets/admin-styles.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/pulse-styles.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/../_header.php'; ?>
    
    <main class="admin-content">
        <div class="pulse-container">
            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Pulse Check-in Card -->
            <div class="pulse-card">
                <form method="POST" action="" id="pulseForm">
                    <!-- Week Selection -->
                    <div class="week-selector">
                        <h3>Select Week</h3>
                        <div class="week-options">
                            <div class="week-option">
                                <input 
                                    type="radio" 
                                    id="last_week" 
                                    name="week_selection" 
                                    value="last_week"
                                    <?= ($selected_week === 'last_week') ? 'checked' : '' ?>
                                    onchange="this.form.submit()"
                                >
                                <label for="last_week">
                                    <span class="week-title">Last Week</span>
                                    <span class="week-dates"><?= htmlspecialchars($last_week_label) ?></span>
                                </label>
                            </div>
                            <div class="week-option">
                                <input 
                                    type="radio" 
                                    id="this_week" 
                                    name="week_selection" 
                                    value="this_week"
                                    <?= ($selected_week === 'this_week') ? 'checked' : '' ?>
                                    onchange="this.form.submit()"
                                >
                                <label for="this_week">
                                    <span class="week-title">This Week</span>
                                    <span class="week-dates"><?= htmlspecialchars($this_week_label) ?></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Existing Entry Notice -->
                    <?php if ($existing_entry): ?>
                        <div class="existing-entry-notice">
                            <p>✓ You already submitted for this week</p>
                            <small>Submitted on <?= date('M j, Y \a\t g:i A', strtotime($existing_entry['date_created'])) ?></small>
                            <small style="display: block; margin-top: 0.5rem;">You can update your response below</small>
                        </div>
                    <?php endif; ?>

                    <!-- Pulse Question -->
                    <div class="question-section">
                        <h3>How are you doing?</h3>
                        <div class="scale-container">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <div class="scale-option">
                                    <input type="radio" id="pulse<?= $i ?>" name="pulse" value="<?= $i ?>"
                                        <?= ($existing_entry && $existing_entry['pulse'] == $i) ? 'checked' : '' ?>
                                        <?= ($i === 1) ? 'required' : '' ?>>
                                    <label for="pulse<?= $i ?>">
                                        <span class="scale-number"><?= $i ?></span>
                                    </label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Workload Question -->
                    <div class="question-section">
                        <h3>How was your workload?</h3>
                        <div class="scale-container workload-scale">
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                <div class="scale-option">
                                    <input type="radio" id="workload<?= $i ?>" name="work_load" value="<?= $i ?>"
                                        <?= ($existing_entry && $existing_entry['work_load'] == $i) ? 'checked' : '' ?>
                                        <?= ($i === 1) ? 'required' : '' ?>>
                                    <label for="workload<?= $i ?>">
                                        <span class="scale-number"><?= $i ?></span>
                                    </label>
                                </div>
                            <?php endfor; ?>
                        </div>
                        <div class="scale-labels">
                            <span>Very Light</span>
                            <span>Balanced</span>
                            <span>Overwhelmed</span>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="submit-section">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <?= $existing_entry ? 'Update Check-in' : 'Save & Continue' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Form validation and UX enhancements
        document.getElementById('pulseForm').addEventListener('submit', function(e) {
            const pulseSelected = document.querySelector('input[name="pulse"]:checked');
            const workloadSelected = document.querySelector('input[name="work_load"]:checked');
            
            if (!pulseSelected) {
                e.preventDefault();
                alert('Please rate how you are doing (1-5)');
                return false;
            }
            
            if (!workloadSelected) {
                e.preventDefault();
                alert('Please rate your workload (1-10)');
                return false;
            }
        });

        // Prevent accidental week change submission when form fields are filled
        document.querySelectorAll('input[name="week_selection"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                const pulseSelected = document.querySelector('input[name="pulse"]:checked');
                const workloadSelected = document.querySelector('input[name="work_load"]:checked');
                
                if (pulseSelected || workloadSelected) {
                    const confirmChange = confirm('Changing the week will reload the page. Your unsaved selections will be lost. Continue?');
                    if (!confirmChange) {
                        // Revert to the other option
                        document.querySelectorAll('input[name="week_selection"]').forEach(function(r) {
                            if (r !== radio) {
                                r.checked = true;
                            }
                        });
                        return false;
                    }
                }
                this.form.submit();
            });
        });
    </script>
</body>
</html>
