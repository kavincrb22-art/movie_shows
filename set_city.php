<?php
// set_city.php
require_once 'config/db.php';
$id = (int) ($_GET['id'] ?? 0);
$name = $_GET['name'] ?? '';
if ($id && $name) {
    $_SESSION['city_id'] = $id;
    $_SESSION['city'] = $name;
}
echo 'ok';
