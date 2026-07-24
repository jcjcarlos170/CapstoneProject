<?php
// ================================================================
//  CANAOPTICALCLINIC — api/users/update_profile.php
//
//  POST { firstName, lastName, phone, email }
//  Authenticated admin / staff / doctor only.
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('POST');
startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}

$role = $_SESSION['role'] ?? '';
$tableMap = ['admin' => 'admins', 'staff' => 'staff', 'doctor' => 'doctors'];
if (!array_key_exists($role, $tableMap)) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
}

$userId = (int)$_SESSION['user_id'];
$b      = getBody();
$fn     = trim($b['firstName'] ?? '');
$ln     = trim($b['lastName']  ?? '');
$phone  = trim($b['phone']     ?? '');
$email  = trim($b['email']     ?? '');

if (!$fn || !$ln) {
    jsonResponse(['success' => false, 'message' => 'First and last name are required.']);
}

$table = $tableMap[$role];

try {
    $pdo = getDB();

    $pdo->prepare(
        "UPDATE `{$table}` SET first_name = ?, last_name = ?, contact = ? WHERE user_id = ?"
    )->execute([$fn, $ln, $phone, $userId]);

    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
        $chk->execute([$email, $userId]);
        if ($chk->fetch()) {
            jsonResponse(['success' => false, 'message' => 'That email is already in use by another account.']);
        }
        $pdo->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$email, $userId]);
    }

    jsonResponse(['success' => true]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error. Please try again.'], 500);
}
