<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'connect.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        try {
            $stmt = $conn->prepare("SELECT user_id, first_name, last_name, password_hash, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if (password_verify($password, $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];

                    if ($user['role'] === 'admin') {
                        header('Location: admin_dashboard.php');
                    } elseif ($user['role'] === 'driver') {
                        header('Location: driver_dashboard.php');
                    } elseif ($user['role'] === 'passenger') {
                        header('Location: passenger_dashboard.php');
                    }
                    exit;
                } else {
                    $error = "Invalid password. Please try again.";
                }
            } else {
                $error = "No account found with that email address.";
            }
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - TransitCore</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        body { 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            min-height: 100vh; 
            padding-top: 0; 
            background-color: #020617; 
        }
        
        /* Premium Card Styling for the Form */
        .auth-container { 
            width: 100%;
            max-width: 420px; 
            margin: 24px; 
            padding: 40px;
            background: rgba(15, 23, 42, 0.6); 
            border: 1px solid #1e293b;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
            box-sizing: border-box;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .auth-header h2 {
            margin-bottom: 8px;
            font-weight: 600;
            letter-spacing: -0.02em;
        }
        
        /* Elite Link Styling */
        .auth-footer-note {
            text-align: center;
            margin-top: 24px;
            font-size: 0.85rem;
            color: #94a3b8;
        }

        .auth-footer-note a {
            color: #2dd4bf; /* Teal accent to replace the default purple */
            text-decoration: none;
            font-weight: 500;
            margin-left: 4px;
            transition: all 0.3s ease;
        }

        .auth-footer-note a:hover {
            color: #5eead4; /* Brighter teal on hover */
            text-shadow: 0 0 10px rgba(45, 212, 191, 0.3); /* Subtle glow effect */
        }
    </style>
</head>
<body>

    <main class="auth-container">
        
        <div class="auth-header">
            <h2>Welcome back</h2>
            <p class="section-subtitle" style="margin-bottom: 0;">Sign in to access your TransitCore account.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-box alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" autocomplete="off">
            <div class="input-wrapper">
                <label class="form-label-sm" for="email" style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Email Address</label>
                <input type="email" name="email" id="email" placeholder="name@example.com" required autofocus>
            </div>

            <div class="input-wrapper">
                <label class="form-label-sm" for="password" style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Password</label>
                <input type="password" name="password" id="password" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top: 16px;">Sign In</button>
        </form>

        <div class="auth-footer-note">
            <p style="margin: 0;">Don't have an account? <a href="register.html">Register here</a></p>
        </div>
    </main>

</body>
</html>