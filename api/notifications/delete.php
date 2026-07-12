<?php
// ================================================================
//  OPTICANA — api/notifications/delete.php
//  POST { id }      → delete one notification (must belong to caller)
//  POST { all:true} → delete all notifications for the caller
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('POST');
startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}

$userId = (int)$_SESSION['user_id'];
$b      = getBody();
$id     = isset($b['id'])  ? (int)$b['id'] : null;
$all    = !empty($b['all']);

if (!$id && !$all) {
    jsonResponse(['success' => false, 'message' => 'No target specified.']);
}

try {
    $pdo = getDB();

    if ($all) {
        $pdo->prepare('DELETE FROM notifications WHERE user_id = ?')->execute([$userId]);
    } else {
        // WHERE user_id ensures users can only delete their own notifications
        $pdo->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
    }

    jsonResponse(['success' => true]);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error.'], 500);
}
