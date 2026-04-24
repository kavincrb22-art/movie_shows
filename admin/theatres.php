<?php
// admin/theatres.php - Theatre Management (with Edit + Delete)
require_once '../config/db.php';
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$db  = getDB();
$msg = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name    = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city_id = (int) ($_POST['city_id'] ?? 1);
        $dist    = (float) ($_POST['distance_from_center'] ?? 0);
        $cancel  = isset($_POST['is_cancellable']) ? 1 : 0;
        if ($name) {
            $stmt = $db->prepare("INSERT INTO theatres (name,address,city_id,distance_from_center,is_cancellable) VALUES (?,?,?,?,?)");
            $stmt->bind_param('ssidi', $name, $address, $city_id, $dist, $cancel);
            $stmt->execute();
            $msg = "Theatre added.";
        }
    }

    if ($action === 'edit') {
        $id      = (int) $_POST['id'];
        $name    = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city_id = (int) ($_POST['city_id'] ?? 1);
        $dist    = (float) ($_POST['distance_from_center'] ?? 0);
        $cancel  = isset($_POST['is_cancellable']) ? 1 : 0;
        $stmt = $db->prepare("UPDATE theatres SET name=?, address=?, city_id=?, distance_from_center=?, is_cancellable=? WHERE id=?");
        $stmt->bind_param('ssidii', $name, $address, $city_id, $dist, $cancel, $id);
        $stmt->execute();
        $msg = "Theatre updated.";
    }

    if ($action === 'delete') {
        $id = (int) $_POST['id'];
        $check = $db->query("SELECT COUNT(*) as cnt FROM shows WHERE theatre_id=$id")->fetch_assoc();
        if ($check['cnt'] > 0) {
            $msg = "Cannot delete: This theatre has {$check['cnt']} show(s). Delete those shows first.";
            $msg_type = 'danger';
        } else {
            $db->query("DELETE FROM theatres WHERE id=$id");
            $msg = 'Theatre deleted.';
        }
    }
}

$theatres = $db->query("
    SELECT t.*, c.name AS city_name
    FROM theatres t LEFT JOIN cities c ON c.id = t.city_id
    ORDER BY t.name
")->fetch_all(MYSQLI_ASSOC);
$cities = $db->query("SELECT * FROM cities ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Theatres - Admin TicketNew</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/responsive.css">
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-logo">🎫 <span>TICKE</span>NEW Admin</div>
    <nav class="sidebar-nav">
        <div class="sidebar-section">Main</div>
        <a href="index.php"      class="sidebar-link"><span class="icon">📊</span> Dashboard</a>
        <a href="movies.php"     class="sidebar-link"><span class="icon">🎬</span> Movies</a>
        <a href="theatres.php"   class="sidebar-link active"><span class="icon">🎭</span> Theatres</a>
        <a href="shows.php"      class="sidebar-link"><span class="icon">🕐</span> Shows</a>
        <a href="seat_types.php" class="sidebar-link"><span class="icon">💺</span> Seat Types</a>
        <div class="sidebar-section">Business</div>
        <a href="bookings.php"   class="sidebar-link"><span class="icon">📋</span> Bookings</a>
        <a href="users.php"      class="sidebar-link"><span class="icon">👥</span> Users</a>
        <a href="cities.php"     class="sidebar-link"><span class="icon">🏙</span> Cities</a>
        <div class="sidebar-section">Account</div>
        <a href="logout.php"     class="sidebar-link"><span class="icon">↪</span> Logout</a>
    </nav>
    <div class="sidebar-footer">TicketNew v1.0</div>
</aside>

<div class="main-content">
    <div class="topbar">
        <div class="topbar-title">Theatres</div>
        <div class="topbar-right">
            <button class="btn btn-dark" onclick="document.getElementById('addModal').style.display='flex'">+ Add Theatre</button>
        </div>
    </div>
    <div class="page-body">
        <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_type ?>">
            <?= $msg_type==='success'?'✓':'⚠' ?> <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; ?>
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th><th>Name</th><th>Address</th><th>City</th>
                        <th>Distance</th><th>Cancellable</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($theatres as $t): ?>
                <tr>
                    <td style="color:#888;"><?= $t['id'] ?></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($t['name']) ?></td>
                    <td style="font-size:0.82rem;"><?= htmlspecialchars($t['address']) ?></td>
                    <td><?= htmlspecialchars($t['city_name'] ?? '—') ?></td>
                    <td><?= $t['distance_from_center'] ?> km</td>
                    <td><?= $t['is_cancellable'] ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-danger">No</span>' ?></td>
                    <td style="white-space:nowrap;">
                        <button class="btn btn-sm btn-icon" style="background:#e8f0fe;color:#1a73e8;margin-right:4px;"
                            onclick="openEdit(<?= htmlspecialchars(json_encode($t)) ?>)">✏️</button>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-icon btn-red"
                                onclick="return confirm('Delete theatre?')">🗑</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ADD MODAL -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:200;">
    <div style="background:#fff;border-radius:14px;padding:28px;width:500px;max-width:95vw;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2 style="font-size:1.1rem;font-weight:700;">Add Theatre</h2>
            <button onclick="document.getElementById('addModal').style.display='none'"
                style="background:none;border:1px solid #ddd;width:32px;height:32px;border-radius:50%;cursor:pointer;">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group"><label>Theatre Name *</label><input type="text" class="form-control" name="name" required></div>
            <div class="form-group"><label>Address</label><input type="text" class="form-control" name="address"></div>
            <div class="form-group"><label>City</label>
                <select class="form-control" name="city_id">
                    <?php foreach ($cities as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-grid">
                <div class="form-group"><label>Distance from Center (km)</label><input type="number" step="0.1" class="form-control" name="distance_from_center" value="1.0"></div>
                <div class="form-group" style="padding-top:26px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_cancellable" checked> Tickets Cancellable
                    </label>
                </div>
            </div>
            <button type="submit" class="btn btn-dark" style="width:100%;">Add Theatre</button>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:200;">
    <div style="background:#fff;border-radius:14px;padding:28px;width:500px;max-width:95vw;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2 style="font-size:1.1rem;font-weight:700;">Edit Theatre</h2>
            <button onclick="document.getElementById('editModal').style.display='none'"
                style="background:none;border:1px solid #ddd;width:32px;height:32px;border-radius:50%;cursor:pointer;">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group"><label>Theatre Name *</label><input type="text" class="form-control" name="name" id="edit_name" required></div>
            <div class="form-group"><label>Address</label><input type="text" class="form-control" name="address" id="edit_address"></div>
            <div class="form-group"><label>City</label>
                <select class="form-control" name="city_id" id="edit_city">
                    <?php foreach ($cities as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-grid">
                <div class="form-group"><label>Distance from Center (km)</label>
                    <input type="number" step="0.1" class="form-control" name="distance_from_center" id="edit_dist">
                </div>
                <div class="form-group" style="padding-top:26px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_cancellable" id="edit_cancel"> Tickets Cancellable
                    </label>
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:4px;">
                <button type="button" onclick="document.getElementById('editModal').style.display='none'"
                    class="btn" style="flex:1;background:#f5f5f5;color:#333;">Cancel</button>
                <button type="submit" class="btn btn-dark" style="flex:2;">Update Theatre</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(t) {
    document.getElementById('edit_id').value      = t.id;
    document.getElementById('edit_name').value    = t.name;
    document.getElementById('edit_address').value = t.address || '';
    document.getElementById('edit_city').value    = t.city_id;
    document.getElementById('edit_dist').value    = t.distance_from_center;
    document.getElementById('edit_cancel').checked = t.is_cancellable == 1;
    document.getElementById('editModal').style.display = 'flex';
}
</script>
</body>
</html>
