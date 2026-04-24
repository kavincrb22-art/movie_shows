<?php
// admin/movies.php - Movie Management (with image upload & edit)
require_once '../config/db.php';
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$msg = '';

function handlePosterUpload(string $inputName): ?string
{
    if (empty($_FILES[$inputName]['name']))
        return null;
    $file = $_FILES[$inputName];
    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($file['type'], $allowed))
        return null;
    if ($file['size'] > 5 * 1024 * 1024)
        return null;
    $uploadDir = __DIR__ . '/../assets/';
    if (!is_dir($uploadDir))
        mkdir($uploadDir, 0755, true);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'poster_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        return 'assets/' . $filename;
    }
    return null;
}

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
        $trailer_url = trim($_POST['trailer_url'] ?? '');
        $new_poster = handlePosterUpload('poster_file');

        if ($action === 'add') {
            $poster_url = $new_poster ?? '';
            $stmt = $db->prepare("INSERT INTO movies (title,language,genre,rating,duration,description,release_date,is_now_showing,is_upcoming,poster_url,trailer_url) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('ssssissiiss', $title, $language, $genre, $rating, $duration, $description, $release_date, $is_now_show, $is_upcoming, $poster_url, $trailer_url);
            $stmt->execute();
            $msg = 'Movie added successfully.';
        } else {
            $id = (int) $_POST['id'];
            if ($new_poster) {
                $old = $db->query("SELECT poster_url FROM movies WHERE id=$id")->fetch_assoc();
                if ($old && $old['poster_url'] && file_exists(__DIR__ . '/../' . $old['poster_url'])) {
                    @unlink(__DIR__ . '/../' . $old['poster_url']);
                }
                $stmt = $db->prepare("UPDATE movies SET title=?,language=?,genre=?,rating=?,duration=?,description=?,release_date=?,is_now_showing=?,is_upcoming=?,poster_url=?,trailer_url=? WHERE id=?");
                $stmt->bind_param('ssssissiissi', $title, $language, $genre, $rating, $duration, $description, $release_date, $is_now_show, $is_upcoming, $new_poster, $trailer_url, $id);
            } else {
                $stmt = $db->prepare("UPDATE movies SET title=?,language=?,genre=?,rating=?,duration=?,description=?,release_date=?,is_now_showing=?,is_upcoming=?,trailer_url=? WHERE id=?");
                $stmt->bind_param('ssssissiisi', $title, $language, $genre, $rating, $duration, $description, $release_date, $is_now_show, $is_upcoming, $trailer_url, $id);
            }
            $stmt->execute();
            $msg = 'Movie updated.';
        }
    }

    if ($action === 'delete') {
        $id = (int) $_POST['id'];
        $old = $db->query("SELECT poster_url FROM movies WHERE id=$id")->fetch_assoc();
        if ($old && $old['poster_url'] && file_exists(__DIR__ . '/../' . $old['poster_url'])) {
            @unlink(__DIR__ . '/../' . $old['poster_url']);
        }
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
    <link rel="stylesheet" href="../css/responsive.css">
    <style>
        .poster-upload-area {
            border: 2px dashed #d1d5db;
            border-radius: 10px;
            padding: 16px;
            text-align: center;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            position: relative;
        }

        .poster-upload-area:hover {
            border-color: #6366f1;
            background: #f5f3ff;
        }

        .poster-upload-area input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }

        .poster-preview-wrap {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-top: 10px;
        }

        .poster-preview-wrap img {
            width: 64px;
            height: 90px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            flex-shrink: 0;
        }

        .poster-preview-wrap .preview-info {
            font-size: 0.78rem;
            color: #6b7280;
        }

        .poster-preview-wrap .preview-name {
            font-weight: 600;
            color: #111827;
            font-size: 0.85rem;
        }

        .poster-existing {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.8rem;
            color: #6b7280;
            margin-bottom: 6px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 8px 12px;
        }

        .poster-existing img {
            width: 38px;
            height: 54px;
            object-fit: cover;
            border-radius: 4px;
        }

        .movie-thumb {
            width: 40px;
            height: 56px;
            object-fit: cover;
            border-radius: 5px;
            border: 1px solid #e5e7eb;
            vertical-align: middle;
        }

        .movie-thumb-placeholder {
            width: 40px;
            height: 56px;
            background: #f3f4f6;
            border-radius: 5px;
            border: 1px solid #e5e7eb;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            vertical-align: middle;
        }

        .edit-btn {
            background: #6366f1;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 4px 10px;
            cursor: pointer;
            font-size: 0.78rem;
        }

        .edit-btn:hover {
            background: #4f46e5;
        }
    </style>
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
                <button class="btn btn-dark" onclick="openAdd()">+ Add Movie</button>
            </div>
        </div>
        <div class="page-body">
            <?php if ($msg): ?>
                <div class="alert alert-success">&#10003; <?= htmlspecialchars($msg) ?></div><?php endif; ?>
            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Poster</th>
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
                                <td colspan="9" style="text-align:center;color:#888;padding:32px;">No movies found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($movies as $m): ?>
                                <tr>
                                    <td style="color:#888;"><?= $m['id'] ?></td>
                                    <td>
                                        <?php if (!empty($m['poster_url']) && file_exists(__DIR__ . '/../' . $m['poster_url'])): ?>
                                            <img class="movie-thumb" src="../<?= htmlspecialchars($m['poster_url']) ?>"
                                                alt="poster">
                                        <?php else: ?>
                                            <span class="movie-thumb-placeholder">&#127916;</span>
                                        <?php endif; ?>
                                    </td>
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
                                    <td style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                                        <button class="edit-btn" onclick='openEdit(<?= json_encode($m) ?>)'>&#9998;
                                            Edit</button>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-icon btn-red"
                                                onclick="return confirm('Delete this movie?')">&#128465;</button>
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

    <!-- ADD MODAL -->
    <div id="addModal"
        style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:200;">
        <div
            style="background:#fff;border-radius:14px;padding:28px;width:580px;max-width:95vw;max-height:90vh;overflow-y:auto;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h2 style="font-size:1.1rem;font-weight:700;">Add New Movie</h2>
                <button onclick="closeAdd()"
                    style="background:none;border:1px solid #ddd;width:32px;height:32px;border-radius:50%;cursor:pointer;">&#10005;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
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
                            <option>UA13+</option>
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
                <div class="form-group">
                    <label>YouTube Trailer URL</label>
                    <input type="url" class="form-control" name="trailer_url"
                        placeholder="https://www.youtube.com/watch?v=XXXXXXXXXXX">
                    <small style="color:#9ca3af;font-size:0.75rem;">Paste YouTube watch link or youtu.be short
                        link</small>
                </div>
                <div class="form-group">
                    <label>Movie Poster</label>
                    <div class="poster-upload-area">
                        <input type="file" name="poster_file" accept="image/*"
                            onchange="previewPoster(this,'addPreviewWrap','addPreviewImg','addPreviewName','addUploadHint')">
                        <div id="addUploadHint">
                            <div style="font-size:1.6rem;margin-bottom:4px;">&#128444;</div>
                            <div style="font-size:0.85rem;color:#6b7280;">Click or drag a poster image here</div>
                            <div style="font-size:0.75rem;color:#9ca3af;margin-top:2px;">JPG / PNG / WebP &middot; max 5
                                MB</div>
                        </div>
                        <div id="addPreviewWrap" class="poster-preview-wrap"
                            style="display:none;justify-content:center;">
                            <img id="addPreviewImg" src="" alt="preview">
                            <div>
                                <div id="addPreviewName" class="preview-name"></div>
                                <div class="preview-info">Click to change</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div style="display:flex;gap:20px;margin-bottom:16px;">
                    <label style="display:flex;align-items:center;gap:8px;font-size:0.85rem;cursor:pointer;"><input
                            type="checkbox" name="is_now_showing"> Now Showing</label>
                    <label style="display:flex;align-items:center;gap:8px;font-size:0.85rem;cursor:pointer;"><input
                            type="checkbox" name="is_upcoming"> Upcoming</label>
                </div>
                <button type="submit" class="btn btn-dark" style="width:100%;">Add Movie</button>
            </form>
        </div>
    </div>

    <!-- EDIT MODAL -->
    <div id="editModal"
        style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:200;">
        <div
            style="background:#fff;border-radius:14px;padding:28px;width:580px;max-width:95vw;max-height:90vh;overflow-y:auto;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h2 style="font-size:1.1rem;font-weight:700;">Edit Movie</h2>
                <button onclick="closeEdit()"
                    style="background:none;border:1px solid #ddd;width:32px;height:32px;border-radius:50%;cursor:pointer;">&#10005;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-grid">
                    <div class="form-group"><label>Title *</label><input type="text" class="form-control" name="title"
                            id="edit_title" required></div>
                    <div class="form-group"><label>Language</label><input type="text" class="form-control"
                            name="language" id="edit_language"></div>
                    <div class="form-group"><label>Genre</label><input type="text" class="form-control" name="genre"
                            id="edit_genre"></div>
                    <div class="form-group"><label>Rating</label>
                        <select class="form-control" name="rating" id="edit_rating">
                            <option>U</option>
                            <option>UA</option>
                            <option>UA13+</option>
                            <option>A</option>
                            <option>S</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Duration (minutes)</label><input type="number" class="form-control"
                            name="duration" id="edit_duration" min="60" max="300"></div>
                    <div class="form-group"><label>Release Date</label><input type="date" class="form-control"
                            name="release_date" id="edit_release_date"></div>
                </div>
                <div class="form-group"><label>Description</label><textarea class="form-control" name="description"
                        id="edit_description" rows="3" style="resize:vertical;"></textarea></div>
                <div class="form-group">
                    <label>YouTube Trailer URL</label>
                    <input type="url" class="form-control" name="trailer_url" id="edit_trailer_url"
                        placeholder="https://www.youtube.com/watch?v=XXXXXXXXXXX">
                    <small style="color:#9ca3af;font-size:0.75rem;">Paste YouTube watch link or youtu.be short
                        link</small>
                </div>
                <div class="form-group">
                    <label>Movie Poster</label>
                    <div id="editExistingPoster" class="poster-existing" style="display:none;">
                        <img id="editExistingImg" src="" alt="current poster">
                        <div>
                            <div style="font-weight:600;color:#111827;font-size:0.82rem;">Current poster</div>
                            <div>Upload a new image below to replace it</div>
                        </div>
                    </div>
                    <div class="poster-upload-area">
                        <input type="file" name="poster_file" accept="image/*"
                            onchange="previewPoster(this,'editPreviewWrap','editPreviewImg','editPreviewName','editUploadHint')">
                        <div id="editUploadHint">
                            <div style="font-size:1.6rem;margin-bottom:4px;">&#128444;</div>
                            <div style="font-size:0.85rem;color:#6b7280;">Click or drag to replace poster</div>
                            <div style="font-size:0.75rem;color:#9ca3af;margin-top:2px;">Leave empty to keep current
                            </div>
                        </div>
                        <div id="editPreviewWrap" class="poster-preview-wrap"
                            style="display:none;justify-content:center;">
                            <img id="editPreviewImg" src="" alt="preview">
                            <div>
                                <div id="editPreviewName" class="preview-name"></div>
                                <div class="preview-info">Click to change</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div style="display:flex;gap:20px;margin-bottom:16px;">
                    <label style="display:flex;align-items:center;gap:8px;font-size:0.85rem;cursor:pointer;"><input
                            type="checkbox" name="is_now_showing" id="edit_is_now_showing"> Now Showing</label>
                    <label style="display:flex;align-items:center;gap:8px;font-size:0.85rem;cursor:pointer;"><input
                            type="checkbox" name="is_upcoming" id="edit_is_upcoming"> Upcoming</label>
                </div>
                <button type="submit" class="btn btn-dark" style="width:100%;">Save Changes</button>
            </form>
        </div>
    </div>

    <script>
        function previewPoster(input, wrapId, imgId, nameId, hintId) {
            var file = input.files[0]; if (!file) return;
            var reader = new FileReader();
            reader.onload = function (e) {
                document.getElementById(imgId).src = e.target.result;
                document.getElementById(nameId).textContent = file.name;
                document.getElementById(wrapId).style.display = 'flex';
                document.getElementById(hintId).style.display = 'none';
            };
            reader.readAsDataURL(file);
        }
        function openAdd() { document.getElementById('addModal').style.display = 'flex'; }
        function closeAdd() { document.getElementById('addModal').style.display = 'none'; }
        function closeEdit() { document.getElementById('editModal').style.display = 'none'; }
        function openEdit(m) {
            document.getElementById('edit_id').value = m.id;
            document.getElementById('edit_title').value = m.title || '';
            document.getElementById('edit_language').value = m.language || '';
            document.getElementById('edit_genre').value = m.genre || '';
            document.getElementById('edit_duration').value = m.duration || 120;
            document.getElementById('edit_description').value = m.description || '';
            document.getElementById('edit_release_date').value = m.release_date || '';
            document.getElementById('edit_is_now_showing').checked = m.is_now_showing == 1;
            document.getElementById('edit_is_upcoming').checked = m.is_upcoming == 1;
            var sel = document.getElementById('edit_rating');
            for (var i = 0; i < sel.options.length; i++) sel.options[i].selected = (sel.options[i].value === m.rating);
            var ep = document.getElementById('editExistingPoster');
            if (m.poster_url) { document.getElementById('editExistingImg').src = '../' + m.poster_url; ep.style.display = 'flex'; }
            else { ep.style.display = 'none'; }
            document.getElementById('edit_trailer_url').value = m.trailer_url || '';
            document.getElementById('editPreviewWrap').style.display = 'none';
            document.getElementById('editUploadHint').style.display = '';
            document.getElementById('editModal').style.display = 'flex';
        }
        ['addModal', 'editModal'].forEach(function (id) {
            document.getElementById(id).addEventListener('click', function (e) { if (e.target === this) this.style.display = 'none'; });
        });
    </script>
</body>

</html>