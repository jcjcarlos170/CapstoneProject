<?php
// ================================================================
//  OPTICANA — api/auth/verify-otp.php
//  POST { email, otp }
//  → 200 { success:true, token }   — OTP matched, token for reset
//  → 200 { success:false, message }
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('POST');

$b     = getBody();
$email = strtolower(trim($b['email'] ?? ''));
$otp   = trim($b['otp'] ?? '');

if (!$email || strlen($otp) !== 6) {
    jsonResponse(['success' => false, 'message' => 'Invalid request.']);
}

try {
    $pdo = getDB();

    $s = $pdo->prepare(
        'SELECT id FROM password_resets
          WHERE email = ? AND otp = ? AND used = 0 AND expires_at > NOW()
          ORDER BY id DESC LIMIT 1'
    );
    $s->execute([$email, $otp]);
    $row = $s->fetch();

    if (!$row) {
        jsonResponse(['success' => false, 'message' => 'Invalid or expired code. Please try again.']);
    }

    // Generate a secure one-time reset token
    $token = bin2hex(random_bytes(32));

    // Store token on the row (still not "used" until password is actually reset)
    $pdo->prepare('UPDATE password_resets SET token = ? WHERE id = ?')
        ->execute([$token, $row['id']]);

    jsonResponse(['success' => true, 'token' => $token]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Server error. Please try again.'], 500);
}
