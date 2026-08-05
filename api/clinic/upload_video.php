<?php
// ================================================================
//  CANAOPTICALCLINIC — api/clinic/upload_video.php
//  POST multipart/form-data { video: File } — admin only.
//  Saves to assets/videos/clinic-video.<ext>, updates clinic_settings.video_url.
//  DELETE — admin only. Removes the current video and clears video_url.
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}
if (($_SESSION['role'] ?? '') !== 'admin') {
    jsonResponse(['success' => false, 'message' => 'Only admins may change the clinic video.'], 403);
}

define('VIDEO_DIR', __DIR__ . '/../../assets/videos/');

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    try {
        $pdo = getDB();
        foreach (['mp4', 'webm'] as $e) {
            $old = VIDEO_DIR . 'clinic-video.' . $e;
            if (file_exists($old)) @unlink($old);
        }
        $pdo->prepare('UPDATE clinic_settings SET video_url = NULL WHERE id = 1')->execute();
        jsonResponse(['success' => true]);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => 'Database error.'], 500);
    }
}

requireMethod('POST');

if (empty($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['success' => false, 'message' => 'No file uploaded.']);
}

$file     = $_FILES['video'];
$mimeType = mime_content_type($file['tmp_name']);
$allowed  = ['video/mp4', 'video/webm'];

if (!in_array($mimeType, $allowed, true)) {
    jsonResponse(['success' => false, 'message' => 'Invalid file type. Use MP4 or WebM.']);
}

// A generous but real ceiling — large enough for a proper clinic tour video,
// small enough that one bad/oversized upload can't fill the disk or hang
// the server indefinitely (matches this server's 2048M ini limit).
$maxBytes = 2048 * 1024 * 1024;
if ($file['size'] > $maxBytes) {
    jsonResponse(['success' => false, 'message' => 'Video is too large. Please keep it under 2GB.']);
}

$ext = $mimeType === 'video/webm' ? 'webm' : 'mp4';

if (!is_dir(VIDEO_DIR)) {
    mkdir(VIDEO_DIR, 0755, true);
}

foreach (['mp4', 'webm'] as $e) {
    $old = VIDEO_DIR . 'clinic-video.' . $e;
    if (file_exists($old)) @unlink($old);
}

$filename = 'clinic-video.' . $ext;
$destPath = VIDEO_DIR . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    jsonResponse(['success' => false, 'message' => 'Failed to save file. Check server permissions.'], 500);
}

$videoUrl = 'assets/videos/' . $filename;

try {
    $pdo = getDB();
    $pdo->prepare('UPDATE clinic_settings SET video_url = ? WHERE id = 1')
        ->execute([$videoUrl]);

    jsonResponse(['success' => true, 'videoUrl' => $videoUrl]);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error.'], 500);
}
