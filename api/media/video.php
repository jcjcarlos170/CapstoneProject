<?php
// ================================================================
//  CANAOPTICALCLINIC — api/media/video.php
//  Serves a video file with real HTTP Range Request support (206 Partial
//  Content). This exists because PHP's built-in dev server (php -S, what
//  this app's Railway deployment runs — see nixpacks.toml) does NOT honor
//  Range headers for static files at all: it always returns the whole
//  file with 200 OK regardless of what Range was requested. Without Range
//  support, a browser can't seek to an arbitrary timestamp — dragging the
//  scrubber or tapping skip ±10s either does nothing, hangs re-downloading
//  the whole file, or resets to the start. Works fine on Apache (localhost
//  via XAMPP), which supports Range natively for static files, which is
//  why this only ever showed up on the hosted deployment.
//
//  Public — the clinic video is shown on the pre-login landing page, same
//  as when it was served as a plain static file.
//
//  GET ?src=<filename inside assets/videos/>
// ================================================================

define('VIDEO_DIR', realpath(__DIR__ . '/../../assets/videos'));

if (VIDEO_DIR === false) {
    http_response_code(404);
    exit;
}

// basename() strips any directory traversal attempt outright; realpath()
// below is the actual security check (it collapses any remaining ../
// tricks and resolves symlinks, so the strpos() containment check that
// follows can't be fooled).
$requested = basename($_GET['src'] ?? '');
$path      = VIDEO_DIR . DIRECTORY_SEPARATOR . $requested;
$realPath  = $requested !== '' ? realpath($path) : false;

if (!$realPath || strpos($realPath, VIDEO_DIR) !== 0 || !is_file($realPath)) {
    http_response_code(404);
    exit;
}

$mimeTypes = ['mp4' => 'video/mp4', 'webm' => 'video/webm'];
$ext  = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
$mime = $mimeTypes[$ext] ?? 'application/octet-stream';

$size  = filesize($realPath);
$start = 0;
$end   = $size - 1;

header('Accept-Ranges: bytes');
header('Content-Type: ' . $mime);
// The video file only ever changes via re-upload (which overwrites the
// same filename — see upload_video.php), so long-term caching is safe;
// a hard refresh or new deploy is the only time this URL's content changes.
header('Cache-Control: public, max-age=31536000, immutable');

$range = $_SERVER['HTTP_RANGE'] ?? '';
if ($range !== '' && preg_match('/bytes=(\d*)-(\d*)/', $range, $m)) {
    $start = $m[1] === '' ? 0 : (int)$m[1];
    $end   = $m[2] === '' ? $size - 1 : (int)$m[2];

    if ($start > $end || $start >= $size) {
        http_response_code(416); // Range Not Satisfiable
        header("Content-Range: bytes */{$size}");
        exit;
    }
    $end = min($end, $size - 1);

    http_response_code(206);
    header("Content-Range: bytes {$start}-{$end}/{$size}");
} else {
    http_response_code(200);
}

$length = $end - $start + 1;
header('Content-Length: ' . $length);

$fh = fopen($realPath, 'rb');
if (!$fh) {
    http_response_code(500);
    exit;
}
fseek($fh, $start);

// Stream in chunks rather than reading the whole (possibly ~2GB) file into
// memory at once — matches the upload side's own 2GB ceiling.
$chunkSize = 8192;
$bytesLeft = $length;
while ($bytesLeft > 0 && !feof($fh)) {
    $readSize = (int)min($chunkSize, $bytesLeft);
    echo fread($fh, $readSize);
    flush();
    $bytesLeft -= $readSize;
}
fclose($fh);
