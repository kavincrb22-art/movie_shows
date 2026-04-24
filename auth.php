<?php
// auth.php - Authentication Handler (FIXED)
require_once 'config/db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'send_otp') {
    $mobile = preg_replace('/[^0-9]/', '', $_POST['mobile'] ?? '');
    if (strlen($mobile) !== 10) {
        header('Location: index.php?login=1&error=Invalid+mobile+number');
        exit;
    }

    $db = getDB();

    // Generate OTP as STRING always 6 digits
    $otp = str_pad((string) rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    $expiry = date('Y-m-d H:i:s', time() + 600);

    $stmt = $db->prepare("SELECT id FROM users WHERE mobile = ?");
    $stmt->bind_param('s', $mobile);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    if ($existing) {
        $stmt = $db->prepare("UPDATE users SET otp = ?, otp_expiry = ? WHERE mobile = ?");
        $stmt->bind_param('sss', $otp, $expiry, $mobile);
        $stmt->execute();
    } else {
        $name = 'User' . substr($mobile, -4);
        $stmt = $db->prepare("INSERT INTO users (mobile, name, otp, otp_expiry) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $mobile, $name, $otp, $expiry);
        $stmt->execute();
    }

    $_SESSION['pending_mobile'] = $mobile;
    $_SESSION['demo_otp'] = $otp;
    $_SESSION['otp_generated'] = time();

    header('Location: verify_otp.php');
    exit;
}

if ($action === 'verify_otp') {
    $entered_otp = preg_replace('/[^0-9]/', '', trim($_POST['otp'] ?? ''));
    $mobile = $_SESSION['pending_mobile'] ?? '';

    if (!$mobile) {
        header('Location: index.php?login=1');
        exit;
    }

    if (strlen($entered_otp) !== 6) {
        header('Location: verify_otp.php?error=Please+enter+all+6+digits');
        exit;
    }

    $db = getDB();

    // PRIMARY: Session-based check (avoids timezone issues)
    $session_otp = $_SESSION['demo_otp'] ?? '';
    $otp_generated = $_SESSION['otp_generated'] ?? 0;
    $not_expired = (time() - $otp_generated) <= 600;

    if ($session_otp && $not_expired && $entered_otp === $session_otp) {
        $stmt = $db->prepare("SELECT * FROM users WHERE mobile = ?");
        $stmt->bind_param('s', $mobile);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            $id = $user['id'];
            $stmt_clear = $db->prepare("UPDATE users SET otp = NULL, otp_expiry = NULL WHERE id = ?");
            $stmt_clear->bind_param('i', $id);
            $stmt_clear->execute();
            $_SESSION['user'] = $user;
            unset($_SESSION['pending_mobile'], $_SESSION['demo_otp'], $_SESSION['otp_generated']);
            header('Location: index.php');
            exit;
        }
    }

    // FALLBACK: DB-based check
    $stmt = $db->prepare("SELECT * FROM users WHERE mobile = ? AND otp = ?");
    $stmt->bind_param('ss', $mobile, $entered_otp);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && strtotime($user['otp_expiry']) >= time()) {
        $id = $user['id'];
        $stmt_clear = $db->prepare("UPDATE users SET otp = NULL, otp_expiry = NULL WHERE id = ?");
            $stmt_clear->bind_param('i', $id);
            $stmt_clear->execute();
        $_SESSION['user'] = $user;
        unset($_SESSION['pending_mobile'], $_SESSION['demo_otp'], $_SESSION['otp_generated']);
        header('Location: index.php');
        exit;
    }

    header('Location: verify_otp.php?error=Invalid+or+expired+OTP');
    exit;
}

if ($action === 'resend') {
    $mobile = $_SESSION['pending_mobile'] ?? '';
    if ($mobile) {
        $db = getDB();
        $otp = str_pad((string) rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        $expiry = date('Y-m-d H:i:s', time() + 600);
        $stmt = $db->prepare("UPDATE users SET otp = ?, otp_expiry = ? WHERE mobile = ?");
        $stmt->bind_param('sss', $otp, $expiry, $mobile);
        $stmt->execute();
        $_SESSION['demo_otp'] = $otp;
        $_SESSION['otp_generated'] = time();
    }
    header('Location: verify_otp.php?resent=1');
    exit;
}

if ($action === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}
