<?php
include 'connect.php';

header('Content-Type: application/json');

try {
    $sql = "SELECT route_id, route_number, route_name, ST_AsGeoJSON(route_geom) as geom FROM bus_routes";
    $stmt = $conn->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($data);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database query failed']);
}
?>