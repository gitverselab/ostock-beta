<?php
session_start();
include 'db.php';
$error_message = '';

// --- Secure "Remember Me" Check ---
if (empty($_SESSION['user_id']) && !empty($_COOKIE['remember_token'])) {
    list($selector, $validator) = explode(':', $_COOKIE['remember_token']);

    if ($selector && $validator) {
        $stmt = $conn->prepare("SELECT * FROM auth_tokens WHERE selector = ? AND expires >= NOW()");
        $stmt->bind_param("s", $selector);
        $stmt->execute();
        $result = $stmt->get_result();
        $token_data = $result->fetch_assoc();
        $stmt->close();

        if ($token_data) {
            // Token found, now verify the validator
            if (hash_equals($token_data['hashed_validator'], hash('sha256', $validator))) {
                // Token is valid, log the user in
                $user_stmt = $conn->prepare("SELECT id, username, role_id FROM users WHERE id = ?");
                $user_stmt->bind_param("i", $token_data['user_id']);
                $user_stmt->execute();
                $user_result = $user_stmt->get_result();
                $user = $user_result->fetch_assoc();
                $user_stmt->close();

                if ($user) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role_id'] = $user['role_id'];
                    $_SESSION['username'] = $user['username'];
                    header("Location: dashboard.php"); // Redirect to dashboard or index
                    exit;
                }
            }
        }
    }
    // If token is invalid, clear the cookie
    setcookie('remember_token', '', time() - 3600, "/");
}


// --- Standard Login Form Processing ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    $stmt = $conn->prepare("SELECT id, password, role_id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            // Password is correct, create session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['username'] = $username;

            // Handle "Remember Me"
            if ($remember) {
                // Generate secure token
                $selector = bin2hex(random_bytes(16));
                $validator = bin2hex(random_bytes(32));
                $hashed_validator = hash('sha256', $validator);
                $expires = new DateTime('+30 days');

                // Store token in the database
                $token_stmt = $conn->prepare("INSERT INTO auth_tokens (user_id, selector, hashed_validator, expires) VALUES (?, ?, ?, ?)");
                $token_stmt->bind_param("isss", $user['id'], $selector, $hashed_validator, $expires->format('Y-m-d H:i:s'));
                $token_stmt->execute();
                $token_stmt->close();

                // Set cookie
                setcookie('remember_token', $selector . ':' . $validator, $expires->getTimestamp(), '/', '', false, true); // secure and httponly flags
            } else {
                // Clear any existing remember me cookie
                 setcookie('remember_token', '', time() - 3600, "/");
            }

            header("Location: dashboard.php"); // Redirect to dashboard or index
            exit;
        } else {
            $error_message = "Invalid password.";
        }
    } else {
        $error_message = "No user found with that username.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Inventory App</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">Login</h2>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= htmlspecialchars($error_message) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="login.php">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" name="username" id="username" class="form-control" placeholder="Username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" id="password" placeholder="Password" required>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" name="remember" class="form-check-input" id="remember">
                            <label class="form-check-label" for="remember">Remember Me</label>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Login</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6 d-none d-md-block">
            <img src="osplogo.png" alt="Login Image" class="img-fluid">
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>