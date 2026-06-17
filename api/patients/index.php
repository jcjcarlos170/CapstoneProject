<?php
// ================================================================
//  OPTICANA — api/patients/index.php
//  GET → returns all patients (admin/staff/doctor only)
//  Each patient includes their examinations, prescriptions,
//  and consultations so the front-end can work offline.
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin', 'staff', 'doctor'], true)) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
}

try {
    $pdo = getDB();

    // ── Patients ──────────────────────────────────────────────────
    $ptRows = $pdo->query(
        'SELECT p.*, u.email
         FROM patients p
         LEFT JOIN users u ON u.id = p.user_id
         WHERE p.archived_at IS NULL
         ORDER BY p.last_name, p.first_name'
    )->fetchAll();

    // Fetch photo_url separately; gracefully handles pre-migration databases
    $photoMap = [];
    try {
        $photoRows = $pdo->query('SELECT id, photo_url FROM users WHERE photo_url IS NOT NULL')->fetchAll();
        foreach ($photoRows as $pr) {
            $photoMap[(int)$pr['id']] = $pr['photo_url'];
        }
    } catch (PDOException) { /* column may not exist yet — skip */ }

    // ── Examinations ──────────────────────────────────────────────
    $examRows = $pdo->query(
        'SELECT e.*,
                CONCAT("Dr. ", d.first_name, " ", d.last_name) AS doctor_name
         FROM examinations e
         LEFT JOIN doctors d ON d.id = e.doctor_id
         ORDER BY e.date DESC'
    )->fetchAll();

    // ── Prescriptions ─────────────────────────────────────────────
    $rxRows = $pdo->query(
        'SELECT rx.*,
                CONCAT("Dr. ", d.first_name, " ", d.last_name) AS doctor_name
         FROM prescriptions rx
         LEFT JOIN doctors d ON d.id = rx.doctor_id
         ORDER BY rx.date DESC'
    )->fetchAll();

    // ── Consultations ─────────────────────────────────────────────
    $conRows = $pdo->query(
        'SELECT c.*,
                CONCAT("Dr. ", d.first_name, " ", d.last_name) AS doctor_name
         FROM consultations c
         LEFT JOIN doctors d ON d.id = c.doctor_id
         ORDER BY c.date DESC'
    )->fetchAll();

    // ── Group sub-records by patient_id ───────────────────────────
    $examsByPt = [];
    foreach ($examRows as $e) {
        $pid = $e['patient_id'];
        $examsByPt[$pid][] = [
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
        ];
    }

    $rxByPt = [];
    foreach ($rxRows as $rx) {
        $pid = $rx['patient_id'];
        $rxByPt[$pid][] = [
            'id'       => $rx['id'],
            'date'     => $rx['date'],
            'doctor'   => $rx['doctor_name'] ?? '',
            'od'       => ['sph' => $rx['od_sph'] ?? '', 'cyl' => $rx['od_cyl'] ?? '', 'axis' => $rx['od_axis'] ?? ''],
            'os'       => ['sph' => $rx['os_sph'] ?? '', 'cyl' => $rx['os_cyl'] ?? '', 'axis' => $rx['os_axis'] ?? ''],
            'lensType' => $rx['lens_type'] ?? '',
            'remarks'  => $rx['remarks'] ?? '',
        ];
    }

    $conByPt = [];
    foreach ($conRows as $c) {
        $pid = $c['patient_id'];
        $conByPt[$pid][] = [
            'id'           => $c['id'],
            'date'         => $c['date'],
            'doctor'       => $c['doctor_name'] ?? '',
            'type'         => $c['type'] ?? '',
            'diagnosis'    => $c['diagnosis'] ?? '',
            'prescription' => $c['prescription'] ?? '',
            'remarks'      => $c['remarks'] ?? '',
        ];
    }

    // ── Build patient objects ─────────────────────────────────────
    $result = array_map(function ($p) use ($examsByPt, $rxByPt, $conByPt, $photoMap) {
        $pid = $p['id'];
        return [
            'id'             => $pid,
            'firstName'      => $p['first_name'],
            'lastName'       => $p['last_name'],
            'name'           => $p['first_name'] . ' ' . $p['last_name'],
            'email'          => $p['email'] ?? '',
            'gender'         => $p['gender'] ?? '',
            'dob'            => $p['dob'] ?? '',
            'age'            => (int)($p['age'] ?? 0),
            'contact'        => $p['contact'] ?? '',
            'address'        => $p['address'] ?? '',
            'bloodType'      => $p['blood_type'] ?? '',
            'occupation'     => $p['occupation'] ?? '',
            'medicalHistory' => $p['medical_history'] ?? '',
            'opticalHistory' => $p['optical_history'] ?? '',
            'qrData'         => $p['qr_data'] ?? '',
            'registeredDate' => $p['registered_date'] ?? '',
            'lastVisit'      => $p['last_visit'] ?: '—',
            'status'         => $p['status'] ?? 'active',
            'photoUrl'       => $photoMap[(int)($p['user_id'] ?? 0)] ?? null,
            'examinations'   => $examsByPt[$pid] ?? [],
            'prescriptions'  => $rxByPt[$pid]   ?? [],
            'consultations'  => $conByPt[$pid]   ?? [],
        ];
    }, $ptRows);

    jsonResponse(['success' => true, 'patients' => $result]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error.'], 500);
}
