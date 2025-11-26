<?php
session_start();
?>
<!DOCTYPE html>
<html>
  <head>
    <link rel="stylesheet" type="text/css" href="mystyle.css">
    <script src="jquery-3.7.1.min.js"></script>
    <script type="text/javascript" src="Java.js" defer></script>
    <title> Travel Deals: My Account </title>
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
      <a href="register.html">register</a>
      <a href="contact.html">Contact Us</a>
      <a href="cart.html">Cart</a>
      <a href="myaccount.php">MyAccount</a>
    </div>

    <div class="section">
      <h2>My Account</h2>

      <?php
        // user is "logged in" if we have their firstName in the session
        if (isset($_SESSION['firstName']) && $_SESSION['firstName'] !== '') {
      ?>
          <h3>Admin Tools</h3>
          <p>Click the button below to load <code>flights.json</code> into the database.</p>
          <form action="load_flights.php" method="post" style="margin-bottom:20px;">
            <button type="submit">Load flights.json into database</button>
          </form>
          <form method="post" action="load_hotels.php">
            <button type="submit">Load hotels.xml into database</button>
          </form>
      <?php
        } else {
          echo "<p>You must be logged in to load flights data.</p>";
        }
      ?>
    </div>
  </body>
</html>