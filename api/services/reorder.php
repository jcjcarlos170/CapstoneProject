<?php
// ================================================================
//  CANAOPTICALCLINIC — api/services/reorder.php
//  POST { order: [id, id, ...] } — admin only.
//  Updates sort_order for each service based on array position.
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('POST');
startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}
if (($_SESSION['role'] ?? '') !== 'admin') {
    jsonResponse(['success' => false, 'message' => 'Only admins may reorder services.'], 403);
}

$b     = getBody();
$order = $b['order'] ?? [];
if (!is_array($order) || !count($order)) {
    jsonResponse(['success' => false, 'message' => 'Invalid order.']);
}

try {
    $pdo  = getDB();
    $stmt = $pdo->prepare('UPDATE clinic_services SET sort_order = ? WHERE id = ?');
    foreach ($order as $i => $id) {
        $stmt->execute([$i, (int)$id]);
    }
    jsonResponse(['success' => true]);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error.'], 500);
}
