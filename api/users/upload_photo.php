<?php
// ================================================================
//  OPTICANA — api/users/upload_photo.php
//  POST multipart/form-data { photo: File }
//  Saves to assets/images/profiles/<user_id>.<ext>
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

if ($file['size'] > 3 * 1024 * 1024) {
    jsonResponse(['success' => false, 'message' => 'File too large. Maximum 3 MB.']);
}

$userId    = (int)$_SESSION['user_id'];
$ext       = match($mimeType) {
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
    default      => 'jpg',
};

$uploadDir = __DIR__ . '/../../assets/images/profiles/';
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

$photoUrl = 'assets/images/profiles/' . $filename;

try {
    $pdo = getDB();
    $pdo->prepare('UPDATE users SET photo_url = ? WHERE id = ?')
        ->execute([$photoUrl, $userId]);

    jsonResponse(['success' => true, 'photoUrl' => $photoUrl]);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error.'], 500);
}
