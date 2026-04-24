<?php
// theatres.php - Browse Theatres
require_once 'config/db.php';

$db = getDB();
$user = getLoggedInUser();

$city = $_SESSION['city'] ?? 'Mettur';
$city_id = $_SESSION['city_id'] ?? 6;

// Optional filters from query string
$search = trim($_GET['q'] ?? '');
$today = date('Y-m-d');

// Fetch theatres in selected city, with count of today's shows
$sql = "
    SELECT t.*,
           COUNT(DISTINCT s.id) AS show_count,
           COUNT(DISTINCT s.movie_id) AS movie_count
    FROM theatres t
    LEFT JOIN shows s ON s.theatre_id = t.id AND s.show_date = ?
    WHERE t.city_id = ?
";
$params = [$today, $city_id];
$types = 'si';

if ($search !== '') {
  $sql .= " AND t.name LIKE ?";
  $params[] = "%$search%";
  $types .= 's';
}

$sql .= " GROUP BY t.id ORDER BY t.distance_from_center ASC";

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$theatres = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Popular cities for city modal
$popular_cities = $db->query("SELECT id, name FROM cities WHERE is_popular=1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$all_cities = $db->query("SELECT id, name FROM cities ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Theatres in <?= htmlspecialchars($city) ?> - TicketNew</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/responsive.css">
  <link rel="icon" href="img/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="css/bootstrap-icons-1.13.1/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/bootstrap-form.css">
  <script src="js/bootstrap.bundle.min.js"></script>
  <style>
    /* ── Page-specific styles ── */
    .page-hero {
      background: linear-gradient(135deg, #0f0f1a 0%, #1a1a2e 60%, #2d1a3e 100%);
      color: #fff;
      padding: 36px 60px;
      position: relative;
      overflow: hidden;
    }

    .page-hero::before {
      content: '';
      position: absolute;
      inset: 0;
      background: radial-gradient(ellipse at 80% 50%, rgba(227, 24, 55, 0.12), transparent 70%);
    }

    .page-hero-content {
      position: relative;
      z-index: 1;
    }

    .page-hero h1 {
      font-size: 1.7rem;
      font-weight: 800;
      margin-bottom: 6px;
    }

    .page-hero p {
      font-size: 0.88rem;
      color: rgba(255, 255, 255, 0.65);
    }

    .theatres-wrap {
      max-width: 900px;
      margin: 0 auto;
      padding: 32px 24px;
    }

    /* ── Search bar (standalone) ── */
    .theatre-search-wrap {
      margin-bottom: 28px;
    }

    .theatre-search {
      width: 100%;
      padding: 12px 20px;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      font-family: inherit;
      font-size: 0.9rem;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
      background: #f8f9fb;
    }

    .theatre-search:focus {
      border-color: #aaa;
      box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.04);
      background: #fff;
    }

    /* ── Result count ── */
    .result-count {
      font-size: 0.82rem;
      color: var(--muted);
      margin-bottom: 20px;
    }

    .result-count strong {
      color: var(--text);
    }

    /* ── Theatre card ── */
    .theatre-card {
      background: #fff;
      border: 1.5px solid var(--border);
      border-radius: 14px;
      padding: 22px 24px;
      margin-bottom: 16px;
      transition: box-shadow 0.2s, border-color 0.2s;
    }

    .theatre-card:hover {
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      border-color: #d0d0d0;
    }

    .theatre-top {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 12px;
    }

    .theatre-icon {
      width: 48px;
      height: 48px;
      background: linear-gradient(135deg, #1a1a2e, #2d1038);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.4rem;
      flex-shrink: 0;
    }

    .theatre-name-wrap {
      flex: 1;
    }

    .theatre-name {
      font-size: 1rem;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 4px;
    }

    .theatre-address {
      font-size: 0.8rem;
      color: var(--muted);
    }

    .theatre-badges {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      align-items: center;
    }

    .badge {
      font-size: 0.7rem;
      font-weight: 600;
      padding: 3px 10px;
      border-radius: 20px;
      white-space: nowrap;
    }

    .badge-cancel {
      background: #f0fdf4;
      color: #15803d;
      border: 1px solid #bbf7d0;
    }

    .badge-dist {
      background: #fafafa;
      color: #555;
      border: 1px solid var(--border);
    }

    .badge-shows {
      background: #eff6ff;
      color: #1d4ed8;
      border: 1px solid #bfdbfe;
    }

    /* ── Show times strip ── */
    .theatre-shows {
      border-top: 1px solid var(--border);
      padding-top: 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
    }

    .shows-label {
      font-size: 0.75rem;
      font-weight: 600;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.8px;
      white-space: nowrap;
    }

    .time-chips {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      flex: 1;
    }

    .time-chip {
      padding: 5px 14px;
      border: 1.5px solid var(--border);
      border-radius: 6px;
      font-size: 0.78rem;
      font-weight: 500;
      color: var(--text);
      background: #fff;
      text-decoration: none;
      transition: background 0.15s, border-color 0.15s, color 0.15s;
    }

    .time-chip:hover {
      background: var(--dark);
      border-color: var(--dark);
      color: #fff;
    }

    .no-shows-msg {
      font-size: 0.8rem;
      color: var(--muted);
      font-style: italic;
    }

    .btn-view-movies {
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--red);
      text-decoration: none;
      white-space: nowrap;
    }

    .btn-view-movies:hover {
      text-decoration: underline;
    }

    /* ── Empty state ── */
    .empty-state {
      text-align: center;
      padding: 64px 24px;
      color: var(--muted);
    }

    .empty-state .empty-icon {
      font-size: 3rem;
      margin-bottom: 16px;
    }

    .empty-state h3 {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 8px;
    }

    .empty-state p {
      font-size: 0.85rem;
    }

    @media (max-width: 600px) {
      .page-hero {
        padding: 28px 20px;
      }

      .theatres-wrap {
        padding: 20px 16px;
      }

      .theatre-top {
        flex-direction: column;
      }

      .theatre-badges {
        margin-top: 8px;
      }
    }
  </style>
</head>

<body>

  <!-- NAVBAR -->
  <nav class="navbar">
    <a href="index.php" class="nav-logo">
      <span class="logo-icon">🎫</span>
      <span class="logo-ticket">TICKE</span><span class="logo-new">NEW</span>
    </a>
    <div class="nav-links">
      <a href="index.php">Home</a>
      <a href="movies.php">Movies</a>
      <a href="theatres.php" class="active">Theatres</a>
      <?php if ($user): ?><a href="orders.php">Orders</a><?php endif; ?>
    </div>
    <div class="nav-right">
      <div class="search-bar">
        <span class="icon">🔍</span>
        <input type="text" placeholder="Search movies, cinemas…" id="searchInput">
      </div>
      <button class="city-btn" onclick="openCityModal()">
        <span class="pin">📍</span> <?= htmlspecialchars($city) ?>
      </button>
      <?php if ($user): ?>
        <a href="profile.php" class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></a>
      <?php else: ?>
        <a href="#" class="user-avatar" onclick="openLoginModal()">K</a>
      <?php endif; ?>
    </div>
  </nav>

  <!-- PAGE HERO -->
  <div class="page-hero">
    <div class="page-hero-content">
      <h1>Theatres in <?= htmlspecialchars($city) ?></h1>
      <p><?= count($theatres) ?> cinema<?= count($theatres) !== 1 ? 's' : '' ?> showing movies today</p>
    </div>
  </div>

  <!-- BREADCRUMB -->
  <div class="breadcrumb">
    <a href="index.php">Home</a><span>›</span>
    <a href="movies.php">Movies</a><span>›</span>
    Theatres in <?= htmlspecialchars($city) ?>
  </div>

  <!-- MAIN CONTENT -->
  <div class="theatres-wrap">

    <!-- Search -->
    <div class="theatre-search-wrap">
      <input type="text" class="theatre-search" id="theatreSearch" placeholder="🔍  Search theatres by name…"
        value="<?= htmlspecialchars($search) ?>">
    </div>

    <div class="result-count">
      Showing <strong><?= count($theatres) ?></strong> theatre<?= count($theatres) !== 1 ? 's' : '' ?> in
      <strong><?= htmlspecialchars($city) ?></strong>
    </div>

    <?php if (empty($theatres)): ?>
      <div class="empty-state">
        <div class="empty-icon">🎭</div>
        <h3>No theatres found</h3>
        <p>Try selecting a different city or broadening your search.</p>
      </div>

    <?php else: ?>

      <?php foreach ($theatres as $theatre):
        // Fetch today's shows for this theatre
        $t_stmt = $db->prepare("
          SELECT s.id, s.show_time, s.movie_id, m.title
          FROM shows s
          JOIN movies m ON m.id = s.movie_id
          WHERE s.theatre_id = ? AND s.show_date = ?
          ORDER BY s.show_time ASC
      ");
        $t_stmt->bind_param('is', $theatre['id'], $today);
        $t_stmt->execute();
        $shows = $t_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        ?>
        <div class="theatre-card" data-name="<?= htmlspecialchars(strtolower($theatre['name'])) ?>">
          <div class="theatre-top">
            <div class="theatre-icon">🎬</div>
            <div class="theatre-name-wrap">
              <div class="theatre-name"><?= htmlspecialchars($theatre['name']) ?></div>
              <div class="theatre-address">📍 <?= htmlspecialchars($theatre['address']) ?></div>
            </div>
            <div class="theatre-badges">
              <?php if ($theatre['distance_from_center']): ?>
                <span class="badge badge-dist"><?= number_format($theatre['distance_from_center'], 1) ?> km</span>
              <?php endif; ?>
              <?php if ($theatre['is_cancellable']): ?>
                <span class="badge badge-cancel">✓ Free Cancellation</span>
              <?php endif; ?>
              <?php if ($theatre['show_count'] > 0): ?>
                <span class="badge badge-shows"><?= $theatre['show_count'] ?>
                  show<?= $theatre['show_count'] !== 1 ? 's' : '' ?> today</span>
              <?php endif; ?>
            </div>
          </div>

          <div class="theatre-shows">
            <span class="shows-label">Today's shows</span>
            <?php if (empty($shows)): ?>
              <span class="no-shows-msg">No shows scheduled for today</span>
            <?php else: ?>
              <div class="time-chips">
                <?php foreach ($shows as $show): ?>
                  <a href="movie.php?id=<?= $show['movie_id'] ?>" class="time-chip"
                    title="<?= htmlspecialchars($show['title']) ?>"><?= date('h:i A', strtotime($show['show_time'])) ?></a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <a href="movies.php" class="btn-view-movies">View all movies →</a>
          </div>
        </div>
      <?php endforeach; ?>

    <?php endif; ?>
  </div>

  <!-- FOOTER -->
  <footer class="footer">
    <div class="footer-top">
      <div class="footer-app">
        <h4>Download App</h4>
        <a href="#" class="store-badge">🤖 GET IT ON<br><strong>Google Play</strong></a>
        <a href="#" class="store-badge">🍎 Download on the<br><strong>App Store</strong></a>
      </div>
      <div class="footer-support">
        <div class="support-item"><span class="support-icon">📞</span>Customer Care</div>
        <div class="support-item"><span class="support-icon">❓</span>FAQ</div>
      </div>
    </div>
    <div class="footer-bottom">
      Copyright &copy; <?= date('Y') ?> Orbgen Technologies Pvt. Ltd. All rights reserved &bull;
      <a href="#">Terms of Use</a> &bull; <a href="#">Privacy Policy</a>
    </div>
  </footer>

  <!-- LOGIN MODAL -->
  <div class="modal-overlay" id="loginModal">
    <div class="modal">
      <div class="modal-logo">🎫 <span style="color:var(--red)">TICKE</span><span style="color:var(--dark)">NEW</span>
      </div>
      <h2>Enter your mobile number</h2>
      <p>If you don't have an account yet, we'll create one for you</p>
      <form action="auth.php" method="POST">
        <input type="hidden" name="action" value="send_otp">
        <div class="input-group">
          <span class="prefix">🇮🇳 +91</span>
          <input type="tel" name="mobile" placeholder="Enter mobile number" maxlength="10" required>
        </div>
        <button type="submit" class="btn-primary">Continue</button>
      </form>
      <div class="modal-footer">By continuing, you agree to our <a href="#">Terms of Service</a> &amp; <a
          href="#">Privacy Policy</a></div>
    </div>
  </div>

  <!-- CITY MODAL -->
  <div class="modal-overlay" id="cityModal">
    <div class="city-modal">
      <h2 style="margin-bottom:16px;">Select Location</h2>
      <input type="text" class="city-search" placeholder="Search city, area or locality" id="citySearch">
      <div class="city-section-title">Popular Cities</div>
      <div class="popular-cities">
        <?php foreach ($popular_cities as $c): ?>
          <button class="city-chip"
            onclick="selectCity(<?= $c['id'] ?>, '<?= addslashes($c['name']) ?>')"><?= htmlspecialchars($c['name']) ?></button>
        <?php endforeach; ?>
      </div>
      <div class="city-section-title">All Cities</div>
      <div class="all-cities-grid">
        <?php foreach ($all_cities as $c): ?>
          <a href="#" class="city-link"
            onclick="selectCity(<?= $c['id'] ?>, '<?= addslashes($c['name']) ?>'); return false;"><?= htmlspecialchars($c['name']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <script src="js/main.js"></script>
  <script>
    // Live client-side theatre search (no page reload)
    document.getElementById('theatreSearch').addEventListener('input', function () {
      const q = this.value.toLowerCase().trim();
      document.querySelectorAll('.theatre-card').forEach(function (card) {
        const name = card.dataset.name || '';
        card.style.display = name.includes(q) ? '' : 'none';
      });

      // Update result count text
      const visible = document.querySelectorAll('.theatre-card:not([style*="none"])').length;
      const countEl = document.querySelector('.result-count');
      if (countEl) {
        countEl.innerHTML = 'Showing <strong>' + visible + '</strong> theatre' +
          (visible !== 1 ? 's' : '') + ' in <strong><?= addslashes(htmlspecialchars($city)) ?></strong>';
      }
    });
  </script>
</body>

</html>