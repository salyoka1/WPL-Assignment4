<?php
session_start();
if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn'] !== true || empty($_SESSION['isAdmin']) || $_SESSION['isAdmin'] != 1) {
    die("Access denied. Admins only.");
}

$conn = new mysqli("localhost","root","","travel_deals");
if ($conn->connect_error) die("Database connection failed: ".$conn->connect_error);

$sql = "
SELECT COUNT(*) AS total_flights
FROM flight_booking fb
JOIN flights f ON fb.flight_id = f.flight_id
WHERE f.destination LIKE '%California%'
  AND f.arrival_date BETWEEN '2024-09-01' AND '2024-10-31'
";

$result = $conn->query($sql);
$row = $result->fetch_assoc();
$total = $row['total_flights'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin: Count of California Arrivals</title>
    <link rel="stylesheet" href="mystyle.css">
</head>
<body>
<div class="header">
    <h1>Admin: Number of Flights Arriving in California (Sep-Oct 2024)</h1>
    <p><a href="myaccount.php">Back to My Account</a></p>
</div>
<div class="section">
    <p>Total booked flights arriving in California in Sep-Oct 2024: <strong><?php echo htmlspecialchars($total); ?></strong></p>
</div>
</body>
</html>
<?php $conn->close(); ?>
