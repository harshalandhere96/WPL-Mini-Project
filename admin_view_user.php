<?php
session_start();
include("includes/config.php");

// Check if the user is an admin
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: admin_manage_users.php");
    exit();
}

$user_id = $_GET['id'];

// Get user details
$stmt = $conn->prepare("
    SELECT id, username, email, is_admin, DATE_FORMAT(created_at, '%M %d, %Y %H:%i') as joined_date, 
           last_login
    FROM users 
    WHERE id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: admin_manage_users.php");
    exit();
}

$user = $result->fetch_assoc();

// Get user's portfolio data
$portfolio = $conn->query("
    SELECT coin_name, symbol, SUM(quantity) as total_quantity, 
           AVG(buy_price) as avg_buy_price,
           SUM(quantity * buy_price) as total_investment
    FROM portfolio 
    WHERE user_id = $user_id
    GROUP BY coin_name, symbol
    ORDER BY total_investment DESC
");

// Get portfolio value
$portfolio_value = $conn->query("
    SELECT SUM(quantity * buy_price) as total_investment
    FROM portfolio
    WHERE user_id = $user_id
")->fetch_assoc()['total_investment'] ?? 0;

// Get coin symbols for price lookup
$symbols = [];
if ($portfolio && $portfolio->num_rows > 0) {
    $portfolio_data = $portfolio->data_seek(0);
    while ($row = $portfolio->fetch_assoc()) {
        $symbols[] = strtoupper($row['symbol']);
    }
    $portfolio->data_seek(0); // Reset result pointer
}

// Get current price data for all coins using CryptoCompare API
$prices = [];
$total_current_value = 0;

if (!empty($symbols)) {
    // Convert symbols array to comma-separated string
    $symbols_list = implode(",", $symbols);
    
    // CryptoCompare API endpoint for multiple prices
    $api_url = "https://min-api.cryptocompare.com/data/pricemultifull?fsyms={$symbols_list}&tsyms=USD";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['RAW'])) {
            $prices = $data['RAW'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User - Admin</title>
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
                <h1>User Profile: <?php echo htmlspecialchars($user['username']); ?></h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
            </header>

            <div class="breadcrumb">
                <a href="admin_manage_users.php">Users</a> &gt; 
                <span><?php echo htmlspecialchars($user['username']); ?></span>
            </div>

            <div class="content-container">
                <div class="user-details-card">
                    <div class="user-header">
                        <div class="avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="user-meta">
                            <h2><?php echo htmlspecialchars($user['username']); ?></h2>
                            <p class="user-email"><?php echo htmlspecialchars($user['email']); ?></p>
                            <span class="role-badge <?php echo $user['is_admin'] ? 'admin' : 'user'; ?>">
                                <?php echo $user['is_admin'] ? 'Admin' : 'User'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="user-info-grid">
                        <div class="info-item">
                            <span class="label">User ID</span>
                            <span class="value">#<?php echo $user['id']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Joined</span>
                            <span class="value"><?php echo $user['joined_date']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Last Login</span>
                            <span class="value"><?php echo $user['last_login'] ? date("M d, Y H:i", strtotime($user['last_login'])) : 'Never'; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Portfolio Value</span>
                            <span class="value">$<?php echo number_format($portfolio_value, 2); ?></span>
                        </div>
                    </div>
                    
                    <div class="user-actions">
                        <a href="?action=toggle_admin&id=<?php echo $user['id']; ?>" class="btn">
                            <i class="fas <?php echo $user['is_admin'] ? 'fa-user-minus' : 'fa-user-plus'; ?>"></i>
                            <?php echo $user['is_admin'] ? 'Remove Admin' : 'Make Admin'; ?>
                        </a>
                        <a href="?action=reset_password&id=<?php echo $user['id']; ?>" class="btn" onclick="return confirm('Are you sure you want to reset this user\'s password?');">
                            <i class="fas fa-key"></i> Reset Password
                        </a>
                        <a href="?action=delete&id=<?php echo $user['id']; ?>" class="btn delete" onclick="return confirm('Are you sure you want to delete this user? This will also delete all their portfolio data.');">
                            <i class="fas fa-trash"></i> Delete User
                        </a>
                    </div>
                </div>
                
                <div class="portfolio-section">
                    <h2>User's Portfolio</h2>
                    
                    <?php if ($portfolio && $portfolio->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Cryptocurrency</th>
                                    <th>Total Quantity</th>
                                    <th>Avg. Buy Price</th>
                                    <th>Current Price</th>
                                    <th>Investment</th>
                                    <th>Current Value</th>
                                    <th>Profit/Loss</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($coin = $portfolio->fetch_assoc()): 
                                    $symbol = strtoupper($coin['symbol']);
                                    $current_price = isset($prices[$symbol]['USD']['PRICE']) ? $prices[$symbol]['USD']['PRICE'] : 'N/A';
                                    $investment = $coin['total_investment'];
                                    
                                    // Calculate current value and profit/loss
                                    if (is_numeric($current_price)) {
                                        $current_value = $coin['total_quantity'] * $current_price;
                                        $profit_loss = $current_value - $investment;
                                        $profit_loss_percentage = ($profit_loss / $investment) * 100;
                                        $total_current_value += $current_value;
                                    } else {
                                        $current_value = 'N/A';
                                        $profit_loss = 'N/A';
                                        $profit_loss_percentage = 'N/A';
                                    }
                                    
                                    $profit_class = is_numeric($profit_loss) && $profit_loss >= 0 ? 'positive' : 'negative';
                                ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($coin['coin_name']); ?> 
                                        <span class="symbol">(<?php echo htmlspecialchars($coin['symbol']); ?>)</span>
                                    </td>
                                    <td><?php echo number_format($coin['total_quantity'], 8); ?></td>
                                    <td>$<?php echo number_format($coin['avg_buy_price'], 2); ?></td>
                                    <td>$<?php echo is_numeric($current_price) ? number_format($current_price, 2) : $current_price; ?></td>
                                    <td>$<?php echo number_format($investment, 2); ?></td>
                                    <td>$<?php echo is_numeric($current_value) ? number_format($current_value, 2) : $current_value; ?></td>
                                    <td class="<?php echo $profit_class; ?>">
                                        <?php if (is_numeric($profit_loss)): ?>
                                            $<?php echo number_format($profit_loss, 2); ?> 
                                            (<?php echo $profit_loss >= 0 ? '+' : ''; ?><?php echo number_format($profit_loss_percentage, 2); ?>%)
                                        <?php else: ?>
                                            <?php echo $profit_loss; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                
                                <?php if (is_numeric($total_current_value)): 
                                    $total_profit_loss = $total_current_value - $portfolio_value;
                                    $total_profit_loss_percentage = ($total_profit_loss / $portfolio_value) * 100;
                                    $total_profit_class = $total_profit_loss >= 0 ? 'positive' : 'negative';
                                ?>
                                <tr class="total-row">
                                    <td colspan="4"><strong>Total</strong></td>
                                    <td><strong>$<?php echo number_format($portfolio_value, 2); ?></strong></td>
                                    <td><strong>$<?php echo number_format($total_current_value, 2); ?></strong></td>
                                    <td class="<?php echo $total_profit_class; ?>">
                                        <strong>
                                            $<?php echo number_format($total_profit_loss, 2); ?> 
                                            (<?php echo $total_profit_loss >= 0 ? '+' : ''; ?><?php echo number_format($total_profit_loss_percentage, 2); ?>%)
                                        </strong>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="empty-message">This user has no portfolio entries.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>