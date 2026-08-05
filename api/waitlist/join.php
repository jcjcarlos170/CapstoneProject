<?php
// ================================================================
//  CANAOPTICALCLINIC — api/waitlist/join.php
//  Patient-only. Joins the waitlist for an exact doctor+date+time slot
//  that was unavailable at booking time.
//  POST { doctorId, doctorName, date, time, type, termsAgreed }
//  → { success:true, id:<int> }
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('POST');
startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}
if (($_SESSION['role'] ?? '') !== 'patient') {
    jsonResponse(['success' => false, 'message' => 'Only patients may join the waitlist.'], 403);
}

$patientId = $_SESSION['profile_id'] ?? '';
$b = getBody();

$doctorId    = trim($b['doctorId']    ?? '');
$doctorName  = trim($b['doctorName']  ?? '');
$date        = trim($b['date']        ?? '');
$time        = trim($b['time']        ?? '');
$type        = trim($b['type']        ?? 'Eye Examination');
$termsAgreed = !empty($b['termsAgreed']);

if (!$doctorId || !$date || !$time) {
    jsonResponse(['success' => false, 'message' => 'Doctor, date and time are required.']);
}
if (!$termsAgreed) {
    jsonResponse(['success' => false, 'message' => 'Please agree to the appointment policy before joining the waitlist.']);
}

try {
    $pdo = getDB();

    // Same restriction gates as create.php — waitlisting can't be used to
    // route around them.
    $restricted = $pdo->prepare('SELECT booking_restricted FROM patients WHERE id = ? LIMIT 1');
    $restricted->execute([$patientId]);
    if ((int)$restricted->fetchColumn() === 1) {
        jsonResponse(['success' => false, 'message' =>
            'Online booking is currently unavailable for your account due to repeated missed appointments. Please contact the clinic directly to schedule.']);
    }

    $minDays = minAdvanceBookingDays($pdo);
    $today   = new DateTime('today');
    $reqDate = new DateTime($date);
    $daysOut = (int)$today->diff($reqDate)->format('%r%a');
    if ($daysOut < $minDays) {
        jsonResponse(['success' => false, 'message' => 'Please select a valid date.']);
    }

    $bs = $pdo->prepare('SELECT reason FROM blocked_dates WHERE doctor_id = ? AND date = ? LIMIT 1');
    $bs->execute([$doctorId, $date]);
    if ($bs->fetch()) {
        jsonResponse(['success' => false, 'message' => 'This doctor is unavailable on the selected date.']);
    }

    // One active waitlist entry per patient, app-wide.
    $existing = $pdo->prepare(
        "SELECT id FROM appointment_waitlist WHERE patient_id = ? AND status IN ('waiting','offered') LIMIT 1"
    );
    $existing->execute([$patientId]);
    if ($existing->fetch()) {
        jsonResponse(['success' => false, 'message' =>
            "You're already on a waitlist. Please leave that one before joining another."]);
    }

    // Re-verify the slot is genuinely unavailable — a stale frontend
    // shouldn't be able to waitlist an actually-open slot.
    $durStr = $pdo->query('SELECT default_duration FROM clinic_settings WHERE id = 1 LIMIT 1')->fetchColumn();
    preg_match('/(\d+)/', $durStr ?: '30', $dm);
    $durationMin = isset($dm[1]) ? (int)$dm[1] : 30;
    $conflict = checkApptConflict($pdo, $doctorId, $date, $time, $durationMin);
    $held     = checkWaitlistHold($pdo, $doctorId, $date, $time, $patientId);
    if ($conflict === null && !$held) {
        jsonResponse(['success' => false, 'message' => 'This slot is actually available — please book it directly.']);
    }

    $patient = $pdo->prepare('SELECT CONCAT(first_name, " ", last_name) AS name FROM patients WHERE id = ? LIMIT 1');
    $patient->execute([$patientId]);
    $patientName = $patient->fetchColumn() ?: '';

    $pdo->prepare(
        'INSERT INTO appointment_waitlist
           (patient_id, patient_name, doctor_id, doctor_name, date, time, type, terms_agreed)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
    )->execute([$patientId, $patientName, $doctorId, $doctorName, $date, $time, $type]);

    jsonResponse(['success' => true, 'id' => (int)$pdo->lastInsertId()]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error. Please try again.'], 500);
}
