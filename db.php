<?php
$host = 'localhost';
$user = 'root';          // your XAMPP user
$pass = '';              // your XAMPP password (often empty)
$db   = 'travel_deals';

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_errno) {
    die('DB connection failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');