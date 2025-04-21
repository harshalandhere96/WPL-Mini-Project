<?php
include("includes/config.php");
session_start();

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

$error = '';
$debug_info = ''; // For debugging purposes

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validate input
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        // Check user credentials
        $stmt = $conn->prepare("SELECT id, username, password, is_admin FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // For debugging
            $debug_info .= "User found: ID=" . $user['id'] . ", Username=" . $user['username'] . ", is_admin=" . $user['is_admin'] . "<br>";
            $debug_info .= "Stored password hash: " . $user['password'] . "<br>";
            
            // Verify password
            $password_verified = password_verify($password, $user['password']);
            $debug_info .= "Password verification result: " . ($password_verified ? 'Success' : 'Failed') . "<br>";
            
            if ($password_verified) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['admin'] = ($user['is_admin'] == 1);
                
                $debug_info .= "Session variables set: user_id=" . $_SESSION['user_id'] . ", username=" . $_SESSION['username'] . ", admin=" . ($_SESSION['admin'] ? 'true' : 'false') . "<br>";
                
                // Update last login time
                $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();
                
                // Redirect based on user type
                if ($_SESSION['admin']) {
                    $debug_info .= "Redirecting to admin dashboard<br>";
                    header("Location: admin_dashboard.php");
                } else {
                    $debug_info .= "Redirecting to user dashboard<br>";
                    header("Location: dashboard.php");
                }
                exit();
            } else {
                $error = "Invalid email or password.";
            }
        } else {
            $error = "Invalid email or password.";
            $debug_info .= "No user found with email: $email<br>";
        }
        
        $stmt->close();
    }
}

// For security purposes, in production you should remove this SQL insertion and debug info
// The code below ensures an admin account exists and is set up correctly
$check_admin = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_admin = 1");
$admin_count = $check_admin->fetch_assoc()['count'];

if ($admin_count == 0) {
    // If no admin exists, create one
    $admin_username = "admin";
    $admin_email = "admin@example.com";
    $admin_password = "Admin123!";
    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, is_admin) VALUES (?, ?, ?, 1)");
    $stmt->bind_param("sss", $admin_username, $admin_email, $hashed_password);
    
    if ($stmt->execute()) {
        $debug_info .= "Admin account created:<br>";
        $debug_info .= "Email: admin@example.com<br>";
        $debug_info .= "Password: Admin123!<br>";
    } else {
        $debug_info .= "Failed to create admin account: " . $conn->error . "<br>";
    }
    
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TripleH Crypto</title>
    <link rel="stylesheet" href="loginstyles.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<div class="circle-blur circle-1"></div>
<div class="circle-blur circle-2"></div>
    <div class="login-container">
        <div class="form-container">
            <div class="logo">
                <i class="fas fa-chart-line"></i>
                <h1>TripleH Crypto</h1>
            </div>
            
            <h2>Login to Your Account</h2>
            
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['registered']) && $_GET['registered'] == 'success'): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> Registration successful! Please login with your credentials.
                </div>
            <?php endif; ?>
            
            <form method="post" class="login-form">
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
                
                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            
            <div class="form-footer">
                <p>Don't have an account? <a href="register.php">Register</a></p>
                <p><a href="index.php">Back to Home</a></p>
            </div>
            
            <?php if (!empty($debug_info) && isset($_GET['debug'])): ?>
            <div class="debug-info" style="margin-top: 20px; padding: 15px; background: rgba(0,0,0,0.5); border-radius: 5px; font-size: 0.8rem; color: #ddd;">
                <h3>Debug Information</h3>
                <?php echo $debug_info; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
