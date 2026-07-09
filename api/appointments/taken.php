<?php
// ================================================================
//  OPTICANA — api/appointments/taken.php
//  GET ?doctorId=D001&date=2026-07-15
//  → { success:true, taken:["9:00 AM","2:00 PM"], duration:30 }
//
//  Returns the booked (non-cancelled/disapproved) appointment times
//  for a given doctor on a given date, plus the clinic's default
//  appointment duration so the frontend can compute the gap zone
//  around each booked slot.
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('GET');
startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}

$doctorId = trim($_GET['doctorId'] ?? '');
$date     = trim($_GET['date']     ?? '');

if (!$doctorId || !$date) {
    jsonResponse(['success' => false, 'message' => 'doctorId and date are required.']);
}

try {
    $pdo = getDB();

    $durStr = $pdo->query(
        'SELECT default_duration FROM clinic_settings WHERE id = 1 LIMIT 1'
    )->fetchColumn();
    preg_match('/(\d+)/', $durStr ?: '30', $dm);
    $duration = isset($dm[1]) ? (int)$dm[1] : 30;

    $stmt = $pdo->prepare(
        "SELECT time FROM appointments
         WHERE doctor_id = ? AND date = ? AND status NOT IN ('cancelled','disapproved')"
    );
    $stmt->execute([$doctorId, $date]);
    $taken = $stmt->fetchAll(PDO::FETCH_COLUMN);

    jsonResponse(['success' => true, 'taken' => array_values($taken), 'duration' => $duration]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error.'], 500);
}
