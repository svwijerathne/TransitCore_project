<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$host = 'localhost';
$db   = 'maas_fintech_db';
$user = 'root'; 
$pass = ''; // Set your database password here
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    echo json_encode(["error" => "Database connection dropped: " . $e->getMessage()]);
    exit;
}

// Check what request actions the app.js script is requesting
$action = $_GET['action'] ?? '';

if ($action === 'get_faqs') {
    try {
        $stmt = $pdo->query("SELECT question AS q, answer AS a FROM faqs");
        echo json_encode($stmt->fetchAll());
    } catch (Exception $e) {
        echo json_encode(["error" => $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "System operational", "message" => "TransitCore Ledger Matrix Active."]);
}
?>