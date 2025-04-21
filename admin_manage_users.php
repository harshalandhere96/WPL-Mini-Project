<?php
include("includes/session_config.php"); 
include("includes/config.php");

// Check if the user is an admin
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit();
}

$success_message = '';
$error_message = '';

// Handle user actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $user_id = $_GET['id'];
    
    // Don't allow admins to modify their own account via this page
    if ($user_id == $_SESSION['user_id']) {
        $error_message = "You cannot modify your own account from this page.";
    } else {
        switch ($action) {
            case 'delete':
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                if ($stmt->execute()) {
                    // Also delete all portfolio entries for this user
                    $stmt = $conn->prepare("DELETE FROM portfolio WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $success_message = "User and all their portfolio entries have been deleted.";
                } else {
                    $error_message = "Error deleting user: " . $conn->error;
                }
                break;
                
            case 'toggle_admin':
                // First check current admin status
                $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                
                // Toggle the admin status
                $new_status = $user['is_admin'] ? 0 : 1;
                $stmt = $conn->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
                $stmt->bind_param("ii", $new_status, $user_id);
                
                if ($stmt->execute()) {
                    $status_text = $new_status ? "now an admin" : "no longer an admin";
                    $success_message = "User is $status_text.";
                } else {
                    $error_message = "Error updating user: " . $conn->error;
                }
                break;
                
            case 'reset_password':
                // Generate a random password
                $new_password = bin2hex(random_bytes(8)); // 16 characters
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    $success_message = "User password has been reset. New password: $new_password";
                } else {
                    $error_message = "Error resetting password: " . $conn->error;
                }
                break;
        }
    }
}


$users = $conn->query("
    SELECT u.id, u.username, u.email, u.is_admin, 
           DATE_FORMAT(u.created_at, '%M %d, %Y') as joined_date,
           COUNT(p.id) as portfolio_count,
           SUM(p.quantity * p.buy_price) as total_investment
    FROM users u
    LEFT JOIN portfolio p ON u.id = p.user_id
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link rel="stylesheet" href="admin_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="admin-wrapper">
        <div class="sidebar">
            <div class="brand">
                <h2>Crypto Tracker</h2>
                <p>Admin Panel</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="admin_add_crypto.php"><i class="fas fa-plus-circle"></i> Add Cryptocurrencies</a></li>
                <li class="active"><a href="admin_manage_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                <li><a href="admin_market_overview.php"><i class="fas fa-chart-line"></i> Market Overview</a></li>
                <li><a href="index.php"><i class="fas fa-home"></i> Visit Website</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <div class="main-content">
            <header>
                <h1>Manage Users</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
            </header>

            <div class="content-container">
                <?php if ($success_message): ?>
                    <div class="alert success">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="user-management">
                    <h2>User List</h2>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Joined Date</th>
                                    <th>Portfolio Entries</th>
                                    <th>Total Investment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($users && $users->num_rows > 0): ?>
                                    <?php while ($user = $users->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <span class="role-badge <?php echo $user['is_admin'] ? 'admin' : 'user'; ?>">
                                                    <?php echo $user['is_admin'] ? 'Admin' : 'User'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $user['joined_date']; ?></td>
                                            <td><?php echo $user['portfolio_count']; ?></td>
                                            <td>$<?php echo number_format($user['total_investment'] ?? 0, 2); ?></td>
                                            <td class="actions">
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <a href="admin_view_user.php?id=<?php echo $user['id']; ?>" class="action-link" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="?action=toggle_admin&id=<?php echo $user['id']; ?>" class="action-link" title="<?php echo $user['is_admin'] ? 'Remove Admin' : 'Make Admin'; ?>">
                                                        <i class="fas <?php echo $user['is_admin'] ? 'fa-user-minus' : 'fa-user-plus'; ?>"></i>
                                                    </a>
                                                    <a href="?action=reset_password&id=<?php echo $user['id']; ?>" class="action-link" title="Reset Password" onclick="return confirm('Are you sure you want to reset this user\'s password?');">
                                                        <i class="fas fa-key"></i>
                                                    </a>
                                                    <a href="?action=delete&id=<?php echo $user['id']; ?>" class="action-link delete" title="Delete User" onclick="return confirm('Are you sure you want to delete this user? This will also delete all their portfolio data.');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="current-user">(Current User)</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7">No users found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
