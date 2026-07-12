<?php
// ================================================================
//  OPTICANA — api/clinic/gallery-image.php
//  GET ?id=N — serves raw binary image from about_gallery table
// ================================================================

require_once '../../config/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); exit; }

try {
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT image_data, mime_type FROM about_gallery WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row  = $stmt->fetch();

    if (!$row) { http_response_code(404); exit; }

    header('Content-Type: ' . $row['mime_type']);
    header('Cache-Control: public, max-age=604800');
    echo $row['image_data'];
} catch (PDOException $e) {
    http_response_code(500);
    exit;
}
