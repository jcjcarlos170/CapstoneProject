<?php
// ================================================================
//  OPTICANA — api/auth/register.php
//  POST { firstName, lastName, dob, gender, address, contact,
//         bloodType, email, password }
//  → 200 { success:true, role:'patient', user }
//  → 200 { success:false, message }
// ================================================================

require_once '../../config/db.php';
require_once '../helpers.php';

requireMethod('POST');
startSession();

$b = getBody();

$first   = trim($b['firstName'] ?? '');
$last    = trim($b['lastName']  ?? '');
$dob     = trim($b['dob']       ?? '');
$gender  = trim($b['gender']    ?? '');
$address = trim($b['address']   ?? '');
$contact = trim($b['contact']   ?? '');
$blood   = trim($b['bloodType'] ?? '');
$email   = trim($b['email']     ?? '');
$pass    = $b['password']       ?? '';

// Validation
$required = [$first, $last, $dob, $gender, $address, $contact, $email, $pass];
if (in_array('', $required, true)) {
    jsonResponse(['success' => false, 'message' => 'Please complete all required fields.']);
}
if (strlen($pass) < 8) {
    jsonResponse(['success' => false, 'message' => 'Password must be at least 8 characters.']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'message' => 'Please enter a valid email address.']);
}

try {
    $pdo = getDB();

    // Unique email check
    $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $chk->execute([$email]);
    if ($chk->fetch()) {
        jsonResponse(['success' => false, 'message' => 'This email is already registered. Please sign in.']);
    }

    // Compute age from dob
    $bd  = new DateTime($dob);
    $now = new DateTime();
    $age = (int)$bd->diff($now)->y;

    // Generate unique patient ID (P001, P002, …)
    $cnt = (int)$pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn();
    $pid = 'P' . str_pad($cnt + 1, 3, '0', STR_PAD_LEFT);
    $dup = $pdo->prepare('SELECT id FROM patients WHERE id = ?');
    while (true) {
        $dup->execute([$pid]);
        if (!$dup->fetch()) break;
        $cnt++;
        $pid = 'P' . str_pad($cnt + 1, 3, '0', STR_PAD_LEFT);
    }

    $hash  = password_hash($pass, PASSWORD_DEFAULT);
    $qr    = 'OPTICANA-' . $pid . '-' . strtoupper($first . $last);
    $today = date('Y-m-d');

    $pdo->beginTransaction();

    $pdo->prepare('INSERT INTO users (email, password_hash, role) VALUES (?,?,?)')
        ->execute([$email, $hash, 'patient']);
    $uid = (int)$pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO patients
         (id, user_id, first_name, last_name, gender, dob, age,
          contact, address, blood_type, occupation,
          medical_history, optical_history,
          qr_data, registered_date, status)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $pid, $uid, $first, $last, $gender, $dob, $age,
        $contact, $address, $blood ?: 'Unknown', '',
        '', '',
        $qr, $today, 'active',
    ]);

    $pdo->commit();

    // Welcome notification for the new patient
    createNotification($pdo, $uid, 'welcome',
        'Welcome to OPTICANA',
        'Your patient account has been activated. You can now book appointments and view your records online.'
    );

    $userObj = [
        'id'             => $pid,
        'firstName'      => $first,
        'lastName'       => $last,
        'name'           => "$first $last",
        'email'          => $email,
        'gender'         => $gender,
        'dob'            => $dob,
        'age'            => $age,
        'contact'        => $contact,
        'address'        => $address,
        'bloodType'      => $blood ?: 'Unknown',
        'occupation'     => '',
        'medicalHistory' => '',
        'opticalHistory' => '',
        'qrData'         => $qr,
        'registeredDate' => $today,
        'lastVisit'      => '—',
        'status'         => 'active',
        'consultations'  => [],
        'examinations'   => [],
        'prescriptions'  => [],
        'role'           => 'patient',
    ];

    $_SESSION['user_id']    = $uid;
    $_SESSION['profile_id'] = $pid;
    $_SESSION['role']       = 'patient';

    jsonResponse(['success' => true, 'role' => 'patient', 'user' => $userObj]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(['success' => false, 'message' => 'Registration failed. Please try again.'], 500);
}
