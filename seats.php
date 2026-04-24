<?php
// seats.php — Seat Selection
require_once 'config/db.php';
requireLogin();

$show_id = (int) ($_GET['show_id'] ?? 0);
if (!$show_id) {
  header('Location: index.php');
  exit;
}

$db = getDB();
$user = $_SESSION['user'];

/* ── show + movie + theatre ── */
$stmt = $db->prepare("
    SELECT s.*, m.title, m.rating, m.language, m.duration, m.id as movie_id,
           t.name AS theatre_name, t.address AS theatre_address
    FROM shows s
    JOIN movies m ON m.id = s.movie_id
    JOIN theatres t ON t.id = s.theatre_id
    WHERE s.id = ?
");
$stmt->bind_param('i', $show_id);
$stmt->execute();
$show = $stmt->get_result()->fetch_assoc();
if (!$show) {
  header('Location: index.php');
  exit;
}

/* ── other show times for same movie / theatre / date ── */
$stmt2 = $db->prepare("
    SELECT id, show_time FROM shows
    WHERE movie_id=? AND theatre_id=? AND show_date=?
    ORDER BY show_time
");
$stmt2->bind_param('iis', $show['movie_id'], $show['theatre_id'], $show['show_date']);
$stmt2->execute();
$other_times = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

/* ── seats ── */
$stmt3 = $db->prepare("
    SELECT s.*, sc.category_name, sc.price
    FROM seats s
    JOIN seat_categories sc ON sc.id = s.category_id
    WHERE s.show_id = ?
    ORDER BY sc.price DESC, s.row_label, s.seat_number
");
$stmt3->bind_param('i', $show_id);
$stmt3->execute();
$all_seats = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);

/* ── build seat map: category → row → seats[] ── */
$seat_map = [];   // [cat_name][row] = [seat,…]
$cat_prices = [];   // [cat_name] = price
foreach ($all_seats as $s) {
  $c = $s['category_name'];
  $r = $s['row_label'];
  $seat_map[$c][$r][] = $s;
  $cat_prices[$c] = $s['price'];
}

/* ── detect BOX rows (AA, BB, CC …) ── */
// Rows whose label is 2+ chars are treated as BOX tier
$box_cats = [];   // categories that contain only box rows
$main_cats = [];   // all other categories
foreach ($seat_map as $cat => $rows) {
  $has_box = false;
  $has_main = false;
  foreach ($rows as $row => $_) {
    strlen($row) >= 2 ? $has_box = true : $has_main = true;
  }
  if ($has_box && !$has_main)
    $box_cats[$cat] = $rows;
  else
    $main_cats[$cat] = $rows;
}

/* helper: split seat array into groups separated by gaps in seat_number */
function split_groups(array $seats, int $gap_threshold = 2): array
{
  $groups = [];
  $cur = [];
  foreach ($seats as $i => $s) {
    if ($i === 0) {
      $cur[] = $s;
      continue;
    }
    $prev = $seats[$i - 1];
    if ($s['seat_number'] - $prev['seat_number'] > $gap_threshold) {
      $groups[] = $cur;
      $cur = [];
    }
    $cur[] = $s;
  }
  if ($cur)
    $groups[] = $cur;
  return $groups;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Select Seats — <?= htmlspecialchars($show['title']) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/seats.css">
  <link rel="stylesheet" href="css/responsive.css">
  <link rel="stylesheet" href="css/bootstrap-icons-1.13.1/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/bootstrap-form.css">
  <script src="js/bootstrap.bundle.min.js"></script>
</head>

<body>

  <!-- ── NAVBAR ── -->
  <nav class="seats-nav">
    <a href="movie.php?id=<?= $show['movie_id'] ?>" class="nav-back" title="Back">&#8592;</a>
    <div>
      <div class="nav-title"><?= htmlspecialchars($show['title']) ?></div>
      <div class="nav-sub">
        <?= date('D, d M', strtotime($show['show_date'])) ?> &bull;
        <?= date('h:i A', strtotime($show['show_time'])) ?> &bull;
        <?= htmlspecialchars($show['theatre_name']) ?>
      </div>
    </div>
  </nav>

  <!-- ── TIME TABS ── -->
  <div class="time-tabs">
    <span class="month-chip"><?= strtoupper(date('M', strtotime($show['show_date']))) ?></span>
    <?php foreach ($other_times as $ot): ?>
      <a href="seats.php?show_id=<?= $ot['id'] ?>" style="text-decoration:none;">
        <button class="time-tab-btn <?= $ot['id'] == $show_id ? 'active' : '' ?>">
          <?= date('h:i A', strtotime($ot['show_time'])) ?>
        </button>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- ── SEAT CANVAS ── -->
  <div class="seat-page">
    <div class="seat-canvas">

      <!-- BOX TIER (AA / BB / CC rows split left & right) -->
      <?php if ($box_cats): ?>
        <?php foreach ($box_cats as $cat_name => $box_rows): ?>
          <div class="box-tier">
            <!-- Header row: empty label cell + two column labels -->
            <div class="box-tier-header">
              <div></div>
              <div class="box-tier-labels">
                <span>BOX LEFT</span><span>BOX RIGHT</span>
              </div>
            </div>

            <?php foreach ($box_rows as $row => $seats):
              $groups = split_groups($seats, 1);
              $left = $groups[0] ?? [];
              $right = $groups[1] ?? [];
              ?>
              <!-- Each box row is its own grid row -->
              <div class="box-row-wrap">
                <div class="row-id"><?= htmlspecialchars($row) ?></div>

                <div class="box-sections">
                  <!-- left group -->
                  <div class="box-section left">
                    <div class="box-row">
                      <?php foreach ($left as $s): ?>
                        <div class="seat <?= htmlspecialchars($s['status']) ?>" id="seat_<?= $s['id'] ?>"
                          data-id="<?= $s['id'] ?>" data-price="<?= $s['price'] ?>"
                          data-label="<?= htmlspecialchars($row . $s['seat_number']) ?>">
                          <?= $s['seat_number'] ?>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>

                  <!-- right group -->
                  <div class="box-section right">
                    <div class="box-row">
                      <?php foreach ($right as $s): ?>
                        <div class="seat <?= htmlspecialchars($s['status']) ?>" id="seat_<?= $s['id'] ?>"
                          data-id="<?= $s['id'] ?>" data-price="<?= $s['price'] ?>"
                          data-label="<?= htmlspecialchars($row . $s['seat_number']) ?>">
                          <?= $s['seat_number'] ?>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>

          </div><!-- /box-tier -->
        <?php endforeach; ?>
      <?php endif; ?>

      <!-- MAIN CATEGORIES (Gold Class, Silver Class, etc.) -->
      <?php foreach ($main_cats as $cat_name => $rows): ?>
        <div class="category-block">
          <div class="cat-label-row">
            ₹<?= number_format($cat_prices[$cat_name]) ?> &nbsp;<?= strtoupper(htmlspecialchars($cat_name)) ?>
          </div>
          <div class="cat-body">
            <?php foreach ($rows as $row => $seats):
              $groups = split_groups($seats, 2);
              ?>
              <div class="seat-row">
                <div class="row-id"><?= htmlspecialchars($row) ?></div>
                <div class="seats-wrap">
                  <?php foreach ($groups as $gi => $grp):
                    if ($gi > 0): ?>
                      <div class="aisle"></div><?php endif;
                    foreach ($grp as $s):
                      ?>
                      <div class="seat <?= htmlspecialchars($s['status']) ?>" id="seat_<?= $s['id'] ?>"
                        data-id="<?= $s['id'] ?>" data-price="<?= $s['price'] ?>"
                        data-label="<?= htmlspecialchars($row . $s['seat_number']) ?>">
                        <?= $s['seat_number'] ?>
                      </div>
                    <?php endforeach; endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <!-- SCREEN -->
      <div class="screen-wrap">
        <div class="screen-arc"></div>
        <div class="screen-text">All eyes this way please</div>
      </div>

    </div><!-- /seat-canvas -->

    <!-- LEGEND -->
    <div class="legend-bar">
      <div class="legend-item">
        <div class="legend-sq available"></div> Available
      </div>
      <div class="legend-item">
        <div class="legend-sq occupied"></div> Occupied
      </div>
      <div class="legend-item">
        <div class="legend-sq selected"></div> Selected
      </div>
    </div>

  </div><!-- /seat-page -->

  <!-- BOOKING BAR -->
  <div class="booking-bar">
    <div class="booking-info">
      <span class="booking-total" id="selectedTotal">Select seats</span>
      <span class="booking-seats" id="selectedCount"></span>
    </div>
    <form action="booking.php" method="POST" id="bookingForm">
      <input type="hidden" name="show_id" value="<?= $show_id ?>">
      <input type="hidden" name="seat_ids" id="seatIdsInput">
      <input type="hidden" name="total" id="totalInput">
      <button type="submit" class="btn-proceed" id="proceedBtn" disabled>Book Now</button>
    </form>
  </div>

  <!-- ── HOW MANY SEATS MODAL ── -->
  <div id="seatCountModal" class="scm-overlay" role="dialog" aria-modal="true" aria-labelledby="scmTitle">
    <div class="scm-card">
      <h2 class="scm-title" id="scmTitle">How many seats?</h2>

      <!-- Vehicle illustration (SVGs injected by JS) -->
      <div class="scm-vehicle" id="scmVehicle" aria-hidden="true"></div>

      <!-- Number pills 1-10 -->
      <div class="scm-numbers" id="scmNumbers">
        <?php for ($i = 1; $i <= 10; $i++): ?>
          <button class="scm-num <?= $i === 1 ? 'active' : '' ?>"
                  data-n="<?= $i ?>"><?= $i ?></button>
        <?php endfor; ?>
      </div>

      <hr class="scm-divider">

      <!-- Category availability -->
      <div class="scm-cats" id="scmCats">
        <?php foreach ($cat_prices as $cat => $price): ?>
          <div class="scm-cat-item">
            <div class="scm-cat-name"><?= strtoupper(htmlspecialchars($cat)) ?></div>
            <div class="scm-cat-price">₹<?= number_format($price) ?></div>
            <div class="scm-cat-avail">AVAILABLE</div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Bestseller note -->
      <div class="scm-best">
        <span class="scm-best-swatch"></span>
        Book the <strong>Bestseller Seats</strong> in this cinema at no extra cost!
      </div>

      <!-- CTA -->
      <button class="scm-cta" id="scmSelectBtn">Select Seats</button>
    </div>
  </div>

  <script src="js/seats.js"></script>
</body>

</html>