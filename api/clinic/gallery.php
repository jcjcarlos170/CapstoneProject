<?php
// ================================================================
//  CANAOPTICALCLINIC — api/clinic/gallery.php
//  GET (public)   — list {id, caption, filename, sortOrder}
//  POST (admin)   — upload {imageData: base64DataUrl, caption}
//  DELETE (admin) — remove {id}
//  PATCH (admin)  — reorder {order: [id,...]}
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

startSession();

define('GALLERY_DIR', __DIR__ . '/../../assets/images/about/');

try {
    $pdo = getDB();

    // ── GET — public ───────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $maxPhotos = 0;
        try {
            $cs = $pdo->query('SELECT gallery_max_photos FROM clinic_settings WHERE id = 1 LIMIT 1')->fetch();
            $maxPhotos = $cs ? max(0, (int)($cs['gallery_max_photos'] ?? 0)) : 0;
        } catch (PDOException $e) {}

        $rows = [];
        try {
            $isAdmin = isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin';
            $sql = 'SELECT id, caption, filename, sort_order FROM about_gallery ORDER BY sort_order ASC, id ASC';
            if ($maxPhotos > 0 && !$isAdmin) $sql .= ' LIMIT ' . $maxPhotos;
            $rows = $pdo->query($sql)->fetchAll();
        } catch (PDOException $e) {}

        jsonResponse(['success' => true,
            'maxPhotos' => $maxPhotos ?: null,
            'images'    => array_map(fn($r) => [
                'id'        => (int)$r['id'],
                'caption'   => $r['caption'],
                'sortOrder' => (int)$r['sort_order'],
                'filename'  => $r['filename'],
            ], $rows),
        ]);
    }

    // ── POST / DELETE / PATCH — admin only ────────────────────
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
    }
    if (($_SESSION['role'] ?? '') !== 'admin') {
        jsonResponse(['success' => false, 'message' => 'Admin only.'], 403);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $b         = getBody();
        $dataUrl   = $b['imageData'] ?? '';
        $caption   = trim($b['caption'] ?? '');
        $sortOrder = (int)($b['sortOrder'] ?? 0);

        if (!preg_match('/^data:([\w\/\-\+]+);base64,(.+)$/s', $dataUrl, $m)) {
            jsonResponse(['success' => false, 'message' => 'Invalid file data.']);
        }
        $mimeType = $m[1];

        $extMap  = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
        $allowed = array_keys($extMap);
        if (!in_array($mimeType, $allowed, true)) {
            jsonResponse(['success' => false, 'message' => 'Only PNG or JPG files are accepted.']);
        }

        $binary = base64_decode($m[2]);
        if ($binary === false) {
            jsonResponse(['success' => false, 'message' => 'Could not decode file.']);
        }

        if (!is_dir(GALLERY_DIR)) mkdir(GALLERY_DIR, 0755, true);

        $filename = 'photo_' . uniqid('', true) . '.' . $extMap[$mimeType];
        if (file_put_contents(GALLERY_DIR . $filename, $binary) === false) {
            jsonResponse(['success' => false, 'message' => 'Could not save file.']);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO about_gallery (caption, filename, sort_order) VALUES (?, ?, ?)'
        );
        $stmt->execute([$caption, $filename, $sortOrder]);

        jsonResponse(['success' => true, 'id' => (int)$pdo->lastInsertId(), 'filename' => $filename]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $id = (int)(getBody()['id'] ?? 0);
        if (!$id) jsonResponse(['success' => false, 'message' => 'ID required.']);

        $row = $pdo->prepare('SELECT filename FROM about_gallery WHERE id = ?');
        $row->execute([$id]);
        $photo = $row->fetch();

        $pdo->prepare('DELETE FROM about_gallery WHERE id = ?')->execute([$id]);

        if ($photo && $photo['filename']) {
            $path = GALLERY_DIR . basename($photo['filename']);
            if (file_exists($path)) unlink($path);
        }

        jsonResponse(['success' => true]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        $order = getBody()['order'] ?? [];
        if (!is_array($order)) jsonResponse(['success' => false, 'message' => 'order must be an array.']);
        $stmt = $pdo->prepare('UPDATE about_gallery SET sort_order = ? WHERE id = ?');
        foreach (array_values($order) as $i => $id) {
            $stmt->execute([$i, (int)$id]);
        }
        jsonResponse(['success' => true]);
    }

    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error.'], 500);
}
