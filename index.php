<?php
// index.php - Homepage
require_once 'config/db.php';

$db = getDB();
$user = getLoggedInUser();

$city = $_SESSION['city'] ?? 'Mettur';
$city_id = $_SESSION['city_id'] ?? 6;

$stmt = $db->prepare("
    SELECT DISTINCT m.* FROM movies m
    JOIN shows s ON s.movie_id = m.id
    JOIN theatres t ON t.id = s.theatre_id
    WHERE m.is_now_showing = 1
    ORDER BY m.release_date DESC
");
$stmt->execute();
$now_showing = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$upcoming = $db->query("SELECT * FROM movies WHERE is_upcoming = 1 ORDER BY release_date ASC")->fetch_all(MYSQLI_ASSOC);
$featured = $now_showing[0] ?? null;
$popular_cities = $db->query("SELECT DISTINCT name, id FROM cities WHERE is_popular=1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$all_cities = $db->query("SELECT DISTINCT name, id FROM cities ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>TicketNew - Book Movie Tickets Online</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/responsive.css">
  <link rel="icon" href="img/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="css/bootstrap-icons-1.13.1/bootstrap-icons.min.css">
  <!-- <link rel="stylesheet" href="css/bootstrap.min.css"> -->
  <link rel="stylesheet" href="css/bootstrap-form.css">
  <script src="js/bootstrap.bundle.min.js"></script>
</head>

<body>

  <!-- NAVBAR -->
  <nav class="navbar">
    <a href="index.php" class="nav-logo">
      <span class="logo-icon">🎫</span>
      <span class="logo-ticket">TICKE</span><span class="logo-new">NEW</span>
    </a>
    <div class="nav-links">
      <a href="index.php" class="active">Home</a>
      <a href="movies.php">Movies</a>
      <a href="theatres.php">Theatres</a>
      <?php if ($user): ?><a href="orders.php">Orders</a><?php endif; ?>
    </div>
    <div class="nav-right">
      <div class="search-bar">
        <span class="icon">🔍</span>
        <input type="text" placeholder="Search for movies, cinemas and more" id="searchInput">
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

  <?php if ($featured): ?>
    <section class="hero">
      <div class="hero-content">
        <span class="hero-badge">Now Showing</span>
        <h1 class="hero-title"><?= htmlspecialchars($featured['title']) ?></h1>
        <p class="hero-meta"><?= htmlspecialchars($featured['rating']) ?> | <?= htmlspecialchars($featured['genre']) ?>
        </p>
        <p class="hero-desc"><?= htmlspecialchars(substr($featured['description'], 0, 200)) ?></p>
        <a href="movie.php?id=<?= $featured['id'] ?>" class="btn-book">Book now</a>
      </div>
      <div class="hero-poster-placeholder">
        <?php if (!empty($featured['poster_url'])): ?>
          <img src="<?= htmlspecialchars($featured['poster_url']) ?>" alt="<?= htmlspecialchars($featured['title']) ?>"
            style="width:100%;height:100%;object-fit:cover;border-radius:16px;">
        <?php else: ?>🎬<?php endif; ?>
      </div>
    </section>
  <?php endif; ?>

  <!-- NOW SHOWING -->
  <section class="section">
    <div class="section-header">
      <h2 class="section-title">Now Showing in <?= htmlspecialchars($city) ?></h2>
      <a href="movies.php" class="view-all">View all</a>
    </div>
    <div class="filters">
      <button class="filter-btn active" onclick="toggleFilters()">
    ⚙ Filters ▾
  </button>
      <button class="filter-btn" onclick="selectFilter(this)">Tamil</button>
    <button class="filter-btn" onclick="selectFilter(this)">Sci-Fi</button>
    <button class="filter-btn" onclick="selectFilter(this)">Thriller</button>
    </div>
    <div class="movies-grid">
      <?php foreach ($now_showing as $movie): ?>
        <div class="movie-card" onclick="location.href='movie.php?id=<?= $movie['id'] ?>'">
          <div class="movie-card-inner">
            <div class="movie-thumb-placeholder">
              <?php if (!empty($movie['poster_url'])): ?>
                <img src="<?= htmlspecialchars($movie['poster_url']) ?>" alt="<?= htmlspecialchars($movie['title']) ?>"
                  style="width:100%;height:100%;object-fit:cover;border-radius:12px 12px 0 0;">
              <?php else: ?>🎬<?php endif; ?>
              <span class="badge-new">New Release</span>
            </div>
            <div class="movie-info">
              <h3><?= htmlspecialchars($movie['title']) ?></h3>
              <p><?= htmlspecialchars($movie['rating']) ?> • <?= htmlspecialchars($movie['language']) ?></p>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- UPCOMING MOVIES -->
  <?php if (!empty($upcoming)): ?>
    <section class="section"
      style="background:var(--bg-light); border-top:1px solid var(--border); border-bottom:1px solid var(--border);">
      <div class="section-header">
        <h2 class="section-title">Upcoming Movies</h2>
        <a href="movies.php?tab=upcoming" class="view-all">View all</a>
      </div>
      <div class="upcoming-scroll">
        <?php foreach ($upcoming as $movie): ?>
          <div class="upcoming-card" onclick="location.href='movie.php?id=<?= $movie['id'] ?>'">
            <div class="upcoming-thumb">
              <div class="upcoming-thumb-placeholder">
                <?php if (!empty($movie['poster_url'])): ?>
                  <img src="<?= htmlspecialchars($movie['poster_url']) ?>" alt="<?= htmlspecialchars($movie['title']) ?>"
                    style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>🎬<?php endif; ?>
              </div>
              <div class="release-badge">
                <span>Release Date</span>
                <?= date('d M y', strtotime($movie['release_date'])) ?>
              </div>
            </div>
            <h3><?= htmlspecialchars($movie['title']) ?></h3>
            <p><?= htmlspecialchars($movie['language']) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <!-- EXPLORE BY LANGUAGE -->
  <section class="explore-section">
    <h3 class="explore-title">Explore Latest Movies in <?= htmlspecialchars($city) ?> by Language</h3>
    <div class="chip-row">
      <?php foreach (['Hindi', 'English', 'Telugu', 'Tamil', 'Kannada', 'Bengali', 'Bhojpuri', 'Malayalam', 'Odia', 'Marathi', 'Punjabi'] as $lang): ?>
        <a href="movies.php?lang=<?= urlencode($lang) ?>" class="chip"><?= $lang ?> Movies</a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="explore-section" style="border-top:1px solid var(--border); padding-top:32px; margin-top:0;">
    <h3 class="explore-title">Explore Latest Movies in <?= htmlspecialchars($city) ?> by Genre</h3>
    <div class="chip-row">
      <?php foreach (['Comedy', 'Action', 'Drama', 'Romance', 'Horror', 'Thriller', 'Crime', 'Mystery', 'Biography', 'Adventure'] as $genre): ?>
        <a href="movies.php?genre=<?= urlencode($genre) ?>" class="chip"><?= $genre ?> Movies</a>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- BOTTOM INFO SECTION -->
  <div class="info-section">
    <div class="breadcrumb" style="padding:0 0 16px 0;">
      <a href="movies.php">Movie Tickets</a> <span>›</span> <?= htmlspecialchars($city) ?>
    </div>
    <h2>Latest Movies to Book in <?= htmlspecialchars($city) ?></h2>
    <p style="margin-top:6px;color:#555;font-size:0.88rem;">
      <?= implode(', ', array_map(fn($m) => htmlspecialchars($m['title']), $now_showing)) ?>
    </p>

    <h3><?= htmlspecialchars($city) ?> – Online Movie Ticket Booking</h3>
    <p>Now don't miss out on any movie whether it is Hollywood, Bollywood or any regional movies. Book movie tickets for
      your favourite movies from your home, office or while travelling. Just go to ticketnew.com and partake the
      pleasure of effortless online movie tickets booking in <?= htmlspecialchars($city) ?>. Don't let the long queues
      and endless wait time ruin your movie-going experience.</p>

    <h3>Movie Timings and Shows in <?= htmlspecialchars($city) ?></h3>
    <p>So get set to experience a flawless and quick movie ticket booking platform which lets you choose from a number
      of multiplex theatres and a list of latest movies. You can also find all the upcoming movies and book your tickets
      for them so that you don't miss out to see your favourite stars in action.</p>

    <div class="quick-links-accordion">
      <div class="accordion-item">
        <div class="accordion-header" onclick="this.parentElement.classList.toggle('open')">
          Book Movie Tickets in any City with Ticketnew <span class="accordion-arrow">▾</span>
        </div>
      </div>
      <div class="accordion-item">
        <div class="accordion-header" onclick="this.parentElement.classList.toggle('open')">
          Upcoming Movies <span class="accordion-arrow">▾</span>
        </div>
      </div>
      <div class="accordion-item">
        <div class="accordion-header" onclick="this.parentElement.classList.toggle('open')">
          Cinema Halls &amp; Theatres in Top Cities <span class="accordion-arrow">▾</span>
        </div>
      </div>
    </div>
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
    <div class="footer-grid">
      <div class="footer-col">
        <h4>Browse All</h4>
        <a href="movies.php">Now Showing</a>
        <a href="movies.php?tab=upcoming">Coming Soon</a>
        <a href="movies.php">Movies</a>
        <a href="theatres.php">Theatres</a>
      </div>
      <div class="footer-col">
        <h4>Links</h4>
        <a href="auth.php">Register</a>
        <a href="index.php?login=1">Login</a>
        <a href="orders.php">Order</a>
        <a href="#">Help</a>
      </div>
      <div class="footer-col">
        <h4>Theatres</h4>
        <a href="#">Sakthi Cinemas – Thiruvannamalai</a>
        <a href="#">Sakthi Cinemas – Gudiyatham</a>
        <a href="#">Sree Shivaji Sree Vijay Cinemas</a>
        <a href="#">VAB Theatre – Cheyyar</a>
        <a href="#">Devi Chembakassery Cinemas – Cherpulassery</a>
      </div>
      <div class="footer-col">
        <h4>&nbsp;</h4>
        <a href="#">Chembakassery Rimi Cinemas – Melattur</a>
        <a href="#">City Chembakassery Cinemas – Kodakara</a>
        <a href="#">Vettri Meenakshi – Tindivanam</a>
        <a href="#">Okaz Chembakassery – Mannarkkad</a>
      </div>
      <div class="footer-col">
        <h4>Enquiry</h4>
        <a href="#">Support Service (24x7)</a>
        <br>
        <h4 style="margin-top:16px;">Discover More Fun</h4>
        <a href="#">Movie Ticket Booking</a>
        <a href="#">Event Tickets</a>
        <a href="#">Things to do</a>
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
      <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
      <?php endif; ?>
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
  <?php if (isset($_GET['login'])): ?>
    <script>document.addEventListener('DOMContentLoaded', openLoginModal);</script>
  <?php endif; ?>
</body>

</html>