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

const MAX_OTP_ATTEMPTS   = 5;
const MAX_FP_TOTAL       = 10; // hard limit across all OTP resends

try {
    $pdo = getDB();

    // Find the active (unused, not expired) OTP row — don't filter by otp here
    // so we can count wrong attempts even when the code doesn't match.
    $s = $pdo->prepare(
        'SELECT id, otp, attempts, total_attempts FROM password_resets
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

    // Wrong code — increment attempts, then lock/ban if limits reached
    if ($row['otp'] !== $otp) {
        $newAttempts      = (int)$row['attempts'] + 1;
        $newTotalAttempts = (int)$row['total_attempts'] + 1;

        // Hard limit: block all further OTP requests for this email for 1 hour
        if ($newTotalAttempts >= MAX_FP_TOTAL) {
            $pdo->prepare('UPDATE password_resets SET attempts = ?, total_attempts = ?, blocked_until = NOW() + INTERVAL 1 HOUR, used = 1 WHERE id = ?')
                ->execute([$newAttempts, $newTotalAttempts, $row['id']]);
            jsonResponse(['success' => false, 'banned' => true,
                'message' => 'Too many failed attempts. Please try again in 1 hour.']);
        }

        if ($newAttempts >= MAX_OTP_ATTEMPTS) {
            $pdo->prepare('UPDATE password_resets SET attempts = ?, total_attempts = ?, used = 1 WHERE id = ?')
                ->execute([$newAttempts, $newTotalAttempts, $row['id']]);
            jsonResponse(['success' => false, 'locked' => true,
                'message' => 'Too many wrong attempts. Please request a new code.']);
        }

        $pdo->prepare('UPDATE password_resets SET attempts = ?, total_attempts = ? WHERE id = ?')
            ->execute([$newAttempts, $newTotalAttempts, $row['id']]);

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
