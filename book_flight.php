<?php
// book_flight.php
header('Content-Type: application/json');
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Use POST']);
    exit;
}

$type       = $_POST['type']       ?? 'oneway';
$legsJson   = $_POST['legs']       ?? '[]';
$paxJson    = $_POST['passengers'] ?? '[]';
$countsJson = $_POST['counts']     ?? '{}';

$legs       = json_decode($legsJson, true);
$passengers = json_decode($paxJson, true);
$counts     = json_decode($countsJson, true);

if (!is_array($legs) || !is_array($passengers) || !$legs) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid payload']);
    exit;
}

try {
    $mysqli->begin_transaction();

    $adultCount   = (int)($counts['adults']   ?? 0);
    $childCount   = (int)($counts['children'] ?? 0);
    $infantCount  = (int)($counts['infants']  ?? 0);
    $totalPax     = $adultCount + $childCount + $infantCount;

    /* 1) Upsert passengers */
    $stmtP = $mysqli->prepare("
      INSERT INTO passenger (ssn, first_name, last_name, dob, category)
      VALUES (?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        first_name = VALUES(first_name),
        last_name  = VALUES(last_name),
        dob        = VALUES(dob),
        category   = VALUES(category)
    ");

    foreach ($passengers as $p) {
        $ssn  = $p['ssn'];
        $fn   = $p['firstName'];
        $ln   = $p['lastName'];
        $dob  = $p['dob'];         // 'YYYY-MM-DD'
        $cat  = $p['category'];    // 'adult'/'child'/'infant'
        $stmtP->bind_param('sssss', $ssn, $fn, $ln, $dob, $cat);
        $stmtP->execute();
    }

    $bookingsOut = [];
    $ticketsOut  = [];

    /* Prepared statements for bookings / tickets / update seats */
    $stmtPrice = $mysqli->prepare("
      SELECT origin, destination, depart_date, arrival_date,
             depart_time, arrival_time, price, available_seats
      FROM flights
      WHERE flight_id = ? AND depart_date = ?
      FOR UPDATE
    ");

    $stmtBooking = $mysqli->prepare("
      INSERT INTO flight_booking (flight_id, total_price)
      VALUES (?, ?)
    ");

    $stmtTicket = $mysqli->prepare("
      INSERT INTO ticket (flight_booking_id, ssn, price)
      VALUES (?, ?, ?)
    ");

    $stmtUpdateSeats = $mysqli->prepare("
      UPDATE flights
      SET available_seats = available_seats - ?
      WHERE flight_id = ? AND depart_date = ? AND available_seats >= ?
    ");

    foreach ($legs as $leg) {
        $flightId   = $leg['flightId'];
        $departDate = $leg['departDate'];

        // 2) Get current price and seats and lock the row
        $stmtPrice->bind_param('ss', $flightId, $departDate);
        $stmtPrice->execute();
        $res = $stmtPrice->get_result();
        if (!$row = $res->fetch_assoc()) {
            throw new Exception("Flight not found: $flightId on $departDate");
        }

        $adultFare  = (float)$row['price'];
        $childFare  = $adultFare * 0.70;
        $infantFare = $adultFare * 0.10;

        $totalPrice =
            $adultCount  * $adultFare +
            $childCount  * $childFare +
            $infantCount * $infantFare;

        if ((int)$row['available_seats'] < $totalPax) {
            throw new Exception("Not enough seats on $flightId ($departDate)");
        }

        // 3) Insert flight_booking
        $stmtBooking->bind_param('sd', $flightId, $totalPrice);
        $stmtBooking->execute();
        $fbId = $stmtBooking->insert_id;

        // 4) Insert tickets for each passenger
        foreach ($passengers as $idx => $p) {
            $ssn = $p['ssn'];
            $cat = $p['category']; // 'adult'/'child'/'infant'

            if ($cat === 'adult')      $ticketPrice = $adultFare;
            elseif ($cat === 'child')  $ticketPrice = $childFare;
            else                       $ticketPrice = $infantFare;

            $stmtTicket->bind_param('isd', $fbId, $ssn, $ticketPrice);
            $stmtTicket->execute();
            $ticketId = $stmtTicket->insert_id;

            $ticketsOut[] = [
                'ticket_id'        => $ticketId,
                'flight_booking_id'=> $fbId,
                'ssn'              => $ssn,
                'first_name'       => $p['firstName'],
                'last_name'        => $p['lastName'],
                'dob'              => $p['dob'],
                'price'            => $ticketPrice
            ];
        }

        // 5) Update seats
        $stmtUpdateSeats->bind_param('issi', $totalPax, $flightId, $departDate, $totalPax);
        $stmtUpdateSeats->execute();
        if ($stmtUpdateSeats->affected_rows === 0) {
            throw new Exception("Seat update failed for $flightId ($departDate)");
        }

        // collect booking info for output
        $bookingsOut[] = [
            'flight_booking_id' => $fbId,
            'flight_id'         => $flightId,
            'origin'            => $row['origin'],
            'destination'       => $row['destination'],
            'depart_date'       => $row['depart_date'],
            'arrival_date'      => $row['arrival_date'],
            'depart_time'       => $row['depart_time'],
            'arrival_time'      => $row['arrival_time'],
            'total_price'       => $totalPrice
        ];
    }

    $mysqli->commit();
    echo json_encode(['ok' => true, 'bookings' => $bookingsOut, 'tickets' => $ticketsOut]);

} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}