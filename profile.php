<?php
// profile.php
require_once 'config/db.php';
requireLogin();
$user = $_SESSION['user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    $name = trim($_POST['name'] ?? $user['name']);
    $email = trim($_POST['email'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $stmt = $db->prepare("UPDATE users SET name=?, email=?, state=? WHERE id=?");
    $stmt->bind_param('sssi', $name, $email, $state, $user['id']);
    $stmt->execute();
    $_SESSION['user'] = array_merge($user, ['name' => $name, 'email' => $email, 'state' => $state]);
    $user = $_SESSION['user'];
    $msg = 'Profile updated!';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Profile - TicketNew</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            background: #f0f0f5
        }

        .page {
            max-width: 400px;
            margin: 0 auto;
            min-height: 100vh;
            background: #f0f0f5
        }

        .header {
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            background: #fff;
            border-bottom: 1px solid #eee
        }

        .back-btn {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer
        }

        h1 {
            font-size: 1rem;
            font-weight: 700
        }

        .avatar-section {
            padding: 24px;
            text-align: center;
            background: #fff;
            margin-bottom: 8px
        }

        .avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #7c3aed;
            color: #fff;
            font-size: 2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px
        }

        .user-name {
            font-size: 1.1rem;
            font-weight: 600
        }

        .user-phone {
            font-size: 0.85rem;
            color: #888
        }

        .card {
            background: #fff;
            border-radius: 12px;
            margin: 0 16px 12px;
            overflow: hidden
        }

        .card-item {
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #1a1a1a;
            font-size: 0.9rem;
            border-bottom: 1px solid #f5f5f5
        }

        .card-item:last-child {
            border-bottom: none
        }

        .card-item .icon {
            font-size: 1.1rem;
            width: 24px
        }

        .card-item .arrow {
            margin-left: auto;
            color: #ccc
        }

        .section-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 16px 16px 8px
        }

        .edit-form {
            padding: 20px
        }

        .form-group {
            margin-bottom: 14px
        }

        label {
            font-size: 0.78rem;
            color: #888;
            display: block;
            margin-bottom: 4px
        }

        input[type=text],
        input[type=email] {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.9rem;
            outline: none
        }

        input:focus {
            border-color: #7c3aed
        }

        .btn-save {
            width: 100%;
            padding: 12px;
            background: #1a1a1a;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-weight: 600;
            cursor: pointer
        }

        .btn-save:hover {
            background: #e31837
        }

        .alert {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.82rem;
            margin-bottom: 14px
        }

        .logout-btn {
            width: calc(100% - 32px);
            margin: 0 16px 24px;
            padding: 14px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 12px;
            font-family: inherit;
            font-size: 0.9rem;
            cursor: pointer;
            color: #e31837;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px
        }
    </style>
</head>

<body>
    <div class="page">
        <div class="header">
            <button class="back-btn" onclick="history.back()">←</button>
            <h1>Profile</h1>
        </div>
        <div class="avatar-section">
            <div class="avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
            <div class="user-phone">+91 <?= htmlspecialchars($user['mobile']) ?></div>
        </div>

        <div class="card">
            <a href="orders.php" class="card-item">
                <span class="icon">📋</span> View all bookings <span class="arrow">›</span>
            </a>
        </div>

        <div class="card">
            <form method="POST" class="edit-form">
                <?php if (isset($msg)): ?>
                    <div class="alert">✓ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
                <div class="form-group"><label>Full Name</label><input type="text" name="name"
                        value="<?= htmlspecialchars($user['name']) ?>"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email"
                        value="<?= htmlspecialchars($user['email'] ?? '') ?>"></div>
                <div class="form-group"><label>State</label><input type="text" name="state"
                        value="<?= htmlspecialchars($user['state'] ?? '') ?>"></div>
                <button type="submit" class="btn-save">Save Changes</button>
            </form>
        </div>

        <div class="section-label">Support</div>
        <div class="card">
            <a href="#" class="card-item"><span class="icon">💬</span>Frequently Asked Questions<span
                    class="arrow">›</span></a>
            <a href="#" class="card-item"><span class="icon">📞</span>Contact Us<span class="arrow">›</span></a>
        </div>

        <div class="section-label">More</div>
        <div class="card">
            <a href="#" class="card-item"><span class="icon">📄</span>Terms & Conditions<span class="arrow">›</span></a>
        </div>

        <form method="POST" action="auth.php">
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="logout-btn">↪ Logout</button>
        </form>
    </div>
</body>

</html>