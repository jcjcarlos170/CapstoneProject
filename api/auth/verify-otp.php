<?php
// ================================================================
//  OPTICANA — api/auth/verify-otp.php
//  POST { email, otp }
//  → 200 { success:true, token }                  — OTP matched
//  → 200 { success:false, attemptsLeft:N }         — wrong code
//  → 200 { success:false, locked:true }            — 3 wrong tries
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('POST');
rateLimit('verify-otp', 15, 600); // 15 per IP per 10 min

$b     = getBody();
$email = strtolower(trim($b['email'] ?? ''));
$otp   = trim($b['otp'] ?? '');

if (!$email || strlen($otp) !== 6) {
    jsonResponse(['success' => false, 'message' => 'Invalid request.']);
}

const MAX_OTP_ATTEMPTS = 5;

try {
    $pdo = getDB();

    // Find the active (unused, not expired) OTP row — don't filter by otp here
    // so we can count wrong attempts even when the code doesn't match.
    $s = $pdo->prepare(
        'SELECT id, otp, attempts FROM password_resets
          WHERE email = ? AND used = 0 AND expires_at > NOW()
          ORDER BY id DESC LIMIT 1'
    );
    $s->execute([$email]);
    $row = $s->fetch();

    if (!$row) {
        jsonResponse(['success' => false, 'message' => 'Invalid or expired code. Please request a new one.']);
    }

    // Already burned all attempts in a prior request
    if ((int)$row['attempts'] >= MAX_OTP_ATTEMPTS) {
        jsonResponse(['success' => false, 'locked' => true,
            'message' => 'Too many wrong attempts. Please request a new code.']);
    }

    // Wrong code — increment attempts, then lock if limit reached
    if ($row['otp'] !== $otp) {
        $newAttempts = (int)$row['attempts'] + 1;

        if ($newAttempts >= MAX_OTP_ATTEMPTS) {
            // Mark as used so the OTP is dead even if the timer hasn't expired
            $pdo->prepare('UPDATE password_resets SET attempts = ?, used = 1 WHERE id = ?')
                ->execute([$newAttempts, $row['id']]);
            jsonResponse(['success' => false, 'locked' => true,
                'message' => 'Too many wrong attempts. Please request a new code.']);
        }

        $pdo->prepare('UPDATE password_resets SET attempts = ? WHERE id = ?')
            ->execute([$newAttempts, $row['id']]);

        $left = MAX_OTP_ATTEMPTS - $newAttempts;
        jsonResponse(['success' => false, 'attemptsLeft' => $left,
            'message' => 'Incorrect code. ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' remaining.']);
    }

    // Correct OTP — generate a secure one-time reset token
    $token = bin2hex(random_bytes(32));
    $pdo->prepare('UPDATE password_resets SET token = ? WHERE id = ?')
        ->execute([$token, $row['id']]);

    jsonResponse(['success' => true, 'token' => $token]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Server error. Please try again.'], 500);
}
