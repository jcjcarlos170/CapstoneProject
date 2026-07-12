<?php
// ================================================================
//  OPTICANA — api/users/reset_password.php
//  Admin only. Resets any user's password.
//  POST { profileId, role, newPassword }
//  → { success:true } | { success:false, message }
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('POST');
startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}
if (!in_array($_SESSION['role'], ['admin', 'staff'], true)) {
    jsonResponse(['success' => false, 'message' => 'Only admins and staff may reset user passwords.'], 403);
}

$b         = getBody();
$profileId = trim($b['profileId']  ?? '');
$role      = trim($b['role']       ?? '');
$newPass   = $b['newPassword']     ?? '';

if (!$profileId || !$role || !$newPass) {
    jsonResponse(['success' => false, 'message' => 'profileId, role and newPassword are required.']);
}
if (strlen($newPass) < 8) {
    jsonResponse(['success' => false, 'message' => 'Password must be at least 8 characters.']);
}

$tableMap = [
    'Admin'   => 'admins',
    'Staff'   => 'staff',
    'Doctor'  => 'doctors',
    'Patient' => 'patients',
];

$table = $tableMap[$role] ?? null;
if (!$table) {
    jsonResponse(['success' => false, 'message' => 'Invalid role.']);
}

try {
    $pdo = getDB();

    $s = $pdo->prepare("SELECT user_id FROM `{$table}` WHERE id = ? LIMIT 1");
    $s->execute([$profileId]);
    $row = $s->fetch();
    if (!$row || !$row['user_id']) {
        jsonResponse(['success' => false, 'message' => 'User account not found.']);
    }

    $hash = password_hash($newPass, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
        ->execute([$hash, (int)$row['user_id']]);

    jsonResponse(['success' => true]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error. Please try again.'], 500);
}
