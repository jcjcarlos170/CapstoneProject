<?php
// ================================================================
//  OPTICANA — api/patients/update.php
//
//  POST { action:'profile', firstName, lastName, phone, email, address }
//  POST { action:'password', currentPassword, newPassword }
//
//  Only authenticated patients may call this endpoint.
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('POST');
startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}
if (($_SESSION['role'] ?? '') !== 'patient') {
    jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
}

$userId = (int)$_SESSION['user_id'];
$b      = getBody();
$action = trim($b['action'] ?? '');

try {
    $pdo = getDB();

    // ── Profile update ───────────────────────────────────────────
    if ($action === 'profile') {
        $fn      = trim($b['firstName'] ?? '');
        $ln      = trim($b['lastName']  ?? '');
        $phone   = trim($b['phone']     ?? '');
        $address = trim($b['address']   ?? '');
        $email   = trim($b['email']     ?? '');

        if (!$fn || !$ln) {
            jsonResponse(['success' => false, 'message' => 'First and last name are required.']);
        }

        $pdo->prepare(
            'UPDATE patients SET first_name = ?, last_name = ?, contact = ?, address = ? WHERE user_id = ?'
        )->execute([$fn, $ln, $phone, $address, $userId]);

        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
            $chk->execute([$email, $userId]);
            if ($chk->fetch()) {
                jsonResponse(['success' => false, 'message' => 'An account with this email already exists.']);
            }
            $pdo->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$email, $userId]);
        }

        jsonResponse(['success' => true, 'message' => 'Profile updated successfully.']);
    }

    // ── Password change ──────────────────────────────────────────
    if ($action === 'password') {
        $curPw = $b['currentPassword'] ?? '';
        $newPw = $b['newPassword']     ?? '';

        if (!$curPw || !$newPw) {
            jsonResponse(['success' => false, 'message' => 'Current and new password are required.']);
        }
        if (strlen($newPw) < 8) {
            jsonResponse(['success' => false, 'message' => 'New password must be at least 8 characters.']);
        }

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($curPw, $user['password_hash'])) {
            jsonResponse(['success' => false, 'message' => 'Current password is incorrect.']);
        }

        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($newPw, PASSWORD_DEFAULT), $userId]);

        jsonResponse(['success' => true, 'message' => 'Password updated successfully.']);
    }

    jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error. Please try again.'], 500);
}
