<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Database connection
$db_host = "localhost";
$db_user = "root"; // Change if needed
$db_pass = "";     // Change if needed
$db_name = "crypto_tracker";

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get portfolio entries from database
$stmt = $conn->prepare("SELECT id, coin_name, symbol, quantity, buy_price FROM portfolio WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Store raw portfolio entries
$portfolio_entries = [];
while ($row = $result->fetch_assoc()) {
    $portfolio_entries[] = $row;
}
$stmt->close();

// Group entries by symbol
$grouped_portfolio = [];
$symbols = [];

foreach ($portfolio_entries as $entry) {
    $symbol = strtoupper($entry['symbol']); // CryptoCompare uses uppercase symbols
    
    if (!isset($grouped_portfolio[$symbol])) {
        // Create new entry
        $symbols[] = $symbol;
        
        $grouped_portfolio[$symbol] = [
            'coin_name' => $entry['coin_name'],
            'symbol' => $symbol,
            'quantity' => $entry['quantity'],
            'buy_price' => $entry['buy_price'],
            'total_investment' => $entry['quantity'] * $entry['buy_price'],
            'entries' => [$entry['id']]
        ];
    } else {
        // Update existing entry
        $grouped_portfolio[$symbol]['quantity'] += $entry['quantity'];
        $grouped_portfolio[$symbol]['total_investment'] += $entry['quantity'] * $entry['buy_price'];
        $grouped_portfolio[$symbol]['entries'][] = $entry['id'];
    }
}

// Calculate average buy price
foreach ($grouped_portfolio as $symbol => &$entry) {
    if ($entry['quantity'] > 0) {
        $entry['buy_price'] = $entry['total_investment'] / $entry['quantity'];
    }
}

// Get current prices from CryptoCompare
$prices = [];
$total_investment = 0;
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

// Prepare data for pie chart
$pie_chart_labels = [];
$pie_chart_values = [];
$pie_chart_colors = [
    'rgba(255, 99, 132, 0.7)',
    'rgba(54, 162, 235, 0.7)',
    'rgba(255, 206, 86, 0.7)',
    'rgba(75, 192, 192, 0.7)',
    'rgba(153, 102, 255, 0.7)',
    'rgba(255, 159, 64, 0.7)',
    'rgba(199, 199, 199, 0.7)',
    'rgba(83, 102, 255, 0.7)',
    'rgba(40, 167, 69, 0.7)',
    'rgba(220, 53, 69, 0.7)'
];
$pie_chart_border_colors = [
    'rgba(255, 99, 132, 1)',
    'rgba(54, 162, 235, 1)',
    'rgba(255, 206, 86, 1)',
    'rgba(75, 192, 192, 1)',
    'rgba(153, 102, 255, 1)',
    'rgba(255, 159, 64, 1)',
    'rgba(199, 199, 199, 1)',
    'rgba(83, 102, 255, 1)',
    'rgba(40, 167, 69, 1)',
    'rgba(220, 53, 69, 1)'
];

// Process portfolio with market data
foreach ($grouped_portfolio as $symbol => &$entry) {
    $total_investment += $entry['total_investment'];
    
    if (isset($prices[$symbol]) && isset($prices[$symbol]['USD'])) {
        $coin_data = $prices[$symbol]['USD'];
        
        $current_price = $coin_data['PRICE'];
        $entry['current_price'] = $current_price;
        $entry['current_value'] = $entry['quantity'] * $current_price;
        $entry['profit_loss'] = $entry['current_value'] - $entry['total_investment'];
        $entry['profit_loss_percentage'] = ($entry['profit_loss'] / $entry['total_investment']) * 100;
        
        // Price change data from CryptoCompare
        $entry['price_change_24h'] = $coin_data['CHANGEPCT24HOUR'] ?? null;
        $entry['price_change_dollar'] = $coin_data['CHANGE24HOUR'] ?? null;
        $entry['market_cap'] = $coin_data['MKTCAP'] ?? null;
        $entry['volume'] = $coin_data['VOLUME24HOUR'] ?? null;
        $entry['image_url'] = "https://www.cryptocompare.com" . ($coin_data['IMAGEURL'] ?? '');
        
        $total_current_value += $entry['current_value'];
        
        // Add to pie chart data (use current value)
        $pie_chart_labels[] = $entry['coin_name'];
        $pie_chart_values[] = $entry['current_value'];
    } else {
        $entry['current_price'] = 'N/A';
        $entry['current_value'] = 0;
        $entry['profit_loss'] = 0;
        $entry['profit_loss_percentage'] = 0;
        $entry['price_change_24h'] = null;
        $entry['price_change_dollar'] = null;
        $entry['market_cap'] = null;
        $entry['volume'] = null;
        $entry['image_url'] = null;
    }
}

$total_profit_loss = $total_current_value - $total_investment;
$total_profit_loss_percentage = ($total_investment > 0) ? ($total_profit_loss / $total_investment) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crypto Portfolio Dashboard</title>
    <link rel="stylesheet" href="dashboardstyles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .coin-image {
            width: 24px;
            height: 24px;
            vertical-align: middle;
            margin-right: 8px;
        }
        .symbol {
            opacity: 0.7;
            margin-left: 4px;
        }
        .data-source {
            text-align: center;
            margin-top: 20px;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.5);
        }
        .chart-section {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-top: 30px;
        }
        .chart-container {
            height: 400px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <header>
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
            <div class="actions">
                <a href="portfolio.php" class="btn"><i class="fas fa-plus-circle"></i> Add to Portfolio</a>
                <a href="logout.php" class="btn logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </header>

        <div class="overview-section">
            <div class="overview-card">
                <i class="fas fa-money-bill-wave card-icon"></i>
                <div>
                    <h3>Total Investment</h3>
                    <p class="value">$<?php echo number_format($total_investment, 2); ?></p>
                </div>
            </div>
            <div class="overview-card">
                <i class="fas fa-wallet card-icon"></i>
                <div>
                    <h3>Current Value</h3>
                    <p class="value">$<?php echo number_format($total_current_value, 2); ?></p>
                </div>
            </div>
            <div class="overview-card <?php echo $total_profit_loss >= 0 ? 'positive' : 'negative'; ?>">
                <i class="fas fa-chart-line card-icon"></i>
                <div>
                    <h3>Total Profit/Loss</h3>
                    <p class="value">$<?php echo number_format($total_profit_loss, 2); ?> 
                        (<?php echo number_format($total_profit_loss_percentage, 2); ?>%)
                    </p>
                </div>
            </div>
        </div>

        <?php if (empty($grouped_portfolio)): ?>
            <div class="empty-portfolio">
                <h2>Your portfolio is empty!</h2>
                <p>Start tracking your crypto investments by adding your first cryptocurrency.</p>
                <a href="portfolio.php" class="btn large"><i class="fas fa-plus-circle"></i> Add to Portfolio</a>
            </div>
        <?php else: ?>
            <div class="main-content">
                <div class="portfolio-section">
                    <h2>Your Portfolio (<?php echo count($grouped_portfolio); ?> cryptocurrencies)</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Coin</th>
                                <th>Quantity</th>
                                <th>Avg Buy Price</th>
                                <th>Current Price</th>
                                <th>24h Change</th>
                                <th>Current Value</th>
                                <th>Profit/Loss</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grouped_portfolio as $symbol => $coin): 
                                $change_class = isset($coin['price_change_24h']) && $coin['price_change_24h'] >= 0 ? 'positive' : 'negative';
                                $profit_class = $coin['profit_loss'] >= 0 ? 'positive' : 'negative';
                            ?>
                                <tr>
                                    <td>
                                        <a href="#" class="coin-link">
                                            <?php if (isset($coin['image_url']) && !empty($coin['image_url'])): ?>
                                                <img src="<?php echo $coin['image_url']; ?>" alt="<?php echo htmlspecialchars($coin['coin_name']); ?>" class="coin-image">
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($coin['coin_name']); ?> 
                                            <span class="symbol">(<?php echo htmlspecialchars($coin['symbol']); ?>)</span>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($coin['quantity']); ?></td>
                                    <td>$<?php echo number_format($coin['buy_price'], 2); ?></td>
                                    <td>$<?php echo is_numeric($coin['current_price']) ? number_format($coin['current_price'], 2) : $coin['current_price']; ?></td>
                                    <td class="<?php echo $change_class; ?>">
                                        <?php if (isset($coin['price_change_24h']) && is_numeric($coin['price_change_24h'])): ?>
                                            <?php echo $coin['price_change_24h'] >= 0 ? '+' : ''; ?><?php echo number_format($coin['price_change_24h'], 2); ?>%
                                        <?php else: ?>
                                            <span title="Data unavailable">--</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>$<?php echo number_format($coin['current_value'], 2); ?></td>
                                    <td class="<?php echo $profit_class; ?>">
                                        $<?php echo number_format($coin['profit_loss'], 2); ?> 
                                        (<?php echo $coin['profit_loss'] >= 0 ? '+' : ''; ?><?php echo number_format($coin['profit_loss_percentage'], 2); ?>%)
                                    </td>
                                    <td>
                                        <a href="view_coin.php?symbol=<?php echo urlencode($coin['symbol']); ?>" class="action-link" title="View Details"><i class="fas fa-eye"></i></a>
                                        <?php if (count($coin['entries']) === 1): ?>
                                            <a href="edit_coin.php?id=<?php echo $coin['entries'][0]; ?>" class="action-link" title="Edit"><i class="fas fa-edit"></i></a>
                                            <a href="delete_coin.php?id=<?php echo $coin['entries'][0]; ?>" class="action-link delete" title="Delete" onclick="return confirm('Are you sure you want to delete this coin?');"><i class="fas fa-trash"></i></a>
                                        <?php else: ?>
                                            <a href="view_coin.php?symbol=<?php echo urlencode($coin['symbol']); ?>" class="action-link" title="Manage Multiple Entries"><i class="fas fa-edit"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($pie_chart_labels) && !empty($pie_chart_values)): ?>
                <div class="chart-section">
                    <h2>Portfolio Distribution</h2>
                    <div class="chart-container">
                        <canvas id="portfolioDistributionChart"></canvas>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="data-source">
                    Data provided by CryptoCompare
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($pie_chart_labels) && !empty($pie_chart_values)): ?>
    <script>
        // Create pie chart for portfolio distribution
        const ctx = document.getElementById('portfolioDistributionChart').getContext('2d');
        const portfolioDistributionChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($pie_chart_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($pie_chart_values); ?>,
                    backgroundColor: <?php echo json_encode($pie_chart_colors); ?>,
                    borderColor: <?php echo json_encode($pie_chart_border_colors); ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                let value = context.raw;
                                let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                let percentage = ((value / total) * 100).toFixed(2);
                                return `${label}: $${value.toFixed(2)} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
