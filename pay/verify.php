<?php
// pay/verify.php - Payment Status Verification
require_once '../config/db.php';
requireLogin();

header('Content-Type: application/json');

$booking_id = (int) ($_GET['booking_id'] ?? 0);
$user = $_SESSION['user'];

if (!$booking_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing booking id']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT id, booking_ref, payment_status, payment_method, total_amount FROM bookings WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $booking_id, $user['id']);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Booking not found']);
    exit;
}

echo json_encode([
    'status' => $booking['payment_status'],
    'booking_ref' => $booking['booking_ref'],
    'payment_method' => $booking['payment_method'],
    'total_amount' => $booking['total_amount'],
]);
