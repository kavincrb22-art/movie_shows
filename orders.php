<?php
// orders.php
require_once 'config/db.php';
requireLogin();
$db = getDB();
$user = $_SESSION['user'];
$stmt = $db->prepare("
    SELECT b.*, m.title, s.show_date, s.show_time, t.name as theatre_name
    FROM bookings b JOIN shows s ON s.id=b.show_id JOIN movies m ON m.id=s.movie_id JOIN theatres t ON t.id=s.theatre_id
    WHERE b.user_id=? ORDER BY b.created_at DESC
");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?><!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>My Orders - TicketNew</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/bootstrap-icons-1.13.1/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/bootstrap-form.css">
  <script src="js/bootstrap.bundle.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f5f5
        }

        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            height: 60px;
            background: #fff;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .1)
        }

        .nav-logo {
            font-weight: 800;
            font-size: 1.1rem;
            text-decoration: none;
            color: inherit
        }

        .nav-logo span:first-child {
            color: #e31837
        }

        .nav-logo span:last-child {
            color: #1a1a2e
        }

        .page {
            max-width: 720px;
            margin: 32px auto;
            padding: 0 16px
        }

        h1 {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 20px
        }

        .order-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .05)
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px
        }

        .order-title {
            font-size: 1rem;
            font-weight: 600
        }

        .status-badge {
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 12px;
            background: #e8f5e9;
            color: #2e7d32
        }

        .order-detail {
            font-size: 0.82rem;
            color: #666;
            line-height: 2
        }

        .order-ref {
            font-size: 0.75rem;
            color: #aaa;
            margin-top: 8px
        }

        .amount {
            font-size: 0.9rem;
            font-weight: 700;
            color: #e31837;
            margin-top: 8px
        }

        .empty {
            text-align: center;
            padding: 60px;
            color: #888
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <a href="index.php" class="nav-logo">🎫 <span>TICKE</span><span>NEW</span></a>
        <a href="index.php" style="font-size:0.85rem;color:#555;text-decoration:none;">← Home</a>
    </nav>
    <div class="page">
        <h1>My Orders</h1>
        <?php if (empty($bookings)): ?>
            <div class="empty">
                <p style="font-size:2rem">🎟</p>
                <p>No bookings yet</p><a href="index.php" style="color:#e31837">Book your first movie</a>
            </div>
        <?php else: ?>
            <?php foreach ($bookings as $b): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div class="order-title"><?= htmlspecialchars($b['title']) ?></div>
                        <span class="status-badge"><?= ucfirst($b['payment_status'] ?? 'pending') ?></span>
                    </div>
                    <div class="order-detail">
                        🏛 <?= htmlspecialchars($b['theatre_name']) ?><br>
                        📅 <?= date('d M Y', strtotime($b['show_date'])) ?> | ⏰ <?= date('h:i A', strtotime($b['show_time'])) ?>
                    </div>
                    <div class="order-ref">Ref: <?= htmlspecialchars($b['booking_ref']) ?></div>
                    <div class="amount">₹<?= number_format($b['total_amount'], 2) ?> paid</div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>

</html>