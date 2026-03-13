<?php
// PulseHours internal login is disabled; use central auth.
$returnTo = urlencode($_GET['return_to'] ?? url('/'));
header('Location: /auth/login.php?return_to=' . $returnTo);
exit;
