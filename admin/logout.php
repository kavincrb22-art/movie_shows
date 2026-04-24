<?php
// admin/logout.php
if (session_status() === PHP_SESSION_NONE)
    session_start();
unset($_SESSION['admin']);
header('Location: login.php');
exit;
