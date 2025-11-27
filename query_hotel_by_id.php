<?php
session_start();
if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn'] !== true) {
    die("Access denied. Please log in.");
}

$conn = new mysqli("localhost","root","","travel_deals");
if ($conn->connect_error) die("Database connection failed: ".$conn->connect_error);

$hotel_booking_id = isset($_GET['hotel_booking_id']) ? $_GET['hotel_booking_id'] : '';
if ($hotel_booking_id === '') die("Please provide hotel_booking_id.");

// Query hotel booking info
$sql = "
SELECT hb.hotel_booking_id, h.hotel_id, h.hotel_name, h.city,
       hb.check_in_date, hb.check_out_date, hb.number_of_rooms, hb.total_price
FROM hotel_booking hb
JOIN hotels h ON hb.hotel_id = h.hotel_id
WHERE hb.hotel_booking_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $hotel_booking_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Hotel Booking Info</title>
<link rel="stylesheet" href="mystyle.css">
</head>
<body>
<div class="header">
<h1>Hotel Booking Details</h1>
<p><a href="myaccount.php">Back to My Account</a></p>
</div>
<div class="section">
<?php
if($result && $result->num_rows > 0){
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    echo '<tr><th>Booking ID</th><th>Hotel ID</th><th>Hotel Name</th><th>City</th><th>Check-In</th><th>Check-Out</th><th>Rooms</th><th>Price/Room</th><th>Total Price</th></tr>';
    while($row = $result->fetch_assoc()){
        echo '<tr>';
        foreach($row as $val) echo '<td>'.htmlspecialchars($val).'</td>';
        echo '</tr>';
    }
    echo '</table>';
} else echo "<p>No hotel booking found with that ID.</p>";
?>
</div>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>
