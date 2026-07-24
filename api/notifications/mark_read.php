<?php
// ================================================================
//  CANAOPTICALCLINIC — api/notifications/mark_read.php
//  POST { id: N }       → mark single notification as read
//  POST { all: true }   → mark all notifications as read
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

try {
    $pdo = getDB();

    if (!empty($b['all'])) {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')
            ->execute([$userId]);
        jsonResponse(['success' => true]);
    }

    $id = (int)($b['id'] ?? 0);
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'id or all is required.']);
    }

    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
        ->execute([$id, $userId]);

    jsonResponse(['success' => true]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error.'], 500);
}
