<?php
// flights_search.php
session_start();
header('Content-Type: application/json');

// (optional) require login to search
if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

// READ INPUT
$origin     = trim($_GET['origin']      ?? '');
$dest       = trim($_GET['destination'] ?? '');
$departDate = trim($_GET['departDate']  ?? '');
$pax        = (int)($_GET['pax']        ?? 0);

// basic sanity check
if ($origin === '' || $dest === '' || $departDate === '' || $pax < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    exit;
}

// CONNECT TO DB  (change dbname / user / pass to match your setup)
$mysqli = new mysqli('localhost', 'root', '', 'travel_deals');
if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'DB connect error']);
    exit;
}

// helper: convert DB row -> JSON flight object used by JavaScript
function row_to_flight($row) {
    return [
        'flightId'       => $row['flight_id'],
        'origin'         => $row['origin'],
        'destination'    => $row['destination'],
        'departDate'     => $row['depart_date'],
        'arrivalDate'    => $row['arrival_date'],
        'departTime'     => substr($row['depart_time'], 0, 5),  // "08:00"
        'arrivalTime'    => substr($row['arrival_time'], 0, 5),
        'availableSeats' => (int)$row['available_seats'],
        'price'          => (float)$row['price']
    ];
}

$exact = [];
$alt   = [];

/* -------- 1) EXACT DATE SEARCH -------- */
$sql = "SELECT flight_id, origin, destination, depart_date, arrival_date,
               depart_time, arrival_time, available_seats, price
        FROM flights
        WHERE origin = ? AND destination = ?
          AND depart_date = ?
          AND available_seats >= ?
        ORDER BY depart_time";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('sssi', $origin, $dest, $departDate, $pax);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $exact[] = row_to_flight($row);
}
$stmt->close();

/* -------- 2) ±3 DAYS IF NO EXACT -------- */
$altOut = [];
if (count($exact) === 0) {
    $sqlAlt = "SELECT flight_id, origin, destination, depart_date, arrival_date,
                      depart_time, arrival_time, available_seats, price
               FROM flights
               WHERE origin = ? AND destination = ?
                 AND depart_date BETWEEN DATE_SUB(?, INTERVAL 3 DAY)
                                     AND DATE_ADD(?, INTERVAL 3 DAY)
                 AND depart_date <> ?
                 AND available_seats >= ?
               ORDER BY depart_date, depart_time";
    $stmt = $mysqli->prepare($sqlAlt);
    $stmt->bind_param('sssssi',
        $origin, $dest,
        $departDate, $departDate,
        $departDate, $pax
    );
    $stmt->execute();
    $res = $stmt->get_result();

    // group by date to match your renderAltFlights() structure
    $byDate = [];
    while ($row = $res->fetch_assoc()) {
        $d = $row['depart_date'];
        if (!isset($byDate[$d])) {
            $byDate[$d] = [];
        }
        $byDate[$d][] = row_to_flight($row);
    }
    $stmt->close();

    foreach ($byDate as $d => $flights) {
        $altOut[] = ['date' => $d, 'flights' => $flights];
    }
}

echo json_encode([
    'status' => 'ok',
    'exact'  => $exact,
    'alt'    => $altOut
]);