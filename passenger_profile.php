<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'connect.php';

if (!isset($_SESSION['role'])) {
    header('Location: login.php');
    exit();
}

$passenger_id = (int)($_GET['passenger_id'] ?? 0);
if ($passenger_id <= 0) {
    die("Invalid passenger.");
}

$stmt = $conn->prepare("
    SELECT p.passenger_id, p.average_rating, p.total_trips,
           u.user_id, u.first_name, u.last_name, u.phone, u.gender
    FROM passengers p
    JOIN users u ON p.user_id = u.user_id
    WHERE p.passenger_id = ?
");
$stmt->execute([$passenger_id]);
$passenger = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$passenger) {
    die("Passenger not found.");
}

$reviews_stmt = $conn->prepare("
    SELECT rt.rating, rt.review, rt.created_at, u.first_name, u.last_name
    FROM ratings rt
    JOIN users u ON rt.reviewer_id = u.user_id
    WHERE rt.reviewee_id = ?
    ORDER BY rt.created_at DESC
    LIMIT 5
");
$reviews_stmt->execute([$passenger['user_id']]);
$reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);

$initials = strtoupper(substr($passenger['first_name'], 0, 1) . substr($passenger['last_name'], 0, 1));

function starDisplay($rating) {
    $rating = round((float)$rating);
    return str_repeat('★', max(0, min(5, $rating))) . str_repeat('☆', 5 - max(0, min(5, $rating)));
}

$back_url = ($_SESSION['role'] === 'driver') ? 'driver_dashboard.php' : 'passenger_dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($passenger['first_name'] . ' ' . $passenger['last_name']) ?> - Passenger Profile - TransitCore</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        :root {
            --bg-dark: #020617;
            --card-bg: rgba(15, 23, 42, 0.6);
            --border-color: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent-teal: #2dd4bf;
        }
        body {
            background-color: var(--bg-dark) !important;
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            padding-bottom: 40px;
        }
        .navbar-custom {
            background-color: rgba(2, 6, 23, 0.9) !important;
            border-bottom: 1px solid var(--border-color);
            backdrop-filter: blur(10px);
        }
        .navbar-brand { font-weight: 600; color: var(--text-main) !important; letter-spacing: -0.02em; }
        .btn-logout {
            color: var(--text-muted); border: 1px solid var(--border-color); background: transparent;
            padding: 4px 16px; border-radius: 20px; font-size: 0.875rem; text-decoration: none; transition: all 0.2s ease;
        }
        .btn-logout:hover { color: #f43f5e; border-color: #f43f5e; background: rgba(244, 63, 94, 0.1); }
        .card { background: var(--card-bg) !important; border: 1px solid var(--border-color); border-radius: 12px; backdrop-filter: blur(8px); color: var(--text-main); }
        .avatar-circle {
            width: 96px; height: 96px; border-radius: 50%;
            background: linear-gradient(135deg, #a78bfa, var(--accent-teal));
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; font-weight: 700; color: #020617; margin: 0 auto 16px auto;
        }
        .stars { color: #facc15; font-size: 1.3rem; letter-spacing: 2px; }
        .badge { font-weight: 500; padding: 0.4em 0.7em; }
        .bg-primary { background-color: rgba(45, 212, 191, 0.2) !important; color: var(--accent-teal) !important; border: 1px solid var(--accent-teal); }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(30,41,59,0.5); font-size: 0.92rem; }
        .info-row:last-child { border-bottom: none; }
        .info-row span:first-child { color: var(--text-muted); }
        .review-item { padding: 14px 0; border-bottom: 1px solid rgba(30,41,59,0.5); }
        .review-item:last-child { border-bottom: none; }
    </style>
</head>
<body>
    <nav class="navbar navbar-custom px-4 py-3">
        <span class="navbar-brand">TransitCore <span style="color: var(--text-muted); font-weight: 400;">| Passenger Profile</span></span>
        <a href="login.php" class="btn-logout">Logout</a>
    </nav>
    <div class="container my-5">
        <a href="<?= htmlspecialchars($back_url) ?>" class="btn btn-outline-secondary btn-sm mb-4" style="color: var(--text-muted); border-color: var(--border-color);">&larr; Back to dashboard</a>

        <div class="row">
            <div class="col-md-4">
                <div class="card p-4 shadow-lg text-center mb-4">
                    <div class="avatar-circle"><?= htmlspecialchars($initials) ?></div>
                    <h4 class="mb-1"><?= htmlspecialchars($passenger['first_name'] . ' ' . $passenger['last_name']) ?></h4>
                    <div class="stars mb-2"><?= starDisplay($passenger['average_rating']) ?></div>
                    <p class="text-muted mb-3"><?= htmlspecialchars($passenger['average_rating']) ?> average rating &middot; <?= (int)$passenger['total_trips'] ?> trips completed</p>
                    <span class="badge bg-primary mx-auto">Passenger</span>
                </div>

                <div class="card p-4 shadow-lg">
                    <h5 style="color: var(--text-muted); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em;">Contact</h5>
                    <div class="info-row"><span>Phone</span><span><?= htmlspecialchars($passenger['phone'] ?? 'N/A') ?></span></div>
                    <div class="info-row"><span>Gender</span><span><?= htmlspecialchars($passenger['gender'] ?? 'N/A') ?></span></div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card p-4 shadow-lg">
                    <h5 style="color: var(--text-main); font-size: 1.05rem; margin-bottom: 8px;">Recent Reviews</h5>
                    <?php if (count($reviews) === 0): ?>
                        <p class="text-muted py-3">No reviews yet.</p>
                    <?php else: ?>
                        <?php foreach ($reviews as $rv): ?>
                            <div class="review-item">
                                <div class="d-flex justify-content-between">
                                    <strong><?= htmlspecialchars($rv['first_name'] . ' ' . $rv['last_name']) ?></strong>
                                    <span class="stars" style="font-size: 0.95rem;"><?= starDisplay($rv['rating']) ?></span>
                                </div>
                                <?php if (!empty($rv['review'])): ?>
                                    <p class="text-muted mb-0 mt-1" style="font-size: 0.9rem;"><?= htmlspecialchars($rv['review']) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>