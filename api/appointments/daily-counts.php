<?php
// ================================================================
//  CANAOPTICALCLINIC — api/appointments/daily-counts.php
//  GET ?date=2026-08-05
//  → { success:true, counts: { "D001": 3, "D005": 14, ... } }
//
//  Aggregate per-doctor appointment counts for a single date — how many
//  non-cancelled/disapproved appointments each doctor already has, so the
//  booking wizard can grey out a doctor who has hit the clinic's
//  max-appointments-per-doctor-per-day cap *before* the patient picks them,
//  not just get bounced by create.php after filling out the whole form.
//
//  Deliberately just counts, no patient/appointment details — safe for any
//  authenticated role (patients only ever see their own individual
//  appointments via appointments/index.php, which isn't enough on its own
//  to know a doctor's true daily load across every other patient).
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('GET');
startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}

$date = trim($_GET['date'] ?? '');
if (!$date) {
    jsonResponse(['success' => false, 'message' => 'date is required.']);
}

try {
    $pdo = getDB();

    $stmt = $pdo->prepare(
        "SELECT doctor_id, COUNT(*) AS cnt FROM appointments
         WHERE date = ? AND status NOT IN ('cancelled','disapproved')
         GROUP BY doctor_id"
    );
    $stmt->execute([$date]);

    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $counts[$row['doctor_id']] = (int)$row['cnt'];
    }

    jsonResponse(['success' => true, 'counts' => $counts]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error.'], 500);
}
