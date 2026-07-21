<?php
/**
 * request_ride.php
 *
 * A logged-in passenger submits a request to join a specific ride.
 * Stores the passenger's chosen pickup/dropoff as PostGIS POINT
 * geometries so they can later be matched/analyzed spatially.
 *
 * POST params:
 *   ride_id, pickup_lat, pickup_lng, dropoff_lat, dropoff_lng
 *
 * Response: {status, message, request_id?}
 */

include 'connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'passenger') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

try {
    $user_id = (int)$_SESSION['user_id'];

    $ride_id     = (int)($_POST['ride_id'] ?? 0);
    $pickup_lat  = $_POST['pickup_lat']  ?? null;
    $pickup_lng  = $_POST['pickup_lng']  ?? null;
    $dropoff_lat = $_POST['dropoff_lat'] ?? null;
    $dropoff_lng = $_POST['dropoff_lng'] ?? null;

    if ($ride_id <= 0) {
        throw new Exception('A valid ride must be selected.');
    }
    if (!is_numeric($pickup_lat) || !is_numeric($pickup_lng) || !is_numeric($dropoff_lat) || !is_numeric($dropoff_lng)) {
        throw new Exception('Pickup and dropoff coordinates are required.');
    }

    $pickup_lat  = (float)$pickup_lat;
    $pickup_lng  = (float)$pickup_lng;
    $dropoff_lat = (float)$dropoff_lat;
    $dropoff_lng = (float)$dropoff_lng;

    if ($pickup_lat < -90 || $pickup_lat > 90 || $dropoff_lat < -90 || $dropoff_lat > 90
        || $pickup_lng < -180 || $pickup_lng > 180 || $dropoff_lng < -180 || $dropoff_lng > 180) {
        throw new Exception('Coordinates out of range.');
    }

    // Resolve passenger_id from the session's user_id
    $p_stmt = $conn->prepare("SELECT passenger_id FROM passengers WHERE user_id = ?");
    $p_stmt->execute([$user_id]);
    $passenger_id = $p_stmt->fetchColumn();

    if (!$passenger_id) {
        throw new Exception('Passenger profile not found for this account.');
    }

    // Confirm the ride exists and is still open
    $ride_stmt = $conn->prepare("SELECT ride_id, status, available_seats FROM rides WHERE ride_id = ?");
    $ride_stmt->execute([$ride_id]);
    $ride = $ride_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ride) {
        throw new Exception('Ride not found.');
    }
    if ($ride['status'] !== 'Open' || (int)$ride['available_seats'] <= 0) {
        throw new Exception('This ride is no longer accepting requests.');
    }

    // Prevent duplicate active requests from the same passenger for the same ride
    $dup_stmt = $conn->prepare("
        SELECT request_id FROM ride_requests
        WHERE ride_id = ? AND passenger_id = ? AND status IN ('Pending', 'Accepted')
    ");
    $dup_stmt->execute([$ride_id, $passenger_id]);
    if ($dup_stmt->rowCount() > 0) {
        throw new Exception('You have already requested this ride.');
    }

    $insert_stmt = $conn->prepare("
        INSERT INTO ride_requests (ride_id, passenger_id, pickup_location, dropoff_location, status)
        VALUES (
            ?, ?,
            ST_SetSRID(ST_MakePoint(?, ?), 4326),
            ST_SetSRID(ST_MakePoint(?, ?), 4326),
            'Pending'
        )
        RETURNING request_id
    ");
    $insert_stmt->execute([
        $ride_id, $passenger_id,
        $pickup_lng, $pickup_lat,
        $dropoff_lng, $dropoff_lat,
    ]);
    $request_id = $insert_stmt->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'message' => 'Ride request sent!',
        'request_id' => $request_id,
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error while creating the request.']);
}