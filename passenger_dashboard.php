<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'passenger') {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];

$metric_stmt = $conn->prepare("SELECT passenger_id, total_trips, average_rating FROM passengers WHERE user_id = ?");
$metric_stmt->execute([$user_id]);
$passenger = $metric_stmt->fetch(PDO::FETCH_ASSOC);
$passenger_id = $passenger['passenger_id'] ?? 0;

$pending_stmt = $conn->prepare("SELECT COUNT(*) FROM ride_requests WHERE passenger_id = ? AND status = 'Pending'");
$pending_stmt->execute([$passenger_id]);
$pending_count = $pending_stmt->fetchColumn();

// My Ride Requests — join through to the ride and driver so the
// passenger can see status, driver info, and trip details in one place.
$requests_stmt = $conn->prepare("
    SELECT rr.request_id, rr.status, rr.created_at,
           r.ride_id, r.start_address, r.destination_address, r.departure_time, r.price,
           d.driver_id, du.first_name AS driver_first_name, du.last_name AS driver_last_name, du.phone AS driver_phone
    FROM ride_requests rr
    JOIN rides r ON rr.ride_id = r.ride_id
    JOIN drivers d ON r.driver_id = d.driver_id
    JOIN users du ON d.user_id = du.user_id
    WHERE rr.passenger_id = ?
    ORDER BY rr.created_at DESC
");
$requests_stmt->execute([$passenger_id]);
$my_requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Passenger Dashboard (Advanced) - TransitCore</title>
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
        label { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px; }
        .btn-teal { background-color: var(--accent-teal); color: #020617; font-weight: 600; border: none; transition: all 0.3s ease; }
        .btn-teal:hover { background-color: var(--accent-teal-hover); transform: translateY(-1px); }
        .btn-teal:disabled { background-color: #334155; color: #94a3b8; }
        .btn-outline-secondary { color: var(--text-muted); border-color: var(--border-color); }
        .btn-outline-secondary:hover { background-color: var(--border-color); color: var(--text-main); }
        #map { height: 420px; width: 100%; border-radius: 8px; border: 1px solid var(--border-color); cursor: crosshair; }
        .leaflet-layer, .leaflet-control-zoom-in, .leaflet-control-zoom-out, .leaflet-control-attribution {
            filter: invert(100%) hue-rotate(180deg) brightness(95%) contrast(90%);
        }
        .badge { font-weight: 500; padding: 0.4em 0.6em; }
        .bg-primary { background-color: rgba(45, 212, 191, 0.2) !important; color: var(--accent-teal) !important; border: 1px solid var(--accent-teal); }
        .bg-success { background-color: rgba(34, 197, 94, 0.2) !important; color: #4ade80 !important; border: 1px solid #4ade80; }
        .bg-danger { background-color: rgba(244, 63, 94, 0.2) !important; color: #fb7185 !important; border: 1px solid #fb7185; }
        .bg-secondary { background-color: rgba(148, 163, 184, 0.2) !important; color: #cbd5e1 !important; border: 1px solid #94a3b8; }
        .ride-card { background-color: rgba(30, 41, 59, 0.4); border: 1px solid var(--border-color); border-radius: 10px; padding: 16px; margin-bottom: 14px; }
        .distance-pill { font-size: 0.75rem; color: var(--accent-teal); background: rgba(45,212,191,0.1); border: 1px solid var(--accent-teal); border-radius: 20px; padding: 2px 10px; }
        .avatar-sm { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, var(--accent-teal), #0891b2); display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; color: #020617; }
        .table { color: var(--text-main); margin-bottom: 0; }
        .table th { background-color: transparent !important; border-bottom: 1px solid var(--border-color) !important; color: var(--text-muted) !important; font-weight: 500; font-size: 0.85rem; text-transform: uppercase; }
        .table td { border-bottom: 1px solid rgba(30, 41, 59, 0.5); vertical-align: middle; background: transparent !important; }
        .table-striped>tbody>tr:nth-of-type(odd)>* { color: var(--text-main); background-color: rgba(30, 41, 59, 0.3) !important; }
        .table-striped>tbody>tr:nth-of-type(even)>* { color: var(--text-main); background-color: transparent !important; }
        .nav-pills .nav-link { color: var(--text-muted); }
        .nav-pills .nav-link.active { background-color: var(--accent-teal); color: #020617; font-weight: 600; }
    </style>
</head>
<body>
    <nav class="navbar navbar-custom px-4 py-3">
        <span class="navbar-brand">TransitCore <span style="color: var(--text-muted); font-weight: 400;">| <?= htmlspecialchars($_SESSION['name'] ?? 'Passenger') ?> (Advanced)</span></span>
        <a href="login.php" class="btn-logout">Logout</a>
    </nav>
    <div class="container my-5">

        <div class="row text-center mb-4">
            <div class="col-md-4 mb-3">
                <div class="card p-4 shadow-lg h-100">
                    <h5>Total Trips</h5>
                    <h2><?= $passenger['total_trips'] ?? 0 ?></h2>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card p-4 shadow-lg h-100">
                    <h5>Average Rating</h5>
                    <h2><?= $passenger['average_rating'] ?? '5.0' ?> ★</h2>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card p-4 shadow-lg h-100">
                    <h5>Pending Requests</h5>
                    <h2><?= (int)$pending_count ?></h2>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-5">
                <div class="card p-4 shadow-lg mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0" style="color: var(--text-main); font-size: 1.1rem;">Search Panel</h5>
                        <button class="btn btn-sm btn-outline-secondary" onclick="clearMapPoints()">Clear Points</button>
                    </div>
                    <div class="mb-2 text-muted" style="font-size: 0.75rem;">Click the map to set pickup, then dropoff — or type coordinates directly.</div>

                    <label>Pickup (lat, lng)</label>
                    <div class="row g-2 mb-3">
                        <div class="col"><input type="text" id="pickup_lat" class="form-control" placeholder="Latitude"></div>
                        <div class="col"><input type="text" id="pickup_lng" class="form-control" placeholder="Longitude"></div>
                    </div>

                    <label>Dropoff (lat, lng)</label>
                    <div class="row g-2 mb-3">
                        <div class="col"><input type="text" id="dropoff_lat" class="form-control" placeholder="Latitude"></div>
                        <div class="col"><input type="text" id="dropoff_lng" class="form-control" placeholder="Longitude"></div>
                    </div>

                    <div class="mb-3">
                        <label>Or search by address</label>
                        <div class="row g-2">
                            <div class="col"><input type="text" id="pickup_text" class="form-control" placeholder="Pickup e.g. Colombo"></div>
                            <div class="col"><input type="text" id="dropoff_text" class="form-control" placeholder="Dropoff e.g. Kandy"></div>
                        </div>
                    </div>

                    <button class="btn btn-teal w-100 py-2" onclick="searchRides()">Search Matching Rides</button>
                </div>

                <div class="card p-4 shadow-lg">
                    <h5 style="color: var(--text-main); font-size: 1.1rem;">Available Rides</h5>
                    <div id="ridesList" class="mt-2">
                        <p class="text-muted" style="font-size: 0.9rem;">Perform a search to look up matching rides.</p>
                    </div>
                </div>
            </div>

            <div class="col-md-7">
                <div class="card p-4 shadow-lg h-100">
                    <h5 style="color: var(--text-main); font-size: 1.1rem; margin-bottom: 16px;">Passenger Map</h5>
                    <div id="map"></div>
                </div>
            </div>
        </div>

        <div class="card p-4 shadow-lg mt-4">
            <h5 style="color: var(--text-main); font-size: 1.1rem; margin-bottom: 16px;">My Ride Requests</h5>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Driver</th>
                            <th>Route</th>
                            <th>Departure</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Profile</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($my_requests) > 0): ?>
                            <?php foreach ($my_requests as $req): ?>
                                <?php
                                    $badge_class = 'bg-secondary';
                                    if ($req['status'] === 'Pending') $badge_class = 'bg-primary';
                                    elseif ($req['status'] === 'Accepted') $badge_class = 'bg-success';
                                    elseif ($req['status'] === 'Rejected') $badge_class = 'bg-danger';
                                ?>
                                <tr>
                                    <td style="font-weight: 500;"><?= htmlspecialchars($req['driver_first_name'] . ' ' . $req['driver_last_name']) ?></td>
                                    <td style="color: var(--text-muted); font-size: 0.88rem;"><?= htmlspecialchars($req['start_address']) ?> → <?= htmlspecialchars($req['destination_address']) ?></td>
                                    <td><?= date('Y-m-d H:i', strtotime($req['departure_time'])) ?></td>
                                    <td>Rs. <?= htmlspecialchars($req['price']) ?></td>
                                    <td><span class="badge <?= $badge_class ?>"><?= htmlspecialchars($req['status']) ?></span></td>
                                    <td><a href="driver_profile.php?driver_id=<?= (int)$req['driver_id'] ?>" class="btn btn-sm btn-outline-secondary">View</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">You haven't requested any rides yet.</td></tr>
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

        var pickupMarker = null;
        var dropoffMarker = null;

        map.on('click', function(e) {
            const lat = e.latlng.lat.toFixed(6);
            const lng = e.latlng.lng.toFixed(6);

            if (!pickupMarker) {
                pickupMarker = L.marker(e.latlng, {title: "Pickup"}).addTo(map).bindPopup("Pickup Location").openPopup();
                document.getElementById('pickup_lat').value = lat;
                document.getElementById('pickup_lng').value = lng;
            } else if (!dropoffMarker) {
                dropoffMarker = L.marker(e.latlng, {title: "Dropoff"}).addTo(map).bindPopup("Dropoff Location").openPopup();
                document.getElementById('dropoff_lat').value = lat;
                document.getElementById('dropoff_lng').value = lng;
            }
        });

        function clearMapPoints() {
            if (pickupMarker) map.removeLayer(pickupMarker);
            if (dropoffMarker) map.removeLayer(dropoffMarker);
            pickupMarker = null;
            dropoffMarker = null;
            ['pickup_lat','pickup_lng','dropoff_lat','dropoff_lng','pickup_text','dropoff_text'].forEach(id => {
                document.getElementById(id).value = '';
            });
        }

        function escapeHtml(str) {
            return String(str ?? '').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
        }

        function searchRides() {
            const pLat = document.getElementById('pickup_lat').value.trim();
            const pLng = document.getElementById('pickup_lng').value.trim();
            const dLat = document.getElementById('dropoff_lat').value.trim();
            const dLng = document.getElementById('dropoff_lng').value.trim();
            const pText = document.getElementById('pickup_text').value.trim();
            const dText = document.getElementById('dropoff_text').value.trim();

            const params = new URLSearchParams();
            if (pLat && pLng && dLat && dLng) {
                params.set('pickup_lat', pLat);
                params.set('pickup_lng', pLng);
                params.set('dropoff_lat', dLat);
                params.set('dropoff_lng', dLng);
            } else if (pText || dText) {
                if (pText) params.set('pickup', pText);
                if (dText) params.set('dropoff', dText);
            } else {
                alert('Enter pickup & dropoff coordinates (click the map) or type addresses to search.');
                return;
            }

            const container = document.getElementById('ridesList');
            container.innerHTML = "<p class='text-muted' style='font-size:0.9rem;'>Searching...</p>";

            fetch(`search_rides_advanced.php?${params.toString()}`)
                .then(r => r.json())
                .then(data => {
                    container.innerHTML = "";
                    if (!Array.isArray(data) || data.length === 0) {
                        container.innerHTML = "<p class='text-danger'>No matching rides found.</p>";
                        return;
                    }
                    data.forEach(ride => {
                        const initials = (ride.first_name?.[0] || '') + (ride.last_name?.[0] || '');
                        let distanceHtml = '';
                        if (ride.match_type === 'spatial') {
                            distanceHtml = `<span class="distance-pill me-2">Pickup ${ride.pickup_distance_km}km</span><span class="distance-pill">Dropoff ${ride.dropoff_distance_km}km</span>`;
                        }
                        container.innerHTML += `
                            <div class="ride-card">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="d-flex align-items-center">
                                        <span class="avatar-sm me-2">${escapeHtml(initials.toUpperCase())}</span>
                                        <div>
                                            <div style="font-weight:600;">${escapeHtml(ride.first_name)} ${escapeHtml(ride.last_name)}</div>
                                            <small class="text-muted">★ ${ride.driver_rating ?? '5.0'} &middot; ${escapeHtml(ride.make || '')} ${escapeHtml(ride.model || '')}</small>
                                        </div>
                                    </div>
                                    <span style="color: var(--accent-teal); font-weight: 700; font-size: 1.05rem;">Rs. ${ride.price}</span>
                                </div>
                                <p class="mb-2 small" style="color: var(--text-muted);">${escapeHtml(ride.start_address)} → ${escapeHtml(ride.destination_address)}</p>
                                <div class="mb-2">${distanceHtml}</div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small style="color: var(--text-muted);">Seats: ${ride.available_seats} | Departs: ${ride.departure_time}</small>
                                    <div>
                                        <a href="driver_profile.php?driver_id=${ride.driver_id}" class="btn btn-sm btn-outline-secondary me-1">Profile</a>
                                        <button class="btn btn-sm btn-teal" onclick="requestRide(${ride.ride_id})">Request</button>
                                    </div>
                                </div>
                            </div>`;
                    });
                })
                .catch(err => {
                    console.error(err);
                    container.innerHTML = "<p style='color: #fb7185;'>Error fetching rides.</p>";
                });
        }

        function requestRide(rideId) {
            const pLat = document.getElementById('pickup_lat').value.trim();
            const pLng = document.getElementById('pickup_lng').value.trim();
            const dLat = document.getElementById('dropoff_lat').value.trim();
            const dLng = document.getElementById('dropoff_lng').value.trim();

            if (!pLat || !pLng || !dLat || !dLng) {
                alert('Please set pickup and dropoff coordinates (click the map) before requesting a ride.');
                return;
            }

            const formData = new FormData();
            formData.append('ride_id', rideId);
            formData.append('pickup_lat', pLat);
            formData.append('pickup_lng', pLng);
            formData.append('dropoff_lat', dLat);
            formData.append('dropoff_lng', dLng);

            fetch('request_ride.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    alert(res.message);
                    if (res.status === 'success') {
                        location.reload();
                    }
                })
                .catch(err => {
                    alert('An error occurred while sending the request.');
                    console.error(err);
                });
        }
    </script>
</body>
</html>