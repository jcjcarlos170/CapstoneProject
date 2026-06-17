<?php
// ================================================================
//  OPTICANA — api/examinations/create.php
//  POST { patientId, apptId?, od, os, iop, pd, diagnosis, ... }
//  → { success:true, id:'E001' }
//  Saves examination, consultation, optional prescription,
//  updates patient last_visit, marks linked appointment completed.
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('POST');
startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}

$role      = $_SESSION['role']       ?? '';
$profileId = $_SESSION['profile_id'] ?? '';

if (!in_array($role, ['admin', 'staff', 'doctor'], true)) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
}

$b = getBody();

$patientId = trim($b['patientId'] ?? '');
$apptId    = trim($b['apptId']    ?? '');

if (!$patientId) {
    jsonResponse(['success' => false, 'message' => 'patientId is required.']);
}

// Determine doctor id/name
if ($role === 'doctor') {
    $doctorId = $profileId;
} else {
    // admin/staff: use doctorId from the linked appointment or the body
    $doctorId = trim($b['doctorId'] ?? '');
}

try {
    $pdo = getDB();

    // Resolve doctor name
    $drRow = null;
    if ($doctorId) {
        $drRow = $pdo->prepare('SELECT first_name, last_name FROM doctors WHERE id = ? LIMIT 1');
        $drRow->execute([$doctorId]);
        $drRow = $drRow->fetch();
    }
    $doctorName = $drRow ? 'Dr. ' . $drRow['first_name'] . ' ' . $drRow['last_name'] : ($b['doctorName'] ?? '');

    // ── Generate exam ID ─────────────────────────────────────────
    $last = $pdo->query("SELECT id FROM examinations ORDER BY id DESC LIMIT 1")->fetchColumn();
    $next = 1;
    if ($last && preg_match('/^E(\d+)$/i', $last, $m)) {
        $next = (int)$m[1] + 1;
    }
    $examId = 'E' . str_pad($next, 3, '0', STR_PAD_LEFT);
    $dup = $pdo->prepare('SELECT id FROM examinations WHERE id = ?');
    while (true) {
        $dup->execute([$examId]);
        if (!$dup->fetch()) break;
        $next++;
        $examId = 'E' . str_pad($next, 3, '0', STR_PAD_LEFT);
    }

    $date = $b['date'] ?? date('Y-m-d');

    $od  = $b['od']  ?? [];
    $os  = $b['os']  ?? [];
    $iop = $b['iop'] ?? [];

    $lensCoating = $b['lensCoating'] ?? [];
    $coatingJson = is_array($lensCoating) ? json_encode($lensCoating) : '[]';

    // ── Insert examination ────────────────────────────────────────
    $pdo->prepare(
        'INSERT INTO examinations
           (id, patient_id, doctor_id, date,
            od_sph, od_cyl, od_axis, od_va, od_add,
            os_sph, os_cyl, os_axis, os_va, os_add,
            iop_od, iop_os, pd,
            diagnosis, recommendation, test_results, prescription_details,
            lens_type, lens_material, lens_coating, frame_selection, remarks, status)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $examId, $patientId, $doctorId ?: null, $date,
        $od['sph']  ?? '', $od['cyl']  ?? '', $od['axis'] ?? '', $od['va']  ?? '', $od['add']  ?? '',
        $os['sph']  ?? '', $os['cyl']  ?? '', $os['axis'] ?? '', $os['va']  ?? '', $os['add']  ?? '',
        $iop['od']  ?? '', $iop['os']  ?? '',
        $b['pd']  ?? '',
        $b['diagnosis']           ?? '',
        $b['recommendation']      ?? '',
        $b['testResults']         ?? '',
        $b['prescriptionDetails'] ?? '',
        $b['lensType']            ?? '',
        $b['lensMaterial']        ?? '',
        $coatingJson,
        $b['frameSelection']      ?? '',
        $b['remarks']             ?? '',
        'completed',
    ]);

    // ── Insert consultation ───────────────────────────────────────
    $lastCon = $pdo->query("SELECT id FROM consultations ORDER BY id DESC LIMIT 1")->fetchColumn();
    $cNext   = 1;
    if ($lastCon && preg_match('/^C(\d+)$/i', $lastCon, $m)) $cNext = (int)$m[1] + 1;
    $conId   = 'C' . str_pad($cNext, 3, '0', STR_PAD_LEFT);

    $rxSummary = '';
    if (!empty($od['sph']) || !empty($os['sph'])) {
        $rxSummary = 'OD: ' . ($od['sph'] ?? '') . ' ' . ($od['cyl'] ?? '') . ' x' . ($od['axis'] ?? '')
                   . ' / OS: ' . ($os['sph'] ?? '') . ' ' . ($os['cyl'] ?? '') . ' x' . ($os['axis'] ?? '');
    }

    $pdo->prepare(
        'INSERT INTO consultations (id, patient_id, doctor_id, date, type, diagnosis, prescription, remarks)
         VALUES (?,?,?,?,?,?,?,?)'
    )->execute([
        $conId, $patientId, $doctorId ?: null, $date,
        'Eye Examination',
        $b['diagnosis']  ?? '',
        $rxSummary,
        $b['remarks']    ?? '',
    ]);

    // ── Insert prescription (if filled) ───────────────────────────
    $hasPrescription = (!empty($od['sph']) || !empty($os['sph'])) && !empty($b['lensType']);
    if ($hasPrescription) {
        $lastRx = $pdo->query("SELECT id FROM prescriptions ORDER BY id DESC LIMIT 1")->fetchColumn();
        $rNext  = 1;
        if ($lastRx && preg_match('/^RX(\d+)$/i', $lastRx, $m)) $rNext = (int)$m[1] + 1;
        $rxId   = 'RX' . str_pad($rNext, 3, '0', STR_PAD_LEFT);

        $pdo->prepare(
            'INSERT INTO prescriptions
               (id, patient_id, doctor_id, date,
                od_sph, od_cyl, od_axis, os_sph, os_cyl, os_axis, lens_type, remarks)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $rxId, $patientId, $doctorId ?: null, $date,
            $od['sph']  ?? '', $od['cyl']  ?? '', $od['axis'] ?? '',
            $os['sph']  ?? '', $os['cyl']  ?? '', $os['axis'] ?? '',
            $b['lensType'] ?? '',
            $b['remarks']  ?? '',
        ]);
    }

    // ── Update patient last_visit ──────────────────────────────────
    $pdo->prepare('UPDATE patients SET last_visit = ? WHERE id = ?')
        ->execute([$date, $patientId]);

    // ── Mark linked appointment as completed ───────────────────────
    if ($apptId) {
        $pdo->prepare(
            'UPDATE appointments SET status = "completed" WHERE id = ?'
        )->execute([$apptId]);
    }

    // ── Activity log ───────────────────────────────────────────────
    $ptRow = $pdo->prepare('SELECT first_name, last_name FROM patients WHERE id = ? LIMIT 1');
    $ptRow->execute([$patientId]);
    $ptRow = $ptRow->fetch();
    $ptName = $ptRow ? $ptRow['first_name'] . ' ' . $ptRow['last_name'] : $patientId;

    $staffName = '';
    switch ($role) {
        case 'doctor':
            $staffName = $doctorName;
            break;
        default:
            $uRow = $pdo->prepare('SELECT u.id, p.first_name, p.last_name FROM users u LEFT JOIN admins p ON p.user_id = u.id WHERE u.id = ? LIMIT 1');
            $uRow->execute([(int)$_SESSION['user_id']]);
            $uRow = $uRow->fetch();
            $staffName = $uRow ? ($uRow['first_name'] . ' ' . $uRow['last_name']) : 'Staff';
    }

    $logId = 'L' . date('YmdHis') . rand(100, 999);
    $pdo->prepare(
        'INSERT IGNORE INTO activity_log (id, user_name, role, action, timestamp, type)
         VALUES (?,?,?,?,NOW(),?)'
    )->execute([
        substr($logId, 0, 20),
        $staffName,
        ucfirst($role),
        "Saved optical examination for {$ptName} ({$patientId})",
        'examination',
    ]);

    jsonResponse(['success' => true, 'id' => $examId]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error. Please try again.'], 500);
}
