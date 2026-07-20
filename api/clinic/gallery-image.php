<?php
// ================================================================
//  OPTICANA — api/clinic/gallery-image.php
//  GET ?id=N — redirects to the static file in assets/images/about/
// ================================================================

require_once '../../config/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); exit; }

try {
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT filename FROM about_gallery WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row  = $stmt->fetch();

    if (!$row || !$row['filename']) { http_response_code(404); exit; }

    $filename = basename($row['filename']);
    header('Location: ../../assets/images/about/' . rawurlencode($filename), true, 301);
} catch (PDOException $e) {
    http_response_code(500);
    exit;
}
