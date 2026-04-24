<?php
// admin/seat_types.php – Seat Type Templates + Vehicle Images Management
require_once '../config/db.php';
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$db  = getDB();
$msg = '';
$msg_type = 'success';

/* ── Auto-create vehicle_images table if missing ── */
$db->query("CREATE TABLE IF NOT EXISTS `vehicle_images` (
  `seat_count` tinyint     NOT NULL,
  `label`      varchar(60) NOT NULL DEFAULT '',
  `img_path`   varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`seat_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ── Seed rows 1–10 if missing ── */
$def_labels = [1=>'Bicycle',2=>'Scooter',3=>'Auto Rickshaw',4=>'Mini Car',
               5=>'Sedan',6=>'SUV',7=>'Van',8=>'Van',9=>'Van',10=>'Van'];
for ($i = 1; $i <= 10; $i++) {
    $lbl  = $def_labels[$i];
    $path = $i <= 7 ? "assets/vehicle_{$i}.png" : "assets/vehicle_7.png";
    $db->query("INSERT IGNORE INTO vehicle_images (seat_count,label,img_path) VALUES ($i,'$lbl','$path')");
}

$upload_dir = dirname(__DIR__) . '/assets/';

/* ════════════════════════════════
   POST handlers
════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ── add seat template ── */
    if ($action === 'add') {
        $cat_name   = trim($_POST['category_name'] ?? '');
        $price      = (float)($_POST['price'] ?? 0);
        $rows       = strtoupper(trim($_POST['rows'] ?? ''));
        $seats_per  = (int)($_POST['seats_per_row'] ?? 10);
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $box_split  = isset($_POST['box_split']) ? 1 : 0;
        if ($cat_name && $price > 0 && $rows) {
            $stmt = $db->prepare("INSERT INTO seat_type_templates
                (category_name,price,`rows`,seats_per_row,sort_order,box_split)
                VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('sdssii', $cat_name, $price, $rows, $seats_per, $sort_order, $box_split);
            $stmt->execute();
            $msg = "Seat type \"$cat_name\" added.";
        } else { $msg = 'Please fill all required fields.'; $msg_type = 'danger'; }
    }

    /* ── edit seat template ── */
    if ($action === 'edit') {
        $id         = (int)$_POST['id'];
        $cat_name   = trim($_POST['category_name'] ?? '');
        $price      = (float)($_POST['price'] ?? 0);
        $rows       = strtoupper(trim($_POST['rows'] ?? ''));
        $seats_per  = (int)($_POST['seats_per_row'] ?? 10);
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $box_split  = isset($_POST['box_split']) ? 1 : 0;
        $stmt = $db->prepare("UPDATE seat_type_templates
            SET category_name=?,price=?,`rows`=?,seats_per_row=?,sort_order=?,box_split=?
            WHERE id=?");
        $stmt->bind_param('sdssiii', $cat_name, $price, $rows, $seats_per, $sort_order, $box_split, $id);
        $stmt->execute();
        $msg = 'Seat type updated.';
    }

    /* ── delete seat template ── */
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("DELETE FROM seat_type_templates WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $msg = 'Seat type deleted.';
    }

    /* ── vehicle image: upload / label-only save ── */
    if ($action === 'vehicle_upload') {
        $seat_count = (int)($_POST['seat_count'] ?? 0);
        $label      = trim($_POST['label'] ?? '');
        if ($seat_count < 1 || $seat_count > 10) goto end_post;

        $has_file = isset($_FILES['vehicle_img']) && $_FILES['vehicle_img']['error'] === UPLOAD_ERR_OK;

        if ($has_file) {
            $file    = $_FILES['vehicle_img'];
            $allowed = ['image/png','image/jpeg','image/gif','image/webp','image/svg+xml'];
            if (!in_array($file['type'], $allowed)) {
                $msg = 'Invalid file type. Allowed: PNG, JPG, GIF, WEBP, SVG.';
                $msg_type = 'danger';
                goto end_post;
            }
            if ($file['size'] > 5 * 1024 * 1024) {
                $msg = 'File too large (max 5 MB).';
                $msg_type = 'danger';
                goto end_post;
            }
            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'png');
            $filename = "vehicle_{$seat_count}.{$ext}";
            $dest     = $upload_dir . $filename;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $img_path = "assets/$filename";
                $stmt = $db->prepare("UPDATE vehicle_images SET img_path=?,label=? WHERE seat_count=?");
                $stmt->bind_param('ssi', $img_path, $label, $seat_count);
                $stmt->execute();
                regenerateVehicleJS($db, $upload_dir);
                $msg = "Vehicle image for {$seat_count} seat(s) updated.";
            } else {
                $msg = 'Upload failed – check write permissions on assets/ folder.';
                $msg_type = 'danger';
            }
        } else {
            // Label-only update (no file chosen)
            $stmt = $db->prepare("UPDATE vehicle_images SET label=? WHERE seat_count=?");
            $stmt->bind_param('si', $label, $seat_count);
            $stmt->execute();
            $msg = "Label for {$seat_count} seat(s) updated.";
        }
    }

    /* ── vehicle image: delete ── */
    if ($action === 'vehicle_delete') {
        $seat_count = (int)($_POST['seat_count'] ?? 0);
        if ($seat_count >= 1 && $seat_count <= 10) {
            $row = $db->query("SELECT img_path FROM vehicle_images WHERE seat_count=$seat_count")->fetch_assoc();
            if ($row && $row['img_path']) {
                $full = dirname(__DIR__) . '/' . $row['img_path'];
                if (file_exists($full)) @unlink($full);
            }
            $empty = '';
            $stmt = $db->prepare("UPDATE vehicle_images SET img_path=? WHERE seat_count=?");
            $stmt->bind_param('si', $empty, $seat_count);
            $stmt->execute();
            regenerateVehicleJS($db, $upload_dir);
            $msg = "Vehicle image for {$seat_count} seat(s) removed.";
        }
    }

    end_post:;
}

/* ── Rewrite VEHICLES block in seats.js ── */
function regenerateVehicleJS($db, $upload_dir) {
    $rows = $db->query("SELECT seat_count,label,img_path FROM vehicle_images ORDER BY seat_count")->fetch_all(MYSQLI_ASSOC);
    $entries = [];
    foreach ($rows as $r) {
        $n   = (int)$r['seat_count'];
        $lbl = addslashes($r['label']);
        $p   = $r['img_path'] ? addslashes($r['img_path']) : 'assets/vehicle_placeholder.png';
        $entries[] = "    $n: /* $lbl */\n    '<img src=\"$p\" alt=\"$lbl\" class=\"scm-vehicle-img\">'";
    }
    $block = "  /* ── PNG vehicle images (managed via admin) ── */\n  var VEHICLES = {\n"
           . implode(",\n", $entries) . "\n  };";

    $js_path = realpath(dirname($upload_dir)) . '/js/seats.js';
    if (!$js_path || !file_exists($js_path)) return;
    $js = file_get_contents($js_path);
    $js = preg_replace(
        '/\/\* ──[^*]*(?:SVG|PNG)[^*]*vehicle[^*]*── \*\/\s*var VEHICLES\s*=\s*\{.*?\};/s',
        $block,
        $js
    );
    file_put_contents($js_path, $js);
}

/* ── Fetch all data ── */
$templates   = $db->query("SELECT * FROM seat_type_templates ORDER BY sort_order,id")->fetch_all(MYSQLI_ASSOC);
$vehicles    = $db->query("SELECT * FROM vehicle_images ORDER BY seat_count")->fetch_all(MYSQLI_ASSOC);
$vmap = [];
foreach ($vehicles as $v) $vmap[(int)$v['seat_count']] = $v;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Seat Types – Admin TicketNew</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/admin.css">
<link rel="stylesheet" href="../css/responsive.css">
<style>
/* ── vehicle image grid ── */
.vi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(175px,1fr));gap:14px;padding:18px 20px 20px}
.vi-card{border:2px solid #ebebeb;border-radius:14px;background:#fff;overflow:hidden;transition:box-shadow .18s,border-color .18s}
.vi-card:hover{box-shadow:0 6px 22px rgba(0,0,0,.1);border-color:#e31837}
.vi-card-img{background:#f7f8fc;height:116px;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden}
.vi-card-img img{max-width:90%;max-height:108px;object-fit:contain}
.vi-badge{position:absolute;top:8px;left:8px;background:#e31837;color:#fff;border-radius:20px;font-size:.7rem;font-weight:700;padding:2px 8px}
.vi-no-img{display:flex;flex-direction:column;align-items:center;gap:4px;color:#ccc;font-size:.8rem;padding:8px}
.vi-no-img i{font-size:2rem}
.vi-body{padding:10px 12px 12px}
.vi-label{font-weight:600;font-size:.85rem;color:#1a1a2e;margin-bottom:8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.vi-actions{display:flex;gap:6px}
.vi-btn-edit{flex:1;padding:7px 0;background:#e8f0fe;color:#1a73e8;border:none;border-radius:8px;font-size:.78rem;font-weight:600;cursor:pointer;transition:background .15s}
.vi-btn-edit:hover{background:#c5d8ff}
.vi-btn-del{padding:7px 10px;background:#fdecea;color:#e31837;border:none;border-radius:8px;font-size:.8rem;font-weight:700;cursor:pointer;transition:background .15s}
.vi-btn-del:hover{background:#f9c5c5}
.vi-btn-del:disabled{opacity:.3;cursor:not-allowed}

/* ── upload drop zone ── */
.upload-drop{border:2px dashed #ddd;border-radius:12px;padding:26px 14px;text-align:center;cursor:pointer;transition:border-color .18s,background .18s;position:relative}
.upload-drop:hover,.upload-drop.drag-over{border-color:#e31837;background:#fff5f5}
.upload-drop input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.upload-preview{margin-top:10px;display:none}
.upload-preview img{max-width:180px;max-height:100px;border-radius:8px;border:1px solid #eee;object-fit:contain}
.cur-img-box{background:#f7f8fc;border-radius:10px;padding:10px;text-align:center;margin-bottom:14px;min-height:70px;display:flex;flex-direction:column;align-items:center;justify-content:center}
.cur-img-box img{max-height:80px;max-width:100%;object-fit:contain}

/* ── card section header ── */
.card-section-hdr{padding:16px 20px 14px;font-weight:700;font-size:.95rem;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;justify-content:space-between}
</style>
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
    <a href="seat_types.php" class="sidebar-link active"><span class="icon">💺</span> Seat Types</a>
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
    <div class="topbar-title">Seat Types</div>
    <div class="topbar-right">
      <button class="btn btn-dark" onclick="show('addModal')">+ Add Seat Type</button>
    </div>
  </div>

  <div class="page-body">

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type ?>">
      <?= $msg_type === 'success' ? '✓' : '⚠' ?> <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:10px;padding:13px 17px;margin-bottom:18px;font-size:.83rem;color:#7a5c00;">
      <strong>ℹ️ How it works:</strong> Templates define seat layout for new shows. Vehicle images appear in the <em>"How many seats?"</em> booking modal — 1 seat = bicycle, 2 = scooter … 7–10 = van. Uploading a new image auto-updates <code>seats.js</code>.
    </div>

    <!-- ══ SEAT TYPE TEMPLATES ══ -->
    <div class="card" style="margin-bottom:26px;">
      <div class="card-section-hdr">
        <span>💺 Seat Type Templates</span>
        <span style="font-size:.75rem;font-weight:400;color:#aaa;">Defines row layout for new shows</span>
      </div>
      <table>
        <thead>
          <tr>
            <th>ID</th><th>Category</th><th>Price (₹)</th><th>Rows</th>
            <th>Seats/Row</th><th>Box Split</th><th>Sort</th><th>Total</th><th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($templates)): ?>
          <tr><td colspan="9" style="text-align:center;color:#aaa;padding:30px;">No seat types yet.</td></tr>
        <?php else: ?>
        <?php foreach ($templates as $t):
          $ra  = array_filter(array_map('trim', explode(',', $t['rows'])));
          $rc  = count($ra);
          $tot = $t['box_split'] ? $rc * 13 : $rc * (int)$t['seats_per_row'];
        ?>
          <tr>
            <td style="color:#aaa"><?= $t['id'] ?></td>
            <td style="font-weight:600"><?= htmlspecialchars($t['category_name']) ?></td>
            <td style="font-weight:700;color:#e31837">₹<?= number_format($t['price'],2) ?></td>
            <td style="font-size:.79rem;color:#555">
              <?php foreach ($ra as $r): ?>
                <span style="background:#f0f0f0;border-radius:4px;padding:1px 5px;margin:1px;display:inline-block"><?= htmlspecialchars($r) ?></span>
              <?php endforeach ?>
            </td>
            <td style="text-align:center">
              <?= $t['box_split'] ? '<span style="color:#888">13 <small>(split)</small></span>' : $t['seats_per_row'] ?>
            </td>
            <td style="text-align:center">
              <?= $t['box_split']
                ? '<span class="badge badge-info">Yes</span>'
                : '<span class="badge" style="background:#eee;color:#777">No</span>' ?>
            </td>
            <td style="text-align:center"><?= $t['sort_order'] ?></td>
            <td style="text-align:center;font-weight:600"><?= $tot ?></td>
            <td>
              <button class="btn btn-sm btn-icon" style="background:#e8f0fe;color:#1a73e8;margin-right:4px"
                onclick="openEdit(<?= htmlspecialchars(json_encode($t)) ?>)">✏️</button>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                <button type="submit" class="btn btn-sm btn-icon btn-red"
                  onclick="return confirm('Delete this seat type?')">🗑</button>
              </form>
            </td>
          </tr>
        <?php endforeach ?>
        <?php endif ?>
        </tbody>
      </table>
    </div>

    <!-- ══ VEHICLE IMAGES ══ -->
    <div class="card" style="padding:0 0 6px">
      <div class="card-section-hdr">
        <span>🚗 Seat Count Vehicle Images</span>
        <span style="font-size:.75rem;font-weight:400;color:#aaa;">Shown in "How many seats?" modal</span>
      </div>
      <div class="vi-grid">
      <?php for ($n = 1; $n <= 10; $n++):
        $v      = $vmap[$n] ?? ['seat_count'=>$n,'label'=>'','img_path'=>''];
        $fpath  = !empty($v['img_path']) ? (dirname(__DIR__).'/'.$v['img_path']) : '';
        $has    = $fpath && file_exists($fpath);
        $imgUrl = $has ? ('../'.$v['img_path'].'?v='.filemtime($fpath)) : '';
      ?>
        <div class="vi-card" id="vic-<?= $n ?>">
          <div class="vi-card-img">
            <span class="vi-badge"><?= $n ?> seat<?= $n>1?'s':'' ?></span>
            <?php if ($has): ?>
              <img src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= htmlspecialchars($v['label']) ?>" id="vi-img-<?= $n ?>">
            <?php else: ?>
              <div class="vi-no-img" id="vi-noimg-<?= $n ?>">
                <i>🖼️</i>No image
              </div>
            <?php endif ?>
          </div>
          <div class="vi-body">
            <div class="vi-label" id="vi-lbl-<?= $n ?>"><?= htmlspecialchars($v['label'] ?: '—') ?></div>
            <div class="vi-actions">
              <button class="vi-btn-edit"
                onclick="openVehicleModal(<?= $n ?>,'<?= addslashes(htmlspecialchars($v['label'])) ?>','<?= $imgUrl ? addslashes($imgUrl) : '' ?>')">
                ✏️ Edit
              </button>
              <form method="POST" style="margin:0">
                <input type="hidden" name="action" value="vehicle_delete">
                <input type="hidden" name="seat_count" value="<?= $n ?>">
                <button type="submit" class="vi-btn-del" <?= $has?'':'disabled' ?>
                  onclick="return confirm('Remove image for <?= $n ?> seat<?= $n>1?'s':'' ?>?')">🗑</button>
              </form>
            </div>
          </div>
        </div>
      <?php endfor ?>
      </div>
    </div>

  </div><!-- /page-body -->
</div><!-- /main-content -->

<!-- ══ ADD SEAT TYPE MODAL ══ -->
<div id="addModal" class="adm-overlay">
  <div class="adm-modal-box">
    <div class="adm-modal-hdr">
      <h2>Add Seat Type</h2>
      <button onclick="hide('addModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-group">
        <label>Category Name *</label>
        <input type="text" class="form-control" name="category_name" placeholder="e.g. Gold Class" required>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label>Price per Seat (₹) *</label>
          <input type="number" step="0.01" class="form-control" name="price" placeholder="150.00" required>
        </div>
        <div class="form-group">
          <label>Sort Order</label>
          <input type="number" class="form-control" name="sort_order" value="0" min="0">
        </div>
      </div>
      <div class="form-group">
        <label>Row Labels * <small style="color:#888">(comma-sep, e.g. A,B,C or AA,BB)</small></label>
        <input type="text" class="form-control" name="rows" placeholder="A,B,C,D,E,F,G,H" required>
      </div>
      <div class="form-group">
        <label>Seats Per Row * <small id="spr-note-add" style="color:#e31837;display:none">— ignored for Box Split</small></label>
        <input type="number" class="form-control" name="seats_per_row" id="add-spr" value="23" min="1" max="50">
      </div>
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" name="box_split" id="add-bx"
            onchange="q('#spr-note-add').style.display=this.checked?'inline':'none';q('#add-spr').disabled=this.checked">
          Box Split <small style="color:#888">(L 1–7, gap, R 9–14)</small>
        </label>
      </div>
      <button type="submit" class="btn btn-dark" style="width:100%;margin-top:6px">Add Seat Type</button>
    </form>
  </div>
</div>

<!-- ══ EDIT SEAT TYPE MODAL ══ -->
<div id="editModal" class="adm-overlay">
  <div class="adm-modal-box">
    <div class="adm-modal-hdr">
      <h2>Edit Seat Type</h2>
      <button onclick="hide('editModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="e-id">
      <div class="form-group">
        <label>Category Name *</label>
        <input type="text" class="form-control" name="category_name" id="e-name" required>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label>Price per Seat (₹) *</label>
          <input type="number" step="0.01" class="form-control" name="price" id="e-price" required>
        </div>
        <div class="form-group">
          <label>Sort Order</label>
          <input type="number" class="form-control" name="sort_order" id="e-sort" min="0">
        </div>
      </div>
      <div class="form-group">
        <label>Row Labels *</label>
        <input type="text" class="form-control" name="rows" id="e-rows" required>
      </div>
      <div class="form-group">
        <label>Seats Per Row * <small id="spr-note-edit" style="color:#e31837;display:none">— ignored for Box Split</small></label>
        <input type="number" class="form-control" name="seats_per_row" id="e-spr" min="1" max="50">
      </div>
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" name="box_split" id="e-bx"
            onchange="q('#spr-note-edit').style.display=this.checked?'inline':'none';q('#e-spr').disabled=this.checked">
          Box Split <small style="color:#888">(L 1–7, gap, R 9–14)</small>
        </label>
      </div>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="button" class="btn" style="flex:1;background:#f5f5f5;color:#333" onclick="hide('editModal')">Cancel</button>
        <button type="submit" class="btn btn-dark" style="flex:2">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ VEHICLE UPLOAD MODAL ══ -->
<div id="vehicleModal" class="adm-overlay" style="z-index:300">
  <div class="adm-modal-box" style="width:430px">
    <div class="adm-modal-hdr">
      <h2 id="vm-title">Edit Vehicle Image</h2>
      <button onclick="hide('vehicleModal')">✕</button>
    </div>

    <!-- Current image preview -->
    <div class="cur-img-box">
      <div style="font-size:.72rem;font-weight:700;color:#aaa;margin-bottom:6px;letter-spacing:.05em">CURRENT IMAGE</div>
      <img id="vm-cur-img" src="" alt="" style="display:none">
      <div id="vm-cur-none" style="color:#ccc;font-size:.85rem">No image uploaded yet</div>
    </div>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="vehicle_upload">
      <input type="hidden" name="seat_count" id="vm-n" value="">

      <div class="form-group">
        <label style="font-weight:600;font-size:.84rem">Label <small style="color:#888">(e.g. Scooter, Auto, Van)</small></label>
        <input type="text" class="form-control" name="label" id="vm-label" placeholder="e.g. Scooter">
      </div>

      <div class="form-group">
        <label style="font-weight:600;font-size:.84rem">Upload New Image
          <small style="color:#888;font-weight:400"> PNG / JPG / GIF / WEBP / SVG — max 5 MB</small>
        </label>
        <div class="upload-drop" id="vm-drop"
          ondragover="this.classList.add('drag-over');event.preventDefault()"
          ondragleave="this.classList.remove('drag-over')"
          ondrop="this.classList.remove('drag-over');handleFileDrop(event)">
          <input type="file" name="vehicle_img" id="vm-file"
            accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml"
            onchange="previewFile(this)">
          <div id="vm-drop-txt">
            <div style="font-size:2rem;margin-bottom:5px">📁</div>
            <div style="font-weight:600;color:#555;font-size:.86rem">Click to browse or drag &amp; drop</div>
          </div>
          <div class="upload-preview" id="vm-preview">
            <img id="vm-prev-img" src="" alt="preview">
            <div style="font-size:.76rem;color:#666;margin-top:5px" id="vm-fname"></div>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:10px;margin-top:6px">
        <button type="button" class="btn" style="flex:1;background:#f5f5f5;color:#333;font-size:.86rem"
          onclick="hide('vehicleModal')">Cancel</button>
        <button type="submit" class="btn btn-dark" style="flex:2;font-size:.86rem">💾 Save</button>
      </div>
    </form>
  </div>
</div>

<style>
/* ── shared overlay + modal box ── */
.adm-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.52);align-items:center;justify-content:center;z-index:200}
.adm-modal-box{background:#fff;border-radius:16px;padding:26px 28px;width:520px;max-width:95vw;max-height:92vh;overflow-y:auto}
.adm-modal-hdr{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
.adm-modal-hdr h2{font-size:1.05rem;font-weight:700;margin:0}
.adm-modal-hdr button{background:none;border:1px solid #ddd;width:32px;height:32px;border-radius:50%;font-size:1rem;cursor:pointer;display:flex;align-items:center;justify-content:center}
</style>

<script>
var q = function(sel){ return document.querySelector(sel); };
function show(id){ document.getElementById(id).style.display='flex'; }
function hide(id){ document.getElementById(id).style.display='none'; }

/* Close overlay on backdrop click */
['addModal','editModal','vehicleModal'].forEach(function(id){
  document.getElementById(id).addEventListener('click',function(e){
    if(e.target===this) this.style.display='none';
  });
});

/* ── Seat template: open edit modal ── */
function openEdit(t){
  q('#e-id').value   = t.id;
  q('#e-name').value = t.category_name;
  q('#e-price').value= t.price;
  q('#e-sort').value = t.sort_order;
  q('#e-rows').value = t.rows;
  q('#e-spr').value  = t.seats_per_row;
  var bs = t.box_split==1;
  q('#e-bx').checked = bs;
  q('#e-spr').disabled = bs;
  q('#spr-note-edit').style.display = bs?'inline':'none';
  show('editModal');
}

/* ── Vehicle image modal ── */
function openVehicleModal(n, label, imgUrl){
  q('#vm-n').value     = n;
  q('#vm-label').value = label;
  q('#vm-title').textContent = 'Edit Vehicle Image — '+n+' Seat'+(n>1?'s':'');

  var ci = q('#vm-cur-img'), nc = q('#vm-cur-none');
  if(imgUrl){ ci.src=imgUrl; ci.style.display='block'; nc.style.display='none'; }
  else { ci.style.display='none'; nc.style.display='block'; }

  // Reset upload zone
  q('#vm-preview').style.display   = 'none';
  q('#vm-drop-txt').style.display  = 'block';
  q('#vm-file').value = '';
  show('vehicleModal');
}

/* ── File preview ── */
function previewFile(input){
  if(!input.files||!input.files[0]) return;
  var f=input.files[0];
  if(f.size>5*1024*1024){ alert('File too large (max 5 MB).'); input.value=''; return; }
  var r=new FileReader();
  r.onload=function(e){
    q('#vm-prev-img').src=e.target.result;
    q('#vm-fname').textContent=f.name+' ('+(f.size/1024).toFixed(1)+' KB)';
    q('#vm-preview').style.display='block';
    q('#vm-drop-txt').style.display='none';
  };
  r.readAsDataURL(f);
}

function handleFileDrop(e){
  e.preventDefault();
  var dt=e.dataTransfer, inp=q('#vm-file');
  if(dt.files&&dt.files[0]){
    try{
      var dt2=new DataTransfer(); dt2.items.add(dt.files[0]);
      inp.files=dt2.files; previewFile(inp);
    }catch(err){ /* Safari fallback — just show filename */ }
  }
}
</script>
</body>
</html>
