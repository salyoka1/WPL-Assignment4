<?php
session_start();

// ---------- 1) Admin check ----------
if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn'] !== true || empty($_SESSION['isAdmin']) || $_SESSION['isAdmin'] != 1) {
    die("Access denied. Admins only.");
}

// ---------- 2) Connect to database ----------
$host = "localhost";
$user = "root";
$pass = "";
$db   = "travel_deals";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// ---------- 3) Query: booked hotels in Texas (Sep-Oct 2024) ----------
$sql = "
SELECT hb.hotel_booking_id, hb.hotel_id, h.hotel_name, h.city, hb.check_in_date, hb.check_out_date, hb.number_of_rooms, hb.price_per_night, hb.total_price
FROM hotel_booking hb
JOIN hotels h ON hb.hotel_id = h.hotel_id
WHERE h.city IN ('Dallas','Houston','Austin','San Antonio','Fort Worth') -- add other TX cities if needed
AND hb.check_in_date BETWEEN '2024-09-01' AND '2024-10-31'
";

$result = $conn->query($sql);

// ---------- 4) Display results ----------
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin: Hotels in Texas (Sep-Oct 2024)</title>
    <link rel="stylesheet" type="text/css" href="mystyle.css">
</head>
<body>
    <div class="header">
        <h1>Admin: Hotels Booked in Texas (Sep-Oct 2024)</h1>
        <p><a href="myaccount.php">Back to My Account</a></p>
    </div>

    <div class="section">
        <?php
        if ($result && $result->num_rows > 0) {
            echo '<table border="1" cellpadding="5" cellspacing="0">';
            echo '<tr>
                    <th>Booking ID</th>
                    <th>Hotel ID</th>
                    <th>Hotel Name</th>
                    <th>City</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Rooms</th>
                    <th>Price/Night</th>
                    <th>Total Price</th>
                  </tr>';
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['hotel_booking_id']) . '</td>';
                echo '<td>' . htmlspecialchars($row['hotel_id']) . '</td>';
                echo '<td>' . htmlspecialchars($row['hotel_name']) . '</td>';
                echo '<td>' . htmlspecialchars($row['city']) . '</td>';
                echo '<td>' . htmlspecialchars($row['check_in_date']) . '</td>';
                echo '<td>' . htmlspecialchars($row['check_out_date']) . '</td>';
                echo '<td>' . htmlspecialchars($row['number_of_rooms']) . '</td>';
                echo '<td>' . htmlspecialchars($row['price_per_night']) . '</td>';
                echo '<td>' . htmlspecialchars($row['total_price']) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo "<p>No booked hotels found for the specified period and city.</p>";
        }
        ?>
    </div>
</body>
</html>
<?php
$conn->close();
?>
