<?php
// register.php

// 1. Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // If someone opens register.php directly, send them back to the form
    header('Location: register.html');
    exit;
}

// 2. Get form values and trim spaces
$phone           = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$password        = isset($_POST['password']) ? trim($_POST['password']) : '';
$confirmPassword = isset($_POST['confirmPassword']) ? trim($_POST['confirmPassword']) : '';
$firstName       = isset($_POST['firstName']) ? trim($_POST['firstName']) : '';
$lastName        = isset($_POST['lastName']) ? trim($_POST['lastName']) : '';
$dob             = isset($_POST['dob']) ? trim($_POST['dob']) : '';
$email           = isset($_POST['email']) ? trim($_POST['email']) : '';
$gender          = isset($_POST['gender']) ? trim($_POST['gender']) : '';  // optional

$errors = [];

// 3. Required fields check
if ($phone === '' || $password === '' || $confirmPassword === '' ||
    $firstName === '' || $lastName === '' || $dob === '' || $email === '') {
    $errors[] = "Phone, Password, Confirm Password, First Name, Last Name, Date of birth, and Email are required.";
}

// 4. Phone format: ddd-ddd-dddd
if ($phone !== '' && !preg_match('/^\d{3}-\d{3}-\d{4}$/', $phone)) {
    $errors[] = "Phone number must be in the format ddd-ddd-dddd (e.g., 123-456-7890).";
}

// 5. Password checks
if ($password !== '' && strlen($password) < 8) {
    $errors[] = "Password must be at least 8 characters long.";
}
if ($password !== '' && $confirmPassword !== '' && $password !== $confirmPassword) {
    $errors[] = "Password and Confirm Password do not match.";
}

// 6. Date of birth format: MM/DD/YYYY
if ($dob !== '' && !preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dob)) {
    $errors[] = "Date of birth must be in the format MM/DD/YYYY.";
} elseif ($dob !== '') {
    // Optional: check that it's a real date
    list($mm, $dd, $yyyy) = explode('/', $dob);
    if (!checkdate((int)$mm, (int)$dd, (int)$yyyy)) {
        $errors[] = "Date of birth is not a valid calendar date.";
    }
}

// 7. Email must contain @ and .com
if ($email !== '' && (strpos($email, '@') === false || strpos($email, '.com') === false)) {
    $errors[] = "Email must contain '@' and end with '.com'.";
}

// If there are validation errors so far, show them and stop
if (!empty($errors)) {
    showResult(false, $errors);
    exit;
}

// 8. Connect to MySQL database
$host    = "localhost";       // change this if needed
$db_user = "root";            // your DB username
$db_pass = "";                // your DB password
$db_name = "travel_deals";    // your DB name

$mysqli = new mysqli($host, $db_user, $db_pass, $db_name);

if ($mysqli->connect_error) {
    $errors[] = "Database connection failed: " . $mysqli->connect_error;
    showResult(false, $errors);
    exit;
}

// 9. Check that phone number is unique
$checkSql = "SELECT phone FROM users WHERE phone = ?";
$stmt = $mysqli->prepare($checkSql);
if (!$stmt) {
    $errors[] = "Database error (prepare): " . $mysqli->error;
    showResult(false, $errors);
    exit;
}

$stmt->bind_param("s", $phone);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $errors[] = "This phone number is already registered. Please use a different phone number.";
    $stmt->close();
    $mysqli->close();
    showResult(false, $errors);
    exit;
}
$stmt->close();

// 10. Insert into users table
// NOTE: For a real project you should hash the password with password_hash().
// For this assignment, you can store it as plain text OR switch to hashing.
// $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$hashedPassword = $password;  // simple version for the assignment

$insertSql = "INSERT INTO users (phone, password, firstName, lastName, dateOfBirth, gender, email)
              VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $mysqli->prepare($insertSql);
if (!$stmt) {
    $errors[] = "Database error (prepare insert): " . $mysqli->error;
    showResult(false, $errors);
    exit;
}

$stmt->bind_param("sssssss", $phone, $hashedPassword, $firstName, $lastName, $dob, $gender, $email);

if ($stmt->execute()) {
    // Success
    $stmt->close();
    $mysqli->close();
    showResult(true, [], $firstName, $lastName);
    exit;
} else {
    $errors[] = "Error inserting user: " . $stmt->error;
    $stmt->close();
    $mysqli->close();
    showResult(false, $errors);
    exit;
}

/**
 * Helper function to output HTML result
 * @param bool   $success
 * @param array  $errors
 * @param string $firstName
 * @param string $lastName
 */
function showResult($success, $errors = [], $firstName = '', $lastName = '')
{
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Registration Result</title>
        <link rel="stylesheet" type="text/css" href="mystyle.css">
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
          <h2>Registration Result</h2>

          <?php if ($success): ?>
            <p class="summary">
              Registration successful!<br>
              Welcome, <strong><?php echo htmlspecialchars($firstName . ' ' . $lastName); ?></strong>.<br>
              You can now <a href="login.html">log in</a> using your phone number and password.
            </p>
          <?php else: ?>
            <div class="error">
              <p><strong>There were problems with your registration:</strong></p>
              <ul>
                <?php foreach ($errors as $err): ?>
                  <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
              </ul>
              <p><a href="register.html">Go back to the registration page</a> and try again.</p>
            </div>
          <?php endif; ?>
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

      <!-- optional: your Java.js will fill in date/time + appearance -->
      <script type="text/javascript" src="Java.js"></script>
      <script src="jquery-3.7.1.min.js"></script>
    </body>
    </html>
    <?php
}