<?php
session_start();

// --- 1) Check if user is logged in and admin ---
if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn'] !== true || empty($_SESSION['isAdmin']) || $_SESSION['isAdmin'] != 1) {
    die("Access denied. Admins only.");
}

// --- 2) Connect to database ---
$host = "localhost";
$user = "root";
$pass = "";
$db   = "travel_deals";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// --- 3) Define the query ---
$sql = "
SELECT fb.flight_booking_id, f.flight_id, f.origin, f.destination, f.depart_date, f.arrival_date, f.depart_time, f.arrival_time, fb.total_price
FROM flight_booking fb
JOIN flights f ON fb.flight_id = f.flight_id
WHERE f.origin IN (
        'Houston', 'Dallas', 'Austin', 'San Antonio', 'Fort Worth', 'El Paso', 'Arlington', 'Corpus Christi', 'Plano', 'Laredo'
      )
  AND f.depart_date BETWEEN '2024-09-01' AND '2024-10-31'
ORDER BY f.depart_date, f.depart_time
";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin: Flights Departing from Texas</title>
    <link rel="stylesheet" href="mystyle.css">
</head>
<body>
<div class="header">
    <h1>Admin: Booked Flights Departing from Texas (Sep-Oct 2024)</h1>
    <p><a href="myaccount.php">Back to My Account</a></p>
</div>

<div class="section">
<?php
if ($result && $result->num_rows > 0) {
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    echo '<tr>
            <th>Flight Booking ID</th>
            <th>Flight ID</th>
            <th>Origin</th>
            <th>Destination</th>
            <th>Departure Date</th>
            <th>Arrival Date</th>
            <th>Departure Time</th>
            <th>Arrival Time</th>
            <th>Total Price</th>
          </tr>';
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        foreach ($row as $val) {
            echo '<td>' . htmlspecialchars($val) . '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo "<p>No flights found for the specified criteria.</p>";
}
$conn->close();
?>
</div>
</body>
</html>
