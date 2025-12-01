<?php
session_start();
if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn'] !== true || empty($_SESSION['isAdmin']) || $_SESSION['isAdmin'] != 1) {
    die("Access denied. Admins only.");
}

$conn = new mysqli("localhost","root","","travel_deals");
if ($conn->connect_error) die("Database connection failed: ".$conn->connect_error);

$sql = "
SELECT hb.hotel_booking_id, hb.hotel_id, h.hotel_name, h.city, hb.check_in_date, hb.check_out_date, hb.number_of_rooms, hb.price_per_night, hb.total_price
FROM hotel_booking hb
JOIN hotels h ON hb.hotel_id = h.hotel_id
ORDER BY hb.total_price DESC
LIMIT 1
";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin: Most Expensive Booked Hotels</title>
    <link rel="stylesheet" href="mystyle.css">
</head>
<body>
<div class="header">
    <h1>Admin: Most Expensive Booked Hotels</h1>
    <p><a href="myaccount.php">Back to My Account</a></p>
</div>
<div class="section">
<?php
if ($result && $result->num_rows > 0) {
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    echo '<tr><th>Booking ID</th><th>Hotel ID</th><th>Hotel Name</th><th>City</th><th>Check-in</th><th>Check-out</th><th>Rooms</th><th>Price/Night</th><th>Total Price</th></tr>';
    while($row = $result->fetch_assoc()){
        echo '<tr>';
        foreach($row as $val) echo '<td>'.htmlspecialchars($val).'</td>';
        echo '</tr>';
    }
    echo '</table>';
} else echo "<p>No bookings found.</p>";
?>
</div>
</body>
</html>
<?php $conn->close(); ?>
