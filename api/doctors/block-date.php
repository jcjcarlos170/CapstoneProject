<?php
// ================================================================
//  CANAOPTICALCLINIC — api/doctors/block-date.php
//  POST { doctorId, date, reason } — admin/staff only.
//  Marks a single date as unavailable for a doctor (leave, conference,
//  holiday, etc.) — separate from their recurring weekly schedule.
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('POST');
startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}
if (!in_array($_SESSION['role'] ?? '', ['admin', 'staff'], true)) {
    jsonResponse(['success' => false, 'message' => 'Only admin or staff may block dates.'], 403);
}

$b         = getBody();
$doctorId  = trim($b['doctorId']  ?? '');
$date      = trim($b['date']      ?? '');
$reason    = trim($b['reason']    ?? '');
$blockedBy = trim($b['blockedBy'] ?? '') ?: 'Staff';

if (!$doctorId || !$date) {
    jsonResponse(['success' => false, 'message' => 'doctorId and date are required.']);
}

$d = DateTime::createFromFormat('Y-m-d', $date);
if (!$d || $d->format('Y-m-d') !== $date) {
    jsonResponse(['success' => false, 'message' => 'Invalid date format.']);
}

try {
    $pdo = getDB();

    $chk = $pdo->prepare('SELECT id FROM doctors WHERE id = ? LIMIT 1');
    $chk->execute([$doctorId]);
    if (!$chk->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Doctor not found.'], 404);
    }

    $s = $pdo->prepare(
        'INSERT INTO blocked_dates (doctor_id, date, reason, created_by) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE reason = VALUES(reason)'
    );
    $s->execute([$doctorId, $date, $reason ?: null, $blockedBy]);

    jsonResponse(['success' => true]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error. Please try again.'], 500);
}
