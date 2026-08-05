<?php
// ================================================================
//  CANAOPTICALCLINIC — api/waitlist/index.php
//  GET → patient: their own active (waiting/offered) waitlist entry, if any.
//        admin/staff: every active entry across all patients.
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

function mapWaitlistRow(array $r): array {
    return [
        'id'             => (int)$r['id'],
        'patientId'      => $r['patient_id'],
        'patientName'    => $r['patient_name'] ?? '',
        'doctorId'       => $r['doctor_id'],
        'doctorName'     => $r['doctor_name'] ?? '',
        'date'           => $r['date'],
        'time'           => $r['time'],
        'type'           => $r['type'] ?? '',
        'status'         => $r['status'],
        'offeredAt'      => $r['offered_at'],
        'offerExpiresAt' => $r['offer_expires_at'],
        'createdAt'      => $r['created_at'],
    ];
}

try {
    $pdo = getDB();

    if ($role === 'patient') {
        $profileId = $_SESSION['profile_id'] ?? '';
        $stmt = $pdo->prepare(
            "SELECT * FROM appointment_waitlist WHERE patient_id = ? AND status IN ('waiting','offered') LIMIT 1"
        );
        $stmt->execute([$profileId]);
        $row = $stmt->fetch();
        jsonResponse(['success' => true, 'entry' => $row ? mapWaitlistRow($row) : null]);
    } elseif (in_array($role, ['admin', 'staff'], true)) {
        $rows = $pdo->query(
            "SELECT * FROM appointment_waitlist WHERE status IN ('waiting','offered')
             ORDER BY date ASC, time ASC, created_at ASC"
        )->fetchAll();
        jsonResponse(['success' => true, 'entries' => array_map('mapWaitlistRow', $rows)]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
    }

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error.'], 500);
}
