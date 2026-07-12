<?php
// TEMPORARY DEBUG — delete this file after email is confirmed working
require_once '../config/smtp.php';

header('Content-Type: application/json');

$result = [
    'mode'   => BREVO_API_KEY ? 'brevo-api' : 'smtp-fallback',
    'config' => [
        'from'      => SMTP_FROM,
        'from_name' => SMTP_FROM_NAME,
        'api_key'   => BREVO_API_KEY ? substr(BREVO_API_KEY, 0, 8) . '...' : '(not set)',
    ],
];

try {
    sendEmail(
        SMTP_FROM, SMTP_FROM_NAME,
        'Opticana Email Test',
        '<p>Email delivery is working correctly.</p>',
        'Email delivery is working correctly.'
    );
    $result['success'] = true;
    $result['message'] = 'Email sent successfully!';
} catch (\Exception $e) {
    $result['success'] = false;
    $result['error']   = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT);
