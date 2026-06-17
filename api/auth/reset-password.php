<?php
// ================================================================
//  OPTICANA — api/auth/reset-password.php
//  POST { token, password }
//  → 200 { success:true }
//  → 200 { success:false, message }
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('POST');

$b        = getBody();
$token    = trim($b['token']    ?? '');
$password = $b['password'] ?? '';

if (!$token || strlen($password) < 8) {
    jsonResponse(['success' => false, 'message' => 'Invalid request.']);
}

try {
    $pdo = getDB();

    // Find a valid, unused token that hasn't expired
    $s = $pdo->prepare(
        'SELECT id, email FROM password_resets
          WHERE token = ? AND used = 0 AND expires_at > NOW()
          LIMIT 1'
    );
    $s->execute([$token]);
    $row = $s->fetch();

    if (!$row) {
        jsonResponse(['success' => false, 'message' => 'Reset link is invalid or has expired.']);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();

    // Update password on the users table
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE LOWER(email) = ?')
        ->execute([$hash, strtolower($row['email'])]);

    // Mark the token as used
    $pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = ?')
        ->execute([$row['id']]);

    $pdo->commit();

    jsonResponse(['success' => true]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(['success' => false, 'message' => 'Server error. Please try again.'], 500);
}
