<?php
// ================================================================
//  OPTICANA — api/services/public.php
//  GET — public endpoint, no auth required.
//  Returns active services sorted by sort_order for the public page.
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $pdo  = getDB();
    $rows = $pdo->query(
        "SELECT id, name, description, icon FROM clinic_services
         WHERE status = 'active' ORDER BY sort_order ASC, id ASC"
    )->fetchAll();

    $services = array_map(fn($r) => [
        'id'          => (int)$r['id'],
        'name'        => $r['name'],
        'description' => $r['description'] ?? '',
        'icon'        => $r['icon'] ?? 'eye',
    ], $rows);

    jsonResponse(['success' => true, 'services' => $services]);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'services' => []], 500);
}
