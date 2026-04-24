<?php
// movie.php - Movie Detail & Show Timings
require_once 'config/db.php';

$movie_id = (int) ($_GET['id'] ?? 0);
if (!$movie_id) {
    header('Location: index.php');
    exit;
}

$db = getDB();
$user = getLoggedInUser();
$city = $_SESSION['city'] ?? 'Mettur';

// Fetch movie
$stmt = $db->prepare("SELECT * FROM movies WHERE id = ?");
$stmt->bind_param('i', $movie_id);
$stmt->execute();
$movie = $stmt->get_result()->fetch_assoc();
if (!$movie) {
    header('Location: index.php');
    exit;
}

// Selected date
$selected_date = $_GET['date'] ?? date('Y-m-d');
$dates = [];
for ($i = 0; $i < 7; $i++) {
    $dates[] = date('Y-m-d', strtotime("+$i days"));
}

// Fetch shows with theatres for selected date
$stmt = $db->prepare("
    SELECT t.id as theatre_id, t.name as theatre_name, t.address as theatre_address,
           t.distance_from_center, t.is_cancellable,
           GROUP_CONCAT(TIME_FORMAT(s.show_time, '%h:%i %p') ORDER BY s.show_time SEPARATOR '|') as all_times,
           GROUP_CONCAT(s.id ORDER BY s.show_time SEPARATOR '|') as show_ids,
           GROUP_CONCAT(s.format ORDER BY s.show_time SEPARATOR '|') as show_formats,
           GROUP_CONCAT(COALESCE(s.language,'') ORDER BY s.show_time SEPARATOR '|') as show_languages
    FROM shows s
    JOIN theatres t ON t.id = s.theatre_id
    WHERE s.movie_id = ? AND s.show_date = ?
    GROUP BY t.id
    ORDER BY t.distance_from_center ASC
");
$stmt->bind_param('is', $movie_id, $selected_date);
$stmt->execute();
$theatres = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$duration_h = floor($movie['duration'] / 60);
$duration_m = $movie['duration'] % 60;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($movie['title']) ?> - TicketNew</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
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
            background: #fff;
            color: #1a1a1a;
        }

        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            height: 60px;
            background: #fff;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
        }

        .nav-logo {
            font-size: 1.2rem;
            font-weight: 800;
            text-decoration: none;
            color: inherit;
        }

        .nav-logo span:first-child {
            color: #e31837;
        }

        .nav-logo span:last-child {
            color: #1a1a2e;
        }

        .nav-links a {
            margin-left: 24px;
            text-decoration: none;
            color: #555;
            font-size: 0.9rem;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #1a1a2e;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.85rem;
        }

        /* MOVIE HEADER */
        .movie-header {
            background: #1a1a1a;
            color: #fff;
            padding: 32px 60px;
            display: flex;
            align-items: center;
            gap: 32px;
        }

        .movie-thumb {
            width: 100px;
            height: 130px;
            border-radius: 10px;
            background: linear-gradient(135deg, #e31837, #ff6b35);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            overflow: hidden;
            cursor: pointer;
            position: relative;
        }

        .play-btn {
            position: absolute;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: transform 0.2s;
        }

        .play-btn:hover {
            transform: scale(1.1);
        }

        /* YOUTUBE MODAL */
        .yt-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.85);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .yt-modal-overlay.active {
            display: flex;
        }

        .yt-modal-box {
            position: relative;
            width: 90%;
            max-width: 800px;
            aspect-ratio: 16/9;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.8);
        }

        .yt-modal-box iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        .yt-close {
            position: absolute;
            top: -40px;
            right: 0;
            background: none;
            border: none;
            color: #fff;
            font-size: 1.8rem;
            cursor: pointer;
            line-height: 1;
        }

        .no-trailer-msg {
            color: #fff;
            text-align: center;
            padding: 40px;
            font-size: 1rem;
        }

        .movie-info h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .movie-meta {
            font-size: 0.85rem;
            color: #ccc;
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .meta-dot {
            color: #666;
        }

        .btn-details {
            border: 1px solid #fff;
            padding: 6px 16px;
            border-radius: 6px;
            font-size: 0.8rem;
            color: #fff;
            background: transparent;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s;
        }

        .btn-details:hover {
            background: #fff;
            color: #1a1a1a;
        }

        /* DATE SELECTOR */
        .date-section {
            padding: 24px 60px;
            border-bottom: 1px solid #eee;
        }

        .dates {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .month-label {
            font-size: 0.8rem;
            color: #888;
            font-weight: 500;
            margin-right: 8px;
        }

        .date-btn {
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px solid #ddd;
            background: #fff;
            font-family: inherit;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
            min-width: 60px;
        }

        .date-btn:hover {
            border-color: #1a1a1a;
        }

        .date-btn.active {
            background: #1a1a1a;
            color: #fff;
            border-color: #1a1a1a;
        }

        .date-btn .day {
            font-size: 0.75rem;
            color: inherit;
            opacity: 0.7;
            display: block;
        }

        .date-btn .num {
            font-size: 1rem;
            font-weight: 700;
            display: block;
        }

        /* FILTERS */
        .filter-section {
            padding: 16px 60px;
            border-bottom: 1px solid #eee;
            display: flex;
            gap: 10px;
        }

        .filter-pill {
            padding: 6px 16px;
            border-radius: 20px;
            border: 1px solid #ddd;
            background: #fff;
            font-size: 0.8rem;
            cursor: pointer;
            font-family: inherit;
        }

        .filter-pill.active {
            background: #1a1a1a;
            color: #fff;
            border-color: #1a1a1a;
        }

        /* AVAILABILITY LEGEND */
        .legend {
            padding: 12px 60px;
            background: #f9f9f9;
            border-bottom: 1px solid #eee;
            display: flex;
            gap: 20px;
            font-size: 0.78rem;
            color: #555;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .dot.available {
            background: #333;
        }

        .dot.filling {
            background: #f5a623;
        }

        .dot.full {
            background: #e31837;
        }

        /* THEATRE LISTING */
        .theatres-list {
            padding: 24px 60px;
        }

        .theatre-card {
            padding: 24px 0;
            border-bottom: 1px solid #eee;
        }

        .theatre-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .theatre-info {
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .theatre-icon {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .theatre-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .theatre-meta {
            font-size: 0.78rem;
            color: #888;
            margin-top: 2px;
        }

        .theatre-meta span {
            color: #e31837;
        }

        .heart-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
        }

        .show-times {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .time-btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: 1px solid #1a1a1a;
            background: #fff;
            font-family: inherit;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: #1a1a1a;
            display: inline-block;
            text-align: center;
        }

        .time-btn:hover {
            background: #1a1a1a;
            color: #fff;
        }

        .time-format {
            display: block;
            font-size: 0.68rem;
            color: #888;
            font-weight: 400;
            margin-top: 2px;
        }

        .time-btn:hover .time-format {
            color: #ccc;
        }

        .no-shows {
            text-align: center;
            padding: 48px;
            color: #888;
        }

        @media (max-width:768px) {
            .movie-header {
                padding: 20px;
                flex-direction: column;
            }

            .date-section,
            .theatres-list,
            .filter-section,
            .legend {
                padding-left: 16px;
                padding-right: 16px;
            }
        }

        .theatre-name .address {
            font-size: 13px;
            color: #666;
            line-height: 1.5;
            max-width: 300px;
            word-wrap: break-word;
        }

        .address {
            display: inline-block;
            color: #007bff;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .address:hover {
            text-decoration: underline;
            color: #0056b3;
        }
    </style>
</head>

<body>

    <nav class="navbar">
        <a href="index.php" class="nav-logo">🎫 <span>TICKE</span><span>NEW</span></a>
        <div style="display:flex;align-items:center;gap:16px;">
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="movies.php">Movies</a>
                <a href="theatres.php">Theatres</a>
            </div>
            <a href="profile.php" class="user-avatar"><?= $user ? strtoupper(substr($user['name'], 0, 1)) : 'K' ?></a>
        </div>
    </nav>

    <div class="movie-header">
        <div class="movie-thumb" onclick="openTrailer()"
            data-trailer="<?= htmlspecialchars($movie['trailer_url'] ?? '') ?>">
            <?php if (!empty($movie['poster_url'])): ?>
                <img src="<?= htmlspecialchars($movie['poster_url']) ?>" alt="<?= htmlspecialchars($movie['title']) ?>"
                    style="width:100%;height:100%;object-fit:cover;border-radius:10px;">
            <?php else: ?>🎬<?php endif; ?>
            <div class="play-btn">▶</div>
        </div>
        <div class="movie-info">
            <h1><?= htmlspecialchars($movie['title']) ?></h1>
            <div class="movie-meta">
                <span><?= htmlspecialchars($movie['rating']) ?></span>
                <span class="meta-dot">•</span>
                <span><?= htmlspecialchars($movie['language']) ?></span>
                <span class="meta-dot">|</span>
                <span><?= $duration_h ?>hr <?= $duration_m ?>min</span>
            </div>
            <button class="btn-details" style="margin-top:12px;">View details</button>
        </div>
    </div>

    <!-- DATE SELECTOR -->
    <div class="date-section">
        <div class="dates">
            <span class="month-label">APR</span>
            <?php foreach ($dates as $d): ?>
                <a href="movie.php?id=<?= $movie_id ?>&date=<?= $d ?>" style="text-decoration:none;">
                    <button class="date-btn <?= $d === $selected_date ? 'active' : '' ?>">
                        <span class="day"><?= date('D', strtotime($d)) ?></span>
                        <span class="num"><?= date('j', strtotime($d)) ?></span>
                    </button>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="filter-section">
        <button class="filter-pill">⚙ Filters</button>
        <button class="filter-pill active">After 5 PM</button>
    </div>

    <!-- LEGEND -->
    <div class="legend">
        <div class="legend-item">
            <div class="dot available"></div> Available
        </div>
        <div class="legend-item">
            <div class="dot filling"></div> Filling fast
        </div>
        <div class="legend-item">
            <div class="dot full"></div> Almost full
        </div>
    </div>

    <!-- THEATRES -->
    <div class="theatres-list">
        <?php if (empty($theatres)): ?>
            <div class="no-shows">
                <p style="font-size:2rem;">🎬</p>
                <p style="font-weight:600;margin:12px 0;">No shows available on this date</p>
                <p style="font-size:0.85rem;">Try selecting a different date</p>
            </div>
        <?php else: ?>
            <?php foreach ($theatres as $theatre): ?>
                <div class="theatre-card">
                    <div class="theatre-header">
                        <div class="theatre-info">
                            <div class="theatre-icon">🎭</div>
                            <div>
                                <div class="theatre-name">
                                    <div class="name">
                                        <?= htmlspecialchars($theatre['theatre_name']) ?>
                                    </div>

                                    <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($theatre['theatre_address']) ?>"
                                        target="_blank" class="address" title="Open in Google Maps">

                                        📍 <?= htmlspecialchars($theatre['theatre_address']) ?>
                                    </a>
                                </div>
                                <div class="theatre-meta">
                                    <?= $theatre['distance_from_center'] ?> km away |
                                    <span><?= $theatre['is_cancellable'] ? 'Cancellable' : 'Non-cancellable' ?></span>
                                </div>
                            </div>
                        </div>
                        <button class="heart-btn">🤍</button>
                    </div>
                    <div class="show-times">
                        <?php
                        $times = explode('|', $theatre['all_times']);
                        $show_ids = explode('|', $theatre['show_ids']);
                        $show_formats = explode('|', $theatre['show_formats']);
                        $show_languages = explode('|', $theatre['show_languages']);
                        foreach ($times as $idx => $time):
                            $sid = $show_ids[$idx] ?? 0;
                            $fmt = $show_formats[$idx] ?? '';
                            $lang = $show_languages[$idx] ?? '';
                            ?>
                            <a href="seats.php?show_id=<?= $sid ?>" class="time-btn">
                                <?= htmlspecialchars($time) ?>
                                <?php if ($fmt): ?><span
                                        class="time-format"><?= htmlspecialchars($fmt) ?><?= $lang ? ' · ' . $lang : '' ?></span><?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>


    <!-- YOUTUBE TRAILER MODAL -->
    <div class="yt-modal-overlay" id="ytModal" onclick="closeTrailer(event)">
        <div class="yt-modal-box">
            <button class="yt-close" onclick="closeTrailerBtn()">✕</button>
            <iframe id="ytFrame" src="" allow="autoplay; encrypted-media" allowfullscreen></iframe>
        </div>
    </div>

    <script>
        function getYouTubeEmbedUrl(url) {
            if (!url) return '';
            // Handle youtu.be short links
            var shortMatch = url.match(/youtu\.be\/([a-zA-Z0-9_-]{11})/);
            if (shortMatch) return 'https://www.youtube.com/embed/' + shortMatch[1] + '?autoplay=1';
            // Handle youtube.com/watch?v=
            var longMatch = url.match(/[?&]v=([a-zA-Z0-9_-]{11})/);
            if (longMatch) return 'https://www.youtube.com/embed/' + longMatch[1] + '?autoplay=1';
            // Handle youtube.com/embed/ already
            if (url.includes('youtube.com/embed/')) return url + (url.includes('?') ? '&' : '?') + 'autoplay=1';
            return '';
        }

        function openTrailer() {
            var thumb = document.querySelector('.movie-thumb');
            var trailerUrl = thumb ? thumb.getAttribute('data-trailer') : '';
            var embedUrl = getYouTubeEmbedUrl(trailerUrl);
            var modal = document.getElementById('ytModal');
            var frame = document.getElementById('ytFrame');
            if (!embedUrl) {
                frame.style.display = 'none';
                frame.insertAdjacentHTML('afterend', '<div class="no-trailer-msg">🎬 Trailer not available yet</div>');
            } else {
                frame.style.display = 'block';
                var msg = modal.querySelector('.no-trailer-msg');
                if (msg) msg.remove();
                frame.src = embedUrl;
            }
            modal.classList.add('active');
        }

        function closeTrailer(e) {
            if (e.target === document.getElementById('ytModal')) closeTrailerBtn();
        }

        function closeTrailerBtn() {
            document.getElementById('ytModal').classList.remove('active');
            document.getElementById('ytFrame').src = ''; // stop video
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeTrailerBtn();
        });
    </script>

</body>

</html>