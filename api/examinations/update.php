<?php
// ================================================================
//  CANAOPTICALCLINIC — api/examinations/update.php
//  POST { examId, patientId, od, os, iop, pd, diagnosis, ... }
//  → { success:true, id:'E001' } | { success:false, message }
//  Updates an existing examination record in place. Unlike create.php,
//  this does NOT insert new consultation/prescription rows — there's no
//  exam_id column linking those tables back to a specific exam, so there's
//  no reliable way to find "the ones this exam originally created" to
//  update instead of duplicating them.
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('POST');
startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['doctor'], true)) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
}

$b = getBody();

$examId    = trim($b['examId']    ?? '');
$patientId = trim($b['patientId'] ?? '');

if (!$examId || !$patientId) {
    jsonResponse(['success' => false, 'message' => 'examId and patientId are required.']);
}

try {
    $pdo = getDB();

    $exists = $pdo->prepare('SELECT id FROM examinations WHERE id = ? AND patient_id = ?');
    $exists->execute([$examId, $patientId]);
    if (!$exists->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Examination record not found.'], 404);
    }

    $od  = $b['od']  ?? [];
    $os  = $b['os']  ?? [];
    $iop = $b['iop'] ?? [];

    $lensCoating = $b['lensCoating'] ?? [];
    $coatingJson = is_array($lensCoating) ? json_encode($lensCoating) : '[]';

    $date = $b['date'] ?? date('Y-m-d');

    $pdo->prepare(
        'UPDATE examinations SET
            date = ?,
            od_sph = ?, od_cyl = ?, od_axis = ?, od_va = ?, od_add = ?,
            os_sph = ?, os_cyl = ?, os_axis = ?, os_va = ?, os_add = ?,
            iop_od = ?, iop_os = ?, pd = ?,
            diagnosis = ?, recommendation = ?, test_results = ?, prescription_details = ?,
            lens_type = ?, lens_material = ?, lens_coating = ?, frame_selection = ?, remarks = ?
         WHERE id = ? AND patient_id = ?'
    )->execute([
        $date,
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
        $examId, $patientId,
    ]);

    // ── Update patient last_visit (an edit still counts as the most recent touch) ──
    $pdo->prepare('UPDATE patients SET last_visit = ? WHERE id = ?')
        ->execute([$date, $patientId]);

    // ── Activity log ───────────────────────────────────────────────
    $ptRow = $pdo->prepare('SELECT first_name, last_name FROM patients WHERE id = ? LIMIT 1');
    $ptRow->execute([$patientId]);
    $ptRow = $ptRow->fetch();
    $ptName = $ptRow ? $ptRow['first_name'] . ' ' . $ptRow['last_name'] : $patientId;

    $drRow = $pdo->prepare('SELECT first_name, last_name FROM doctors WHERE id = ? LIMIT 1');
    $drRow->execute([$_SESSION['profile_id'] ?? '']);
    $drRow = $drRow->fetch();
    $doctorName = $drRow ? 'Dr. ' . $drRow['first_name'] . ' ' . $drRow['last_name'] : '';

    $logId = 'L' . date('YmdHis') . rand(100, 999);
    $pdo->prepare(
        'INSERT IGNORE INTO activity_log (id, user_name, role, action, timestamp, type)
         VALUES (?,?,?,?,NOW(),?)'
    )->execute([
        substr($logId, 0, 20),
        $doctorName,
        ucfirst($role),
        "Updated optical examination {$examId} for {$ptName} ({$patientId})",
        'examination',
    ]);

    jsonResponse(['success' => true, 'id' => $examId]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error. Please try again.'], 500);
}
