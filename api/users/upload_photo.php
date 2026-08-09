<?php
// ================================================================
//  CANAOPTICALCLINIC — api/users/upload_photo.php
//  POST multipart/form-data { photo: File }
//  Saves to assets/uploads/profiles/<user_id>.<ext>
//  NOTE: this directory must have a Railway Volume mounted on it in
//  production (see README/deploy notes) — without one, Railway's
//  container filesystem is ephemeral and anything written here at
//  runtime is lost the next time the container restarts (redeploys,
//  or waking back up after going idle), even though the DB row
//  pointing at it survives. assets/images/profiles/ (committed to
//  git) is left alone on purpose so this change can't wipe out any
//  pre-existing seed photos there.
//  Updates users.photo_url and returns the public path.
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('POST');
startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}

if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['success' => false, 'message' => 'No file uploaded.']);
}

$file     = $_FILES['photo'];
$mimeType = mime_content_type($file['tmp_name']);
$allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

if (!in_array($mimeType, $allowed, true)) {
    jsonResponse(['success' => false, 'message' => 'Invalid file type. Use JPEG, PNG, or WebP.']);
}

$userId    = (int)$_SESSION['user_id'];
$ext       = match($mimeType) {
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
    default      => 'jpg',
};

$uploadDir = __DIR__ . '/../../assets/uploads/profiles/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Remove any previous photo for this user regardless of extension
foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $e) {
    $old = $uploadDir . $userId . '.' . $e;
    if (file_exists($old)) @unlink($old);
}

$filename = $userId . '.' . $ext;
$destPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    jsonResponse(['success' => false, 'message' => 'Failed to save file. Check server permissions.'], 500);
}

$photoUrl = 'assets/uploads/profiles/' . $filename;

try {
    $pdo = getDB();
    $pdo->prepare('UPDATE users SET photo_url = ? WHERE id = ?')
        ->execute([$photoUrl, $userId]);

    jsonResponse(['success' => true, 'photoUrl' => $photoUrl]);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error.'], 500);
}
