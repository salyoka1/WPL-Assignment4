<?php
// login.php
session_start();

// Only handle POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit;
}

$phone    = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

$errors = [];

if ($phone === '' || $password === '') {
    $errors[] = "Phone and Password are required.";
}

if (!preg_match('/^\d{3}-\d{3}-\d{4}$/', $phone)) {
    $errors[] = "Phone number must be in the format ddd-ddd-dddd.";
}

if (!empty($errors)) {
    showLoginResult(false, $errors);
    exit;
}

// DB connection (same as in register.php)
$host    = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "travel_deals";

$mysqli = new mysqli($host, $db_user, $db_pass, $db_name);

if ($mysqli->connect_error) {
    $errors[] = "Database connection failed: " . $mysqli->connect_error;
    showLoginResult(false, $errors);
    exit;
}

// Look up user
$sql = "SELECT phone, password, firstName, lastName, dateOfBirth, gender, email
        FROM users
        WHERE phone = ?";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    $errors[] = "Database error: " . $mysqli->error;
    showLoginResult(false, $errors);
    exit;
}

$stmt->bind_param("s", $phone);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // NOTE: we stored plain password in register.php
    if ($row['password'] === $password) {
        // ✅ Login success: save in session
        $_SESSION['phone']     = $row['phone'];
        $_SESSION['firstName'] = $row['firstName'];
        $_SESSION['lastName']  = $row['lastName'];
        $_SESSION['dob']       = $row['dateOfBirth'];
        $_SESSION['gender']    = $row['gender'];
        $_SESSION['email']     = $row['email'];
        $_SESSION['is_admin']  = ($row['phone'] === '222-222-2222');

        $stmt->close();
        $mysqli->close();

        // Redirect to home (or wherever you like)
        header('Location: index.html');
        exit;
    } else {
        $errors[] = "Incorrect password.";
    }
} else {
    $errors[] = "No user found with that phone number.";
}

$stmt->close();
$mysqli->close();
showLoginResult(false, $errors);

// Helper to show errors (similar layout)
function showLoginResult($success, $errors = [])
{
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Login Result</title>
        <script src="jquery-3.7.1.min.js"></script>
<script type="text/javascript" src="Java.js"></script>
    </head>
    <body>
      <div class="header">
        <p class="clock" id="date-time"></p>
        <h1 class="tittle">Travel Deals</h1>
      </div>

      <div class="nav">
        <a href="index.html">Home</a>
        <a href="stays.html">Stays</a>
        <a href="flights.html">Flights</a>
        <a href="login.html">Login</a>
        <a href="register.html">Register</a>
        <a href="contact.html">Contact Us</a>
        <a href="cart.html">Cart</a>
        <a href="myaccount.html">MyAccount</a>
      </div>

      <div class="main">
        <div class="side">
          <h3>Appearance</h3>
          <label>Background color:
            <input type="color" id="bgColorControl">
          </label>
          <br><br>
          <label>Font size:
            <input type="range" id="fontSizeControl" min="12" max="28" value="16">
          </label>
        </div>

        <div class="section">
          <h2>Login Result</h2>
          <div class="error">
            <p><strong>Login failed:</strong></p>
            <ul>
              <?php foreach ($errors as $err): ?>
                <li><?php echo htmlspecialchars($err); ?></li>
              <?php endforeach; ?>
            </ul>
            <p><a href="login.html">Go back to login</a></p>
          </div>
        </div>
      </div>

      <div class="footer">
        <p>
          Name: Sally Badr - NetID: sxb230121<br>
          Name: Sayali Balasaheb Kadam - NetID: dal680698<br>
          Name: Gopika Murali - NetID: gxm240020<br>
          Name: Sreeranj Sreenivasan - NetID: dal629689
        </p>
      </div>

      <script type="text/javascript" src="Java.js"></script>
      <script src="jquery-3.7.1.min.js"></script>
    </body>
    </html>
    <?php
}