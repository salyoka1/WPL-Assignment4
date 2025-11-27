<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1) CHECK LOGIN
if (!isset($_SESSION['phone'])) {
    die("Access denied. Please log in.");
}
// ADMIN CHECK
if (!isset($_SESSION['isAdmin']) || $_SESSION['isAdmin'] != 1) {
    die("Access denied. Admins only.");
}

// 2) CONNECT TO DATABASE travel_deals
$host = "localhost";
$user = "root";
$pass = "";
$db   = "travel_deals";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// 3) READ hotels.xml
$xmlFile = __DIR__ . "/hotels.xml";
if (!file_exists($xmlFile)) {
    die("hotels.xml not found in this folder.");
}

$xml = simplexml_load_file($xmlFile);
if ($xml === false) {
    die("Invalid XML in hotels.xml");
}

// 4) PREPARE INSERT
$sql = "
INSERT INTO hotels
(hotel_id, hotel_name, city, price_per_night)
VALUES
(?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
hotel_name = VALUES(hotel_name),
city = VALUES(city),
price_per_night = VALUES(price_per_night)
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param(
    "sssd",
    $hotel_id,        // hotel_id VARCHAR
    $hotel_name,      // hotel_name VARCHAR
    $city,            // city VARCHAR
    $price_per_night  // price_per_night DECIMAL
);

$count = 0;

// 5) LOOP OVER XML AND INSERT
foreach ($xml->hotel as $h) {
    $hotel_id        = (string)$h->hotelId;
    $hotel_name      = (string)$h->hotelName;
    $city            = (string)$h->city;
    $price_per_night = (float)$h->pricePerNight;

    if (!$stmt->execute()) {
        echo "Error inserting hotel {$hotel_id}: " . $stmt->error . "<br>";
    } else {
        $count++;
    }
}

$stmt->close();
$conn->close();

// 6) DONE
echo "$count hotel rows inserted/updated successfully.<br>";
echo '<a href="myaccount.php">Back to My Account</a>';
?>