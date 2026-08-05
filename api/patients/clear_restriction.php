<?php
// ================================================================
//  CANAOPTICALCLINIC — api/patients/clear_restriction.php
//  Admin/Staff only. Lifts the online-booking restriction applied
//  after repeated no-shows (see helpers.php's recordNoShow()).
//  The no-show count itself is left intact as a historical record —
//  only the restriction flag is cleared, so a fresh no-show still
//  re-restricts them immediately per the same threshold.
//
//  POST { id }
//  → { success:true } | { success:false, message }
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
$id = trim($b['id'] ?? '');
if (!$id) {
    jsonResponse(['success' => false, 'message' => 'Patient id is required.']);
}

try {
    $pdo = getDB();

    $stmt = $pdo->prepare('SELECT id FROM patients WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Patient not found.'], 404);
    }

    $pdo->prepare('UPDATE patients SET booking_restricted = 0 WHERE id = ?')->execute([$id]);

    jsonResponse(['success' => true]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error. Please try again.'], 500);
}
