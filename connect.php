<?php
$host = "localhost";
$port = "5432";
$dbname = "transitcoredb";
$user = "vimi_05"; 
$password = "";    

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['action']) && $_GET['action'] === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handleRegistration($conn);
}

function handleRegistration($conn) {
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $nic = $_POST['nic'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'passenger';

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($nic) || empty($gender)) {
        die("All fields are required!");
    }

    try {
        $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check_stmt->execute([$email]);
        if ($check_stmt->rowCount() > 0) {
            die("Email already registered!");
        }

        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        $user_stmt = $conn->prepare("
            INSERT INTO users (first_name, last_name, email, password_hash, phone, role, nic, gender)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $user_stmt->execute([$first_name, $last_name, $email, $password_hash, $phone, $role, $nic, $gender]);
        $user_id = $conn->lastInsertId();

        if ($role === 'driver') {
            $license_number = $_POST['license_number'] ?? '';
            $vehicle_number = $_POST['vehicle_number'] ?? '';

            if (empty($license_number) || empty($vehicle_number)) {
                die("License number and vehicle number are required for drivers!");
            }

            $driver_stmt = $conn->prepare("
                INSERT INTO drivers (user_id, license_number, verification_status)
                VALUES (?, ?, 'Pending')
            ");
            $driver_stmt->execute([$user_id, $license_number]);
            $driver_id = $conn->lastInsertId();

            $make = $_POST['vehicle_make'] ?? 'Unknown';
            $model = $_POST['vehicle_model'] ?? 'Unknown';
            $color = $_POST['vehicle_color'] ?? 'Unknown';
            $capacity = $_POST['vehicle_capacity'] ?? 4;

            $vehicle_stmt = $conn->prepare("
                INSERT INTO vehicles (driver_id, make, model, plate_number, color, seat_capacity)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $vehicle_stmt->execute([$driver_id, $make, $model, $vehicle_number, $color, $capacity]);
        } 
        elseif ($role === 'passenger') {
            $passenger_stmt = $conn->prepare("
                INSERT INTO passengers (user_id)
                VALUES (?)
            ");
            $passenger_stmt->execute([$user_id]);
        }

        $_SESSION['user_id'] = $user_id;
        $_SESSION['role'] = $role;
        $_SESSION['name'] = $first_name . ' ' . $last_name;

        if ($role === 'driver') {
            header('Location: driver_dashboard.php');
        } else {
            header('Location: passenger_dashboard.php');
        }
        exit;

    } catch (PDOException $e) {
        die("Registration Error: " . $e->getMessage());
    }
}
?>