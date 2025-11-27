<?php
session_start();
if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn'] !== true) {
    die("Access denied. Please log in.");
}

$conn = new mysqli("localhost","root","","travel_deals");
if ($conn->connect_error) die("Database connection failed: ".$conn->connect_error);

$flight_booking_id = isset($_GET['flight_booking_id']) ? $_GET['flight_booking_id'] : '';
if ($flight_booking_id === '') die("Please provide flight_booking_id.");

// Query passengers
$sql = "
SELECT p.ssn, p.first_name, p.last_name, p.dob, p.category, t.price
FROM passenger p
JOIN ticket t ON p.ssn = t.ssn
WHERE t.flight_booking_id = ?
";
//all this is done to prevent SQL injection
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $flight_booking_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Flight Passengers</title>
<link rel="stylesheet" href="mystyle.css">
</head>
<body>
<div class="header">
<h1>Passengers for Flight Booking ID <?php echo htmlspecialchars($flight_booking_id); ?></h1>
<p><a href="myaccount.php">Back to My Account</a></p>
</div>
<div class="section">
<?php
if($result && $result->num_rows > 0){
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    echo '<tr><th>SSN</th><th>First Name</th><th>Last Name</th><th>DOB</th><th>Category</th><th>Ticket Price</th></tr>';
    while($row = $result->fetch_assoc()){
        echo '<tr>';
        foreach($row as $val) echo '<td>'.htmlspecialchars($val).'</td>';
        echo '</tr>';
    }
    echo '</table>';
} else echo "<p>No passengers found for this flight booking.</p>";
?>
</div>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>
