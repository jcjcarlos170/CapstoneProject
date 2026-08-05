<?php
// ================================================================
//  CANAOPTICALCLINIC — api/appointments/create.php
//  POST { patientId, patientName, doctorId, doctorName,
//         date, time, type, status, notes, termsAgreed }
//  → { success:true, id:'A001' }
//  → { success:false, message, waitlistAvailable:true, doctorId, doctorName,
//      date, time, type } when the slot is taken/held and the caller is a
//      patient — frontend offers api/waitlist/join.php for this exact slot.
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('POST');
startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}

$role = $_SESSION['role'] ?? '';
// Patients book their own via requestAppointment; admin/staff create for any patient
if (!in_array($role, ['admin', 'staff', 'patient'], true)) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
}

$b = getBody();

$patientId   = trim($b['patientId']   ?? '');
$patientName = trim($b['patientName'] ?? '');
$doctorId    = trim($b['doctorId']    ?? '');
$doctorName  = trim($b['doctorName']  ?? '');
$date        = trim($b['date']        ?? '');
$time        = trim($b['time']        ?? '');
$type        = trim($b['type']        ?? 'Eye Examination');
$notes       = trim($b['notes']       ?? '');
$status      = trim($b['status']      ?? 'pending');
$termsAgreed = !empty($b['termsAgreed']);

// Patient can only book for themselves
if ($role === 'patient') {
    $profileId = $_SESSION['profile_id'] ?? '';
    $patientId = $profileId;
    $status    = 'pending';

    if (!$termsAgreed) {
        jsonResponse(['success' => false, 'message' => 'Please agree to the appointment policy before submitting.']);
    }
}

if (!$patientId || !$doctorId || !$date || !$time) {
    jsonResponse(['success' => false, 'message' => 'Patient, doctor, date and time are required.']);
}

// Validate status
$allowedStatus = ['pending', 'approved', 'cancelled', 'disapproved', 'completed'];
if (!in_array($status, $allowedStatus, true)) $status = 'pending';

try {
    $pdo = getDB();

    // Patients with repeated no-shows lose self-service booking — they have
    // to go through the clinic directly instead. Checked first since there's
    // no point validating anything else if this blocks the request outright.
    if ($role === 'patient') {
        $restricted = $pdo->prepare('SELECT booking_restricted FROM patients WHERE id = ? LIMIT 1');
        $restricted->execute([$patientId]);
        if ((int)$restricted->fetchColumn() === 1) {
            jsonResponse(['success' => false, 'message' =>
                'Online booking is currently unavailable for your account due to repeated missed appointments. Please contact the clinic directly to schedule.']);
        }
    }

    // Enforce the clinic's minimum-advance-booking rule for self-service patient
    // bookings (admin/staff retain discretion to schedule same-day walk-ins).
    if ($role === 'patient') {
        $minDays = minAdvanceBookingDays($pdo);
        $today   = new DateTime('today');
        $reqDate = new DateTime($date);
        $daysOut = (int)$today->diff($reqDate)->format('%r%a');
        if ($daysOut < $minDays) {
            $msg = $minDays === 0
                ? 'Please select a valid date.'
                : "Appointments must be booked at least {$minDays} day" . ($minDays > 1 ? 's' : '') . ' in advance.';
            jsonResponse(['success' => false, 'message' => $msg]);
        }

        // Reject self-service bookings on a date the doctor has explicitly
        // blocked, even if a stale frontend calendar let the request through.
        // Admin/staff keep discretion to book anyway (e.g. squeezing in an
        // urgent case), so this check is patient-only.
        $bs = $pdo->prepare('SELECT reason FROM blocked_dates WHERE doctor_id = ? AND date = ? LIMIT 1');
        $bs->execute([$doctorId, $date]);
        $blockedRow = $bs->fetch();
        if ($blockedRow) {
            jsonResponse(['success' => false, 'message' =>
                'This doctor is unavailable on the selected date' . ($blockedRow['reason'] ? " ({$blockedRow['reason']})" : '') . '. Please choose another date.']);
        }

        // Enforce the clinic's max-appointments-per-doctor-per-day cap for
        // self-service patient bookings — cancelled/disapproved slots don't
        // count against it since they're not actually occupying the doctor's day.
        $maxPerDay = (int)($pdo->query('SELECT max_appts_per_doctor_per_day FROM clinic_settings WHERE id = 1 LIMIT 1')->fetchColumn() ?: 12);
        $cs = $pdo->prepare(
            "SELECT COUNT(*) FROM appointments
             WHERE doctor_id = ? AND date = ? AND status NOT IN ('cancelled','disapproved')"
        );
        $cs->execute([$doctorId, $date]);
        if ((int)$cs->fetchColumn() >= $maxPerDay) {
            jsonResponse(['success' => false, 'message' =>
                'This doctor is fully booked on the selected date. Please choose another date or doctor.']);
        }
    }

    // Reject if the requested time conflicts with an existing appointment
    // (same doctor, same date, within the clinic's default appointment duration).
    // Applies to all roles — admin/staff bookings are equally subject to gaps.
    $durStr = $pdo->query('SELECT default_duration FROM clinic_settings WHERE id = 1 LIMIT 1')->fetchColumn();
    preg_match('/(\d+)/', $durStr ?: '30', $dm);
    $durationMin = isset($dm[1]) ? (int)$dm[1] : 30;

    $conflict    = checkApptConflict($pdo, $doctorId, $date, $time, $durationMin);
    $held        = checkWaitlistHold($pdo, $doctorId, $date, $time, $patientId);
    if ($conflict !== null || $held) {
        $message = $conflict !== null
            ? "This time conflicts with an existing appointment at {$conflict}. Please choose a different slot."
            : "This slot is currently reserved for another patient. Please choose a different slot.";
        $response = ['success' => false, 'message' => $message];
        // Only patients booking for themselves get offered the waitlist —
        // admin/staff scheduling on a patient's behalf handle this by phone.
        if ($role === 'patient') {
            $response['waitlistAvailable'] = true;
            $response['doctorId']   = $doctorId;
            $response['doctorName'] = $doctorName;
            $response['date']       = $date;
            $response['time']       = $time;
            $response['type']       = $type;
        }
        jsonResponse($response);
    }

    $newId = createAppointmentRecord($pdo, [
        'patientId' => $patientId, 'patientName' => $patientName,
        'doctorId'  => $doctorId,  'doctorName'  => $doctorName,
        'date'      => $date,      'time'        => $time,
        'type'      => $type,      'status'      => $status,
        'notes'     => $notes,     'termsAgreed' => $termsAgreed,
    ]);

    // Send notifications about the new appointment
    $fmtDate = date('M j, Y', strtotime($date));
    if ($role === 'patient') {
        // Patient booked — notify admin/staff
        notifyAdminStaff($pdo, 'new_appointment',
            'New Appointment Request',
            "{$patientName} has requested an appointment with {$doctorName} on {$fmtDate} at {$time}."
        );
    } else {
        // Admin/staff booked — notify patient
        $ps = $pdo->prepare('SELECT user_id FROM patients WHERE id = ? LIMIT 1');
        $ps->execute([$patientId]);
        $pRow = $ps->fetch();
        if ($pRow && $pRow['user_id']) {
            createNotification($pdo, (int)$pRow['user_id'], 'info',
                'Appointment Scheduled',
                "An appointment with {$doctorName} has been scheduled for you on {$fmtDate} at {$time}."
            );
        }
    }

    jsonResponse(['success' => true, 'id' => $newId]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error. Please try again.'], 500);
}
