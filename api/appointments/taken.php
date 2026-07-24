<?php
// ================================================================
//  CANAOPTICALCLINIC — api/appointments/taken.php
//  GET ?doctorId=D001&date=2026-07-15
//  → { success:true, taken:[{time:"9:00 AM",duration:30},…], defaultDuration:30 }
//
//  Returns the booked (non-cancelled/disapproved) appointment times
//  for a given doctor on a given date, with each appointment's own
//  service duration (from clinic_services) so the frontend can
//  compute per-slot gap zones correctly.
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('GET');
startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}

$doctorId  = trim($_GET['doctorId']  ?? '');
$date      = trim($_GET['date']      ?? '');
$excludeId = trim($_GET['excludeId'] ?? '');

if (!$doctorId || !$date) {
    jsonResponse(['success' => false, 'message' => 'doctorId and date are required.']);
}

try {
    $pdo = getDB();

    $durStr = $pdo->query(
        'SELECT default_duration FROM clinic_settings WHERE id = 1 LIMIT 1'
    )->fetchColumn();
    preg_match('/(\d+)/', $durStr ?: '30', $dm);
    $defaultDuration = isset($dm[1]) ? (int)$dm[1] : 30;

    // JOIN clinic_services so each booked slot carries its own duration.
    // COALESCE falls back to the clinic-wide default if the service name
    // doesn't match (renamed service, legacy data, etc.).
    // excludeId lets the reschedule picker omit the appointment being moved
    // so its own current slot does not show as taken.
    $sql = "SELECT a.time, COALESCE(cs.duration, :def) AS duration
            FROM appointments a
            LEFT JOIN clinic_services cs ON cs.name = a.type
            WHERE a.doctor_id = :doc AND a.date = :date
              AND a.status NOT IN ('cancelled','disapproved')"
         . ($excludeId ? ' AND a.id != :excl' : '');
    $stmt = $pdo->prepare($sql);
    $params = [':def' => $defaultDuration, ':doc' => $doctorId, ':date' => $date];
    if ($excludeId) $params[':excl'] = $excludeId;
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $taken = array_map(fn($r) => [
        'time'     => $r['time'],
        'duration' => (int)$r['duration'],
    ], $rows);

    jsonResponse(['success' => true, 'taken' => array_values($taken), 'defaultDuration' => $defaultDuration]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error.'], 500);
}
