<?php
// ================================================================
//  CANAOPTICALCLINIC — api/archive/index.php
//  GET — admin only. Returns all archived records.
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
if ($_SESSION['role'] !== 'admin') {
    jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
}

try {
    $pdo  = getDB();
    $rows = $pdo->query('SELECT * FROM archived_records ORDER BY id DESC')->fetchAll();

    $records = array_map(function ($r) {
        $data = null;
        if ($r['data_json']) {
            $decoded = json_decode($r['data_json'], true);
            if (is_array($decoded)) $data = $decoded;
        }
        return [
            'id'         => $r['id'],
            'type'       => $r['type'],
            'name'       => $r['name'],
            'refId'      => $r['ref_id'],
            'archivedBy' => $r['archived_by'],
            'reason'     => $r['reason'],
            'date'       => $r['date'],
            'data'       => $data,
        ];
    }, $rows);

    jsonResponse(['success' => true, 'records' => $records]);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'records' => []], 500);
}
