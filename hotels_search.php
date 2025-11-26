<?php
header('Content-Type: application/json');
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

$city = trim($_GET['city'] ?? '');

if ($city === '') {
    echo json_encode(['status' => 'error', 'message' => 'City is required']);
    exit;
}

$mysqli = new mysqli('localhost', 'root', '', 'travel_deals');
if ($mysqli->connect_error) {
    echo json_encode(['status'=>'error','message'=>'DB connect error']);
    exit;
}

// Updated SQL: case-insensitive city match
$sql = "SELECT hotel_id, hotel_name, city, price_per_night 
        FROM hotels 
        WHERE LOWER(city) = LOWER(?)";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $city);
$stmt->execute();
$res = $stmt->get_result();

$hotels = [];
while ($row = $res->fetch_assoc()) {
    $hotels[] = [
        'hotelId' => $row['hotel_id'],
        'hotelName' => $row['hotel_name'],
        'city' => $row['city'],
        'pricePerNight' => $row['price_per_night']
    ];
}

$stmt->close();
$mysqli->close();

echo json_encode(['status' => 'ok', 'hotels' => $hotels]);
?>
