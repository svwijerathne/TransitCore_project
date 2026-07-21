<?php
/**
 * handle_request.php
 *
 * A logged-in driver accepts or rejects an incoming ride request.
 * On accept: available_seats decrements by 1, and the ride's status
 * flips to 'Full' once seats reach 0.
 *
 * POST params:
 *   request_id, action ('accept' | 'reject')
 *
 * Response: {status, message}
 */

include 'connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

try {
    $user_id    = (int)$_SESSION['user_id'];
    $request_id = (int)($_POST['request_id'] ?? 0);
    $action     = $_POST['action'] ?? '';

    if ($request_id <= 0 || !in_array($action, ['accept', 'reject'], true)) {
        throw new Exception('Invalid request parameters.');
    }

    // Resolve this driver's driver_id
    $d_stmt = $conn->prepare("SELECT driver_id FROM drivers WHERE user_id = ?");
    $d_stmt->execute([$user_id]);
    $driver_id = $d_stmt->fetchColumn();

    if (!$driver_id) {
        throw new Exception('Driver profile not found for this account.');
    }

    $conn->beginTransaction();

    // Lock the request + ride row together and confirm ownership
    $lookup = $conn->prepare("
        SELECT rr.request_id, rr.status AS request_status, rr.ride_id,
               r.driver_id, r.available_seats, r.status AS ride_status
        FROM ride_requests rr
        JOIN rides r ON rr.ride_id = r.ride_id
        WHERE rr.request_id = ?
        FOR UPDATE
    ");
    $lookup->execute([$request_id]);
    $row = $lookup->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new Exception('Ride request not found.');
    }
    if ((int)$row['driver_id'] !== (int)$driver_id) {
        throw new Exception('You do not have permission to manage this request.');
    }
    if ($row['request_status'] !== 'Pending') {
        throw new Exception('This request has already been ' . strtolower($row['request_status']) . '.');
    }

    if ($action === 'accept') {
        if ((int)$row['available_seats'] <= 0) {
            throw new Exception('No seats remaining on this ride.');
        }

        $new_seats = (int)$row['available_seats'] - 1;
        $new_ride_status = $new_seats === 0 ? 'Full' : $row['ride_status'];

        $update_ride = $conn->prepare("UPDATE rides SET available_seats = ?, status = ? WHERE ride_id = ?");
        $update_ride->execute([$new_seats, $new_ride_status, $row['ride_id']]);

        $update_req = $conn->prepare("UPDATE ride_requests SET status = 'Accepted' WHERE request_id = ?");
        $update_req->execute([$request_id]);

        $message = 'Request accepted.';
    } else {
        $update_req = $conn->prepare("UPDATE ride_requests SET status = 'Rejected' WHERE request_id = ?");
        $update_req->execute([$request_id]);

        $message = 'Request rejected.';
    }

    $conn->commit();

    echo json_encode(['status' => 'success', 'message' => $message]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'Database error while updating the request.']);
}