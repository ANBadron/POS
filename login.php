<?php
session_start();
include 'db.php';

// Initialize variables
$error    = '';
$username = '';

// Rate limiting (5 attempts per 15 minutes)
$max_attempts = 5;
$lockout_time = 900; // 15 minutes in seconds

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Optional lockout check (uncomment to enable)
/*    if (
        isset($_SESSION['login_attempts']) &&
        $_SESSION['login_attempts'] >= $max_attempts &&
        isset($_SESSION['last_login_attempt']) &&
        (time() - $_SESSION['last_login_attempt']) < $lockout_time
    ) {
        $error = "Too many failed attempts. Please try again later.";
    }
*/

    if (empty($error)) {
        // CSRF check
        if (
            empty($_POST['csrf_token']) ||
            empty($_SESSION['csrf_token']) ||
            $_POST['csrf_token'] !== $_SESSION['csrf_token']
        ) {
            $error = "Invalid request. Please try again.";
        } else {
            $username = trim($_POST['username']);
            $password = $_POST['password'] ?? '';

            if ($username === '' || $password === '') {
                $error = "Username and password are required!";
            } else {
                try {
                    $stmt = $conn->prepare("SELECT UserID, Username, Password, Role FROM Users WHERE Username = ?");
                    $stmt->execute([$username]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($user && password_verify($password, $user['Password'])) {
                        // Successful login
                        session_regenerate_id(true);
                        unset($_SESSION['login_attempts'], $_SESSION['last_login_attempt']);

                        // Update last login timestamp
                        $u = $conn->prepare("UPDATE Users SET LastLogin = NOW() WHERE UserID = ?");
                        $u->execute([$user['UserID']]);

                        $_SESSION['user_id']  = $user['UserID'];
                        $_SESSION['role']     = $user['Role'];
                        $_SESSION['username'] = $user['Username'];

                        header('Location: dashboard.php');
                        exit;
                    } else {
                        // Failed login
                        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                        $_SESSION['last_login_attempt'] = time();
                        $error = "Invalid username or password!";
                    }
                } catch (PDOException $e) {
                    error_log("Login error: " . $e->getMessage());
                    $error = "Database error. Please try again later.";
                }
            }
        }
    }
}

// Generate fresh CSRF token for the form
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .login-card h1 {
            text-align: center;
            margin-bottom: 1.5rem;
            color: #2575fc;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <h1>Welcome Back!</h1>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="form-group mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-control"
                        value="<?= htmlspecialchars($username) ?>"
                        required autofocus
                    >
                </div>
                <div class="form-group mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        required
                    >
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2">Login</button>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
