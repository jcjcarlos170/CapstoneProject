<?php
// ================================================================
//  OPTICANA — config/smtp.php
//  SMTP credentials loaded from environment variables.
//  Set these in Railway → Variables dashboard:
//
//  SMTP_HOST      = smtp-relay.brevo.com        (or smtp.gmail.com)
//  SMTP_PORT      = 587
//  SMTP_USERNAME  = your-brevo-login@email.com  (or Gmail address)
//  SMTP_PASSWORD  = your-brevo-api-key          (or Gmail App Password)
//  SMTP_FROM      = sender@yourdomain.com
//  SMTP_FROM_NAME = Opticana - Cana Optical Clinic
//
//  Brevo (recommended for Railway):  https://app.brevo.com → SMTP & API → SMTP
//  Gmail App Password (local dev):   https://myaccount.google.com/apppasswords
// ================================================================

define('SMTP_HOST',      getenv('SMTP_HOST')      ?: 'smtp.gmail.com');
define('SMTP_PORT',      (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_USERNAME',  getenv('SMTP_USERNAME')   ?: 'jcjcarlos892@gmail.com');
define('SMTP_PASSWORD',  getenv('SMTP_PASSWORD')   ?: 'ncpyohmvfhnnskiq');
define('SMTP_FROM',      getenv('SMTP_FROM')       ?: 'jcjcarlos892@gmail.com');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME')  ?: 'Opticana - Cana Optical Clinic');
define('BREVO_API_KEY',  getenv('BREVO_API_KEY')   ?: '');

// ================================================================
//  Unified email sender — uses Brevo HTTP API when BREVO_API_KEY
//  is set (Railway), falls back to PHPMailer SMTP (local dev).
// ================================================================

function sendEmail(string $to, string $toName, string $subject, string $html, string $text): void {
    if (BREVO_API_KEY) {
        _brevoSend($to, $toName, $subject, $html, $text);
    } else {
        _smtpSend($to, $toName, $subject, $html, $text);
    }
}

function _brevoSend(string $to, string $toName, string $subject, string $html, string $text): void {
    $payload = json_encode([
        'sender'      => ['name' => SMTP_FROM_NAME, 'email' => SMTP_FROM],
        'to'          => [['email' => $to, 'name' => $toName ?: $to]],
        'subject'     => $subject,
        'htmlContent' => $html,
        'textContent' => $text,
    ]);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'api-key: ' . BREVO_API_KEY,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        throw new \RuntimeException("Email delivery error: $curlErr");
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        throw new \RuntimeException("Email delivery error ($httpCode): $response");
    }
}

function _smtpSend(string $to, string $toName, string $subject, string $html, string $text): void {
    require_once __DIR__ . '/../vendor/autoload.php';
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host          = SMTP_HOST;
    $mail->SMTPAuth      = true;
    $mail->Username      = SMTP_USERNAME;
    $mail->Password      = SMTP_PASSWORD;
    $mail->SMTPSecure    = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port          = SMTP_PORT;
    $mail->Timeout       = 10;
    $mail->SMTPKeepAlive = false;
    $mail->SMTPOptions   = ['ssl' => [
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'allow_self_signed' => true,
    ]];
    $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
    $toName ? $mail->addAddress($to, $toName) : $mail->addAddress($to);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $html;
    $mail->AltBody = $text;
    $mail->send();
}
