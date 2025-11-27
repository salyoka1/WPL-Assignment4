<?php
session_start();
if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn'] !== true) {
    die("Access denied. Please log in.");
}

$conn = new mysqli("localhost","root","","travel_deals");
if ($conn->connect_error) die("Database connection failed: ".$conn->connect_error);

$flight_booking_id = isset($_GET['flight_booking_id']) ? $_GET['flight_booking_id'] : '';
if ($flight_booking_id === '') die("Please provide flight_booking_id.");

// Query flight booking info
$sql = "
SELECT fb.flight_booking_id, fb.flight_id, f.origin, f.destination,
       f.depart_date, f.arrival_date, f.depart_time, f.arrival_time,
       fb.total_price
FROM flight_booking fb
JOIN flights f ON fb.flight_id = f.flight_id
WHERE fb.flight_booking_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $flight_booking_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Flight Booking Info</title>
<link rel="stylesheet" href="mystyle.css">
</head>
<body>
<div class="header">
<h1>Flight Booking Details</h1>
<p><a href="myaccount.php">Back to My Account</a></p>
</div>
<div class="section">
<?php
if($result && $result->num_rows > 0){
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    echo '<tr><th>Booking ID</th><th>Flight ID</th><th>Origin</th><th>Destination</th><th>Depart Date</th><th>Arrival Date</th><th>Depart Time</th><th>Arrival Time</th><th>Total Price</th></tr>';
    while($row = $result->fetch_assoc()){
        echo '<tr>';
        foreach($row as $val) echo '<td>'.htmlspecialchars($val).'</td>';
        echo '</tr>';
    }
    echo '</table>';
} else echo "<p>No flight found with that ID.</p>";
?>
</div>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>
