<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'connect.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

if (isset($_POST['action']) && isset($_POST['driver_id'])) {
    $driver_id = (int)$_POST['driver_id'];
    $status = $_POST['action'] === 'Approve' ? 'Verified' : 'Rejected';
    
    $stmt = $conn->prepare("UPDATE drivers SET verification_status = ? WHERE driver_id = ?");
    try {
        $stmt->execute([$status, $driver_id]);
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}

try {
    $users_count      = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $drivers_count    = $conn->query("SELECT COUNT(*) FROM drivers")->fetchColumn();
    $passengers_count = $conn->query("SELECT COUNT(*) FROM passengers")->fetchColumn();
    $rides_count      = $conn->query("SELECT COUNT(*) FROM rides")->fetchColumn();

    $verification_query = "
        SELECT d.driver_id, u.first_name, u.last_name, d.license_number, d.verification_status 
        FROM drivers d 
        JOIN users u ON u.user_id = d.user_id 
        WHERE d.verification_status = 'Pending'
        ORDER BY d.driver_id DESC
    ";
    $verifications = $conn->query($verification_query)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - TransitCore</title>
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

        body {
            background-color: var(--bg-dark) !important;
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            padding-bottom: 40px;
        }

        /* Navbar Styling */
        .navbar-custom {
            background-color: rgba(2, 6, 23, 0.9) !important;
            border-bottom: 1px solid var(--border-color);
            backdrop-filter: blur(10px);
        }
        .navbar-brand {
            font-weight: 600;
            color: var(--text-main) !important;
            letter-spacing: -0.02em;
        }

        /* Logout Button Custom Styling */
        .btn-logout {
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            background: transparent;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.875rem;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-logout:hover {
            color: #f43f5e; 
            border-color: #f43f5e;
            background: rgba(244, 63, 94, 0.1);
        }

        /* Card Styling */
        .card {
            background: var(--card-bg) !important;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            backdrop-filter: blur(8px);
            color: var(--text-main);
        }
        .card h5 {
            color: var(--text-muted);
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 12px;
        }
        .card h2 {
            font-weight: 700;
            color: var(--accent-teal);
            margin-bottom: 0;
        }

        /* Table Styling */
        .table {
            color: var(--text-main);
            margin-bottom: 0;
        }
        .table th {
            background-color: transparent !important;
            border-bottom: 1px solid var(--border-color) !important;
            color: var(--text-muted) !important;
            font-weight: 500;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        .table td {
            border-bottom: 1px solid rgba(30, 41, 59, 0.5);
            vertical-align: middle;
            background: transparent !important;
        }
        .table-striped>tbody>tr:nth-of-type(odd)>* {
            color: var(--text-main);
            background-color: rgba(30, 41, 59, 0.3) !important;
        }
        .table-striped>tbody>tr:nth-of-type(even)>* {
            color: var(--text-main);
            background-color: transparent !important;
        }

        /* Map Styling & Dark Mode Filter */
        #map { 
            height: 500px; 
            width: 100%; 
            border-radius: 8px; 
            border: 1px solid var(--border-color);
            z-index: 1; 
        }
        .leaflet-layer,
        .leaflet-control-zoom-in,
        .leaflet-control-zoom-out,
        .leaflet-control-attribution {
            filter: invert(100%) hue-rotate(180deg) brightness(95%) contrast(90%);
        }
        
        /* Badges */
        .badge { font-weight: 500; padding: 0.4em 0.6em; }
        .bg-primary { background-color: rgba(45, 212, 191, 0.2) !important; color: var(--accent-teal) !important; border: 1px solid var(--accent-teal); }
        .bg-success { background-color: rgba(34, 197, 94, 0.2) !important; color: #4ade80 !important; border: 1px solid #4ade80; }
    </style>
</head>
<body>
    <nav class="navbar navbar-custom px-4 py-3">
        <span class="navbar-brand">TransitCore <span style="color: var(--text-muted); font-weight: 400;">| Admin Dashboard</span></span>
        <a href="login.php" class="btn-logout">Logout</a>
    </nav>
    <div class="container my-5">
        
        <div class="row text-center mb-4">
            <div class="col-md-3 mb-3">
                <div class="card p-4 shadow-lg h-100">
                    <h5>Total Users</h5>
                    <h2><?= (int)$users_count ?></h2>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card p-4 shadow-lg h-100">
                    <h5>Drivers</h5>
                    <h2><?= (int)$drivers_count ?></h2>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card p-4 shadow-lg h-100">
                    <h5>Passengers</h5>
                    <h2><?= (int)$passengers_count ?></h2>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card p-4 shadow-lg h-100">
                    <h5>Total Rides</h5>
                    <h2><?= (int)$rides_count ?></h2>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-5">
                <div class="card p-4 shadow-lg mb-4 h-100">
                    <h5 style="color: var(--text-main); font-size: 1.1rem;">Pending Driver Verifications</h5>
                    <table class="table table-striped mt-3">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>License</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($verifications as $row): ?>
                            <tr>
                                <td style="font-weight: 500;"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                <td style="color: var(--text-muted);"><?= htmlspecialchars($row['license_number']) ?></td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="driver_id" value="<?= (int)$row['driver_id'] ?>">
                                        <button type="submit" name="action" value="Approve" class="btn btn-success btn-sm" style="background: rgba(34, 197, 94, 0.2); border: 1px solid #4ade80; color: #4ade80;">Approve</button>
                                        <button type="submit" name="action" value="Reject" class="btn btn-danger btn-sm" style="background: rgba(244, 63, 94, 0.2); border: 1px solid #fb7185; color: #fb7185;">Reject</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(count($verifications) === 0): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">No pending verifications.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-md-7">
                <div class="card p-4 shadow-lg h-100">
                    <h5 style="color: var(--text-main); font-size: 1.1rem; margin-bottom: 12px;">Route Analytics Map</h5>
                    <div class="mb-3">
                        <span class="badge bg-success me-2">Bus Routes</span>
                        <span class="badge bg-primary">Active Rides</span>
                    </div>
                    <div id="map"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        var map = L.map('map').setView([7.8731, 80.7718], 8);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        fetch('get_bus_routes.php')
            .then(r => r.json())
            .then(data => {
                if (Array.isArray(data)) {
                    data.forEach(route => {
                        if(route.geom) {
                            try {
                                L.geoJSON(JSON.parse(route.geom), { 
                                    style: { color: '#4ade80', weight: 4 } 
                                }).addTo(map);
                            } catch(e) {
                                console.error('Error parsing route geometry:', e);
                            }
                        }
                    });
                }
            })
            .catch(err => console.error('Error fetching bus routes:', err));

        fetch('get_ride_routes.php')
            .then(r => r.json())
            .then(data => {
                if (Array.isArray(data)) {
                    data.forEach(route => {
                        if(route.geom) {
                            try {
                                L.geoJSON(JSON.parse(route.geom), { 
                                    style: { color: '#2dd4bf', weight: 3 } 
                                }).addTo(map);
                            } catch(e) {
                                console.error('Error parsing ride geometry:', e);
                            }
                        }
                    });
                }
            })
            .catch(err => console.error('Error fetching ride routes:', err));
    </script>
</body>
</html>