<?php
// admin/shows.php - Show Management (with Edit + Delete)
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
        $movie_id   = (int) $_POST['movie_id'];
        $theatre_id = (int) $_POST['theatre_id'];
        $from_date  = $_POST['from_date'];
        $to_date    = $_POST['to_date'];
        $show_time  = $_POST['show_time'];
        $format     = trim($_POST['format'] ?? '2D');
        $language   = trim($_POST['language'] ?? '');

        // Build array of all dates in range
        $date_range = [];
        $current    = new DateTime($from_date);
        $end        = new DateTime($to_date);
        while ($current <= $end) {
            $date_range[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }

        $templates       = $db->query("SELECT * FROM seat_type_templates ORDER BY sort_order, id")->fetch_all(MYSQLI_ASSOC);
        $tpl_count       = count($templates);
        $shows_created   = 0;
        $seats_generated = 0;

        // Generate batch code: use custom input or auto-generate FORMAT-MOVIEID-HHMMSS
        $batch_code = trim($_POST['code'] ?? '');
        if ($batch_code === '') {
            $batch_code = strtoupper($format) . '-' . $movie_id . '-' . date('His');
        }

        foreach ($date_range as $show_date) {
            $stmt = $db->prepare("INSERT INTO shows (movie_id,theatre_id,show_date,from_date,to_date,show_time,format,language,code) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('iisssssss',
                $movie_id, $theatre_id, $show_date,
                $from_date, $to_date,
                $show_time, $format, $language, $batch_code
            );
            $stmt->execute();
            $show_id = $db->insert_id;
            $shows_created++;

            // Auto-generate seats from seat_type_templates
            foreach ($templates as $tpl) {
                $cat_name  = $tpl['category_name'];
                $price     = (float) $tpl['price'];
                $row_list  = array_filter(array_map('trim', explode(',', $tpl['rows'])));
                $seats_per = (int) $tpl['seats_per_row'];
                $box_split = (int) $tpl['box_split'];

                $cs = $db->prepare("INSERT INTO seat_categories (show_id, category_name, price) VALUES (?, ?, ?)");
                $cs->bind_param('isd', $show_id, $cat_name, $price);
                $cs->execute();
                $cat_id = $db->insert_id;

                $si = $db->prepare("INSERT INTO seats (show_id, category_id, row_label, seat_number, status) VALUES (?, ?, ?, ?, 'available')");
                foreach ($row_list as $row) {
                    if ($box_split) {
                        $seat_nums = array_merge(range(1, 7), range(9, 14));
                    } else {
                        $seat_nums = range(1, $seats_per);
                    }
                    foreach ($seat_nums as $n) {
                        $si->bind_param('iisi', $show_id, $cat_id, $row, $n);
                        $si->execute();
                        $seats_generated++;
                    }
                }
            }
        }
        $msg = "$shows_created show(s) added from " . date('d M Y', strtotime($from_date)) . " to " . date('d M Y', strtotime($to_date)) . ". $tpl_count seat categories &amp; $seats_generated seats auto-generated per show.";
    }

    if ($action === 'edit' || $action === 'edit_group') {
        $id        = (int) $_POST['id'];
        $show_time = trim($_POST['show_time']);
        $format    = trim($_POST['format'] ?? '2D');
        $language  = trim($_POST['language'] ?? '');
        $code      = trim($_POST['code'] ?? '');

        // Sanitize comma-separated show_ids or fall back to single id
        $raw_ids  = trim($_POST['show_ids'] ?? '');
        $safe_ids = $raw_ids
            ? implode(',', array_filter(array_map('intval', explode(',', $raw_ids))))
            : (string)$id;

        // Update all shows in the group (time, type, language, code)
        $esc_time = mysqli_real_escape_string($db, $show_time);
        $esc_fmt  = mysqli_real_escape_string($db, $format);
        $esc_lang = mysqli_real_escape_string($db, $language);
        $esc_code = mysqli_real_escape_string($db, $code);
        $db->query("UPDATE shows SET show_time='$esc_time', format='$esc_fmt', language='$esc_lang', code='$esc_code' WHERE id IN ($safe_ids)");

        // Update seat category prices for the representative show
        foreach ($_POST as $key => $val) {
            if (strpos($key, 'cat_price_') === 0) {
                $cat_id = (int) substr($key, 10);
                $price  = (float) $val;
                $db->query("UPDATE seat_categories SET price=$price WHERE id=$cat_id AND show_id=$id");
            }
        }

        $count = count(array_filter(explode(',', $safe_ids)));
        $msg = "$count show(s) updated — Type: $format | Time: $show_time | Code: $code";
    }

    if ($action === 'delete') {
        $id = (int) $_POST['id'];
        $db->query("DELETE FROM booking_seats WHERE booking_id IN (SELECT id FROM bookings WHERE show_id=$id)");
        $db->query("DELETE FROM bookings WHERE show_id=$id");
        $db->query("DELETE FROM seats WHERE show_id=$id");
        $db->query("DELETE FROM seat_categories WHERE show_id=$id");
        $db->query("DELETE FROM shows WHERE id=$id");
        $msg = 'Show and all associated data deleted.';
    }

    if ($action === 'delete_group') {
        // Safely sanitize comma-separated show IDs
        $raw_ids   = $_POST['show_ids'] ?? '';
        $safe_ids  = implode(',', array_map('intval', explode(',', $raw_ids)));
        if ($safe_ids) {
            $db->query("DELETE FROM booking_seats WHERE booking_id IN (SELECT id FROM bookings WHERE show_id IN ($safe_ids))");
            $db->query("DELETE FROM bookings WHERE show_id IN ($safe_ids)");
            $db->query("DELETE FROM seats WHERE show_id IN ($safe_ids)");
            $db->query("DELETE FROM seat_categories WHERE show_id IN ($safe_ids)");
            $count = $db->query("SELECT COUNT(*) AS n FROM shows WHERE id IN ($safe_ids)")->fetch_assoc()['n'];
            $db->query("DELETE FROM shows WHERE id IN ($safe_ids)");
            $msg = "$count show(s) and all associated bookings/seats deleted.";
        }
    }
}

// Group shows by name + type + code + theatre + time → one row per batch
$shows = $db->query("
    SELECT
        MIN(s.id)                                    AS id,
        m.id                                         AS movie_id,
        t.id                                         AS theatre_id,
        m.title,
        t.name                                       AS theatre_name,
        COALESCE(s.format,'2D')                      AS format,
        COALESCE(s.language,'')                      AS language,
        COALESCE(MIN(s.from_date), MIN(s.show_date)) AS from_date,
        COALESCE(MAX(s.to_date),   MAX(s.show_date)) AS to_date,
        s.show_time,
        COALESCE(s.code,'')                          AS code,
        COUNT(s.id)                                  AS show_count,
        GROUP_CONCAT(s.id ORDER BY s.id)             AS show_ids
    FROM shows s
    JOIN movies   m ON m.id = s.movie_id
    JOIN theatres t ON t.id = s.theatre_id
    GROUP BY m.id, t.id, s.show_time, s.format, s.language, s.code
    ORDER BY MAX(s.show_date) DESC, s.show_time DESC
    LIMIT 100
")->fetch_all(MYSQLI_ASSOC);
$movies   = $db->query("SELECT id,title FROM movies WHERE is_now_showing=1 ORDER BY title")->fetch_all(MYSQLI_ASSOC);
$theatres = $db->query("SELECT id,name FROM theatres ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Build a price lookup using the first show_id of each group
$prices_raw = $db->query("SELECT show_id, category_name, price FROM seat_categories")->fetch_all(MYSQLI_ASSOC);
$prices = [];
foreach ($prices_raw as $p) { $prices[$p['show_id']][$p['category_name']] = $p['price']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Shows - Admin TicketNew</title>
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
        <a href="shows.php"      class="sidebar-link active"><span class="icon">🕐</span> Shows</a>
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
        <div class="topbar-title">Shows</div>
        <div class="topbar-right">
            <button class="btn btn-dark" onclick="document.getElementById('addModal').style.display='flex'">+ Add Show</button>
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
                        <th>ID</th><th>Type</th><th>Name</th><th>Theatre</th>
                        <th>From Date</th><th>To Date</th><th>Time</th><th>Language</th>
                        <th>Code</th>
                        <th>Seat Prices</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($shows as $s):
                    $sp = $prices[$s['id']] ?? [];
                    $show_count = (int)($s['show_count'] ?? 1);
                ?>
                <tr>
                    <td style="color:#888;">
                        <?= $s['id'] ?>
                        <?php if ($show_count > 1): ?>
                        <span style="display:block;font-size:0.68rem;color:#1a73e8;font-weight:600;"><?= $show_count ?> shows</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-info"><?= htmlspecialchars($s['format']) ?></span></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($s['title']) ?></td>
                    <td style="font-size:0.82rem;"><?= htmlspecialchars($s['theatre_name']) ?></td>
                    <td><?= date('d M Y', strtotime($s['from_date'])) ?></td>
                    <td><?= date('d M Y', strtotime($s['to_date'])) ?></td>
                    <td><?= date('h:i A', strtotime($s['show_time'])) ?></td>
                    <td><?= htmlspecialchars($s['language']) ?></td>
                    <td><code style="font-size:0.75rem;background:#f0f4ff;padding:2px 6px;border-radius:4px;color:#1a73e8;"><?= htmlspecialchars($s['code'] ?: '—') ?></code></td>
                    <td style="font-size:0.78rem;">
                        <?php foreach ($sp as $cat_name => $cat_price): ?>
                            <span style="display:inline-block;margin:1px 3px 1px 0;white-space:nowrap;">
                                <?= htmlspecialchars($cat_name) ?>: ₹<?= number_format($cat_price, 0) ?>
                            </span>
                        <?php endforeach; if (empty($sp)) echo '—'; ?>
                    </td>
                    <td style="white-space:nowrap;">
                        <?php
                        $show_cats = $db->query("SELECT id, category_name, price FROM seat_categories WHERE show_id={$s['id']} ORDER BY id")->fetch_all(MYSQLI_ASSOC);
                        ?>
                        <button class="btn btn-sm btn-icon" style="background:#e8f0fe;color:#1a73e8;margin-right:4px;"
                            onclick="openEdit(<?= htmlspecialchars(json_encode([
                                'id'           => $s['id'],
                                'movie'        => $s['title'],
                                'theatre'      => $s['theatre_name'],
                                'show_date'    => $s['from_date'],
                                'from_date'    => $s['from_date'],
                                'to_date'      => $s['to_date'],
                                'show_time'    => substr($s['show_time'],0,5),
                                'format'       => $s['format'],
                                'language'     => $s['language'],
                                'code'         => $s['code'],
                                'show_ids'     => $s['show_ids'],
                                'cats'         => $show_cats,
                            ])) ?>)">✏️</button>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="delete_group">
                            <input type="hidden" name="show_ids" value="<?= htmlspecialchars($s['show_ids']) ?>">
                            <button type="submit" class="btn btn-sm btn-icon btn-red"
                                onclick="return confirm('Delete all <?= $show_count ?> show(s) for <?= addslashes($s['title']) ?> (<?= date('d M', strtotime($s['from_date'])) ?> – <?= date('d M Y', strtotime($s['to_date'])) ?>) and all bookings/seats?')">🗑</button>
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
    <div style="background:#fff;border-radius:14px;padding:28px;width:520px;max-width:95vw;max-height:90vh;overflow-y:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2 style="font-size:1.1rem;font-weight:700;">Add Show</h2>
            <button onclick="document.getElementById('addModal').style.display='none'"
                style="background:none;border:1px solid #ddd;width:32px;height:32px;border-radius:50%;cursor:pointer;">✕</button>
        </div>
        <form method="POST" onsubmit="return validateAddForm()">
            <input type="hidden" name="action" value="add">
            <div class="form-group"><label>Movie *</label>
                <select class="form-control" name="movie_id" required>
                    <option value="">-- Select Movie --</option>
                    <?php foreach ($movies as $m): ?>
                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Theatre *</label>
                <select class="form-control" name="theatre_id" required>
                    <option value="">-- Select Theatre --</option>
                    <?php foreach ($theatres as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- FROM / TO DATE RANGE — inline, always visible -->
            <div style="background:#f5f7fa;border-radius:10px;padding:16px;margin-bottom:16px;">
                <div style="font-size:0.78rem;font-weight:700;color:#555;margin-bottom:12px;display:flex;align-items:center;gap:6px;">
                    📅 Show Date Range *
                </div>
                <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label style="font-size:0.72rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.5px;">From Date</label>
                        <input type="date" class="form-control" name="from_date" id="add_from_date"
                               value="<?= date('Y-m-d') ?>" required
                               onchange="updateDaysCount()">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label style="font-size:0.72rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.5px;">To Date</label>
                        <input type="date" class="form-control" name="to_date" id="add_to_date"
                               value="<?= date('Y-m-d') ?>" required
                               onchange="updateDaysCount()">
                    </div>
                </div>
                <div id="daysCountBadge" style="margin-top:10px;font-size:0.78rem;font-weight:600;color:#1a1a2e;display:none;
                     background:#e8eaf6;border-radius:6px;padding:6px 10px;display:inline-block;">
                    ⚡ 1 show will be created
                </div>
                <div id="dateRangeError" style="margin-top:8px;font-size:0.75rem;font-weight:600;color:#e31837;display:none;"></div>
            </div>

            <div class="form-grid">
                <div class="form-group"><label>Show Time *</label>
                    <select class="form-control" name="show_time" required>
                        <option value="07:00:00">07:00 AM</option>
                        <option value="10:00:00">10:00 AM</option>
                        <option value="13:00:00">01:00 PM</option>
                        <option value="16:00:00">04:00 PM</option>
                        <option value="19:00:00" selected>07:00 PM</option>
                        <option value="22:00:00">10:00 PM</option>
                    </select>
                </div>
                <div class="form-group"><label>Format</label>
                    <select class="form-control" name="format">
                        <option>2D</option><option>3D</option><option>IMAX</option><option>4DX</option>
                    </select>
                </div>
                <div class="form-group"><label>Language</label>
                    <input type="text" class="form-control" name="language" value="Tamil">
                </div>
                <div class="form-group"><label>Code <span style="font-size:0.72rem;color:#aaa;">(auto-generated if blank)</span></label>
                    <input type="text" class="form-control" name="code" placeholder="e.g. 2D-1-103000" maxlength="30">
                </div>
            </div>
            <?php
            $preview_tpls = $db->query("SELECT category_name, price FROM seat_type_templates ORDER BY sort_order, id")->fetch_all(MYSQLI_ASSOC);
            if ($preview_tpls):
                $parts = array_map(fn($t) => htmlspecialchars($t['category_name']) . ' ₹' . number_format($t['price'],0), $preview_tpls);
            ?>
            <p style="font-size:0.78rem;color:#888;margin-bottom:14px;">
                Seats auto-generated from templates: <?= implode(', ', $parts) ?>
            </p>
            <?php endif; ?>
            <button type="submit" class="btn btn-dark" style="width:100%;">Add Show</button>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:200;">
    <div style="background:#fff;border-radius:14px;padding:0;width:520px;max-width:95vw;max-height:90vh;overflow-y:auto;">

        <!-- Modal Header -->
        <div style="background:#1a1a2e;border-radius:14px 14px 0 0;padding:18px 24px;display:flex;align-items:center;justify-content:space-between;">
            <div>
                <div style="color:#fff;font-size:1rem;font-weight:700;">✏️ Edit Show</div>
                <div style="color:rgba(255,255,255,0.5);font-size:0.75rem;margin-top:2px;">Update show details below</div>
            </div>
            <button onclick="document.getElementById('editModal').style.display='none'"
                style="background:rgba(255,255,255,0.1);border:none;width:32px;height:32px;border-radius:50%;
                       color:#fff;cursor:pointer;font-size:1rem;line-height:1;">✕</button>
        </div>

        <div style="padding:24px;">
            <form method="POST">
                <input type="hidden" name="action" value="edit_group">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="show_ids" id="edit_show_ids">

                <!-- Read-only info: Movie + Theatre -->
                <div class="form-grid" style="margin-bottom:16px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Movie</label>
                        <div id="edit_movie_display" style="padding:10px 14px;background:#f5f7fa;border:1px solid #e8e8e8;
                             border-radius:8px;font-size:0.88rem;font-weight:600;color:#1a1a1a;"></div>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Theatre</label>
                        <div id="edit_theatre_display" style="padding:10px 14px;background:#f5f7fa;border:1px solid #e8e8e8;
                             border-radius:8px;font-size:0.88rem;color:#555;"></div>
                    </div>
                </div>

                <!-- From Date / To Date -->
                <div style="background:#f5f7fa;border-radius:10px;padding:14px;margin-bottom:16px;">
                    <div style="font-size:0.72rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;">📅 Show Date</div>
                    <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:0.72rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.5px;">From Date *</label>
                            <input type="date" class="form-control" name="show_date" id="edit_date" required>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:0.72rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.5px;">To Date</label>
                            <div id="edit_to_date_display" style="padding:10px 14px;background:#fff;border:1px solid #e8e8e8;
                                 border-radius:8px;font-size:0.88rem;color:#555;"></div>
                        </div>
                    </div>
                </div>

                <!-- Code -->
                <div class="form-group">
                    <label>Code</label>
                    <input type="text" class="form-control" name="code" id="edit_code" maxlength="30">
                </div>

                <!-- Time / Format / Language -->
                <div class="form-grid">
                    <div class="form-group"><label>Show Time *</label>
                        <select class="form-control" name="show_time" id="edit_time" required>
                            <option value="07:00">07:00 AM</option>
                            <option value="10:00">10:00 AM</option>
                            <option value="13:00">01:00 PM</option>
                            <option value="16:00">04:00 PM</option>
                            <option value="19:00">07:00 PM</option>
                            <option value="22:00">10:00 PM</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Format</label>
                        <select class="form-control" name="format" id="edit_format">
                            <option>2D</option><option>3D</option><option>IMAX</option><option>4DX</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Language</label>
                        <input type="text" class="form-control" name="language" id="edit_language">
                    </div>
                </div>

                <!-- Seat Prices -->
                <p style="font-size:0.78rem;font-weight:700;color:#333;margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px;">Seat Prices</p>
                <div id="edit_cat_prices" class="form-grid" style="grid-template-columns:repeat(3,1fr);"></div>
                <p style="font-size:0.75rem;color:#e31837;margin-bottom:16px;">⚠ Price changes affect only <em>available</em> seats.</p>

                <div style="display:flex;gap:10px;">
                    <button type="button" onclick="document.getElementById('editModal').style.display='none'"
                        class="btn" style="flex:1;background:#f5f5f5;color:#333;">Cancel</button>
                    <button type="submit" class="btn btn-dark" style="flex:2;">Update Show</button>
                </div>
            </form>
        </div>
    </div>
</div>


</div>


</body>
</html>

<script>
// ── Days count updater for Add form ──
function updateDaysCount() {
    var fromVal = document.getElementById('add_from_date').value;
    var toVal   = document.getElementById('add_to_date').value;
    var badge   = document.getElementById('daysCountBadge');
    var errDiv  = document.getElementById('dateRangeError');

    if (!fromVal || !toVal) { badge.style.display='none'; return; }

    var from = new Date(fromVal);
    var to   = new Date(toVal);
    var days = Math.round((to - from) / 86400000) + 1;

    errDiv.style.display = 'none';
    if (days < 1) {
        badge.style.display = 'none';
        errDiv.textContent  = '⚠ "To Date" must be on or after "From Date"';
        errDiv.style.display= 'block';
        return;
    }

    badge.textContent    = '⚡ ' + days + ' show' + (days > 1 ? 's' : '') + ' will be created (one per day)';
    badge.style.display  = 'inline-block';
}

function validateAddForm() {
    var fromVal = document.getElementById('add_from_date').value;
    var toVal   = document.getElementById('add_to_date').value;
    var errDiv  = document.getElementById('dateRangeError');

    if (!fromVal || !toVal) {
        errDiv.textContent   = '⚠ Please fill in both From and To dates.';
        errDiv.style.display = 'block';
        return false;
    }
    var days = Math.round((new Date(toVal) - new Date(fromVal)) / 86400000) + 1;
    if (days < 1) {
        errDiv.textContent   = '⚠ "To Date" must be on or after "From Date".';
        errDiv.style.display = 'block';
        return false;
    }
    return true;
}

// Init badge on page load
document.addEventListener('DOMContentLoaded', function() {
    updateDaysCount();
});

// ── Edit modal ──
function openEdit(s) {
    document.getElementById('edit_id').value       = s.id;
    document.getElementById('edit_show_ids').value = s.show_ids || s.id;
    document.getElementById('edit_date').value     = s.from_date || s.show_date;
    document.getElementById('edit_code').value     = s.code || '';

    // Match show_time (HH:MM) against the 6 option values
    var sel = document.getElementById('edit_time');
    var t = (s.show_time || '').substring(0,5); // "19:00"
    for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === t) { sel.selectedIndex = i; break; }
    }

    // Match format
    var fmtSel = document.getElementById('edit_format');
    for (var j = 0; j < fmtSel.options.length; j++) {
        if (fmtSel.options[j].value === s.format) { fmtSel.selectedIndex = j; break; }
    }

    document.getElementById('edit_language').value = s.language || '';

    // Read-only info
    document.getElementById('edit_movie_display').textContent   = s.movie   || '—';
    document.getElementById('edit_theatre_display').textContent = s.theatre || '—';

    // To Date display
    var toDate = s.to_date || s.from_date || s.show_date;
    var d = toDate ? new Date(toDate + 'T00:00:00') : null;
    document.getElementById('edit_to_date_display').textContent = d
        ? (d.getDate() + ' ' + ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][d.getMonth()] + ' ' + d.getFullYear())
        : '—';

    // Seat price fields
    var container = document.getElementById('edit_cat_prices');
    container.innerHTML = '';
    (s.cats || []).forEach(function(cat) {
        container.innerHTML +=
            '<div class="form-group">' +
            '<label>' + cat.category_name + ' (₹)</label>' +
            '<input type="number" step="0.01" class="form-control" name="cat_price_' + cat.id + '" value="' + parseFloat(cat.price).toFixed(2) + '">' +
            '</div>';
    });
    document.getElementById('editModal').style.display = 'flex';
}
</script>
</body>