<?php
/**
 * search_rides_advanced.php
 *
 * Finds open rides that match a passenger's pickup/dropoff location
 * using PostGIS spatial queries. Falls back to a plain text search
 * on start_address / destination_address when coordinates are not
 * supplied.
 *
 * GET params:
 *   pickup_lat, pickup_lng   - passenger pickup coordinates
 *   dropoff_lat, dropoff_lng - passenger dropoff coordinates
 *   pickup, dropoff          - free-text fallback (address search)
 *
 * Response: JSON array of rides, each including driver, vehicle,
 * rating info, and (when coordinates were used) pickup_distance_km /
 * dropoff_distance_km.
 */

include 'connect.php';

header('Content-Type: application/json');

const MATCH_RADIUS_METERS = 2000; // 2km buffer around the route

$pickup_lat  = isset($_GET['pickup_lat'])  && $_GET['pickup_lat']  !== '' ? (float)$_GET['pickup_lat']  : null;
$pickup_lng  = isset($_GET['pickup_lng'])  && $_GET['pickup_lng']  !== '' ? (float)$_GET['pickup_lng']  : null;
$dropoff_lat = isset($_GET['dropoff_lat']) && $_GET['dropoff_lat'] !== '' ? (float)$_GET['dropoff_lat'] : null;
$dropoff_lng = isset($_GET['dropoff_lng']) && $_GET['dropoff_lng'] !== '' ? (float)$_GET['dropoff_lng'] : null;

$pickup_text  = trim($_GET['pickup'] ?? '');
$dropoff_text = trim($_GET['dropoff'] ?? '');

$has_pickup_coords  = $pickup_lat  !== null && $pickup_lng  !== null;
$has_dropoff_coords = $dropoff_lat !== null && $dropoff_lng !== null;

function validate_coord($lat, $lng) {
    return is_numeric($lat) && is_numeric($lng)
        && $lat >= -90 && $lat <= 90
        && $lng >= -180 && $lng <= 180;
}

try {
    if ($has_pickup_coords && $has_dropoff_coords) {

        if (!validate_coord($pickup_lat, $pickup_lng) || !validate_coord($dropoff_lat, $dropoff_lng)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid coordinates supplied']);
            exit;
        }

        // Spatial match: both pickup and dropoff points must fall
        // within MATCH_RADIUS_METERS of the driver's route line.
        $sql = "
            SELECT
                r.ride_id, r.start_address, r.destination_address,
                r.departure_time, r.available_seats, r.price, r.status,
                u.first_name, u.last_name, u.phone,
                d.driver_id, d.average_rating AS driver_rating, d.total_rides,
                d.verification_status,
                v.make, v.model, v.color, v.plate_number, v.seat_capacity,
                ST_Distance(
                    r.route_geom::geography,
                    ST_SetSRID(ST_MakePoint(:pickup_lng, :pickup_lat), 4326)::geography
                ) / 1000.0 AS pickup_distance_km,
                ST_Distance(
                    r.route_geom::geography,
                    ST_SetSRID(ST_MakePoint(:dropoff_lng, :dropoff_lat), 4326)::geography
                ) / 1000.0 AS dropoff_distance_km
            FROM rides r
            JOIN drivers d  ON r.driver_id = d.driver_id
            JOIN users u    ON d.user_id = u.user_id
            JOIN vehicles v ON r.vehicle_id = v.vehicle_id
            WHERE r.status = 'Open'
              AND r.route_geom IS NOT NULL
              AND ST_DWithin(
                    r.route_geom::geography,
                    ST_SetSRID(ST_MakePoint(:pickup_lng2, :pickup_lat2), 4326)::geography,
                    :radius1
                  )
              AND ST_DWithin(
                    r.route_geom::geography,
                    ST_SetSRID(ST_MakePoint(:dropoff_lng2, :dropoff_lat2), 4326)::geography,
                    :radius2
                  )
            ORDER BY (pickup_distance_km + dropoff_distance_km) ASC, r.departure_time ASC
        ";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':pickup_lng'    => $pickup_lng,
            ':pickup_lat'    => $pickup_lat,
            ':dropoff_lng'   => $dropoff_lng,
            ':dropoff_lat'   => $dropoff_lat,
            ':pickup_lng2'   => $pickup_lng,
            ':pickup_lat2'   => $pickup_lat,
            ':dropoff_lng2'  => $dropoff_lng,
            ':dropoff_lat2'  => $dropoff_lat,
            ':radius1'       => MATCH_RADIUS_METERS,
            ':radius2'       => MATCH_RADIUS_METERS,
        ]);
        $rides = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rides as &$ride) {
            $ride['pickup_distance_km']  = round((float)$ride['pickup_distance_km'], 2);
            $ride['dropoff_distance_km'] = round((float)$ride['dropoff_distance_km'], 2);
            $ride['match_type'] = 'spatial';
        }
        unset($ride);

        echo json_encode($rides);

    } else {
        // Text fallback: no coordinates given, search by address text.
        $sql = "
            SELECT
                r.ride_id, r.start_address, r.destination_address,
                r.departure_time, r.available_seats, r.price, r.status,
                u.first_name, u.last_name, u.phone,
                d.driver_id, d.average_rating AS driver_rating, d.total_rides,
                d.verification_status,
                v.make, v.model, v.color, v.plate_number, v.seat_capacity
            FROM rides r
            JOIN drivers d  ON r.driver_id = d.driver_id
            JOIN users u    ON d.user_id = u.user_id
            JOIN vehicles v ON r.vehicle_id = v.vehicle_id
            WHERE r.status = 'Open'
        ";
        $params = [];

        if (!empty($pickup_text)) {
            $sql .= " AND r.start_address ILIKE ?";
            $params[] = '%' . $pickup_text . '%';
        }
        if (!empty($dropoff_text)) {
            $sql .= " AND r.destination_address ILIKE ?";
            $params[] = '%' . $dropoff_text . '%';
        }

        $sql .= " ORDER BY r.departure_time ASC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $rides = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rides as &$ride) {
            $ride['match_type'] = 'text';
        }
        unset($ride);

        echo json_encode($rides);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database query failed']);
}