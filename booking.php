<?php
// booking.php - Review Booking & Payment
require_once 'config/db.php';
requireLogin();

$db = getDB();
$user = $_SESSION['user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['show_id'])) {
    $show_id = (int) $_POST['show_id'];
    $seat_ids_raw = $_POST['seat_ids'] ?? '';
    $seat_ids = array_filter(array_map('intval', explode(',', $seat_ids_raw)));

    if (empty($seat_ids)) {
        header('Location: index.php');
        exit;
    }

    // Verify seats are still available
    $ids_in = implode(',', $seat_ids);
    $check = $db->query("SELECT * FROM seats WHERE id IN ($ids_in) AND show_id = $show_id AND status = 'available'");
    $available_seats = $check->fetch_all(MYSQLI_ASSOC);

    if (count($available_seats) !== count($seat_ids)) {
        header("Location: seats.php?show_id=$show_id&error=Some+seats+no+longer+available");
        exit;
    }

    // Store in session for payment
    $_SESSION['pending_booking'] = [
        'show_id' => $show_id,
        'seat_ids' => $seat_ids,
    ];
}

// Load from session
$pending = $_SESSION['pending_booking'] ?? null;
if (!$pending) {
    header('Location: index.php');
    exit;
}

$show_id = $pending['show_id'];
$seat_ids = $pending['seat_ids'];
$ids_in = implode(',', $seat_ids);

// Fetch show
$stmt = $db->prepare("
    SELECT s.*, m.title, m.rating, m.language, m.poster_url, t.name as theatre_name, t.address as theatre_address
    FROM shows s
    JOIN movies m ON m.id = s.movie_id
    JOIN theatres t ON t.id = s.theatre_id
    WHERE s.id = ?
");
$stmt->bind_param('i', $show_id);
$stmt->execute();
$show = $stmt->get_result()->fetch_assoc();

// Fetch seats
$seats = $db->query("
    SELECT s.*, sc.price, sc.category_name
    FROM seats s JOIN seat_categories sc ON sc.id = s.category_id
    WHERE s.id IN ($ids_in)
")->fetch_all(MYSQLI_ASSOC);

$order_amount = array_sum(array_column($seats, 'price'));
$tax_breakdown = calculateTax($order_amount, count($seats));
$tax = $tax_breakdown['total'];
$booking_charge = $tax_breakdown['booking_charge'];
$igst = $tax_breakdown['igst'];
$total = $order_amount + $tax;

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_method'])) {
    $pay_method = $_POST['pay_method'];

    // Mark seats as occupied
    $db->query("UPDATE seats SET status='occupied' WHERE id IN ($ids_in)");

    // Create booking (ref generated after insert to get booking id)
    $ref = '';
    $stmt = $db->prepare("INSERT INTO bookings (booking_ref, user_id, show_id, total_amount, tax_amount, payment_method, payment_status) VALUES (?,?,?,?,?,?,'success')");
    $stmt->bind_param('siidds', $ref, $user['id'], $show_id, $total, $tax, $pay_method);
    $stmt->execute();
    $booking_id = $db->insert_id;
    $ref = generateBookingRef($pay_method, $booking_id);
    $db->query("UPDATE bookings SET booking_ref='$ref' WHERE id=$booking_id");

    // Save booking seats
    foreach ($seats as $seat) {
        $label = $seat['row_label'] . $seat['seat_number'];
        $price = $seat['price'];
        $sid = $seat['id'];
        $stmt2 = $db->prepare("INSERT INTO booking_seats (booking_id, seat_id, seat_label, price) VALUES (?,?,?,?)");
        $stmt2->bind_param('iisd', $booking_id, $sid, $label, $price);
        $stmt2->execute();
    }

    unset($_SESSION['pending_booking']);
    header("Location: confirmation.php?id=$booking_id");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Review Booking - TicketNew</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
    <link rel="stylesheet" href="css/bootstrap-icons-1.13.1/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/bootstrap-form.css">
  <script src="js/bootstrap.bundle.min.js"></script>
</head>

<body>

    <nav class="navbar">
        <a href="index.php" class="nav-logo">🎫</a>
        <span class="navbar-title">Review your booking</span>
        <a href="profile.php" class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></a>
    </nav>

    <div class="page">
        <!-- LEFT: Booking Details -->
        <div>
            <div class="booking-detail">
                <div class="movie-row">
                    <div class="movie-thumb">
                        <?php if (!empty($show['poster_url'])): ?>
                            <img src="<?= htmlspecialchars($show['poster_url']) ?>"
                                alt="<?= htmlspecialchars($show['title']) ?>"
                                style="width:100%;height:100%;object-fit:cover;border-radius:8px;">
                        <?php else: ?>
                            <img src="img/poster-placeholder.svg" alt="poster"
                                style="width:100%;height:100%;object-fit:cover;border-radius:8px;">
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="movie-title"><?= htmlspecialchars($show['title']) ?></div>
                        <div class="movie-tags">
                            <span class="tag"><?= htmlspecialchars($show['rating']) ?></span>
                            <span class="tag"><?= htmlspecialchars($show['language']) ?></span>
                            <span class="tag"><?= htmlspecialchars($show['format']) ?></span>
                        </div>
                        <div style="font-size:0.8rem;color:#888;margin-top:6px;">
                            <?= htmlspecialchars($show['theatre_name']) ?>,
                            <?= htmlspecialchars($show['theatre_address']) ?></div>
                    </div>
                </div>

                <div class="show-info">
                    <div class="show-info-row">
                        <strong><?= date('D, d M', strtotime($show['show_date'])) ?></strong>
                        <span><?= date('h:i A', strtotime($show['show_time'])) ?></span>
                    </div>
                </div>

                <?php
                // Group seats by category
                $grouped = [];
                foreach ($seats as $seat) {
                    $cat = $seat['category_name'];
                    if (!isset($grouped[$cat]))
                        $grouped[$cat] = ['price' => 0, 'seats' => [], 'count' => 0];
                    $grouped[$cat]['price'] += $seat['price'];
                    $grouped[$cat]['seats'][] = $seat['row_label'] . $seat['seat_number'];
                    $grouped[$cat]['count']++;
                }
                foreach ($grouped as $cat => $g):
                    $seat_label = $cat . ' - ' . implode(' - ', $g['seats']);
                    $ticket_word = $g['count'] === 1 ? '1 ticket' : $g['count'] . ' tickets';
                    ?>
                    <div class="ticket-row">
                        <div>
                            <div class="ticket-name"><?= $ticket_word ?></div>
                            <div class="ticket-seat"><?= htmlspecialchars($seat_label) ?></div>
                        </div>
                        <div class="ticket-price">₹<?= number_format($g['price'], 2) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- RIGHT: Payment Summary -->
        <div>
            <div class="payment-summary">
                <h2 class="summary-title">Payment summary</h2>
                <div class="summary-row"><span>Order amount</span><span>₹<?= number_format($order_amount, 2) ?></span>
                </div>
                <div class="summary-row taxes-toggle" onclick="toggleTaxBreakdown()">
                    <span>Taxes & fees <span id="tax-arrow">▾</span></span>
                    <span>₹<?= number_format($tax, 2) ?></span>
                </div>
                <div id="tax-breakdown" style="display:none;">
                    <div class="summary-row sub-row"><span>Booking
                            Charge</span><span>₹<?= number_format($booking_charge, 2) ?></span></div>
                    <div class="summary-row sub-row"><span>IGST</span><span>₹<?= number_format($igst, 2) ?></span></div>
                </div>
                <div class="summary-row total"><span>To be paid</span><span>₹<?= number_format($total, 2) ?></span>
                </div>

                <div class="user-detail">
                    <div class="user-detail-header">
                        <span>Your details</span>
                        <a href="profile.php" style="color:#e31837;font-size:0.78rem;text-decoration:none;">Edit</a>
                    </div>
                    <p>👤 <?= htmlspecialchars($user['name']) ?></p>
                    <p>+91-<?= htmlspecialchars($user['mobile']) ?></p>
                    <?php if ($user['email']): ?>
                        <p><?= htmlspecialchars($user['email']) ?></p><?php endif; ?>
                    <?php if ($user['state']): ?>
                        <p><?= htmlspecialchars($user['state']) ?></p><?php endif; ?>
                </div>

                <button class="btn-pay" onclick="openCheckout()">
                    ₹<?= number_format($total, 2) ?> &nbsp; Proceed To Pay
                </button>
            </div>
        </div>
    </div>

    <!-- CHECKOUT MODAL -->
    <div class="modal-overlay" id="checkoutModal">
        <div class="checkout-modal">
            <div class="modal-header">
                <h2>Checkout</h2>
                <button class="close-btn" onclick="closeCheckout()">✕</button>
            </div>

            <!-- Wallets -->
            <div class="payment-option">
                <button class="payment-option-header" onclick="toggleSection('wallets')">Wallets <span>▾</span></button>
                <div class="payment-body" id="wallets">
                    <form method="POST" action="booking.php">
                        <input type="hidden" name="pay_method" value="wallet">
                        <button class="btn-checkout-now">Pay via Wallet ₹<?= number_format($total, 2) ?></button>
                    </form>
                </div>
            </div>

            <!-- Cards -->
            <div class="payment-option">
                <button class="payment-option-header" onclick="toggleSection('cards')">Add credit or debit cards
                    <span>▾</span></button>
                <div class="payment-body" id="cards">
                    <form method="POST" action="booking.php" class="card-form">
                        <input type="hidden" name="pay_method" value="card">
                        <input type="text" placeholder="Name on Card" required>
                        <input type="text" placeholder="Card Number" maxlength="16" required>
                        <div class="card-row">
                            <input type="text" placeholder="Expiry Date (MM/YY)" required>
                            <input type="text" placeholder="CVV" maxlength="3" required>
                        </div>
                        <button class="btn-checkout-now" type="submit">Checkout</button>
                        <p style="font-size:0.7rem;color:#888;margin-top:8px;">We accept Visa, Mastercard, Rupay &
                            Diners</p>
                    </form>
                </div>
            </div>

            <!-- Netbanking -->
            <div class="payment-option">
                <button class="payment-option-header" onclick="toggleSection('netbanking')">Netbanking
                    <span>▾</span></button>
                <div class="payment-body" id="netbanking">
                    <div class="bank-grid">
                        <?php foreach (['HDFC', 'ICICI', 'Kotak', 'SBI', 'Axis'] as $bank): ?>
                            <form method="POST" action="booking.php">
                                <input type="hidden" name="pay_method" value="netbanking_<?= strtolower($bank) ?>">
                                <button type="submit" class="bank-btn">🏦 <?= $bank ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- UPI -->
            <div class="payment-option">
                <button class="payment-option-header" onclick="toggleSection('upi')">UPI <span>▾</span></button>
                <div class="payment-body" id="upi">
                    <p style="font-size:0.8rem;margin-bottom:10px;font-weight:500;">Scan QR to pay</p>
                    <p style="font-size:0.75rem;color:#888;margin-bottom:10px;">Use any UPI app on your phone to scan
                        and pay</p>
                    <div class="upi-apps">
                        <span class="upi-app">GPay</span>
                        <span class="upi-app">PhonePe</span>
                        <span class="upi-app">Paytm</span>
                    </div>
                    <div class="qr-box">
                        <button class="btn-gen-qr" onclick="generateQR()">Generate QR</button>
                    </div>
                    <form method="POST" action="booking.php" style="margin-top:12px;">
                        <input type="hidden" name="pay_method" value="upi">
                        <button class="btn-checkout-now" type="submit">Pay via UPI</button>
                    </form>
                </div>
            </div>

            <!-- Pay Later -->
            <div class="payment-option">
                <button class="payment-option-header" onclick="toggleSection('paylater')">Pay Later
                    <span>▾</span></button>
                <div class="payment-body" id="paylater">
                    <form method="POST" action="booking.php">
                        <input type="hidden" name="pay_method" value="paylater">
                        <button class="btn-checkout-now" type="submit">Pay Later</button>
                    </form>
                </div>
            </div>

            <div class="pay-footer">
                <button class="pay-footer-btn">PAY ₹<?= number_format($total, 2) ?></button>
            </div>
        </div>
    </div>

    <script>
        function toggleTaxBreakdown() {
            var el = document.getElementById('tax-breakdown');
            var arrow = document.getElementById('tax-arrow');
            if (el.style.display === 'none') {
                el.style.display = 'block';
                arrow.textContent = '▴';
            } else {
                el.style.display = 'none';
                arrow.textContent = '▾';
            }
        }
    </script>
    <script src="js/payment.js"></script>
</body>

</html>