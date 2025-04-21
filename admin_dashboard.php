<?php
include("includes/session_config.php"); 
include("includes/config.php");

// Check if the user is an admin
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit();
}

// Stats retrieval
$userCount = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
$cryptoCount = $conn->query("SELECT COUNT(*) as total FROM cryptocurrencies")->fetch_assoc()['total'];
$portfolioCount = $conn->query("SELECT COUNT(*) as total FROM portfolio")->fetch_assoc()['total'];


$popularCryptos = $conn->query("
    SELECT coin_name, COUNT(*) as count 
    FROM portfolio 
    GROUP BY coin_name 
    ORDER BY count DESC 
    LIMIT 5
");

// User activity - most recent registrations
$recentUsers = $conn->query("
    SELECT username, email, DATE_FORMAT(created_at, '%M %d, %Y') as joined_date 
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 5
");

// Calculate total portfolio value across all users
$totalValue = $conn->query("
    SELECT SUM(quantity * buy_price) as total_investment
    FROM portfolio
")->fetch_assoc()['total_investment'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="admin_styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="admin-wrapper">
        <div class="sidebar">
            <div class="brand">
                <h2>TripleH Crypto</h2>
                <p>Admin Panel</p>
            </div>
            <ul class="sidebar-menu">
                <li class="active"><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="admin_add_crypto.php"><i class="fas fa-plus-circle"></i> Add Cryptocurrencies</a></li>
                <li><a href="admin_manage_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                <li><a href="admin_market_overview.php"><i class="fas fa-chart-line"></i> Market Overview</a></li>
                <li><a href="index.php"><i class="fas fa-home"></i> Visit Website</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <div class="main-content">
            <header>
                <h1>Admin Dashboard</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
            </header>

            <div class="stats-cards">
                <div class="card">
                    <div class="card-icon users-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="card-info">
                        <h3>Total Users</h3>
                        <p><?php echo $userCount; ?></p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-icon crypto-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="card-info">
                        <h3>Cryptocurrencies</h3>
                        <p><?php echo $cryptoCount; ?></p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-icon portfolio-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="card-info">
                        <h3>Portfolio Entries</h3>
                        <p><?php echo $portfolioCount; ?></p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-icon value-icon" style="background: rgba(233, 30, 99, 0.2); color: #e91e63;">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="card-info">
                        <h3>Total Investments</h3>
                        <p>$<?php echo number_format($totalValue, 2); ?></p>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="chart-container">
                    <h3>Most Popular Cryptocurrencies</h3>
                    <canvas id="popularCryptoChart"></canvas>
                </div>
                <div class="table-card">
                    <h3>Recent User Registrations</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Joined Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentUsers && $recentUsers->num_rows > 0): ?>
                                <?php while ($user = $recentUsers->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo $user['joined_date']; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3">No recent registrations</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="quick-actions">
                <h3>Quick Actions</h3>
                <div class="action-buttons">
                    <a href="admin_add_crypto.php" class="btn"><i class="fas fa-plus-circle"></i> Add New Cryptocurrency</a>
                    <a href="admin_manage_users.php" class="btn"><i class="fas fa-users-cog"></i> Manage Users</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Chart for popular cryptocurrencies
        <?php
        $labels = [];
        $data = [];
        if ($popularCryptos && $popularCryptos->num_rows > 0) {
            while ($crypto = $popularCryptos->fetch_assoc()) {
                $labels[] = $crypto['coin_name'];
                $data[] = $crypto['count'];
            }
        }
        ?>
        
        const ctx = document.getElementById('popularCryptoChart').getContext('2d');
        const popularCryptoChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Number of Users',
                    data: <?php echo json_encode($data); ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 206, 86, 0.2)',
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(153, 102, 255, 0.2)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        labels: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
