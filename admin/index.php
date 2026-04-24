<?php
// admin/index.php - Admin Dashboard
require_once '../config/db.php';

// Simple admin auth check
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();

$total_bookings = $db->query("SELECT COUNT(*) AS c FROM bookings")->fetch_assoc()['c'] ?? 0;
$total_revenue = $db->query("SELECT COALESCE(SUM(total_amount),0) AS r FROM bookings WHERE payment_status='success'")->fetch_assoc()['r'] ?? 0;
$total_movies = $db->query("SELECT COUNT(*) AS c FROM movies")->fetch_assoc()['c'] ?? 0;
$total_users = $db->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'] ?? 0;

$recent_bookings = $db->query("
    SELECT b.booking_ref, b.total_amount, b.payment_status, b.created_at,
           m.title, u.name AS user_name
    FROM bookings b
    JOIN shows s ON s.id = b.show_id
    JOIN movies m ON m.id = s.movie_id
    LEFT JOIN users u ON u.id = b.user_id
    ORDER BY b.created_at DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Admin Dashboard - TicketNew</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/responsive.css">
</head>

<body>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-logo">🎫 <span>TICKE</span>NEW Admin</div>
        <nav class="sidebar-nav">
            <div class="sidebar-section">Main</div>
            <a href="index.php" class="sidebar-link active"><span class="icon">📊</span> Dashboard</a>
            <a href="movies.php" class="sidebar-link"><span class="icon">🎬</span> Movies</a>
            <a href="theatres.php" class="sidebar-link"><span class="icon">🎭</span> Theatres</a>
            <a href="shows.php" class="sidebar-link"><span class="icon">🕐</span> Shows</a>
            <a href="seat_types.php" class="sidebar-link"><span class="icon">💺</span> Seat Types</a>
            <div class="sidebar-section">Business</div>
            <a href="bookings.php" class="sidebar-link"><span class="icon">📋</span> Bookings</a>
            <a href="users.php" class="sidebar-link"><span class="icon">👥</span> Users</a>
            <a href="cities.php" class="sidebar-link"><span class="icon">🏙</span> Cities</a>
            <div class="sidebar-section">Account</div>
            <a href="logout.php" class="sidebar-link"><span class="icon">↪</span> Logout</a>
        </nav>
        <div class="sidebar-footer">TicketNew v1.0</div>
    </aside>

    <!-- MAIN -->
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title">Dashboard</div>
            <div class="topbar-right">
                <div class="topbar-admin">
                    <div class="admin-avatar">A</div>
                    <span>Admin</span>
                </div>
            </div>
        </div>

        <div class="page-body">
            <!-- STATS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon red">🎟</div>
                    <div>
                        <div class="stat-value"><?= number_format($total_bookings) ?></div>
                        <div class="stat-label">Total Bookings</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">💰</div>
                    <div>
                        <div class="stat-value">₹<?= number_format($total_revenue, 0) ?></div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon blue">🎬</div>
                    <div>
                        <div class="stat-value"><?= number_format($total_movies) ?></div>
                        <div class="stat-label">Movies Listed</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange">👥</div>
                    <div>
                        <div class="stat-value"><?= number_format($total_users) ?></div>
                        <div class="stat-label">Registered Users</div>
                    </div>
                </div>
            </div>

            <!-- RECENT BOOKINGS -->
            <div class="card">
                <div class="card-header">
                    <h2>Recent Bookings</h2>
                    <a href="bookings.php" class="btn btn-outline btn-sm">View all</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Booking Ref</th>
                            <th>User</th>
                            <th>Movie</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_bookings)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center;color:#888;padding:32px;">No bookings yet</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_bookings as $b): ?>
                                <tr>
                                    <td style="font-weight:600;font-size:0.8rem;"><?= htmlspecialchars($b['booking_ref']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($b['user_name'] ?? '(deleted)') ?></td>
                                    <td><?= htmlspecialchars($b['title']) ?></td>
                                    <td style="font-weight:600;">₹<?= number_format($b['total_amount'], 2) ?></td>
                                    <td>
                                        <span
                                            class="badge <?= $b['payment_status'] === 'success' ? 'badge-success' : 'badge-danger' ?>">
                                            <?= ucfirst($b['payment_status']) ?>
                                        </span>
                                    </td>
                                    <td style="color:#888;font-size:0.8rem;">
                                        <?= date('d M Y, h:i A', strtotime($b['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>

</html>