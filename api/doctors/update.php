<?php
// ================================================================
//  OPTICANA — api/doctors/update.php
//  POST { doctorId, days:[], workHours, available } — admin/staff only.
//  Updates a doctor's weekly availability pattern (which days they
//  work, their hours, and whether they're marked available at all).
//  Distinct from blocked_dates, which handles one-off date blocks.
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('POST');
startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}
if (!in_array($_SESSION['role'] ?? '', ['admin', 'staff'], true)) {
    jsonResponse(['success' => false, 'message' => 'Only admin or staff may edit doctor schedules.'], 403);
}

$b         = getBody();
$doctorId  = trim($b['doctorId'] ?? '');
$workHours = trim($b['workHours'] ?? '');
$available = !empty($b['available']);
$days      = is_array($b['days'] ?? null) ? $b['days'] : [];

$validDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$days      = array_values(array_intersect($days, $validDays));

if (!$doctorId) {
    jsonResponse(['success' => false, 'message' => 'doctorId is required.']);
}

try {
    $pdo = getDB();

    $chk = $pdo->prepare('SELECT id FROM doctors WHERE id = ? LIMIT 1');
    $chk->execute([$doctorId]);
    if (!$chk->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Doctor not found.'], 404);
    }

    $pdo->beginTransaction();

    $pdo->prepare('UPDATE doctors SET available = ?, work_hours = ? WHERE id = ?')
        ->execute([$available ? 1 : 0, $workHours ?: null, $doctorId]);

    $pdo->prepare('DELETE FROM doctor_days WHERE doctor_id = ?')->execute([$doctorId]);
    if ($days) {
        $ins = $pdo->prepare('INSERT INTO doctor_days (doctor_id, day_of_week) VALUES (?, ?)');
        foreach ($days as $day) {
            $ins->execute([$doctorId, $day]);
        }
    }

    $pdo->commit();

    jsonResponse(['success' => true]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(['success' => false, 'message' => 'Database error. Please try again.'], 500);
}
