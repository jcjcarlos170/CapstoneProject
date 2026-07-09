<?php
// ================================================================
//  OPTICANA — api/users/create.php
//  Admin-only. Creates an Admin, Staff, or Doctor account.
//  POST { role, firstName, lastName, email, password, contact?,
//         specialization?, prcLicense? }
//  → { success:true, user:{...} }
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('POST');
startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}
if ($_SESSION['role'] !== 'admin') {
    jsonResponse(['success' => false, 'message' => 'Only admins can create user accounts.'], 403);
}

$b       = getBody();
$role    = trim($b['role']           ?? '');
$first   = trim($b['firstName']      ?? '');
$last    = trim($b['lastName']       ?? '');
$email   = strtolower(trim($b['email'] ?? ''));
$pass    = $b['password']            ?? '';
$contact = trim($b['contact']        ?? '');

if (!$role || !$first || !$last || !$email || !$pass) {
    jsonResponse(['success' => false, 'message' => 'Role, name, email and password are required.']);
}

$roleMap = ['Admin' => 'admin', 'Staff' => 'staff', 'Doctor' => 'doctor'];
$dbRole  = $roleMap[$role] ?? null;
if (!$dbRole) {
    jsonResponse(['success' => false, 'message' => 'Invalid role. Must be Admin, Staff, or Doctor.']);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'message' => 'Please enter a valid email address.']);
}

if (strlen($pass) < 8) {
    jsonResponse(['success' => false, 'message' => 'Password must be at least 8 characters.']);
}

$tableMap  = ['Admin' => 'admins', 'Staff' => 'staff', 'Doctor' => 'doctors'];
$prefixMap = ['Admin' => 'ADM',    'Staff' => 'S',     'Doctor' => 'D'];
$table     = $tableMap[$role];
$prefix    = $prefixMap[$role];

try {
    $pdo = getDB();

    // Email uniqueness check
    $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $chk->execute([$email]);
    if ($chk->fetch()) {
        jsonResponse(['success' => false, 'message' => 'This email is already registered.']);
    }

    // Generate next sequential ID (ADM001, S001, D001, …)
    $lastId = $pdo->query("SELECT id FROM `{$table}` ORDER BY id DESC LIMIT 1")->fetchColumn();
    $next   = 1;
    if ($lastId && preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/i', $lastId, $m)) {
        $next = (int)$m[1] + 1;
    }
    $newId = $prefix . str_pad($next, 3, '0', STR_PAD_LEFT);
    $dup   = $pdo->prepare("SELECT id FROM `{$table}` WHERE id = ?");
    while (true) {
        $dup->execute([$newId]);
        if (!$dup->fetch()) break;
        $next++;
        $newId = $prefix . str_pad($next, 3, '0', STR_PAD_LEFT);
    }

    $pdo->beginTransaction();

    // Create login account
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $pdo->prepare('INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)')
        ->execute([$email, $hash, $dbRole]);
    $userId = (int)$pdo->lastInsertId();

    // Create role-specific profile row
    if ($role === 'Doctor') {
        $spec = trim($b['specialization'] ?? '') ?: 'Optometrist';
        $prc  = trim($b['prcLicense']    ?? '');
        $pdo->prepare(
            'INSERT INTO doctors (id, user_id, first_name, last_name, contact, specialization, prc_license, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$newId, $userId, $first, $last, $contact, $spec, $prc ?: null, 'active']);
    } else {
        $pdo->prepare(
            "INSERT INTO `{$table}` (id, user_id, first_name, last_name, contact, status)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([$newId, $userId, $first, $last, $contact, 'active']);
    }

    $pdo->commit();

    $userObj = [
        'id'        => $newId,
        'firstName' => $first,
        'lastName'  => $last,
        'name'      => ($role === 'Doctor' ? 'Dr. ' : '') . "$first $last",
        'email'     => $email,
        'contact'   => $contact,
        'role'      => $dbRole,
        'status'    => 'active',
        'photoUrl'  => null,
    ];
    if ($role === 'Doctor') {
        $userObj['specialization'] = $spec;
        $userObj['prcLicense']     = $prc;
        $userObj['available']      = true;
        $userObj['days']           = [];
        $userObj['hours']          = '8:00 AM – 5:00 PM';
    }

    jsonResponse(['success' => true, 'user' => $userObj]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(['success' => false, 'message' => 'Database error. Please try again.'], 500);
}
