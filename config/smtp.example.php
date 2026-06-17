<?php
// ================================================================
//  OPTICANA — config/smtp.example.php
//  Copy this file to config/smtp.php and fill in real credentials.
//  For Gmail: enable 2FA, then generate an App Password at
//  https://myaccount.google.com/apppasswords
// ================================================================

define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_PORT',      587);                          // TLS
define('SMTP_USERNAME',  'your-email@gmail.com');       // sender Gmail address
define('SMTP_PASSWORD',  'your-16-char-app-password');  // Gmail App Password (16-char, no spaces)
define('SMTP_FROM',      'your-email@gmail.com');
define('SMTP_FROM_NAME', 'Opticana Clinic');
