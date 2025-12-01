<?php
session_start();
if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn'] !== true || empty($_SESSION['isAdmin']) || $_SESSION['isAdmin'] != 1) {
    die("Access denied. Admins only.");
}

$conn = new mysqli("localhost","root","","travel_deals");
if ($conn->connect_error) die("Database connection failed: ".$conn->connect_error);

$sql = "
SELECT fb.flight_booking_id, fb.flight_id, f.origin, f.destination, f.depart_date, f.arrival_date, f.depart_time, f.arrival_time, fb.total_price
FROM flight_booking fb
JOIN flights f ON fb.flight_id = f.flight_id
ORDER BY fb.total_price DESC
LIMIT 1
";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin: Most Expensive Booked Flights</title>
    <link rel="stylesheet" href="mystyle.css">
</head>
<body>
<div class="header">
    <h1>Admin: Most Expensive Booked Flights</h1>
    <p><a href="myaccount.php">Back to My Account</a></p>
</div>
<div class="section">
<?php
if($result && $result->num_rows>0){
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    echo '<tr><th>Booking ID</th><th>Flight ID</th><th>Origin</th><th>Destination</th><th>Depart Date</th><th>Arrival Date</th><th>Depart Time</th><th>Arrival Time</th><th>Total Price</th></tr>';
    while($row=$result->fetch_assoc()){
        echo '<tr>';
        foreach($row as $val) echo '<td>'.htmlspecialchars($val).'</td>';
        echo '</tr>';
    }
    echo '</table>';
}else echo "<p>No bookings found.</p>";
?>
</div>
</body>
</html>
<?php $conn->close(); ?>
