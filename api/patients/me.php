<?php
// ================================================================
//  CANAOPTICALCLINIC — api/patients/me.php
//  GET → returns the logged-in patient's own examinations,
//        prescriptions, and consultations.
//  Accessible only by the 'patient' role.
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('GET');
startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}

$role      = $_SESSION['role']       ?? '';
$profileId = $_SESSION['profile_id'] ?? '';

if ($role !== 'patient' || !$profileId) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
}

try {
    $pdo = getDB();

    // ── Examinations ──────────────────────────────────────────────
    $examStmt = $pdo->prepare(
        'SELECT e.*,
                CONCAT("Dr. ", d.first_name, " ", d.last_name) AS doctor_name
         FROM examinations e
         LEFT JOIN doctors d ON d.id = e.doctor_id
         WHERE e.patient_id = ?
         ORDER BY e.date DESC'
    );
    $examStmt->execute([$profileId]);
    $examRows = $examStmt->fetchAll();

    $examinations = array_map(fn($e) => [
        'id'                  => $e['id'],
        'date'                => $e['date'],
        'doctor'              => $e['doctor_name'] ?? '',
        'od'                  => ['sph' => $e['od_sph'] ?? '', 'cyl' => $e['od_cyl'] ?? '', 'axis' => $e['od_axis'] ?? '', 'va' => $e['od_va'] ?? '', 'add' => $e['od_add'] ?? ''],
        'os'                  => ['sph' => $e['os_sph'] ?? '', 'cyl' => $e['os_cyl'] ?? '', 'axis' => $e['os_axis'] ?? '', 'va' => $e['os_va'] ?? '', 'add' => $e['os_add'] ?? ''],
        'iop'                 => ['od' => $e['iop_od'] ?? '', 'os' => $e['iop_os'] ?? ''],
        'pd'                  => $e['pd'] ?? '',
        'diagnosis'           => $e['diagnosis'] ?? '',
        'recommendation'      => $e['recommendation'] ?? '',
        'testResults'         => $e['test_results'] ?? '',
        'prescriptionDetails' => $e['prescription_details'] ?? '',
        'lensType'            => $e['lens_type'] ?? '',
        'lensMaterial'        => $e['lens_material'] ?? '',
        'lensCoating'         => $e['lens_coating'] ? (json_decode($e['lens_coating'], true) ?? []) : [],
        'frameSelection'      => $e['frame_selection'] ?? '',
        'remarks'             => $e['remarks'] ?? '',
        'status'              => $e['status'] ?? 'completed',
    ], $examRows);

    // ── Prescriptions ─────────────────────────────────────────────
    $rxStmt = $pdo->prepare(
        'SELECT rx.*,
                CONCAT("Dr. ", d.first_name, " ", d.last_name) AS doctor_name
         FROM prescriptions rx
         LEFT JOIN doctors d ON d.id = rx.doctor_id
         WHERE rx.patient_id = ?
         ORDER BY rx.date DESC'
    );
    $rxStmt->execute([$profileId]);
    $rxRows = $rxStmt->fetchAll();

    $prescriptions = array_map(fn($rx) => [
        'id'       => $rx['id'],
        'date'     => $rx['date'],
        'doctor'   => $rx['doctor_name'] ?? '',
        'od'       => ['sph' => $rx['od_sph'] ?? '', 'cyl' => $rx['od_cyl'] ?? '', 'axis' => $rx['od_axis'] ?? ''],
        'os'       => ['sph' => $rx['os_sph'] ?? '', 'cyl' => $rx['os_cyl'] ?? '', 'axis' => $rx['os_axis'] ?? ''],
        'lensType' => $rx['lens_type'] ?? '',
        'remarks'  => $rx['remarks']   ?? '',
    ], $rxRows);

    // ── Consultations ─────────────────────────────────────────────
    $conStmt = $pdo->prepare(
        'SELECT c.*,
                CONCAT("Dr. ", d.first_name, " ", d.last_name) AS doctor_name
         FROM consultations c
         LEFT JOIN doctors d ON d.id = c.doctor_id
         WHERE c.patient_id = ?
         ORDER BY c.date DESC'
    );
    $conStmt->execute([$profileId]);
    $conRows = $conStmt->fetchAll();

    $consultations = array_map(fn($c) => [
        'id'           => $c['id'],
        'date'         => $c['date'],
        'doctor'       => $c['doctor_name'] ?? '',
        'type'         => $c['type']         ?? '',
        'diagnosis'    => $c['diagnosis']    ?? '',
        'prescription' => $c['prescription'] ?? '',
        'remarks'      => $c['remarks']      ?? '',
    ], $conRows);

    jsonResponse([
        'success'       => true,
        'examinations'  => $examinations,
        'prescriptions' => $prescriptions,
        'consultations' => $consultations,
    ]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error.'], 500);
}
