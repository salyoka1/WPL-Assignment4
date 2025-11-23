<?php
// session_info.php
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['phone'])) {
    echo json_encode([
        'loggedIn'  => true,
        'firstName' => $_SESSION['firstName'],
        'lastName'  => $_SESSION['lastName'],
        'isAdmin'   => !empty($_SESSION['is_admin'])
    ]);
} else {
    echo json_encode(['loggedIn' => false]);
}