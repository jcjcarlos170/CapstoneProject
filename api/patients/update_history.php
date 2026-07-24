<?php
// ================================================================
//  CANAOPTICALCLINIC — api/patients/update_history.php
//  Admin, Staff, and Doctor can update a patient's medical/optical
//  history (e.g. after an examination reveals new information).
//  POST { patientId, medicalHistory?, opticalHistory? }
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('POST');
startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}
if (!in_array($_SESSION['role'], ['admin', 'staff', 'doctor'], true)) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
}

$b  = getBody();
$id = trim($b['patientId'] ?? '');
if (!$id) {
    jsonResponse(['success' => false, 'message' => 'Patient ID is required.']);
}

$medical = isset($b['medicalHistory']) ? $b['medicalHistory'] : null;
$optical = isset($b['opticalHistory']) ? $b['opticalHistory'] : null;

if ($medical === null && $optical === null) {
    jsonResponse(['success' => true]);
}

try {
    $pdo  = getDB();
    $sets = [];
    $vals = [];
    if ($medical !== null) { $sets[] = 'medical_history = ?'; $vals[] = $medical; }
    if ($optical !== null) { $sets[] = 'optical_history = ?'; $vals[] = $optical; }
    $vals[] = $id;
    $pdo->prepare('UPDATE patients SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
    jsonResponse(['success' => true]);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error.'], 500);
}
