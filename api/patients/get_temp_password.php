<?php
// ================================================================
//  OPTICANA — api/patients/get_temp_password.php
//  Admin/Staff only. Checks whether a patient's password is still
//  the auto-generated temp (FirstName@Year). Uses password_verify
//  against the live hash so the result is always accurate.
//  POST { patientId }
//  → { success:true, isTemp:bool, tempPassword?:string }
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('POST');
startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}
if (!in_array($_SESSION['role'], ['admin', 'staff'], true)) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
}

$b  = getBody();
$id = trim($b['patientId'] ?? '');
if (!$id) {
    jsonResponse(['success' => false, 'message' => 'Patient ID is required.']);
}

try {
    $pdo = getDB();

    $s = $pdo->prepare(
        'SELECT p.first_name, p.registered_date, u.password_hash
           FROM patients p
           JOIN users u ON u.id = p.user_id
          WHERE p.id = ? LIMIT 1'
    );
    $s->execute([$id]);
    $row = $s->fetch();

    if (!$row) {
        jsonResponse(['success' => false, 'message' => 'No login account found for this patient.']);
    }

    $year   = substr($row['registered_date'], 0, 4);
    $tempPw = ucfirst(strtolower($row['first_name'])) . '@' . $year;
    $isTemp = password_verify($tempPw, $row['password_hash']);

    $resp = ['success' => true, 'isTemp' => $isTemp];
    if ($isTemp) $resp['tempPassword'] = $tempPw;

    jsonResponse($resp);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error.'], 500);
}
