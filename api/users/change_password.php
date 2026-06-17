<?php
// ================================================================
//  OPTICANA — api/users/change_password.php
//
//  POST { currentPassword, newPassword }
//  Any authenticated user (any role).
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
$curPw  = $b['currentPassword'] ?? '';
$newPw  = $b['newPassword']     ?? '';

if (!$curPw || !$newPw) {
    jsonResponse(['success' => false, 'message' => 'Current and new password are required.']);
}
if (strlen($newPw) < 8) {
    jsonResponse(['success' => false, 'message' => 'New password must be at least 8 characters.']);
}

try {
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($curPw, $user['password_hash'])) {
        jsonResponse(['success' => false, 'message' => 'Current password is incorrect.']);
    }

    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($newPw, PASSWORD_DEFAULT), $userId]);

    jsonResponse(['success' => true]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error. Please try again.'], 500);
}
