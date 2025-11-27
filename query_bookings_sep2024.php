<?php
session_start();
if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn'] !== true) {
    die("Access denied. Please log in.");
}

$conn = new mysqli("localhost", "root", "", "travel_deals");
if ($conn->connect_error) die("Database connection failed: " . $conn->connect_error);

// Flights in Sep 2024
$sqlFlights = "
SELECT fb.flight_booking_id, fb.flight_id, f.origin, f.destination,
       f.depart_date, f.arrival_date, f.depart_time, f.arrival_time,
       fb.total_price
FROM flight_booking fb
JOIN flights f ON fb.flight_id = f.flight_id
WHERE f.depart_date >= '2024-09-01' AND f.depart_date <= '2024-09-30'
ORDER BY fb.flight_booking_id
";
$resultFlights = $conn->query($sqlFlights);

// Hotels in Sep 2024
$sqlHotels = "
SELECT hb.hotel_booking_id, hb.hotel_id, h.hotel_name, h.city,
       hb.check_in_date, hb.check_out_date, hb.total_price
FROM hotel_booking hb
JOIN hotels h ON hb.hotel_id = h.hotel_id
WHERE hb.check_in_date >= '2024-09-01' AND hb.check_in_date <= '2024-09-30'
ORDER BY hb.hotel_booking_id
";
$resultHotels = $conn->query($sqlHotels);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bookings: September 2024</title>
    <link rel="stylesheet" href="mystyle.css">
</head>
<body>
<div class="header">
    <h1>All Booked Flights & Hotels for Sep 2024</h1>
    <p><a href="myaccount.php">Back to My Account</a></p>
</div>

<div class="section">
<h2>Flights</h2>
<?php
if ($resultFlights && $resultFlights->num_rows > 0) {
    echo '<table border="1" cellpadding="5"><tr><th>Booking ID</th><th>Flight ID</th><th>Origin</th><th>Destination</th><th>Depart Date</th><th>Arrival Date</th><th>Depart Time</th><th>Arrival Time</th><th>Total Price</th></tr>';
    while ($row = $resultFlights->fetch_assoc()) {
        echo '<tr>';
        foreach ($row as $val) echo '<td>'.htmlspecialchars($val).'</td>';
        echo '</tr>';
    }
    echo '</table>';
} else echo "<p>No flights booked in Sep 2024.</p>";
?>

<h2>Hotels</h2>
<?php
if ($resultHotels && $resultHotels->num_rows > 0) {
    echo '<table border="1" cellpadding="5"><tr><th>Booking ID</th><th>Hotel ID</th><th>Hotel Name</th><th>City</th><th>Check-in</th><th>Check-out</th><th>Total Price</th></tr>';
    while ($row = $resultHotels->fetch_assoc()) {
        echo '<tr>';
        foreach ($row as $val) echo '<td>'.htmlspecialchars($val).'</td>';
        echo '</tr>';
    }
    echo '</table>';
} else echo "<p>No hotels booked in Sep 2024.</p>";
?>
</div>
</body>
</html>
<?php $conn->close(); ?>
