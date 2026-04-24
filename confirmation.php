<?php
// confirmation.php - Booking Confirmed!
require_once 'config/db.php';
requireLogin();

$booking_id = (int) ($_GET['id'] ?? 0);
$db = getDB();
$user = $_SESSION['user'];

// Fetch booking with movie poster_url and trailer_url
$stmt = $db->prepare("
    SELECT b.*, m.title, m.language, m.rating, m.poster_url, m.trailer_url,
           s.show_date, s.show_time, s.format,
           t.name as theatre_name, t.address as theatre_address
    FROM bookings b
    JOIN shows s ON s.id = b.show_id
    JOIN movies m ON m.id = s.movie_id
    JOIN theatres t ON t.id = s.theatre_id
    WHERE b.id = ? AND b.user_id = ?
");
$stmt->bind_param('ii', $booking_id, $user['id']);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
if (!$booking) {
  header('Location: orders.php');
  exit;
}

// Fetch seats WITH category name via JOIN
$stmt2 = $db->prepare("
    SELECT bs.seat_label, bs.price, sc.category_name
    FROM booking_seats bs
    JOIN seats se ON se.id = bs.seat_id
    JOIN seat_categories sc ON sc.id = se.category_id
    WHERE bs.booking_id = ?
");
$stmt2->bind_param('i', $booking_id);
$stmt2->execute();
$booked_seats = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

// Fallback if JOIN returns nothing
if (empty($booked_seats)) {
  $stmt3 = $db->prepare("SELECT seat_label, price FROM booking_seats WHERE booking_id = ?");
  $stmt3->bind_param('i', $booking_id);
  $stmt3->execute();
  $rows = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);
  foreach ($rows as $r) {
    $booked_seats[] = ['seat_label' => $r['seat_label'], 'price' => $r['price'], 'category_name' => ''];
  }
}

$seat_count = count($booked_seats);
$ticket_price = $booking['total_amount'] - $booking['tax_amount'];
$convenience_fee = $booking['tax_amount'];

// QR code
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($booking['booking_ref']);

// Date/time
$show_date = date('D, d M', strtotime($booking['show_date']));
$show_time = date('h:i A', strtotime($booking['show_time']));

// YouTube embed URL
$trailer_embed = '';
if (!empty($booking['trailer_url'])) {
  if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([a-zA-Z0-9_-]{11})/', $booking['trailer_url'], $m)) {
    $trailer_embed = "https://www.youtube.com/embed/{$m[1]}?rel=0";
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Booking Confirmed - TicketNew</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/bootstrap-icons-1.13.1/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/bootstrap-form.css">
  <script src="js/bootstrap.bundle.min.js"></script>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: #f0f0f0;
      min-height: 100vh;
    }

    .navbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 20px;
      height: 56px;
      background: #fff;
      box-shadow: 0 1px 4px rgba(0, 0, 0, .1);
      position: sticky;
      top: 0;
      z-index: 10;
    }

    .nav-logo {
      font-weight: 800;
      font-size: 1.1rem;
      text-decoration: none;
      color: #1a1a1a;
    }

    .nav-logo .red {
      color: #e31837;
    }

    .page {
      max-width: 480px;
      margin: 0 auto;
      padding: 20px 16px 40px;
    }

    /* Ticket card */
    .ticket-wrap {
      background: #fff;
      border-radius: 18px;
      overflow: hidden;
      box-shadow: 0 4px 24px rgba(0, 0, 0, .10);
    }

    /* Movie banner */
    .movie-banner {
      display: flex;
      gap: 14px;
      padding: 16px 16px 14px;
      background: #fff;
      border-bottom: 1px solid #f0f0f0;
      position: relative;
      overflow: hidden;
    }

    .movie-poster {
      width: 72px;
      height: 96px;
      border-radius: 8px;
      flex-shrink: 0;
      background: #1a1a1a;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      color: #fff;
      overflow: hidden;
    }

    .movie-poster img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .movie-info {
      flex: 1;
      min-width: 0;
    }

    .movie-title {
      font-size: 0.95rem;
      font-weight: 700;
      color: #111;
      line-height: 1.3;
      margin-bottom: 5px;
    }

    .movie-meta {
      font-size: 0.72rem;
      color: #666;
      margin-bottom: 3px;
    }

    .movie-meta span {
      margin-right: 8px;
    }

    .pickup-badge {
      position: absolute;
      right: -26px;
      top: 50%;
      transform: translateY(-50%) rotate(90deg);
      font-size: 0.55rem;
      font-weight: 700;
      color: #aaa;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      white-space: nowrap;
    }

    /* Watch Trailer overlay button */
    .poster-wrap {
      position: relative;
      width: 72px;
      height: 96px;
      flex-shrink: 0;
    }

    .movie-poster {
      width: 100%;
      height: 100%;
      border-radius: 8px;
      background: #1a1a1a;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      color: #fff;
      overflow: hidden;
    }

    .movie-poster img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .watch-trailer-btn {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background: rgba(220, 30, 50, 0.92);
      color: #fff;
      font-family: 'Poppins', sans-serif;
      font-size: 0.52rem;
      font-weight: 700;
      text-align: center;
      padding: 5px 2px;
      cursor: pointer;
      border: none;
      width: 100%;
      letter-spacing: 0.5px;
      border-radius: 0 0 8px 8px;
      text-transform: uppercase;
    }

    .watch-trailer-btn:hover {
      background: #e31837;
    }

    /* Trailer Modal */
    .trailer-modal {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 1000;
      background: rgba(0, 0, 0, 0.85);
      align-items: center;
      justify-content: center;
    }

    .trailer-modal.open {
      display: flex;
    }

    .trailer-modal-inner {
      position: relative;
      width: 92vw;
      max-width: 480px;
      background: #000;
      border-radius: 12px;
      overflow: hidden;
    }

    .trailer-modal iframe {
      display: block;
      width: 100%;
      aspect-ratio: 16/9;
      border: 0;
    }

    .trailer-close {
      position: absolute;
      top: 8px;
      right: 10px;
      background: rgba(0, 0, 0, 0.6);
      color: #fff;
      border: none;
      font-size: 1.2rem;
      width: 30px;
      height: 30px;
      border-radius: 50%;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: inherit;
      z-index: 10;
    }

    /* Toggle */
    .toggle-btn {
      width: 100%;
      padding: 10px;
      background: #f7f7f7;
      border: none;
      border-top: 1px solid #eee;
      font-family: inherit;
      font-size: 0.78rem;
      color: #555;
      cursor: pointer;
    }

    /* Ticket body */
    .ticket-body {
      padding: 20px 22px 14px;
    }

    .tickets-count {
      text-align: center;
      font-size: 0.72rem;
      color: #777;
      margin-bottom: 3px;
    }

    .cinema-name {
      text-align: center;
      font-size: 1.05rem;
      font-weight: 800;
      color: #111;
      margin-bottom: 6px;
      line-height: 1.3;
    }

    .seat-list {
      text-align: center;
      font-size: 0.73rem;
      font-weight: 500;
      color: #555;
      line-height: 1.7;
      margin-bottom: 18px;
    }

    /* QR */
    .qr-wrap {
      display: flex;
      justify-content: center;
      margin-bottom: 14px;
    }

    .qr-wrap img {
      width: 190px;
      height: 190px;
      border: 1px solid #eee;
      border-radius: 8px;
      padding: 6px;
      background: #fff;
    }

    .booking-id {
      text-align: center;
      font-size: 0.82rem;
      font-weight: 700;
      color: #111;
      letter-spacing: .5px;
      margin-bottom: 12px;
    }

    .cancel-notice {
      text-align: center;
      font-size: 0.7rem;
      color: #aaa;
      padding: 10px 0;
      border-top: 1px dashed #e0e0e0;
      border-bottom: 1px dashed #e0e0e0;
      margin-bottom: 14px;
    }

    .support-btn {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 3px;
      padding: 8px 0 4px;
      text-decoration: none;
      color: #e31837;
      font-size: 0.72rem;
    }

    .support-icon {
      font-size: 1.2rem;
    }

    /* Tear */
    .tear-divider {
      display: flex;
      align-items: center;
    }

    .tear-line {
      flex: 1;
      height: 1px;
      background: repeating-linear-gradient(to right, #ddd 0, #ddd 6px, transparent 6px, transparent 12px);
    }

    .tear-circle {
      width: 22px;
      height: 22px;
      border-radius: 50%;
      background: #f0f0f0;
      flex-shrink: 0;
      margin: 0 -11px;
    }

    /* Price */
    .price-section {
      padding: 14px 22px 18px;
    }

    .price-row {
      display: flex;
      justify-content: space-between;
      padding: 7px 0;
      font-size: 0.82rem;
      border-bottom: 1px solid #f5f5f5;
    }

    .price-row:last-child {
      border-bottom: none;
    }

    .price-row.total {
      font-weight: 700;
      font-size: 0.95rem;
      border-bottom: 2px solid #f0f0f0 !important;
      padding-bottom: 12px;
    }

    .price-row .lbl {
      color: #555;
    }

    .price-row .amt {
      font-weight: 600;
      color: #111;
    }

    /* Actions */
    .actions {
      display: flex;
      gap: 10px;
      padding: 14px 18px 18px;
      border-top: 1px solid #f0f0f0;
    }

    .btn {
      flex: 1;
      padding: 12px 10px;
      border-radius: 8px;
      border: none;
      font-family: inherit;
      font-size: 0.84rem;
      font-weight: 600;
      cursor: pointer;
      text-align: center;
      text-decoration: none;
      display: block;
    }

    .btn-outline {
      background: #fff;
      border: 1.5px solid #ddd;
      color: #111;
    }

    .btn-outline:hover {
      border-color: #888;
    }

    .btn-red {
      background: #e31837;
      color: #fff;
    }

    .btn-red:hover {
      background: #c0122d;
    }
  </style>
</head>

<body>

  <nav class="navbar">
    <a href="index.php" class="nav-logo"><span class="red">ticket</span>new</a>
    <a href="orders.php" style="font-size:.78rem;color:#555;text-decoration:none;">My Bookings</a>
  </nav>

  <div class="page">
    <div class="ticket-wrap">

      <!-- Movie Banner -->
      <div class="movie-banner">
        <div class="poster-wrap">
          <div class="movie-poster">
            <?php if (!empty($booking['poster_url'])): ?>
              <img src="<?= htmlspecialchars($booking['poster_url']) ?>" alt="Poster">
            <?php else: ?>
              🎬
            <?php endif; ?>
          </div>
          <?php if ($trailer_embed): ?>
            <button class="watch-trailer-btn" onclick="document.getElementById('trailerModal').classList.add('open')">▶
              Watch Trailer</button>
          <?php endif; ?>
        </div>
        <div class="movie-info">
          <div class="movie-title"><?= htmlspecialchars($booking['title']) ?></div>
          <div class="movie-meta">
            <span><?= htmlspecialchars($booking['language']) ?></span>
            <span><?= htmlspecialchars($booking['format']) ?></span>
          </div>
          <div class="movie-meta"><?= $show_date ?> | <?= $show_time ?></div>
          <div class="movie-meta" style="line-height:1.5;">
            <?= htmlspecialchars($booking['theatre_name']) ?><br>
            <span style="font-size:.68rem;color:#aaa;"><?= htmlspecialchars($booking['theatre_address']) ?></span>
          </div>
        </div>
        <div class="pickup-badge">Box Office Pickup</div>
      </div>

      <?php if ($trailer_embed): ?>
        <!-- Trailer Modal -->
        <div id="trailerModal" class="trailer-modal" onclick="if(event.target===this){closeTrailer();}">
          <div class="trailer-modal-inner">
            <button class="trailer-close" onclick="closeTrailer()">✕</button>
            <iframe id="trailerFrame" src="" data-src="<?= htmlspecialchars($trailer_embed) ?>"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowfullscreen title="Watch Trailer"></iframe>
          </div>
        </div>
        <script>
          function closeTrailer() {
            var modal = document.getElementById('trailerModal');
            var frame = document.getElementById('trailerFrame');
            modal.classList.remove('open');
            frame.src = '';
          }
          document.querySelector('.watch-trailer-btn') && document.querySelector('.watch-trailer-btn').addEventListener('click', function () {
            var frame = document.getElementById('trailerFrame');
            frame.src = frame.dataset.src + '&autoplay=1';
          });
        </script>
      <?php endif; ?>

      <button class="toggle-btn"
        onclick="var b=document.getElementById('tbody');var h=b.style.display==='none';b.style.display=h?'':'none';this.textContent=h?'Tap to hide details':'Tap to show details';">
        Tap to hide details
      </button>

      <!-- Ticket Body -->
      <div id="tbody" class="ticket-body">
        <div class="tickets-count"><?= $seat_count ?> Ticket<?= $seat_count > 1 ? 's' : '' ?></div>
        <div class="cinema-name"><?= strtoupper(htmlspecialchars($booking['theatre_name'])) ?></div>

        <div class="seat-list">
          <?php
          // Group seats by category
          $grouped = [];
          foreach ($booked_seats as $s) {
            $cat = !empty($s['category_name']) ? $s['category_name'] : 'Seat';
            $grouped[$cat][] = $s['seat_label'];
          }
          $lines = [];
          foreach ($grouped as $cat => $labels) {
            $lines[] = '<strong>' . htmlspecialchars($cat) . '</strong> (' . implode(', ', array_map('htmlspecialchars', $labels)) . ')';
          }
          echo implode('<br>', $lines);
          ?>
        </div>

        <div class="qr-wrap">
          <img src="<?= $qr_url ?>" alt="QR Code">
        </div>

        <div class="booking-id">BOOKING ID: <?= htmlspecialchars($booking['booking_ref']) ?></div>

        <div class="cancel-notice">Cancellation not available for this venue</div>

        <a href="tel:+918008008000" class="support-btn">
          <span class="support-icon">📞</span>
          <span>Contact support</span>
        </a>
      </div>

      <!-- Tear Divider -->
      <div class="tear-divider">
        <div class="tear-circle"></div>
        <div class="tear-line"></div>
        <div class="tear-circle"></div>
      </div>

      <!-- Price -->
      <div class="price-section">
        <div class="price-row total">
          <span class="lbl">Total Amount</span>
          <span class="amt">₹<?= number_format($booking['total_amount'], 2) ?></span>
        </div>
        <div class="price-row">
          <span class="lbl">Ticket<?= $seat_count > 1 ? 's' : '' ?> price (<?= $seat_count ?>)</span>
          <span class="amt">₹<?= number_format($ticket_price, 2) ?></span>
        </div>
        <div class="price-row">
          <span class="lbl">Convenience fee</span>
          <span class="amt">₹<?= number_format($convenience_fee, 2) ?></span>
        </div>
      </div>

      <!-- Actions -->
      <div class="actions">
        <a href="orders.php" class="btn btn-outline">View Orders</a>
        <a href="index.php" class="btn btn-red">Book More</a>
      </div>

    </div>
  </div>
</body>

</html>