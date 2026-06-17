<?php
// ================================================================
//  OPTICANA — api/qr/stats.php
//  GET — today's QR scan totals.
//  → 200 { success:true, total, found, notFound } | { success:false, message }
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('GET');
startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}

try {
    $pdo = getDB();
    $row = $pdo->query(
        'SELECT COUNT(*) AS total, COALESCE(SUM(found), 0) AS found
         FROM qr_scan_log WHERE DATE(scanned_at) = CURDATE()'
    )->fetch();

    $total = (int)$row['total'];
    $found = (int)$row['found'];

    jsonResponse(['success' => true, 'total' => $total, 'found' => $found, 'notFound' => $total - $found]);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error. Please try again.'], 500);
}
