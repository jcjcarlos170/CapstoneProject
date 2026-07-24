<?php
// ================================================================
//  CANAOPTICALCLINIC — api/stats/public.php
//  GET — public endpoint, no auth required.
//  Returns live clinic stats for the landing page.
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $pdo = getDB();

    $patients = (int)$pdo->query(
        'SELECT COUNT(*) FROM patients WHERE status = "active"'
    )->fetchColumn();

    $doctors = (int)$pdo->query(
        'SELECT COUNT(DISTINCT CONCAT(d.first_name," ",d.last_name))
         FROM doctors d JOIN users u ON u.id = d.user_id
         WHERE d.status = "active" AND u.is_active = 1'
    )->fetchColumn();

    jsonResponse([
        'success'  => true,
        'patients' => $patients,
        'doctors'  => $doctors,
    ]);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'patients' => 0, 'doctors' => 0], 500);
}
