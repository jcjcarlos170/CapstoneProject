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
