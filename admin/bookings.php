<?php
// admin/bookings.php - Bookings Management (with Edit Status + Delete)
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

    if ($action === 'edit') {
        $id             = (int) $_POST['id'];
        $payment_status = trim($_POST['payment_status'] ?? 'pending');
        $payment_method = trim($_POST['payment_method'] ?? '');
        $allowed = ['pending','success','failed','refunded'];
        if (!in_array($payment_status, $allowed)) $payment_status = 'pending';
        $stmt = $db->prepare("UPDATE bookings SET payment_status=?, payment_method=? WHERE id=?");
        $stmt->bind_param('ssi', $payment_status, $payment_method, $id);
        $stmt->execute();
        $msg = "Booking updated.";
    }

    if ($action === 'delete') {
        $id = (int) $_POST['id'];
        // Release seats back to available
        $stmtRelease = $db->prepare("UPDATE seats SET status='available' WHERE id IN (SELECT seat_id FROM booking_seats WHERE booking_id=?)");
        $stmtRelease->bind_param('i', $id);
        $stmtRelease->execute();
        $stmtDelBS = $db->prepare("DELETE FROM booking_seats WHERE booking_id=?");
        $stmtDelBS->bind_param('i', $id);
        $stmtDelBS->execute();
        $stmtDelB = $db->prepare("DELETE FROM bookings WHERE id=?");
        $stmtDelB->bind_param('i', $id);
        $stmtDelB->execute();
        $msg = "Booking deleted and seats released.";
    }
}

// Filters
$filter_status = $_GET['status'] ?? '';
$filter_search = trim($_GET['search'] ?? '');

$sql = "
    SELECT b.*, m.title, u.name AS user_name, u.mobile,
           s.show_date, s.show_time, t.name AS theatre_name
    FROM bookings b
    JOIN shows     s ON s.id = b.show_id
    JOIN movies    m ON m.id = s.movie_id
    JOIN theatres  t ON t.id = s.theatre_id
    LEFT JOIN users     u ON u.id = b.user_id
    WHERE 1=1";
$bind_types = '';
$bind_vals  = [];

if ($filter_status) {
    $sql .= " AND b.payment_status = ?";
    $bind_types .= 's';
    $bind_vals[] = $filter_status;
}
if ($filter_search) {
    $sql .= " AND (b.booking_ref LIKE ? OR u.name LIKE ? OR u.mobile LIKE ? OR m.title LIKE ?)";
    $like = '%' . $filter_search . '%';
    $bind_types .= 'ssss';
    $bind_vals = array_merge($bind_vals, [$like, $like, $like, $like]);
}
$sql .= " ORDER BY b.created_at DESC";

$stmt_list = $db->prepare($sql);
if ($bind_types) {
    $stmt_list->bind_param($bind_types, ...$bind_vals);
}
$stmt_list->execute();
$bookings = $stmt_list->get_result()->fetch_all(MYSQLI_ASSOC);

// Seat labels per booking
$seat_labels = [];
if (!empty($bookings)) {
    $bids = implode(',', array_map('intval', array_column($bookings, 'id')));
    $rows = $db->query("SELECT booking_id, seat_label FROM booking_seats WHERE booking_id IN ($bids)")->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as $r) { $seat_labels[$r['booking_id']][] = $r['seat_label']; }
}

$status_counts = [];
$res = $db->query("SELECT payment_status, COUNT(*) as cnt FROM bookings GROUP BY payment_status");
while ($r = $res->fetch_assoc()) $status_counts[$r['payment_status']] = $r['cnt'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Bookings - Admin TicketNew</title>
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
        <a href="theatres.php"   class="sidebar-link"><span class="icon">🎭</span> Theatres</a>
        <a href="shows.php"      class="sidebar-link"><span class="icon">🕐</span> Shows</a>
        <a href="seat_types.php" class="sidebar-link"><span class="icon">💺</span> Seat Types</a>
        <div class="sidebar-section">Business</div>
        <a href="bookings.php"   class="sidebar-link active"><span class="icon">📋</span> Bookings</a>
        <a href="users.php"      class="sidebar-link"><span class="icon">👥</span> Users</a>
        <a href="cities.php"     class="sidebar-link"><span class="icon">🏙</span> Cities</a>
        <div class="sidebar-section">Account</div>
        <a href="logout.php"     class="sidebar-link"><span class="icon">↪</span> Logout</a>
    </nav>
    <div class="sidebar-footer">TicketNew v1.0</div>
</aside>

<div class="main-content">
    <div class="topbar">
        <div class="topbar-title">Bookings</div>
        <div class="topbar-right">
            <span style="font-size:0.85rem;color:#888;"><?= count($bookings) ?> shown</span>
        </div>
    </div>
    <div class="page-body">

        <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_type ?>">
            <?= $msg_type==='success'?'✓':'⚠' ?> <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; ?>

        <!-- Stats + Filters -->
        <div style="display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap;align-items:center;">
            <a href="bookings.php" class="btn btn-sm <?= !$filter_status?'btn-dark':'' ?>"
                style="<?= !$filter_status?'':'background:#f5f5f5;color:#333;' ?>">All (<?= array_sum($status_counts) ?>)</a>
            <a href="?status=success" class="btn btn-sm" style="background:<?= $filter_status==='success'?'#1d8a4e':'#e8f5e9' ?>;color:<?= $filter_status==='success'?'#fff':'#1d8a4e' ?>;">
                ✅ Success (<?= $status_counts['success'] ?? 0 ?>)</a>
            <a href="?status=pending" class="btn btn-sm" style="background:<?= $filter_status==='pending'?'#e07b00':'#fff8e1' ?>;color:<?= $filter_status==='pending'?'#fff':'#e07b00' ?>;">
                ⏳ Pending (<?= $status_counts['pending'] ?? 0 ?>)</a>
            <a href="?status=failed" class="btn btn-sm" style="background:<?= $filter_status==='failed'?'#e31837':'#fdecea' ?>;color:<?= $filter_status==='failed'?'#fff':'#e31837' ?>;">
                ❌ Failed (<?= $status_counts['failed'] ?? 0 ?>)</a>
            <a href="?status=refunded" class="btn btn-sm" style="background:<?= $filter_status==='refunded'?'#555':'#eee' ?>;color:<?= $filter_status==='refunded'?'#fff':'#555' ?>;">
                ↩ Refunded (<?= $status_counts['refunded'] ?? 0 ?>)</a>
            <form method="GET" style="margin-left:auto;display:flex;gap:6px;">
                <?php if ($filter_status): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>"><?php endif; ?>
                <input type="text" name="search" placeholder="Search ref, user, movie..." class="form-control"
                    style="width:220px;height:36px;font-size:0.82rem;" value="<?= htmlspecialchars($filter_search) ?>">
                <button type="submit" class="btn btn-dark" style="height:36px;padding:0 14px;">🔍</button>
            </form>
        </div>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Ref</th>
                        <th>User</th>
                        <th>Movie</th>
                        <th>Theatre</th>
                        <th>Show</th>
                        <th>Seats</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Booked</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($bookings)): ?>
                    <tr><td colspan="11" style="text-align:center;color:#888;padding:32px;">No bookings found</td></tr>
                <?php else: ?>
                <?php foreach ($bookings as $b):
                    $seats = implode(', ', $seat_labels[$b['id']] ?? []);
                    $status_badge = [
                        'success'  => 'badge-success',
                        'pending'  => 'badge-warning',
                        'failed'   => 'badge-danger',
                        'refunded' => 'badge-secondary',
                    ][$b['payment_status']] ?? 'badge-info';
                ?>
                <tr>
                    <td style="font-weight:700;font-size:0.78rem;color:#e31837;"><?= htmlspecialchars($b['booking_ref']) ?></td>
                    <td>
                        <div style="font-weight:600;font-size:0.85rem;"><?= htmlspecialchars($b['user_name'] ?? '(deleted)') ?></div>
                        <div style="font-size:0.75rem;color:#888;"><?= htmlspecialchars($b['mobile'] ?? '—') ?></div>
                    </td>
                    <td><?= htmlspecialchars($b['title']) ?></td>
                    <td style="font-size:0.82rem;"><?= htmlspecialchars($b['theatre_name']) ?></td>
                    <td style="font-size:0.8rem;">
                        <?= date('d M', strtotime($b['show_date'])) ?><br>
                        <span style="color:#888;"><?= date('h:i A', strtotime($b['show_time'])) ?></span>
                    </td>
                    <td style="font-size:0.78rem;color:#555;"><?= $seats ?: '—' ?></td>
                    <td style="font-weight:700;">₹<?= number_format($b['total_amount'],2) ?></td>
                    <td style="font-size:0.8rem;text-transform:capitalize;"><?= str_replace('_',' ',$b['payment_method']) ?></td>
                    <td><span class="badge <?= $status_badge ?>"><?= ucfirst($b['payment_status']) ?></span></td>
                    <td style="font-size:0.78rem;color:#888;"><?= date('d M Y', strtotime($b['created_at'])) ?></td>
                    <td style="white-space:nowrap;">
                        <!-- Edit button -->
                        <button class="btn btn-sm btn-icon" style="background:#e8f0fe;color:#1a73e8;margin-right:4px;"
                            onclick="openEdit(<?= htmlspecialchars(json_encode([
                                'id'             => $b['id'],
                                'booking_ref'    => $b['booking_ref'],
                                'payment_status' => $b['payment_status'],
                                'payment_method' => $b['payment_method'],
                            ])) ?>)">✏️</button>
                        <!-- Delete button -->
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $b['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-icon btn-red"
                                onclick="return confirm('Delete booking <?= addslashes($b['booking_ref']) ?>? Seats will be released.')">🗑</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- EDIT BOOKING MODAL -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:200;">
    <div style="background:#fff;border-radius:14px;padding:28px;width:440px;max-width:95vw;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2 style="font-size:1.1rem;font-weight:700;">Edit Booking</h2>
            <button onclick="document.getElementById('editModal').style.display='none'"
                style="background:none;border:1px solid #ddd;width:32px;height:32px;border-radius:50%;cursor:pointer;">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label>Booking Ref</label>
                <input type="text" class="form-control" id="edit_ref" readonly
                    style="background:#f5f5f5;color:#888;cursor:not-allowed;">
            </div>
            <div class="form-group">
                <label>Payment Method</label>
                <select class="form-control" name="payment_method" id="edit_method">
                    <option value="">-- Select --</option>
                    <option value="upi">UPI</option>
                    <option value="card">Card</option>
                    <option value="wallet">Wallet</option>
                    <option value="netbanking">Net Banking</option>
                    <option value="paylater">Pay Later</option>
                    <option value="cash">Cash</option>
                </select>
            </div>
            <div class="form-group">
                <label>Payment Status *</label>
                <select class="form-control" name="payment_status" id="edit_status" required>
                    <option value="pending">Pending</option>
                    <option value="success">Success</option>
                    <option value="failed">Failed</option>
                    <option value="refunded">Refunded</option>
                </select>
            </div>
            <div style="background:#fff8e1;border:1px solid #ffc107;border-radius:8px;padding:10px 12px;margin-bottom:16px;font-size:0.8rem;color:#7a5c00;">
                ⚠ Changing status to <strong>Refunded</strong> or <strong>Failed</strong> does NOT automatically release seats. Use Delete to release seats.
            </div>
            <div style="display:flex;gap:10px;">
                <button type="button" onclick="document.getElementById('editModal').style.display='none'"
                    class="btn" style="flex:1;background:#f5f5f5;color:#333;">Cancel</button>
                <button type="submit" class="btn btn-dark" style="flex:2;">Update Booking</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(b) {
    document.getElementById('edit_id').value     = b.id;
    document.getElementById('edit_ref').value    = b.booking_ref;
    document.getElementById('edit_method').value = b.payment_method || '';
    document.getElementById('edit_status').value = b.payment_status;
    document.getElementById('editModal').style.display = 'flex';
}
</script>
</body>
</html>
