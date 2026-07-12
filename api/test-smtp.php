<?php
// TEMPORARY DEBUG — delete this file after fixing email
require_once '../config/smtp.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

$result = [
    'config' => [
        'host'     => SMTP_HOST,
        'port'     => SMTP_PORT,
        'username' => SMTP_USERNAME,
        'from'     => SMTP_FROM,
    ],
];

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    $mail->Timeout    = 15;
    $mail->SMTPDebug  = 0;
    $mail->SMTPOptions = ['ssl' => [
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'allow_self_signed' => true,
    ]];

    $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
    $mail->addAddress(SMTP_FROM); // send test to self
    $mail->Subject = 'Opticana SMTP Test';
    $mail->Body    = 'SMTP is working correctly.';
    $mail->send();

    $result['success'] = true;
    $result['message'] = 'Email sent successfully!';

} catch (Exception $e) {
    $result['success']       = false;
    $result['error']         = $e->getMessage();
    $result['mailer_error']  = $mail->ErrorInfo;
}

echo json_encode($result, JSON_PRETTY_PRINT);
