<?php
// ================================================================
//  CANAOPTICALCLINIC — api/auth/me.php
//  GET — returns the current session user, or { success: false }.
//  Called on page load to restore an active PHP session.
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

startSession();

if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    jsonResponse(['success' => false]);
}

$userId = (int)$_SESSION['user_id'];
$role   = $_SESSION['role'];

try {
    $pdo = getDB();

    $uStmt = $pdo->prepare('SELECT email FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
    $uStmt->execute([$userId]);
    $userRow = $uStmt->fetch();

    if (!$userRow) {
        session_destroy();
        jsonResponse(['success' => false]);
    }

    $result = loadUserProfile($pdo, $userId, $role);
} catch (PDOException $e) {
    jsonResponse(['success' => false]);
}

if (!$result) {
    jsonResponse(['success' => false]);
}

$userObj = buildUserObject($role, $result['profile'], $userRow['email'], $result['days'], $userId);

jsonResponse(['success' => true, 'role' => $role, 'user' => $userObj]);
