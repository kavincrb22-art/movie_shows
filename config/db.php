<?php
// config/db.php - Database Configuration

define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Change to your MySQL username
define('DB_PASS', '');           // Change to your MySQL password
define('DB_NAME', 'ticketnew_db');

function getDB()
{
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper: Get logged-in user
function getLoggedInUser()
{
    return $_SESSION['user'] ?? null;
}

// Helper: Require login (redirect if not)
function requireLogin()
{
    if (!isset($_SESSION['user'])) {
        header('Location: index.php?login=1');
        exit;
    }
}

// Helper: Generate booking reference
function generateBookingRef($pay_method = '', $booking_id = 0)
{
    $type_map = [
        'wallet' => 'W',
        'card' => 'C',
        'upi' => 'U',
        'paylater' => 'P',
        'netbanking' => 'N',
    ];
    $prefix = 'T';
    foreach ($type_map as $key => $letter) {
        if (strpos($pay_method, $key) === 0) {
            $prefix = $letter;
            break;
        }
    }
    $seq = str_pad($booking_id, 3, '0', STR_PAD_LEFT);
    return $prefix . '0PM' . $seq;
}

// Helper: Calculate tax (14.75%)
function calculateTax($amount, $seat_count = 1)
{
    $booking_charge = $seat_count * 20;
    $igst = round($booking_charge * 0.18, 2);
    return ['booking_charge' => $booking_charge, 'igst' => $igst, 'total' => round($booking_charge + $igst, 2)];
}

?>