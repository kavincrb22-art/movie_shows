<?php
// movies.php - Movies Listing
require_once 'config/db.php';
$db = getDB();
$user = getLoggedInUser();
$city = $_SESSION['city'] ?? 'Mettur';

$tab = $_GET['tab'] ?? 'now_showing';
$lang = $_GET['lang'] ?? '';
$genre = $_GET['genre'] ?? '';
$q = $_GET['q'] ?? '';

$where = [];
$params = [];
$types = '';

if ($tab === 'upcoming') {
    $where[] = 'is_upcoming = 1';
} else {
    $where[] = 'is_now_showing = 1';
}
if ($lang) {
    $where[] = 'language LIKE ?';
    $params[] = "%$lang%";
    $types .= 's';
}
if ($genre) {
    $where[] = 'genre LIKE ?';
    $params[] = "%$genre%";
    $types .= 's';
}
if ($q) {
    $where[] = '(title LIKE ? OR description LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
    $types .= 'ss';
}

$sql = "SELECT * FROM movies";
if ($where)
    $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY release_date DESC";

$stmt = $db->prepare($sql);
if ($params)
    $stmt->bind_param($types, ...$params);
$stmt->execute();
$movies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Movies - TicketNew</title>
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
            box-sizing: border-box
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #fff
        }

        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            height: 60px;
            background: #fff;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .1);
            position: sticky;
            top: 0;
            z-index: 10
        }

        .nav-logo {
            font-weight: 800;
            font-size: 1.2rem;
            text-decoration: none;
            color: inherit
        }

        .nav-logo span:first-child {
            color: #e31837
        }

        .nav-logo span:last-child {
            color: #1a1a2e
        }

        .nav-links a {
            margin-left: 24px;
            text-decoration: none;
            color: #555;
            font-size: 0.9rem
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
            text-decoration: none;
            font-size: 0.85rem
        }

        .page {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 24px
        }

        .tabs {
            display: flex;
            gap: 0;
            border-bottom: 2px solid #eee;
            margin-bottom: 24px
        }

        .tab {
            padding: 12px 24px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            background: none;
            font-family: inherit;
            color: #888;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px
        }

        .tab.active {
            color: #e31837;
            border-bottom-color: #e31837
        }

        .search-box {
            width: 100%;
            padding: 10px 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.9rem;
            outline: none;
            margin-bottom: 20px
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px
        }

        .movie-card {
            cursor: pointer;
            transition: transform .2s
        }

        .movie-card:hover {
            transform: translateY(-4px)
        }

        .thumb {
            aspect-ratio: 2/3;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .12)
        }

        .info {
            padding: 10px 4px
        }

        .info h3 {
            font-size: 0.88rem;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis
        }

        .info p {
            font-size: 0.75rem;
            color: #888
        }

        .empty {
            text-align: center;
            padding: 60px;
            color: #888
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <a href="index.php" class="nav-logo">🎫 <span>TICKE</span><span>NEW</span></a>
        <div style="display:flex;align-items:center;gap:16px;">
            <div><a href="index.php" style="margin-right:16px;text-decoration:none;color:#555;font-size:0.9rem">Home</a>
                <a href="movies.php" style="text-decoration:none;color:#555;font-size:0.9rem">Movies</a>
            </div>
            <a href="profile.php" class="user-avatar"><?= $user ? strtoupper(substr($user['name'], 0, 1)) : 'K' ?></a>
        </div>
    </nav>
    <div class="page">
        <div class="tabs">
            <a href="movies.php?tab=now_showing" style="text-decoration:none"><button
                    class="tab <?= $tab === 'now_showing' ? 'active' : '' ?>">Now Showing</button></a>
            <a href="movies.php?tab=upcoming" style="text-decoration:none"><button
                    class="tab <?= $tab === 'upcoming' ? 'active' : '' ?>">Upcoming</button></a>
        </div>
        <form method="GET">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            <input type="text" name="q" class="search-box" placeholder="Search movies..."
                value="<?= htmlspecialchars($q) ?>">
        </form>
        <?php if (empty($movies)): ?>
            <div class="empty">
                <p style="font-size:2rem">🎬</p>
                <p>No movies found</p>
            </div>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($movies as $m): ?>
                    <div class="movie-card" onclick="location.href='movie.php?id=<?= $m['id'] ?>'">
                        <div class="thumb">
                            <?php if (!empty($m['poster_url'])): ?>
                                <img src="<?= htmlspecialchars($m['poster_url']) ?>" alt="<?= htmlspecialchars($m['title']) ?>"
                                    style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>🎬<?php endif; ?>
                        </div>
                        <div class="info">
                            <h3><?= htmlspecialchars($m['title']) ?></h3>
                            <p><?= htmlspecialchars($m['rating']) ?> • <?= htmlspecialchars($m['language']) ?></p>
                            <?php if ($tab === 'upcoming'): ?>
                                <p style="color:#e31837;font-size:0.72rem;font-weight:600;">
                                    <?= date('d M y', strtotime($m['release_date'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>