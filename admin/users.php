<?php
// admin/users.php - User Management
require_once '../config/db.php';
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$users = $db->query("
    SELECT u.*, COUNT(b.id) AS booking_count, COALESCE(SUM(b.total_amount),0) AS total_spent
    FROM users u
    LEFT JOIN bookings b ON b.user_id = u.id
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Users - Admin TicketNew</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/responsive.css">
</head>

<body>

    <aside class="sidebar">
        <div class="sidebar-logo">🎫 <span>TICKE</span>NEW Admin</div>
        <nav class="sidebar-nav">
            <div class="sidebar-section">Main</div>
            <a href="index.php" class="sidebar-link"><span class="icon">📊</span> Dashboard</a>
            <a href="movies.php" class="sidebar-link"><span class="icon">🎬</span> Movies</a>
            <a href="theatres.php" class="sidebar-link"><span class="icon">🎭</span> Theatres</a>
            <a href="shows.php" class="sidebar-link"><span class="icon">🕐</span> Shows</a>
            <a href="seat_types.php" class="sidebar-link"><span class="icon">💺</span> Seat Types</a>
            <div class="sidebar-section">Business</div>
            <a href="bookings.php" class="sidebar-link"><span class="icon">📋</span> Bookings</a>
            <a href="users.php" class="sidebar-link active"><span class="icon">👥</span> Users</a>
            <a href="cities.php" class="sidebar-link"><span class="icon">🏙</span> Cities</a>
            <div class="sidebar-section">Account</div>
            <a href="logout.php" class="sidebar-link"><span class="icon">↪</span> Logout</a>
        </nav>
        <div class="sidebar-footer">TicketNew v1.0</div>
    </aside>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title">Users</div>
            <div class="topbar-right">
                <span style="font-size:0.85rem;color:#888;"><?= count($users) ?> registered</span>
            </div>
        </div>
        <div class="page-body">
            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Mobile</th>
                            <th>Email</th>
                            <th>State</th>
                            <th>Bookings</th>
                            <th>Total Spent</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center;color:#888;padding:32px;">No users yet</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td style="color:#888;"><?= $u['id'] ?></td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <div
                                                style="width:30px;height:30px;border-radius:50%;background:#1a1a2e;color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;">
                                                <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                            </div>
                                            <span style="font-weight:600;"><?= htmlspecialchars($u['name']) ?></span>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($u['mobile']) ?></td>
                                    <td style="font-size:0.82rem;color:#888;"><?= htmlspecialchars($u['email'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($u['state'] ?? '—') ?></td>
                                    <td style="text-align:center;">
                                        <span class="badge badge-info"><?= $u['booking_count'] ?></span>
                                    </td>
                                    <td style="font-weight:600;">₹<?= number_format($u['total_spent'], 0) ?></td>
                                    <td style="font-size:0.78rem;color:#888;"><?= date('d M Y', strtotime($u['created_at'])) ?>
                                    </td>
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