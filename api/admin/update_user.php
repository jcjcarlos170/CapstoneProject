<?php
// ================================================================
//  OPTICANA — api/admin/update_user.php
//  Admin-only endpoint to update any user's profile.
//
//  POST { profileId, role, firstName, lastName, email, contact, status }
//  → { success:true } | { success:false, message }
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('POST');
startSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}
if ($_SESSION['role'] !== 'admin') {
    jsonResponse(['success' => false, 'message' => 'Only admins may edit user profiles.'], 403);
}

$b             = getBody();
$profileId     = trim($b['profileId']     ?? '');
$role          = trim($b['role']          ?? '');
$fn            = trim($b['firstName']     ?? '');
$ln            = trim($b['lastName']      ?? '');
$email         = trim($b['email']         ?? '');
$contact       = trim($b['contact']       ?? '');
$status        = trim($b['status']        ?? '');
// Doctor-only fields — patients/staff/admin profiles don't have these
$specialization = trim($b['specialization'] ?? '');
$prcLicense      = trim($b['prcLicense']     ?? '');

if (!$profileId || !$role || !$fn || !$ln) {
    jsonResponse(['success' => false, 'message' => 'profileId, role, firstName and lastName are required.']);
}

$tableMap = [
    'Admin'   => 'admins',
    'Staff'   => 'staff',
    'Doctor'  => 'doctors',
    'Patient' => 'patients',
];

$table = $tableMap[$role] ?? null;
if (!$table) {
    jsonResponse(['success' => false, 'message' => 'Invalid role.']);
}

$validStatuses = ['active', 'inactive'];
if ($status && !in_array($status, $validStatuses, true)) {
    jsonResponse(['success' => false, 'message' => 'Invalid status value.']);
}

try {
    $pdo = getDB();

    // Look up user_id from role table
    $s = $pdo->prepare("SELECT user_id FROM `{$table}` WHERE id = ? LIMIT 1");
    $s->execute([$profileId]);
    $row = $s->fetch();
    if (!$row || !$row['user_id']) {
        jsonResponse(['success' => false, 'message' => 'User not found.']);
    }
    $userId = (int)$row['user_id'];

    // Update name and contact on the role table
    $pdo->prepare(
        "UPDATE `{$table}` SET first_name = ?, last_name = ?, contact = ?" .
        ($status ? ", status = ?" : "") .
        " WHERE id = ?"
    )->execute($status
        ? [$fn, $ln, $contact, $status, $profileId]
        : [$fn, $ln, $contact, $profileId]
    );

    // Doctor-only: specialization and PRC license are locked on the doctor's
    // own Settings page (shown read-only with "Contact admin to update") —
    // this is the only place those fields can actually be changed.
    if ($role === 'Doctor' && ($specialization !== '' || $prcLicense !== '')) {
        $pdo->prepare(
            "UPDATE doctors SET specialization = COALESCE(NULLIF(?, ''), specialization), prc_license = ? WHERE id = ?"
        )->execute([$specialization, $prcLicense !== '' ? $prcLicense : null, $profileId]);
    }

    // Sync is_active on users table when status changes
    if ($status) {
        $isActive = ($status === 'active') ? 1 : 0;
        // Reset the inactivity clock on reactivation, otherwise the stale
        // last_login_at immediately re-triggers the 1-year auto-deactivation
        // on the user's very next login.
        $pdo->prepare(
            'UPDATE users SET is_active = ?' . ($isActive ? ', last_login_at = NOW()' : '') . ' WHERE id = ?'
        )->execute([$isActive, $userId]);
    }

    // Update email on users table (with duplicate check)
    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
        $chk->execute([$email, $userId]);
        if ($chk->fetch()) {
            jsonResponse(['success' => false, 'message' => 'That email is already registered to another account.']);
        }
        $pdo->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$email, $userId]);
    }

    jsonResponse(['success' => true]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error. Please try again.'], 500);
}
