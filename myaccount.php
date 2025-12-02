<?php
session_start();
$isLoggedIn = isset($_SESSION['firstName']) && $_SESSION['firstName'] !== '';
$isAdmin = isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] == 1;
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" type="text/css" href="mystyle.css">
    <script src="jquery-3.7.1.min.js"></script>
    <script src="java.js"></script>
    <title>My Account</title>

    <style>
        .section-box {
            border: 1px solid #ccc;
            padding: 15px;
            margin: 20px 0;
            background: #f8f8f8;
        }
        .section-box h3 {
            margin-top: 0;
        }
        form {
            margin-bottom: 12px;
        }
        input[type=text] {
            padding: 6px; width: 250px;
        }
        button {
            padding: 6px 10px;
        }
    </style>
</head>

<body>
<div class="header">
    <p class="clock" id="date-time"></p>
    <h1 class="tittle">Travel Deals
        <span id="userNameDisplay" style="margin-left:15px; font-size:16px;"></span>
    </h1>
</div>

<div class="nav">
    <a href="index.html">Home</a>
    <a href="stays.html">Stays</a>
    <a href="flights.html">Flights</a>
    <a href="login.html">Login</a>
    <a href="register.html">Register</a>
    <a href="contact.html">Contact Us</a>
    <a href="cart.html">Cart</a>
    <a href="myaccount.php">MyAccount</a>
</div>

<div class="section">

    <h2>My Account</h2>

<?php if (!$isLoggedIn): ?>
    <p>You must be logged in to view account information.</p>

<?php else: ?>

    <!-- ===================== USER QUERIES ============================ -->
    <div class="section-box">
        <h3>User Tools</h3>

        <form action="query_flight_by_id.php" method="get">
            <label>Flight Booking ID:</label>
            <input type="text" name="flight_booking_id">
            <button type="submit">Get Flight Info</button>
        </form>

        <form action="query_hotel_by_id.php" method="get">
            <label>Hotel Booking ID:</label>
            <input type="text" name="hotel_booking_id">
            <button type="submit">Get Hotel Info</button>
        </form>

        <form action="query_flight_passengers.php" method="get">
            <label>Flight Booking ID:</label>
            <input type="text" name="flight_booking_id">
            <button type="submit">Get Passengers</button>
        </form>

        <form action="query_bookings_sep2024.php" method="get">
            <button type="submit">All Flights + Hotels (Sep 2024)</button>
        </form>

        <form action="query_flights_by_ssn.php" method="get">
            <label>SSN:</label>
            <input type="text" name="ssn">
            <button type="submit">Search Flights by SSN</button>
        </form>
    </div>


    <!-- ===================== ADMIN TOOLS ============================ -->
    <?php if ($_SESSION['isAdmin'] == 1): ?>
    <div class="section-box">
        <h3>Admin Tools</h3>

        <!-- Load data buttons MOVED HERE -->
        <form action="load_flights.php" method="post">
            <button type="submit">Load flights.json into database</button>
        </form>

        <form action="load_hotels.php" method="post">
            <button type="submit">Load hotels.xml into database</button>
        </form>

        <!-- Admin Queries -->
        <form action="admin_flights_texas.php" method="get">
            <button type="submit">Flights Departing Texas (Sep–Oct 2024)</button>
        </form>

        <form action="admin_hotels_texas.php" method="get">
            <button type="submit">Hotel Bookings in Texas (Sep–Oct 2024)</button>
        </form>

        <form action="admin_most_expensive_hotels.php" method="get">
            <button type="submit">Most Expensive Hotels</button>
        </form>

        <form action="admin_flights_infant.php" method="get">
            <button type="submit">Flights with Infant Passenger</button>
        </form>

        <form action="admin_flights_infant_5children.php" method="get">
            <button type="submit">Flights with Infant + ≥5 Children</button>
        </form>

        <form action="admin_most_expensive_flights.php" method="get">
            <button type="submit">Most Expensive Flights</button>
        </form>

        <form action="admin_flights_no_infant.php" method="get">
            <button type="submit">Texas Flights with No Infant Passenger</button>
        </form>

        <form action="admin_count_california_arrivals.php" method="get">
            <button type="submit">Count CA Arrivals (Sep–Oct 2024)</button>
        </form>
    </div>
    <?php endif; ?>

<?php endif; ?>

</div>
</body>
</html>

