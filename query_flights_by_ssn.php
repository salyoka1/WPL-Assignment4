<?php
session_start();
if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn'] !== true) {
    die("Access denied. Please log in.");
}

$ssn = isset($_GET['ssn']) ? trim($_GET['ssn']) : '';
if ($ssn === '') {
    die("Please provide SSN.");
}

$conn = new mysqli("localhost", "root", "", "travel_deals");
if ($conn->connect_error) die("Database connection failed: " . $conn->connect_error);

$sql = "
SELECT fb.flight_booking_id, fb.flight_id, f.origin, f.destination,
       f.depart_date, f.arrival_date, f.depart_time, f.arrival_time,
       fb.total_price, p.first_name, p.last_name, p.category
FROM flight_booking fb
JOIN flights f ON fb.flight_id = f.flight_id
JOIN ticket t ON fb.flight_booking_id = t.flight_booking_id
JOIN passenger p ON t.ssn = p.ssn
WHERE p.ssn = ?
ORDER BY fb.flight_booking_id
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $ssn);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Flights for SSN: <?php echo htmlspecialchars($ssn); ?></title>
    <link rel="stylesheet" href="mystyle.css">
</head>
<body>
<div class="header">
    <h1>Booked Flights for Passenger: <?php echo htmlspecialchars($ssn); ?></h1>
    <p><a href="myaccount.php">Back to My Account</a></p>
</div>

<div class="section">
<?php
if ($result && $result->num_rows > 0) {
    echo '<table border="1" cellpadding="5"><tr><th>Booking ID</th><th>Flight ID</th><th>Origin</th><th>Destination</th><th>Depart Date</th><th>Arrival Date</th><th>Depart Time</th><th>Arrival Time</th><th>Total Price</th><th>First Name</th><th>Last Name</th><th>Category</th></tr>';
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        foreach ($row as $val) echo '<td>'.htmlspecialchars($val).'</td>';
        echo '</tr>';
    }
    echo '</table>';
} else echo "<p>No flights found for SSN: ".htmlspecialchars($ssn)."</p>";
?>
</div>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>
