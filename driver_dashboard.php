<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'driver') {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];

$driver_stmt = $conn->prepare("SELECT driver_id, total_rides, average_rating FROM drivers WHERE user_id = ?");
$driver_stmt->execute([$user_id]);
$driver = $driver_stmt->fetch(PDO::FETCH_ASSOC);
$driver_id = $driver['driver_id'] ?? 0;

$vehicle_stmt = $conn->prepare("SELECT * FROM vehicles WHERE driver_id = ?");
$vehicle_stmt->execute([$driver_id]);
$vehicle = $vehicle_stmt->fetch(PDO::FETCH_ASSOC);

$rides_stmt = $conn->prepare("SELECT * FROM rides WHERE driver_id = ? ORDER BY departure_time DESC");
$rides_stmt->execute([$driver_id]);
$rides = $rides_stmt->fetchAll(PDO::FETCH_ASSOC);
$total_rides_count = count($rides);

$active_rides_stmt = $conn->prepare("SELECT COUNT(*) FROM rides WHERE driver_id = ? AND status = 'Open'");
$active_rides_stmt->execute([$driver_id]);
$active_rides = $active_rides_stmt->fetchColumn();

// Incoming ride requests across all of this driver's rides
$requests_stmt = $conn->prepare("
    SELECT rr.request_id, rr.status, rr.created_at,
           ST_X(rr.pickup_location) AS pickup_lng, ST_Y(rr.pickup_location) AS pickup_lat,
           ST_X(rr.dropoff_location) AS dropoff_lng, ST_Y(rr.dropoff_location) AS dropoff_lat,
           r.ride_id, r.start_address, r.destination_address, r.departure_time,
           p.passenger_id, pu.first_name AS passenger_first_name, pu.last_name AS passenger_last_name,
           p.average_rating AS passenger_rating
    FROM ride_requests rr
    JOIN rides r ON rr.ride_id = r.ride_id
    JOIN passengers p ON rr.passenger_id = p.passenger_id
    JOIN users pu ON p.user_id = pu.user_id
    WHERE r.driver_id = ?
    ORDER BY (rr.status = 'Pending') DESC, rr.created_at DESC
");
$requests_stmt->execute([$driver_id]);
$incoming_requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);

$pending_request_count = count(array_filter($incoming_requests, fn($r) => $r['status'] === 'Pending'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Driver Dashboard (Advanced) - TransitCore</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root {
            --bg-dark: #020617;
            --card-bg: rgba(15, 23, 42, 0.6);
            --border-color: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent-teal: #2dd4bf;
            --accent-teal-hover: #14b8a6;
        }
        body { background-color: var(--bg-dark) !important; color: var(--text-main); font-family: 'Inter', sans-serif; padding-bottom: 40px; }
        .navbar-custom { background-color: rgba(2, 6, 23, 0.9) !important; border-bottom: 1px solid var(--border-color); backdrop-filter: blur(10px); }
        .navbar-brand { font-weight: 600; color: var(--text-main) !important; letter-spacing: -0.02em; }
        .btn-logout { color: var(--text-muted); border: 1px solid var(--border-color); background: transparent; padding: 4px 16px; border-radius: 20px; font-size: 0.875rem; text-decoration: none; transition: all 0.2s ease; }
        .btn-logout:hover { color: #f43f5e; border-color: #f43f5e; background: rgba(244, 63, 94, 0.1); }
        .card { background: var(--card-bg) !important; border: 1px solid var(--border-color); border-radius: 12px; backdrop-filter: blur(8px); color: var(--text-main); }
        .card h5 { color: var(--text-muted); font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px; }
        .card h2 { font-weight: 700; color: var(--accent-teal); margin-bottom: 0; }
        .form-control { background-color: transparent !important; border: 1px solid var(--border-color); color: var(--text-main) !important; border-radius: 6px; padding: 10px 14px; }
        .form-control:focus { background-color: transparent; border-color: var(--accent-teal); box-shadow: 0 0 0 3px rgba(45, 212, 191, 0.15); color: var(--text-main); }
        .form-control::-webkit-calendar-picker-indicator { filter: invert(1); opacity: 0.6; cursor: pointer; }
        label { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px; }
        .btn-teal { background-color: var(--accent-teal); color: #020617; font-weight: 600; border: none; transition: all 0.3s ease; }
        .btn-teal:hover { background-color: var(--accent-teal-hover); transform: translateY(-1px); }
        .btn-teal:disabled { background-color: #334155; color: #94a3b8; }
        .btn-outline-danger { border-color: #f43f5e; color: #f43f5e; }
        .btn-outline-danger:hover { background-color: #f43f5e; color: #fff; }
        .btn-outline-success { border-color: #4ade80; color: #4ade80; }
        .btn-outline-success:hover { background-color: #4ade80; color: #020617; }
        .table { color: var(--text-main); margin-bottom: 0; }
        .table th { background-color: transparent !important; border-bottom: 1px solid var(--border-color) !important; color: var(--text-muted) !important; font-weight: 500; font-size: 0.85rem; text-transform: uppercase; }
        .table td { border-bottom: 1px solid rgba(30, 41, 59, 0.5); vertical-align: middle; background: transparent !important; }
        .table-striped>tbody>tr:nth-of-type(odd)>* { color: var(--text-main); background-color: rgba(30, 41, 59, 0.3) !important; }
        .table-striped>tbody>tr:nth-of-type(even)>* { color: var(--text-main); background-color: transparent !important; }
        #map { height: 400px; width: 100%; border-radius: 8px; cursor: crosshair; border: 1px solid var(--border-color); }
        .leaflet-layer, .leaflet-control-zoom-in, .leaflet-control-zoom-out, .leaflet-control-attribution {
            filter: invert(100%) hue-rotate(180deg) brightness(95%) contrast(90%);
        }
        .badge { font-weight: 500; padding: 0.4em 0.6em; }
        .bg-primary { background-color: rgba(45, 212, 191, 0.2) !important; color: var(--accent-teal) !important; border: 1px solid var(--accent-teal); }
        .bg-success { background-color: rgba(34, 197, 94, 0.2) !important; color: #4ade80 !important; border: 1px solid #4ade80; }
        .bg-danger { background-color: rgba(244, 63, 94, 0.2) !important; color: #fb7185 !important; border: 1px solid #fb7185; }
        .bg-secondary { background-color: rgba(148, 163, 184, 0.2) !important; color: #cbd5e1 !important; border: 1px solid #94a3b8; }
        .request-row.request-Rejected { opacity: 0.5; }
        .avatar-sm { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #a78bfa, var(--accent-teal)); display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; color: #020617; }
    </style>
</head>
<body>
    <nav class="navbar navbar-custom px-4 py-3">
        <span class="navbar-brand">TransitCore <span style="color: var(--text-muted); font-weight: 400;">| <?= htmlspecialchars($_SESSION['name'] ?? 'Driver') ?> (Advanced)</span></span>
        <a href="login.php" class="btn-logout">Logout</a>
    </nav>
    <div class="container my-5">

        <div class="row text-center mb-4">
            <div class="col-md-3 mb-3">
                <div class="card p-4 shadow-lg h-100">
                    <h5>Active Rides</h5>
                    <h2><?= (int)$active_rides ?></h2>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card p-4 shadow-lg h-100">
                    <h5>Total Rides</h5>
                    <h2><?= $driver['total_rides'] ?? 0 ?></h2>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card p-4 shadow-lg h-100">
                    <h5>Average Rating</h5>
                    <h2><?= $driver['average_rating'] ?? '5.0' ?> ★</h2>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card p-4 shadow-lg h-100">
                    <h5>Pending Requests</h5>
                    <h2><?= (int)$pending_request_count ?></h2>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card p-4 shadow-lg mb-4 h-100">
                    <h5 style="color: var(--text-main); font-size: 1.1rem;">Create Ride</h5>
                    <form id="createRideForm" onsubmit="submitRide(event)" class="mt-3">
                        <input type="hidden" name="driver_id" value="<?= $driver_id ?>">
                        <div class="mb-3">
                            <label>Start Address</label>
                            <input type="text" name="start_address" class="form-control" placeholder="e.g. Colombo 03" required>
                        </div>
                        <div class="mb-3">
                            <label>Destination Address</label>
                            <input type="text" name="destination_address" class="form-control" placeholder="e.g. Kandy" required>
                        </div>
                        <div class="mb-3">
                            <label>Departure Time</label>
                            <input type="datetime-local" name="departure_time" class="form-control" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <label>Seats</label>
                                <input type="number" name="seats" class="form-control" min="1" placeholder="4" required>
                            </div>
                            <div class="col">
                                <label>Price (Rs.)</label>
                                <input type="number" step="0.01" name="price" class="form-control" min="0" placeholder="1500.00" required>
                            </div>
                        </div>
                        <div class="mb-4 text-muted" style="font-size: 0.75rem;">Click points on the map to trace your geometry route line.</div>
                        <input type="hidden" id="route_geom" name="route_geom">
                        <button type="submit" class="btn btn-teal w-100 py-2" <?= !$vehicle ? 'disabled' : '' ?>>
                            <?= !$vehicle ? 'Register a Vehicle First' : 'Create Ride' ?>
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card p-4 shadow-lg h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0" style="color: var(--text-main); font-size: 1.1rem;">Route Drawing Canvas</h5>
                        <button class="btn btn-sm btn-outline-danger" onclick="clearRoute()">Reset Path</button>
                    </div>
                    <div id="map"></div>
                </div>
            </div>
        </div>

        <div class="card p-4 shadow-lg mt-4">
            <h5 style="color: var(--text-main); font-size: 1.1rem; margin-bottom: 16px;">Incoming Ride Requests</h5>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Passenger</th>
                            <th>Ride</th>
                            <th>Pickup / Dropoff (lat, lng)</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="requestsTableBody">
                        <?php if (count($incoming_requests) > 0): ?>
                            <?php foreach ($incoming_requests as $req): ?>
                                <?php
                                    $badge_class = 'bg-secondary';
                                    if ($req['status'] === 'Pending') $badge_class = 'bg-primary';
                                    elseif ($req['status'] === 'Accepted') $badge_class = 'bg-success';
                                    elseif ($req['status'] === 'Rejected') $badge_class = 'bg-danger';
                                    $initials = strtoupper(substr($req['passenger_first_name'],0,1) . substr($req['passenger_last_name'],0,1));
                                ?>
                                <tr class="request-row request-<?= htmlspecialchars($req['status']) ?>" id="req-row-<?= (int)$req['request_id'] ?>">
                                    <td>
                                        <span class="avatar-sm me-2"><?= htmlspecialchars($initials) ?></span>
                                        <?= htmlspecialchars($req['passenger_first_name'] . ' ' . $req['passenger_last_name']) ?>
                                        <small class="text-muted d-block">★ <?= htmlspecialchars($req['passenger_rating']) ?></small>
                                    </td>
                                    <td style="font-size: 0.88rem; color: var(--text-muted);">
                                        <?= htmlspecialchars($req['start_address']) ?> → <?= htmlspecialchars($req['destination_address']) ?>
                                        <small class="d-block"><?= date('Y-m-d H:i', strtotime($req['departure_time'])) ?></small>
                                    </td>
                                    <td style="font-size: 0.82rem; color: var(--text-muted);">
                                        <?= number_format((float)$req['pickup_lat'], 4) ?>, <?= number_format((float)$req['pickup_lng'], 4) ?>
                                        &rarr;
                                        <?= number_format((float)$req['dropoff_lat'], 4) ?>, <?= number_format((float)$req['dropoff_lng'], 4) ?>
                                    </td>
                                    <td><span class="badge <?= $badge_class ?>" id="req-status-<?= (int)$req['request_id'] ?>"><?= htmlspecialchars($req['status']) ?></span></td>
                                    <td>
                                        <a href="passenger_profile.php?passenger_id=<?= (int)$req['passenger_id'] ?>" class="btn btn-sm btn-outline-secondary mb-1" style="color: var(--text-muted); border-color: var(--border-color);">Profile</a>
                                        <?php if ($req['status'] === 'Pending'): ?>
                                            <button class="btn btn-sm btn-outline-success mb-1" onclick="handleRequest(<?= (int)$req['request_id'] ?>, 'accept')">Accept</button>
                                            <button class="btn btn-sm btn-outline-danger mb-1" onclick="handleRequest(<?= (int)$req['request_id'] ?>, 'reject')">Reject</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No ride requests yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card p-4 shadow-lg mt-4">
            <h5 style="color: var(--text-main); font-size: 1.1rem; margin-bottom: 16px;">My Rides Log</h5>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Departure</th>
                            <th>Price</th>
                            <th>Seats</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($total_rides_count > 0): ?>
                            <?php foreach ($rides as $r): ?>
                            <tr>
                                <td style="color: var(--text-muted);">#<?= $r['ride_id'] ?></td>
                                <td style="font-weight: 500;"><?= htmlspecialchars($r['start_address']) ?></td>
                                <td style="font-weight: 500;"><?= htmlspecialchars($r['destination_address']) ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($r['departure_time'])) ?></td>
                                <td>Rs. <?= htmlspecialchars($r['price']) ?></td>
                                <td><?= $r['available_seats'] ?></td>
                                <td>
                                    <?php
                                    $badge_class = 'bg-secondary';
                                    if ($r['status'] == 'Open') $badge_class = 'bg-primary';
                                    elseif ($r['status'] == 'Completed') $badge_class = 'bg-success';
                                    elseif ($r['status'] == 'Cancelled') $badge_class = 'bg-danger';
                                    ?>
                                    <span class="badge <?= $badge_class ?>"><?= htmlspecialchars($r['status']) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">You haven't created any rides yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        var map = L.map('map').setView([7.8731, 80.7718], 8);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        let routePoints = [];
        let polyline = L.polyline([], {color: '#2dd4bf', weight: 4}).addTo(map);

        map.on('click', function(e) {
            routePoints.push([e.latlng.lng, e.latlng.lat]);
            polyline.addLatLng(e.latlng);
            updateGeomInput();
        });

        function updateGeomInput() {
            if (routePoints.length > 1) {
                let wktStr = "LINESTRING(" + routePoints.map(p => `${p[0]} ${p[1]}`).join(",") + ")";
                document.getElementById('route_geom').value = wktStr;
            }
        }

        function clearRoute() {
            routePoints = [];
            polyline.setLatLngs([]);
            document.getElementById('route_geom').value = "";
        }

        function submitRide(e) {
            e.preventDefault();
            let formData = new FormData(document.getElementById('createRideForm'));

            fetch('create_ride.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    alert(res.message);
                    if (res.status === 'success') {
                        location.reload();
                    }
                })
                .catch(err => {
                    alert('An error occurred while creating the ride.');
                    console.error(err);
                });
        }

        function handleRequest(requestId, action) {
            if (!confirm(`Are you sure you want to ${action} this request?`)) return;

            const formData = new FormData();
            formData.append('request_id', requestId);
            formData.append('action', action);

            fetch('handle_request.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    alert(res.message);
                    if (res.status === 'success') {
                        location.reload();
                    }
                })
                .catch(err => {
                    alert('An error occurred while updating the request.');
                    console.error(err);
                });
        }
    </script>
</body>
</html>