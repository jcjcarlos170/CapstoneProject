<?php
// ================================================================
//  CANAOPTICALCLINIC — api/appointments/confirm.php
//  Patient-only. Confirms attendance after a reminder was sent, so the
//  cron's auto-cancel doesn't fire on this appointment.
//  POST { id }
//  → { success:true }
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('POST');
startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}
if (($_SESSION['role'] ?? '') !== 'patient') {
    jsonResponse(['success' => false, 'message' => 'Only patients may confirm their own appointment.'], 403);
}

$patientId = $_SESSION['profile_id'] ?? '';
$b  = getBody();
$id = trim($b['id'] ?? '');

if (!$id) {
    jsonResponse(['success' => false, 'message' => 'id is required.']);
}

try {
    $pdo = getDB();

    $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ? AND patient_id = ? LIMIT 1');
    $stmt->execute([$id, $patientId]);
    $appt = $stmt->fetch();
    if (!$appt) {
        jsonResponse(['success' => false, 'message' => 'Appointment not found.']);
    }
    if ($appt['status'] !== 'approved') {
        jsonResponse(['success' => false, 'message' => 'Only approved appointments can be confirmed.']);
    }
    if (!$appt['reminder_sent_at']) {
        jsonResponse(['success' => false, 'message' => 'There is no pending confirmation for this appointment.']);
    }
    if ($appt['confirmed_at']) {
        jsonResponse(['success' => true]); // already confirmed — idempotent
    }

    $pdo->prepare('UPDATE appointments SET confirmed_at = NOW() WHERE id = ?')->execute([$id]);

    // Let anyone waiting for this exact doctor+date+time slot know the
    // current holder just confirmed attendance. This doesn't remove them
    // from the queue — the slot could still open up later (cancellation,
    // no-show, etc.) — but they shouldn't be left assuming a cancellation
    // might be imminent right when the opposite just happened.
    if ($appt['doctor_id']) {
        $waiters = $pdo->prepare(
            "SELECT patient_id FROM appointment_waitlist
             WHERE doctor_id = ? AND date = ? AND time = ? AND status = 'waiting'"
        );
        $waiters->execute([$appt['doctor_id'], $appt['date'], $appt['time']]);
        $fmtDate = date('M j, Y', strtotime($appt['date']));
        foreach ($waiters->fetchAll() as $w) {
            $ps = $pdo->prepare('SELECT user_id FROM patients WHERE id = ? LIMIT 1');
            $ps->execute([$w['patient_id']]);
            $userId = $ps->fetchColumn();
            if ($userId) {
                createNotification($pdo, (int)$userId, 'info', 'Waitlisted Slot Confirmed',
                    "The {$appt['doctor_name']} appointment on {$fmtDate} at {$appt['time']} you're waitlisted for has just been confirmed by the patient currently holding it. "
                  . "You'll remain on the waitlist and we'll still notify you if the slot opens up."
                );
            }
        }
    }

    jsonResponse(['success' => true]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error. Please try again.'], 500);
}
