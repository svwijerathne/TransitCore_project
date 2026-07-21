<?php
include 'connect.php';

header('Content-Type: application/json');

try {
    $sql = "SELECT ride_id, ST_AsGeoJSON(route_geom) AS geom FROM rides WHERE route_geom IS NOT NULL";
    $stmt = $conn->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($data);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database query failed']);
}
?>