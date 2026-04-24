<?php
// admin/cities.php - City Management
require_once '../config/db.php';
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $popular = isset($_POST['is_popular']) ? 1 : 0;
        $stmt = $db->prepare("INSERT INTO cities (name, is_popular) VALUES (?,?)");
        $stmt->bind_param('si', $name, $popular);
        $stmt->execute();
        $msg = 'City added.';
    }
    if ($action === 'delete') {
        $id = (int) $_POST['id'];
        $db->query("DELETE FROM cities WHERE id=$id");
        $msg = 'City deleted.';
    }
    if ($action === 'toggle') {
        $id = (int) $_POST['id'];
        $val = (int) $_POST['val'];
        $db->query("UPDATE cities SET is_popular=$val WHERE id=$id");
        $msg = 'City updated.';
    }
}

$cities = $db->query("SELECT * FROM cities ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Cities - Admin TicketNew</title>
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
            <a href="users.php" class="sidebar-link"><span class="icon">👥</span> Users</a>
            <a href="cities.php" class="sidebar-link active"><span class="icon">🏙</span> Cities</a>
            <div class="sidebar-section">Account</div>
            <a href="logout.php" class="sidebar-link"><span class="icon">↪</span> Logout</a>
        </nav>
        <div class="sidebar-footer">TicketNew v1.0</div>
    </aside>
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title">Cities</div>
            <div class="topbar-right">
                <button class="btn btn-dark" onclick="document.getElementById('addModal').style.display='flex'">+ Add
                    City</button>
            </div>
        </div>
        <div class="page-body">
            <?php if ($msg): ?>
                <div class="alert alert-success">✓ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>City Name</th>
                            <th>Popular</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cities as $c): ?>
                            <tr>
                                <td style="color:#888;"><?= $c['id'] ?></td>
                                <td style="font-weight:600;"><?= htmlspecialchars($c['name']) ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <input type="hidden" name="val" value="<?= $c['is_popular'] ? 0 : 1 ?>">
                                        <button type="submit"
                                            class="badge <?= $c['is_popular'] ? 'badge-success' : 'badge-warning' ?>"
                                            style="cursor:pointer;border:none;">
                                            <?= $c['is_popular'] ? '⭐ Popular' : 'Normal' ?>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-icon btn-red"
                                            onclick="return confirm('Delete?')">🗑</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="addModal"
        style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:200;">
        <div style="background:#fff;border-radius:14px;padding:28px;width:380px;max-width:95vw;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h2 style="font-size:1.1rem;font-weight:700;">Add City</h2>
                <button onclick="document.getElementById('addModal').style.display='none'"
                    style="background:none;border:1px solid #ddd;width:32px;height:32px;border-radius:50%;cursor:pointer;">✕</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group"><label>City Name *</label><input type="text" class="form-control" name="name"
                        required placeholder="e.g. Chennai"></div>
                <div class="form-group" style="padding-top:4px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_popular"> Mark as Popular City
                    </label>
                </div>
                <button type="submit" class="btn btn-dark" style="width:100%;margin-top:8px;">Add City</button>
            </form>
        </div>
    </div>
</body>

</html>