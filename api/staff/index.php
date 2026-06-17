<?php
// ================================================================
//  OPTICANA — api/staff/index.php
//  GET — authenticated (admin/staff only).
//  Returns all staff and admin users with photoUrl for the
//  user management table and profile syncing.
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
if (!in_array($role, ['admin', 'staff'], true)) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
}

try {
    $pdo = getDB();

    // Fetch all photo URLs up front
    $photoMap = [];
    try {
        $prs = $pdo->query('SELECT id, photo_url FROM users WHERE photo_url IS NOT NULL')->fetchAll();
        foreach ($prs as $pr) { $photoMap[(int)$pr['id']] = $pr['photo_url']; }
    } catch (PDOException) { /* photo_url column not yet migrated — skip */ }

    // Staff
    $staffRows = $pdo->query(
        'SELECT s.id, s.first_name, s.last_name, s.contact, s.status, u.id AS user_id, u.email
         FROM staff s
         JOIN users u ON u.id = s.user_id
         WHERE s.archived_at IS NULL
         ORDER BY s.first_name, s.last_name'
    )->fetchAll();

    $staffResult = array_map(fn($r) => [
        'id'        => $r['id'],
        'name'      => $r['first_name'] . ' ' . $r['last_name'],
        'firstName' => $r['first_name'],
        'lastName'  => $r['last_name'],
        'email'     => $r['email'],
        'contact'   => $r['contact'] ?? '',
        'status'    => $r['status'] ?? 'active',
        'photoUrl'  => $photoMap[(int)$r['user_id']] ?? null,
    ], $staffRows);

    // Admins
    $adminRows = $pdo->query(
        'SELECT a.id, a.first_name, a.last_name, a.contact, a.status, u.id AS user_id, u.email
         FROM admins a
         JOIN users u ON u.id = a.user_id
         WHERE a.archived_at IS NULL
         ORDER BY a.first_name, a.last_name'
    )->fetchAll();

    $adminResult = array_map(fn($r) => [
        'id'        => $r['id'],
        'name'      => $r['first_name'] . ' ' . $r['last_name'],
        'firstName' => $r['first_name'],
        'lastName'  => $r['last_name'],
        'email'     => $r['email'],
        'contact'   => $r['contact'] ?? '',
        'status'    => $r['status'] ?? 'active',
        'photoUrl'  => $photoMap[(int)$r['user_id']] ?? null,
    ], $adminRows);

    jsonResponse(['success' => true, 'staff' => $staffResult, 'admins' => $adminResult]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'staff' => [], 'admins' => []], 500);
}
