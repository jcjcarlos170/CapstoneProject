<?php
// ================================================================
//  CANAOPTICALCLINIC — api/activity/clear.php
//  Admin only. Deletes all activity log entries.
//  POST {} → { success:true }
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('POST');
startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}
if ($_SESSION['role'] !== 'admin') {
    jsonResponse(['success' => false, 'message' => 'Only admins may clear the activity log.'], 403);
}

try {
    $pdo = getDB();
    $pdo->exec('DELETE FROM activity_log');
    jsonResponse(['success' => true]);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error.'], 500);
}
