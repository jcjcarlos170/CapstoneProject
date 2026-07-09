<?php
// ================================================================
//  OPTICANA — api/activity/log.php
//
//  GET  → returns all log entries (admin only)
//  POST { id, user, role, action, timestamp, type }
//       → inserts a log entry (any authenticated user)
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: return all logs ─────────────────────────────────────────
if ($method === 'GET') {
    if ($_SESSION['role'] !== 'admin') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
    }
    try {
        $pdo  = getDB();
        $rows = $pdo->query(
            'SELECT al.*, u.photo_url
               FROM activity_log al
               LEFT JOIN users u ON u.id = al.users_id
              ORDER BY al.timestamp DESC'
        )->fetchAll();

        $logs = array_map(fn($r) => [
            'id'        => $r['id'],
            'user'      => $r['user_name'] ?? '',
            'role'      => $r['role']      ?? '',
            'action'    => $r['action']    ?? '',
            'timestamp' => $r['timestamp'] ?? '',
            'type'      => $r['type']      ?? 'info',
            'photoUrl'  => $r['photo_url'] ?? null,
        ], $rows);

        jsonResponse(['success' => true, 'logs' => $logs]);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => 'Database error.'], 500);
    }
}

// ── POST: insert a log entry ─────────────────────────────────────
if ($method === 'POST') {
    $b         = getBody();
    $id        = trim($b['id']        ?? '');
    $user      = trim($b['user']      ?? '');
    $role      = trim($b['role']      ?? '');
    $action    = trim($b['action']    ?? '');
    $timestamp = trim($b['timestamp'] ?? '');
    $type      = trim($b['type']      ?? 'info');
    $usersId   = isset($b['userId']) && is_numeric($b['userId']) ? (int)$b['userId'] : null;

    if (!$id || !$user || !$action) {
        jsonResponse(['success' => false, 'message' => 'id, user and action are required.']);
    }

    // Truncate id to fit VARCHAR(20)
    $id = substr($id, 0, 20);

    try {
        $pdo = getDB();
        $pdo->prepare(
            'INSERT IGNORE INTO activity_log (id, users_id, user_name, role, action, timestamp, type)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $id,
            $usersId,
            $user,
            $role,
            $action,
            $timestamp ?: date('Y-m-d H:i:s'),
            $type,
        ]);
        jsonResponse(['success' => true]);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => 'Database error.'], 500);
    }
}

jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
