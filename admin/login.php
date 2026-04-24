<?php
// admin/login.php
if (session_status() === PHP_SESSION_NONE)
    session_start();

if (isset($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = trim($_POST['password'] ?? '');
    // Change these credentials for production
    if ($u === 'admin' && $p === 'admin123') {
        $_SESSION['admin'] = ['username' => 'admin'];
        header('Location: index.php');
        exit;
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Admin Login - TicketNew</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #1a1a2e;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .card {
            background: #fff;
            border-radius: 16px;
            padding: 40px;
            width: 380px;
            max-width: 95vw;
        }

        .logo {
            text-align: center;
            font-size: 1.3rem;
            font-weight: 800;
            margin-bottom: 28px;
        }

        .logo span {
            color: #e31837;
        }

        h2 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 6px;
        }

        p {
            font-size: 0.82rem;
            color: #888;
            margin-bottom: 24px;
        }

        .form-group {
            margin-bottom: 14px;
        }

        label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
        }

        input {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.88rem;
            outline: none;
        }

        input:focus {
            border-color: #1a1a2e;
        }

        .btn {
            width: 100%;
            padding: 13px;
            background: #1a1a2e;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 6px;
        }

        .btn:hover {
            background: #e31837;
        }

        .alert {
            background: #fce4ec;
            color: #c62828;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.82rem;
            margin-bottom: 16px;
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="logo">🎫 <span>TICKE</span>NEW</div>
        <h2>Admin Login</h2>
        <p>Sign in to the admin panel</p>
        <?php if ($error): ?>
            <div class="alert">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="admin" required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn">Sign In</button>
        </form>
    </div>
</body>

</html>