<?php
// admin/movies.php - Movie Management
require_once '../config/db.php';
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$msg = '';

// Handle add / edit / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $title = trim($_POST['title'] ?? '');
        $language = trim($_POST['language'] ?? '');
        $genre = trim($_POST['genre'] ?? '');
        $rating = trim($_POST['rating'] ?? 'U');
        $duration = (int) ($_POST['duration'] ?? 120);
        $description = trim($_POST['description'] ?? '');
        $release_date = $_POST['release_date'] ?? date('Y-m-d');
        $is_now_show = isset($_POST['is_now_showing']) ? 1 : 0;
        $is_upcoming = isset($_POST['is_upcoming']) ? 1 : 0;

        if ($action === 'add') {
            $stmt = $db->prepare("INSERT INTO movies (title,language,genre,rating,duration,description,release_date,is_now_showing,is_upcoming) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('ssssissii', $title, $language, $genre, $rating, $duration, $description, $release_date, $is_now_show, $is_upcoming);
            $stmt->execute();
            $msg = 'Movie added successfully.';
        } else {
            $id = (int) $_POST['id'];
            $stmt = $db->prepare("UPDATE movies SET title=?,language=?,genre=?,rating=?,duration=?,description=?,release_date=?,is_now_showing=?,is_upcoming=? WHERE id=?");
            $stmt->bind_param('ssssissiii', $title, $language, $genre, $rating, $duration, $description, $release_date, $is_now_show, $is_upcoming, $id);
            $stmt->execute();
            $msg = 'Movie updated.';
        }
    }

    if ($action === 'delete') {
        $id = (int) $_POST['id'];
        $db->query("DELETE FROM movies WHERE id=$id");
        $msg = 'Movie deleted.';
    }
}

$movies = $db->query("SELECT * FROM movies ORDER BY release_date DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Movies - Admin TicketNew</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../css/admin.css">
</head>

<body>

    <aside class="sidebar">
        <div class="sidebar-logo">🎫 <span>TICKE</span>NEW Admin</div>
        <nav class="sidebar-nav">
            <div class="sidebar-section">Main</div>
            <a href="index.php" class="sidebar-link"><span class="icon">📊</span> Dashboard</a>
            <a href="movies.php" class="sidebar-link active"><span class="icon">🎬</span> Movies</a>
            <a href="theatres.php" class="sidebar-link"><span class="icon">🎭</span> Theatres</a>
            <a href="shows.php" class="sidebar-link"><span class="icon">🕐</span> Shows</a>
            <a href="seat_types.php" class="sidebar-link"><span class="icon">💺</span> Seat Types</a>
            <div class="sidebar-section">Business</div>
            <a href="bookings.php" class="sidebar-link"><span class="icon">📋</span> Bookings</a>
            <a href="users.php" class="sidebar-link"><span class="icon">👥</span> Users</a>
            <a href="cities.php" class="sidebar-link"><span class="icon">🏙</span> Cities</a>
            <div class="sidebar-section">Account</div>
            <a href="logout.php" class="sidebar-link"><span class="icon">↪</span> Logout</a>
        </nav>
        <div class="sidebar-footer">TicketNew v1.0</div>
    </aside>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title">Movies</div>
            <div class="topbar-right">
                <button class="btn btn-dark" onclick="document.getElementById('addModal').style.display='flex'">+ Add
                    Movie</button>
            </div>
        </div>
        <div class="page-body">
            <?php if ($msg): ?>
                <div class="alert alert-success">✓ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Language</th>
                            <th>Genre</th>
                            <th>Rating</th>
                            <th>Status</th>
                            <th>Release</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($movies)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center;color:#888;padding:32px;">No movies found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($movies as $m): ?>
                                <tr>
                                    <td style="color:#888;"><?= $m['id'] ?></td>
                                    <td style="font-weight:600;"><?= htmlspecialchars($m['title']) ?></td>
                                    <td><?= htmlspecialchars($m['language']) ?></td>
                                    <td><?= htmlspecialchars($m['genre']) ?></td>
                                    <td><span class="badge badge-info"><?= htmlspecialchars($m['rating']) ?></span></td>
                                    <td>
                                        <?php if ($m['is_now_showing']): ?><span class="badge badge-success">Now
                                                Showing</span><?php endif; ?>
                                        <?php if ($m['is_upcoming']): ?><span
                                                class="badge badge-warning">Upcoming</span><?php endif; ?>
                                    </td>
                                    <td style="font-size:0.8rem;"><?= date('d M Y', strtotime($m['release_date'])) ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-icon btn-red"
                                                onclick="return confirm('Delete this movie?')">🗑</button>
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

    <!-- ADD MOVIE MODAL -->
    <div id="addModal"
        style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:200;">
        <div
            style="background:#fff;border-radius:14px;padding:28px;width:560px;max-width:95vw;max-height:90vh;overflow-y:auto;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h2 style="font-size:1.1rem;font-weight:700;">Add New Movie</h2>
                <button onclick="document.getElementById('addModal').style.display='none'"
                    style="background:none;border:1px solid #ddd;width:32px;height:32px;border-radius:50%;cursor:pointer;">✕</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-grid">
                    <div class="form-group"><label>Title *</label><input type="text" class="form-control" name="title"
                            required></div>
                    <div class="form-group"><label>Language</label><input type="text" class="form-control"
                            name="language" value="Tamil"></div>
                    <div class="form-group"><label>Genre</label><input type="text" class="form-control" name="genre"
                            value="Drama"></div>
                    <div class="form-group"><label>Rating</label>
                        <select class="form-control" name="rating">
                            <option>U</option>
                            <option>UA</option>
                            <option>A</option>
                            <option>S</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Duration (minutes)</label><input type="number" class="form-control"
                            name="duration" value="120" min="60" max="300"></div>
                    <div class="form-group"><label>Release Date</label><input type="date" class="form-control"
                            name="release_date" value="<?= date('Y-m-d') ?>"></div>
                </div>
                <div class="form-group"><label>Description</label><textarea class="form-control" name="description"
                        rows="3" style="resize:vertical;"></textarea></div>
                <div style="display:flex;gap:20px;margin-bottom:16px;">
                    <label style="display:flex;align-items:center;gap:8px;font-size:0.85rem;cursor:pointer;">
                        <input type="checkbox" name="is_now_showing"> Now Showing
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;font-size:0.85rem;cursor:pointer;">
                        <input type="checkbox" name="is_upcoming"> Upcoming
                    </label>
                </div>
                <button type="submit" class="btn btn-dark" style="width:100%;">Add Movie</button>
            </form>
        </div>
    </div>

</body>

</html>