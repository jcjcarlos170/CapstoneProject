<?php
// ================================================================
//  OPTICANA — api/auth/forgot-password.php
//  POST { email }
//  Generates a 6-digit OTP, stores it, and emails it via SMTP.
//  Always returns success:true to avoid leaking whether an email exists.
// ================================================================

require_once '../../config/db.php';
require_once '../../config/smtp.php';
require_once '../../vendor/autoload.php';
require_once '../helpers.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

requireMethod('POST');

$b     = getBody();
$email = strtolower(trim($b['email'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'message' => 'Please enter a valid email address.']);
}

try {
    $pdo = getDB();

    // Check if the email belongs to a registered user
    $s = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = ? LIMIT 1');
    $s->execute([$email]);
    $user = $s->fetch();

    if ($user) {
        // Invalidate any previous unused OTPs for this email
        $pdo->prepare('UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0')
            ->execute([$email]);

        // Generate a cryptographically secure 6-digit OTP
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Use MySQL's NOW() for expiry so timezone mismatches between PHP and MySQL don't break the check
        $pdo->prepare('INSERT INTO password_resets (email, otp, expires_at) VALUES (?, ?, NOW() + INTERVAL 5 MINUTE)')
            ->execute([$email, $otp]);

        // Send OTP via SMTP
        $logoPath = realpath(__DIR__ . '/../../brand_assests/cana logo.png');

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Your Opticana Password Reset Code';

        $hasCidLogo = false;
        if ($logoPath && file_exists($logoPath)) {
            $mail->addEmbeddedImage($logoPath, 'opticana_logo', 'cana logo.png', 'base64', 'image/png');
            $hasCidLogo = true;
        }

        $mail->Body    = emailBody($otp, $hasCidLogo);
        $mail->AltBody = "Your Opticana password reset code is: $otp\n\nThis code expires in 5 minutes. Do not share it with anyone.";

        $mail->send();
    }
    // Always return success so we don't leak whether the email is registered
    jsonResponse(['success' => true]);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Failed to send email. Please try again later.'], 500);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Server error. Please try again.'], 500);
}

function emailBody(string $otp, bool $hasCidLogo): string {
    $logoTag = $hasCidLogo
        ? '<img src="cid:opticana_logo" alt="Opticana" width="60" height="60" style="display:block;margin:0 auto 12px;border-radius:50%;object-fit:contain;background:#fff;">'
        : '';

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Reset Your Password</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body style="margin:0;padding:0;background:#f0f1f5;font-family:'Poppins','Segoe UI',Arial,sans-serif;">

  <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f0f1f5;padding:48px 16px;">
    <tr><td align="center">

      <table width="520" cellpadding="0" cellspacing="0" role="presentation"
             style="background:#ffffff;border-radius:16px;overflow:hidden;
                    box-shadow:0 4px 24px rgba(0,0,0,0.09),0 1px 4px rgba(0,0,0,0.05);
                    max-width:520px;width:100%;">

        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#E8760A 0%,#C4620A 100%);
                     padding:32px 40px 28px;text-align:center;">
            {$logoTag}
            <div style="font-family:'Poppins','Segoe UI',Arial,sans-serif;
                        font-size:22px;font-weight:800;color:#ffffff;
                        letter-spacing:1px;line-height:1;margin-bottom:4px;">
              OPTICANA
            </div>
            <div style="font-family:'Poppins','Segoe UI',Arial,sans-serif;
                        font-size:11px;font-weight:500;letter-spacing:2.5px;
                        color:rgba(255,255,255,0.7);text-transform:uppercase;">
              Cana Optical Clinic
            </div>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:40px 40px 0;text-align:center;">

            <h1 style="font-family:'Poppins','Segoe UI',Arial,sans-serif;
                       margin:0 0 12px;font-size:20px;font-weight:700;
                       color:#1C1C1C;letter-spacing:-0.3px;">
              Password Reset Request
            </h1>
            <p style="font-family:'Poppins','Segoe UI',Arial,sans-serif;
                      margin:0 auto;font-size:14px;color:#6b7280;
                      line-height:1.7;max-width:360px;">
              We received a request to reset your Opticana account password.
              Use the verification code below to continue. It is valid for
              <strong style="color:#1C1C1C;font-weight:600;">5 minutes</strong> only.
            </p>
          </td>
        </tr>

        <!-- OTP block -->
        <tr>
          <td style="padding:32px 40px 28px;">
            <p style="font-family:'Poppins','Segoe UI',Arial,sans-serif;
                      margin:0 0 14px;font-size:10px;font-weight:700;
                      letter-spacing:2.5px;text-transform:uppercase;
                      color:#9ca3af;text-align:center;">
              Your Verification Code
            </p>

            <div style="background:#FFF8F0;border:2px solid #FFD9A8;
                        border-radius:12px;padding:24px 32px;text-align:center;">
              <span style="font-family:'Poppins','Segoe UI',Arial,sans-serif;
                           font-size:42px;font-weight:800;letter-spacing:14px;
                           color:#E8760A;line-height:1;display:block;">
                {$otp}
              </span>
            </div>

            <p style="font-family:'Poppins','Segoe UI',Arial,sans-serif;
                      margin:14px 0 0;font-size:12px;color:#9ca3af;text-align:center;">
              This code expires in 5 minutes.
            </p>
          </td>
        </tr>

        <!-- Divider -->
        <tr>
          <td style="padding:0 40px;">
            <div style="border-top:1px solid #f0f0f4;"></div>
          </td>
        </tr>

        <!-- Security notice -->
        <tr>
          <td style="padding:24px 40px;">
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
              <tr>
                <td style="width:32px;vertical-align:top;padding-top:2px;">
                  <svg width="17" height="17" viewBox="0 0 24 24" fill="none"
                       stroke="#E8760A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                       xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                  </svg>
                </td>
                <td style="font-family:'Poppins','Segoe UI',Arial,sans-serif;
                           font-size:13px;color:#6b7280;line-height:1.65;">
                  If you did not request a password reset, please ignore this email.
                  Your password will remain unchanged and no action is needed.
                  Never share this code with anyone.
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#f8f8fb;padding:20px 40px;
                     border-top:1px solid #f0f0f4;text-align:center;">
            <p style="font-family:'Poppins','Segoe UI',Arial,sans-serif;
                      margin:0;font-size:11px;color:#b0b7c3;">
              &copy; Opticana &nbsp;&bull;&nbsp; Cana Optical Clinic &nbsp;&bull;&nbsp; Do not reply to this email
            </p>
          </td>
        </tr>

      </table>

    </td></tr>
  </table>

</body>
</html>
HTML;
}
