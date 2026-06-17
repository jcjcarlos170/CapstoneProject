<?php
// ================================================================
//  OPTICANA — api/notifications/index.php
//  GET → returns notifications for the current user
//  Query params:
//    ?limit=N  (default 20; pass 0 for all)
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}

$userId = (int)$_SESSION['user_id'];
$limit  = (int)($_GET['limit'] ?? 20);

try {
    $pdo = getDB();

    $sql  = 'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC';
    if ($limit > 0) $sql .= ' LIMIT ' . $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    $notifications = array_map(function (array $r): array {
        return [
            'id'        => (int)$r['id'],
            'type'      => $r['type'],
            'title'     => $r['title'],
            'body'      => $r['body'],
            'isRead'    => (bool)$r['is_read'],
            'createdAt' => $r['created_at'],
        ];
    }, $rows);

    $unreadCount = count(array_filter($notifications, fn($n) => !$n['isRead']));

    jsonResponse([
        'success'      => true,
        'notifications' => $notifications,
        'unread_count'  => $unreadCount,
    ]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error.'], 500);
}
