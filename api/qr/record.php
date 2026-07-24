<?php
// ================================================================
//  CANAOPTICALCLINIC — api/qr/record.php
//  POST { found, patientId } — logs one QR scan attempt.
//  → 200 { success:true } | { success:false, message }
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('POST');
startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}

$body      = getBody();
$found     = !empty($body['found']);
$patientId = trim($body['patientId'] ?? '') ?: null;

try {
    $pdo = getDB();
    $pdo->prepare('INSERT INTO qr_scan_log (scanned_by, patient_id, found) VALUES (?, ?, ?)')
        ->execute([(int)$_SESSION['user_id'], $patientId, $found ? 1 : 0]);
    jsonResponse(['success' => true]);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error. Please try again.'], 500);
}
