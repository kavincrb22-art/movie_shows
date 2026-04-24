<?php
// pay/process.php - Payment Processing Handler
require_once '../config/db.php';
requireLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$method = $input['method'] ?? $_POST['method'] ?? '';
$booking_id = $input['booking_id'] ?? $_POST['booking_id'] ?? 0;

if (!$method || !$booking_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$db = getDB();
$user = $_SESSION['user'];

// Verify booking belongs to user
$stmt = $db->prepare("SELECT * FROM bookings WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $booking_id, $user['id']);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Booking not found']);
    exit;
}

// Simulate payment gateway response
$allowed_methods = [
    'card',
    'upi',
    'wallet',
    'paylater',
    'netbanking_hdfc',
    'netbanking_icici',
    'netbanking_kotak',
    'netbanking_sbi',
    'netbanking_axis'
];

if (!in_array($method, $allowed_methods)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unsupported payment method']);
    exit;
}

// Mock gateway: always succeeds in demo mode
$txn_id = 'TXN' . strtoupper(substr(md5(uniqid()), 0, 10));
$status = 'success';

// Update booking record
$stmt = $db->prepare("UPDATE bookings SET payment_status=?, payment_method=?, txn_id=? WHERE id=?");
$stmt->bind_param('sssi', $status, $method, $txn_id, $booking_id);
$stmt->execute();

echo json_encode([
    'success' => true,
    'txn_id' => $txn_id,
    'method' => $method,
    'redirect' => '../confirmation.php?id=' . $booking_id
]);
