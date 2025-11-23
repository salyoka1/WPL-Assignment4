<?php
// contact.php
session_start();

function showPage($message, $isError = true)
{
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Contact Result</title>
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
        <div class="section">
          <h2>Contact Submission</h2>
          <div class="<?php echo $isError ? 'error' : 'success'; ?>">
            <p><?php echo htmlspecialchars($message); ?></p>
            <p><a href="contact.html">Back to Contact page</a></p>
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
    </body>
    </html>
    <?php
    exit;
}

// 1) Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contact.html');
    exit;
}

// 2) Must be logged in
if (!isset($_SESSION['phone'])) {
    showPage("You must be logged in to submit a comment. Please login first.", true);
}

// 3) Validate comment
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
if (strlen($comment) < 10) {
    showPage("Comment must be at least 10 characters long. Please try again.", true);
}

// 4) Prepare XML file
$xmlFile = 'contacts.xml';

if (file_exists($xmlFile)) {
    $xml = simplexml_load_file($xmlFile);
    if ($xml === false) {
        // If file is corrupted, start a fresh root
        $xml = new SimpleXMLElement('<contacts></contacts>');
    }
} else {
    $xml = new SimpleXMLElement('<contacts></contacts>');
}

// 5) Find next contact-id (simple integer increment)
$nextId = 1;
foreach ($xml->contact as $c) {
    $id = (int)$c->contactId;
    if ($id >= $nextId) {
        $nextId = $id + 1;
    }
}

// 6) Add new contact node
$contact = $xml->addChild('contact');
$contact->addChild('contactId', $nextId);
$contact->addChild('phone',      $_SESSION['phone']);
$contact->addChild('firstName',  $_SESSION['firstName']);
$contact->addChild('lastName',   $_SESSION['lastName']);
$contact->addChild('dateOfBirth', isset($_SESSION['dob']) ? $_SESSION['dob'] : '');
$contact->addChild('email',      $_SESSION['email']);
$contact->addChild('gender',     isset($_SESSION['gender']) ? $_SESSION['gender'] : '');
$contact->addChild('comment',    $comment);

// 7) Save XML
$xml->asXML($xmlFile);

// 8) Show success message
showPage("Thank you! Your comment has been recorded. Your contact-id is: " . $nextId, false);