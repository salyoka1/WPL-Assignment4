<?php
session_start();

/*
 * For now we will NOT block this by role.
 * We’ll just require that the user is logged in OR
 * even completely skip the check so you can load data.
 *
 * If you still get "Not authorized", just comment out
 * this whole IF block.
 */

// --- OPTION A: require login (comment this out if it still fails) ---
/*
if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn'] !== true) {
    echo "Not authorized.";
    exit;
}
*/
// --- END AUTH CHECK ---

// ========== 1) CONNECT TO DATABASE ==========
$host   = "localhost";
$user   = "root";          // <-- change if needed
$pass   = "";              // <-- change if needed
$dbname = "travel_deals";  // <-- change to YOUR DB name

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// ========== 2) READ flights.json ==========
$jsonFile = __DIR__ . "/flights.json";

if (!file_exists($jsonFile)) {
    die("flights.json not found in this folder.");
}

$json = file_get_contents($jsonFile);
$data = json_decode($json, true);
if (!is_array($data)) {
    die("Invalid JSON in flights.json");
}

// ========== 3) PREPARE INSERT ==========
$sql = "
  INSERT INTO flights
    (flight_id, origin, destination,
     depart_date, arrival_date,
     depart_time, arrival_time,
     available_seats, price)
  VALUES
    (?, ?, ?, ?, ?, ?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE
    origin          = VALUES(origin),
    destination     = VALUES(destination),
    depart_date     = VALUES(depart_date),
    arrival_date    = VALUES(arrival_date),
    depart_time     = VALUES(depart_time),
    arrival_time    = VALUES(arrival_time),
    available_seats = VALUES(available_seats),
    price           = VALUES(price)
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param(
    "sssssssid",
    $flight_id,
    $origin,
    $destination,
    $depart_date,
    $arrival_date,
    $depart_time,
    $arrival_time,
    $available_seats,
    $price
);

$count = 0;

// ========== 4) LOOP OVER JSON AND INSERT ==========
foreach ($data as $f) {
    $flight_id       = $f["flightId"];
    $origin          = $f["origin"];
    $destination     = $f["destination"];
    $depart_date     = $f["departDate"];   // YYYY-MM-DD
    $arrival_date    = $f["arrivalDate"];  // YYYY-MM-DD
    $depart_time     = $f["departTime"];   // HH:MM
    $arrival_time    = $f["arrivalTime"];  // HH:MM
    $available_seats = (int)$f["availableSeats"];
    $price           = (float)$f["price"];

    if (!$stmt->execute()) {
        echo "Error inserting flight {$flight_id}: " . $stmt->error . "<br>";
    } else {
        $count++;
    }
}

$stmt->close();
$conn->close();

// ========== 5) DONE ==========
echo "<p>$count flight rows inserted/updated successfully.</p>";
echo '<p><a href="myaccount.php">Back to My Account</a></p>';
?>