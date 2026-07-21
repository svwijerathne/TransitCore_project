<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'driver') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

try {
    $driver_id = (int)$_POST['driver_id'];
    $start_address = $_POST['start_address'] ?? '';
    $destination_address = $_POST['destination_address'] ?? '';
    $departure_time = $_POST['departure_time'] ?? '';
    $seats = (int)$_POST['seats'];
    $price = (float)$_POST['price'];
    $route_geom = $_POST['route_geom'] ?? '';

    if (empty($start_address) || empty($destination_address) || empty($departure_time) || $seats <= 0 || $price < 0) {
        throw new Exception('Invalid input data');
    }

    $vehicle_stmt = $conn->prepare("SELECT vehicle_id FROM vehicles WHERE driver_id = ? LIMIT 1");
    $vehicle_stmt->execute([$driver_id]);
    
    if ($vehicle_stmt->rowCount() === 0) {
        throw new Exception('No vehicle found for this driver. Please register a vehicle first.');
    }
    
    $vehicle_id = $vehicle_stmt->fetchColumn();

    if (!empty($route_geom)) {
        $ride_stmt = $conn->prepare("
            INSERT INTO rides (driver_id, vehicle_id, start_address, destination_address, departure_time, available_seats, price, status, route_geom)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Open', ST_GeomFromText(?, 4326))
        ");
        $ride_stmt->execute([$driver_id, $vehicle_id, $start_address, $destination_address, $departure_time, $seats, $price, $route_geom]);
    } else {
        $ride_stmt = $conn->prepare("
            INSERT INTO rides (driver_id, vehicle_id, start_address, destination_address, departure_time, available_seats, price, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Open')
        ");
        $ride_stmt->execute([$driver_id, $vehicle_id, $start_address, $destination_address, $departure_time, $seats, $price]);
    }

    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'Ride created successfully!']);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>