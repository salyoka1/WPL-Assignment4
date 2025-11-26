<?php
// Enable full error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (
    !isset($data['hotelId'], $data['checkIn'], $data['checkOut'], $data['numberOfRooms'],
           $data['pricePerNight'], $data['totalPrice'], $data['guests']) ||
    !is_array($data['guests']) || count($data['guests']) === 0
) {
    echo json_encode(["status"=>"error","message"=>"Missing or invalid booking/guests data"]);
    exit;
}

$hotelId        = $data['hotelId'];
$checkIn        = $data['checkIn'];
$checkOut       = $data['checkOut'];
$numberOfRooms  = $data['numberOfRooms'];
$pricePerNight  = $data['pricePerNight'];
$totalPrice     = $data['totalPrice'];
$guests         = $data['guests'];

// Open DB
$mysqli = new mysqli('localhost', 'root', '', 'travel_deals');
if ($mysqli->connect_error) {
  echo json_encode(["status"=>"error","message"=>"DB connection failed"]);
  exit;
}

// 1. Insert hotel booking, get booking ID
$stmt = $mysqli->prepare("INSERT INTO hotel_booking (hotel_id, check_in_date, check_out_date, number_of_rooms, price_per_night, total_price) VALUES (?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    echo json_encode(["status"=>"error", "message"=>"Booking prepare error: " . $mysqli->error]);
    $mysqli->close();
    exit;
}
$stmt->bind_param("sssidd", $hotelId, $checkIn, $checkOut, $numberOfRooms, $pricePerNight, $totalPrice);
if (!$stmt->execute()) {
    echo json_encode(["status"=>"error", "message"=>"Booking insert error: " . $stmt->error]);
    $stmt->close();
    $mysqli->close();
    exit;
}
$hotelBookingId = $mysqli->insert_id;
$stmt->close();

// 2. Insert each guest, verify required fields
$guestStmt = $mysqli->prepare("INSERT INTO guests (ssn, hotel_booking_id, first_name, last_name, dob, category) VALUES (?, ?, ?, ?, ?, ?)");
if (!$guestStmt) {
    echo json_encode(["status"=>"error", "message"=>"Guest prepare error: " . $mysqli->error]);
    $mysqli->close();
    exit;
}
foreach ($guests as $g) {
    if (
        !isset($g['ssn'], $g['firstName'], $g['lastName'], $g['dob'], $g['category']) ||
        $g['ssn'] === '' || $g['firstName'] === '' || $g['lastName'] === '' || $g['dob'] === '' || $g['category'] === ''
    ) {
        $guestStmt->close();
        $mysqli->close();
        echo json_encode(["status"=>"error","message"=>"Missing guest fields"]);
        exit;
    }
    $guestStmt->bind_param("sissss", $g['ssn'], $hotelBookingId, $g['firstName'], $g['lastName'], $g['dob'], $g['category']);
    if (!$guestStmt->execute()) {
        $guestStmt->close();
        $mysqli->close();
        echo json_encode(["status"=>"error","message"=>"Guest insert error for SSN {$g['ssn']}: " . $guestStmt->error]);
        exit;
    }
}
$guestStmt->close();

// 3. Fetch booking info from database
$res = $mysqli->query("SELECT * FROM hotel_booking WHERE hotel_booking_id=$hotelBookingId");
$bookingInfo = $res ? $res->fetch_assoc() : null;

// 4. Fetch guests info from database
$res = $mysqli->query("SELECT * FROM guests WHERE hotel_booking_id=$hotelBookingId");
$guestsInfo = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $guestsInfo[] = $row;
    }
}

$mysqli->close();

echo json_encode([
  "status" => "success",
  "bookingId" => $hotelBookingId,
  "bookingInfo" => $bookingInfo,
  "guestsInfo" => $guestsInfo
]);
?>
