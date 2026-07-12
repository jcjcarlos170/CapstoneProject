<?php
// ================================================================
//  OPTICANA — api/clinic/gallery.php
//  GET (public)   — list {id, caption, sortOrder}
//  POST (admin)   — upload {imageData: base64DataUrl, caption}
//  DELETE (admin) — remove {id}
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

startSession();

try {
    $pdo = getDB();

    // ── GET — public ───────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Read optional max_photos limit from clinic_settings (column may not exist yet)
        $maxPhotos = 0;
        try {
            $cs = $pdo->query('SELECT gallery_max_photos FROM clinic_settings WHERE id = 1 LIMIT 1')->fetch();
            $maxPhotos = $cs ? max(0, (int)($cs['gallery_max_photos'] ?? 0)) : 0;
        } catch (PDOException $e) { /* column not yet migrated — silently skip */ }

        $rows = [];
        try {
            $sql  = 'SELECT id, caption, sort_order FROM about_gallery ORDER BY sort_order ASC, id ASC';
            if ($maxPhotos > 0) $sql .= ' LIMIT ' . $maxPhotos;
            $rows = $pdo->query($sql)->fetchAll();
        } catch (PDOException $e) { /* table not yet migrated */ }

        jsonResponse(['success' => true,
            'maxPhotos' => $maxPhotos ?: null,
            'images'    => array_map(fn($r) => [
                'id'        => (int)$r['id'],
                'caption'   => $r['caption'],
                'sortOrder' => (int)$r['sort_order'],
            ], $rows),
        ]);
    }

    // ── POST / DELETE — admin only ─────────────────────────────
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

        $allowed = ['image/png', 'image/jpeg', 'image/svg+xml'];
        if (!in_array($mimeType, $allowed, true)) {
            jsonResponse(['success' => false, 'message' => 'Only PNG, JPG or SVG files are accepted.']);
        }

        $binary = base64_decode($m[2]);
        if ($binary === false) {
            jsonResponse(['success' => false, 'message' => 'Could not decode file.']);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO about_gallery (caption, image_data, mime_type, sort_order) VALUES (?, ?, ?, ?)'
        );
        $stmt->bindValue(1, $caption);
        $stmt->bindValue(2, $binary, PDO::PARAM_LOB);
        $stmt->bindValue(3, $mimeType);
        $stmt->bindValue(4, $sortOrder, PDO::PARAM_INT);
        $stmt->execute();

        jsonResponse(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $id = (int)(getBody()['id'] ?? 0);
        if (!$id) jsonResponse(['success' => false, 'message' => 'ID required.']);
        $pdo->prepare('DELETE FROM about_gallery WHERE id = ?')->execute([$id]);
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
