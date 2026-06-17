<?php
// ================================================================
//  OPTICANA — api/doctors/public.php
//  GET — public endpoint, no auth required.
//  Returns active doctors with schedule and photo URL.
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $pdo = getDB();

    $rows = $pdo->query(
        'SELECT d.id, d.first_name, d.last_name, d.specialization, d.work_hours, u.id AS user_id,
                GROUP_CONCAT(dd.day_of_week
                    ORDER BY FIELD(dd.day_of_week,"Mon","Tue","Wed","Thu","Fri","Sat","Sun")
                    SEPARATOR ","
                ) AS days
         FROM doctors d
         JOIN users u ON u.id = d.user_id
         LEFT JOIN doctor_days dd ON dd.doctor_id = d.id
         WHERE d.status = "active" AND u.is_active = 1
         GROUP BY d.id, d.first_name, d.last_name, d.specialization, d.work_hours, u.id
         ORDER BY d.first_name'
    )->fetchAll();

    // Fetch photos separately so a missing column never breaks this endpoint
    $photoMap = [];
    try {
        $userIds = array_column($rows, 'user_id');
        if ($userIds) {
            $in   = implode(',', array_map('intval', $userIds));
            $prs  = $pdo->query("SELECT id, photo_url FROM users WHERE id IN ($in)")->fetchAll();
            foreach ($prs as $pr) { $photoMap[(int)$pr['id']] = $pr['photo_url']; }
        }
    } catch (PDOException) { /* photo_url column not yet migrated — skip */ }

    $seen    = [];
    $doctors = [];
    foreach ($rows as $r) {
        $key = strtolower($r['first_name'] . ' ' . $r['last_name']);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $doctors[] = [
            'id'             => $r['id'],
            'name'           => 'Dr. ' . $r['first_name'] . ' ' . $r['last_name'],
            'firstName'      => $r['first_name'],
            'lastName'       => $r['last_name'],
            'specialization' => $r['specialization'] ?: 'Optometrist',
            'workHours'      => $r['work_hours'] ?: '',
            'days'           => $r['days'] ? explode(',', $r['days']) : [],
            'photoUrl'       => $photoMap[(int)$r['user_id']] ?? null,
        ];
    }

    jsonResponse(['success' => true, 'doctors' => $doctors]);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'doctors' => []], 500);
}
