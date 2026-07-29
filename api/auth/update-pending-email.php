<?php
// ================================================================
//  OPTICANA — api/auth/update-pending-email.php
//  Lets someone fix a typo'd address on a pending (unverified)
//  registration without restarting the whole signup form.
//  POST { email, newEmail }
//  → { success:true, email } | { success:false, message }
//
//  Updates pending_registrations only — mirrors register.php /
//  send-email-verification.php in style and validation.
// ================================================================

require_once '../../config/db.php';
require_once '../../config/smtp.php';
require_once '../helpers.php';

requireMethod('POST');
rateLimit('update-pending-email', 5, 900);

$b        = getBody();
$email    = strtolower(trim($b['email']    ?? ''));
$newEmail = strtolower(trim($b['newEmail'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'message' => 'Please enter a valid email address.']);
}
if ($newEmail === $email) {
    jsonResponse(['success' => false, 'message' => 'That\'s the same email address you already have on file.']);
}

try {
    $pdo = getDB();

    $s = $pdo->prepare('SELECT id, first_name, total_attempts FROM pending_registrations WHERE email = ? LIMIT 1');
    $s->execute([$email]);
    $row = $s->fetch();

    if (!$row) {
        jsonResponse(['success' => false, 'message' => 'No pending registration found for this email. Please register again.']);
    }

    if ((int)$row['total_attempts'] >= 10) {
        jsonResponse(['success' => false, 'banned' => true,
            'message' => 'Too many failed attempts. Please register again with a valid email.']);
    }

    // Block if the new address already has a confirmed account
    $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $chk->execute([$newEmail]);
    if ($chk->fetch()) {
        jsonResponse(['success' => false, 'message' => 'An account with this email already exists.']);
    }

    // Block if the new address is already mid-registration on a different pending row
    $chkPending = $pdo->prepare('SELECT id FROM pending_registrations WHERE email = ? AND id != ? LIMIT 1');
    $chkPending->execute([$newEmail, $row['id']]);
    if ($chkPending->fetch()) {
        jsonResponse(['success' => false, 'message' => 'This email is already awaiting verification on another registration.']);
    }

    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    $pdo->prepare(
        'UPDATE pending_registrations
            SET email = ?, otp = ?, attempts = 0, expires_at = NOW() + INTERVAL 5 MINUTE
          WHERE id = ?'
    )->execute([$newEmail, $otp, $row['id']]);

    sendVerificationEmail($newEmail, $otp, $row['first_name']);

    jsonResponse(['success' => true, 'email' => $newEmail]);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Failed to send email. Please try again later.'], 500);
} catch (PDOException $e) {
    // Duplicate-key race (two requests changing to the same new email at once)
    if ((int)$e->getCode() === 23000 || str_contains($e->getMessage(), 'uq_pending_email')) {
        jsonResponse(['success' => false, 'message' => 'This email is already awaiting verification on another registration.']);
    }
    jsonResponse(['success' => false, 'message' => 'Server error. Please try again.'], 500);
}

function sendVerificationEmail(string $to, string $otp, string $firstName = ''): void {
    sendEmail(
        $to, $firstName,
        'Your New Opticana Verification Code',
        verificationEmailBody($otp, $firstName),
        "Your new Opticana verification code is: $otp\n\nExpires in 5 minutes."
    );
}

function verificationEmailBody(string $otp, string $firstName = ''): string {
    $greeting = $firstName ? "Hi $firstName," : 'Hello,';
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Verify Your Email</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body style="margin:0;padding:0;background:#f0f1f5;font-family:'Poppins','Segoe UI',Arial,sans-serif;">

  <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f0f1f5;padding:48px 16px;">
    <tr><td align="center">

      <table width="520" cellpadding="0" cellspacing="0" role="presentation"
             style="background:#ffffff;border-radius:16px;overflow:hidden;
                    box-shadow:0 4px 24px rgba(0,0,0,0.09),0 1px 4px rgba(0,0,0,0.05);
                    max-width:520px;width:100%;">

        <tr>
          <td style="background:#E8760A;
                     padding:32px 40px 28px;text-align:center;">
            <div style="font-size:22px;font-weight:800;color:#ffffff;letter-spacing:1px;margin-bottom:4px;">OPTICANA</div>
            <div style="font-size:11px;font-weight:500;letter-spacing:2.5px;color:rgba(255,255,255,0.7);text-transform:uppercase;">Cana Optical Clinic</div>
          </td>
        </tr>

        <tr>
          <td style="padding:40px 40px 0;text-align:center;">
            <h1 style="margin:0 0 12px;font-size:20px;font-weight:700;color:#1C1C1C;">Verify Your Email Address</h1>
            <p style="margin:0 auto;font-size:14px;color:#6b7280;line-height:1.7;max-width:360px;">
              {$greeting} We've updated the email on your pending registration. Here is your new verification code, valid for
              <strong style="color:#1C1C1C;font-weight:600;">5 minutes</strong>.
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:32px 40px 28px;">
            <p style="margin:0 0 14px;font-size:10px;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;color:#9ca3af;text-align:center;">Your Verification Code</p>
            <div style="background:#FFF8F0;border:2px solid #FFD9A8;border-radius:12px;padding:24px 32px;text-align:center;">
              <span style="font-size:42px;font-weight:800;letter-spacing:14px;color:#E8760A;line-height:1;display:block;">{$otp}</span>
            </div>
            <p style="margin:14px 0 0;font-size:12px;color:#9ca3af;text-align:center;">This code expires in 5 minutes.</p>
          </td>
        </tr>

        <tr><td style="padding:0 40px;"><div style="border-top:1px solid #f0f0f4;"></div></td></tr>

        <tr>
          <td style="padding:24px 40px;">
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
              <tr>
                <td style="width:32px;vertical-align:top;padding-top:2px;">
                  <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#E8760A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                  </svg>
                </td>
                <td style="font-size:13px;color:#6b7280;line-height:1.65;">
                  If you did not request this change, please ignore this email. Never share this code with anyone.
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <tr>
          <td style="background:#f8f8fb;padding:20px 40px;border-top:1px solid #f0f0f4;text-align:center;">
            <p style="margin:0;font-size:11px;color:#b0b7c3;">&copy; Opticana &nbsp;&bull;&nbsp; Cana Optical Clinic &nbsp;&bull;&nbsp; Do not reply to this email</p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>

</body>
</html>
HTML;
}
