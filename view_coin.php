<?php
// Updated view_coin.php - Uses CryptoCompare API instead of CoinGecko
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

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

$user_id = $_SESSION['user_id'];

if (!isset($_GET['symbol'])) {
    header("Location: dashboard.php");
    exit();
}

$symbol_param = strtoupper($_GET['symbol']);

// Try to find portfolio entries matching this symbol
$stmt = $conn->prepare("SELECT id, coin_name, symbol, quantity, buy_price, purchase_date FROM portfolio WHERE user_id = ? AND UPPER(symbol) = ?");
$stmt->bind_param("is", $user_id, $symbol_param);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: dashboard.php");
    exit();
}

$portfolio_entries = $result->fetch_all(MYSQLI_ASSOC);
$coin_name = $portfolio_entries[0]['coin_name'];
$symbol = $portfolio_entries[0]['symbol'];
$total_quantity = 0;
$total_investment = 0;

foreach ($portfolio_entries as $entry) {
    $total_quantity += $entry['quantity'];
    $total_investment += $entry['quantity'] * $entry['buy_price'];
}

$average_buy_price = $total_investment / $total_quantity;

// Get current price data from CryptoCompare
$price_data = [];
$api_url = "https://min-api.cryptocompare.com/data/pricemultifull?fsyms={$symbol}&tsyms=USD";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
curl_close($ch);

if ($response) {
    $data = json_decode($response, true);
    if (isset($data['RAW']) && isset($data['RAW'][$symbol]) && isset($data['RAW'][$symbol]['USD'])) {
        $price_data = $data['RAW'][$symbol]['USD'];
        
        $current_price = $price_data['PRICE'];
        $market_cap = $price_data['MKTCAP'] ?? 'N/A';
        $volume = $price_data['VOLUME24HOUR'] ?? 'N/A';
        $change_24h = $price_data['CHANGEPCT24HOUR'] ?? 'N/A';
        $change_24h_dollar = $price_data['CHANGE24HOUR'] ?? 'N/A';
        
        $current_value = $total_quantity * $current_price;
        $profit_loss = $current_value - $total_investment;
        $profit_loss_percentage = ($profit_loss / $total_investment) * 100;
    } else {
        $error_message = "Could not fetch price data for this cryptocurrency.";
    }
} else {
    $error_message = "Could not connect to CryptoCompare API.";
}

// Get historical data for the chart
$historical_data = [];
$hist_url = "https://min-api.cryptocompare.com/data/v2/histoday?fsym={$symbol}&tsym=USD&limit=30";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $hist_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$hist_response = curl_exec($ch);
curl_close($ch);

if ($hist_response) {
    $hist_data = json_decode($hist_response, true);
    if (isset($hist_data['Data']) && isset($hist_data['Data']['Data'])) {
        $historical_data = $hist_data['Data']['Data'];
    }
}

// Get coin info (similar to CoinGecko's /coins/{id} endpoint)
$coin_info = [];
$info_url = "https://min-api.cryptocompare.com/data/coin/generalinfo?fsyms={$symbol}&tsym=USD";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $info_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$info_response = curl_exec($ch);
curl_close($ch);

if ($info_response) {
    $info_data = json_decode($info_response, true);
    if (isset($info_data['Data']) && !empty($info_data['Data'])) {
        $coin_info = $info_data['Data'][0]['CoinInfo'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($coin_name); ?> Details - Crypto Tracker</title>
    <link rel="stylesheet" href="dashboardstyles.css">
    <link rel="stylesheet" href="coin_styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <header>
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a> &gt; 
                <span><?php echo htmlspecialchars($coin_name); ?> Details</span>
            </div>
            <div class="actions">
                <a href="portfolio.php" class="btn"><i class="fas fa-plus-circle"></i> Add to Portfolio</a>
                <a href="dashboard.php" class="btn"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="logout.php" class="btn logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </header>
        
        <?php if (isset($error_message)): ?>
            <div class="alert error">
                <?php echo $error_message; ?>
                <a href="dashboard.php" class="btn">Return to Dashboard</a>
            </div>
        <?php else: ?>
            <div class="coin-overview">
                <div class="coin-header">
                    <?php if (isset($price_data['IMAGEURL'])): ?>
                        <img src="https://www.cryptocompare.com<?php echo $price_data['IMAGEURL']; ?>" alt="<?php echo htmlspecialchars($coin_name); ?>" class="coin-icon">
                    <?php endif; ?>
                    <h1><?php echo htmlspecialchars($coin_name); ?> (<?php echo strtoupper($symbol); ?>)</h1>
                </div>
                
                <div class="coin-price">
                    <span class="current-price">$<?php echo number_format($current_price, 2); ?></span>
                    <span class="price-change <?php echo $change_24h >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo $change_24h >= 0 ? '+' : ''; ?><?php echo number_format($change_24h, 2); ?>% (24h)
                    </span>
                </div>
                
                <?php if (isset($coin_info['Description'])): ?>
                <div class="coin-description">
                    <?php 
                        $description = $coin_info['Description'];
                        // Limit to 300 characters
                        if (strlen($description) > 300) {
                            $description = substr($description, 0, 300) . '...';
                        }
                        echo $description;
                    ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="data-grid">
                <div class="market-data">
                    <h2>Market Data</h2>
                    <div class="data-items">
                        <div class="data-item">
                            <span class="label">Market Cap</span>
                            <span class="value">$<?php echo is_numeric($market_cap) ? number_format($market_cap, 0) : $market_cap; ?></span>
                        </div>
                        <div class="data-item">
                            <span class="label">24h Volume</span>
                            <span class="value">$<?php echo is_numeric($volume) ? number_format($volume, 0) : $volume; ?></span>
                        </div>
                        <div class="data-item">
                            <span class="label">24h Change</span>
                            <span class="value <?php echo is_numeric($change_24h) && $change_24h >= 0 ? 'positive' : 'negative'; ?>">
                                <?php echo is_numeric($change_24h) ? ($change_24h >= 0 ? '+' : '') . number_format($change_24h, 2) . '%' : $change_24h; ?>
                            </span>
                        </div>
                        <div class="data-item">
                            <span class="label">24h Change ($)</span>
                            <span class="value <?php echo is_numeric($change_24h_dollar) && $change_24h_dollar >= 0 ? 'positive' : 'negative'; ?>">
                                <?php echo is_numeric($change_24h_dollar) ? ($change_24h_dollar >= 0 ? '+$' : '-$') . number_format(abs($change_24h_dollar), 2) : $change_24h_dollar; ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="portfolio-data">
                    <h2>Your Holdings</h2>
                    <div class="data-items">
                        <div class="data-item">
                            <span class="label">Total Quantity</span>
                            <span class="value"><?php echo number_format($total_quantity, 8); ?></span>
                        </div>
                        <div class="data-item">
                            <span class="label">Average Buy Price</span>
                            <span class="value">$<?php echo number_format($average_buy_price, 2); ?></span>
                        </div>
                        <div class="data-item">
                            <span class="label">Total Investment</span>
                            <span class="value">$<?php echo number_format($total_investment, 2); ?></span>
                        </div>
                        <div class="data-item">
                            <span class="label">Current Value</span>
                            <span class="value">$<?php echo number_format($current_value, 2); ?></span>
                        </div>
                        <div class="data-item">
                            <span class="label">Profit/Loss</span>
                            <span class="value <?php echo $profit_loss >= 0 ? 'positive' : 'negative'; ?>">
                                $<?php echo number_format($profit_loss, 2); ?> 
                                (<?php echo $profit_loss >= 0 ? '+' : ''; ?><?php echo number_format($profit_loss_percentage, 2); ?>%)
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="chart-section">
                <h2>Price History</h2>
                <div class="chart-container">
                    <canvas id="priceChart"></canvas>
                </div>
                <div class="chart-options">
                    <button class="time-option active" data-days="7" onclick="updateChart(7)">7 Days</button>
                    <button class="time-option" data-days="30" onclick="updateChart(30)">30 Days</button>
                    <button class="time-option" data-days="90" onclick="updateChart(90)">90 Days</button>
                </div>
            </div>
            
            <div class="holdings-section">
                <h2>Your Transactions</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Quantity</th>
                            <th>Buy Price</th>
                            <th>Purchase Date</th>
                            <th>Total Investment</th>
                            <th>Current Value</th>
                            <th>Profit/Loss</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($portfolio_entries as $entry): 
                            $entry_investment = $entry['quantity'] * $entry['buy_price'];
                            $entry_current_value = $entry['quantity'] * $current_price;
                            $entry_profit_loss = $entry_current_value - $entry_investment;
                            $entry_profit_loss_percentage = ($entry_profit_loss / $entry_investment) * 100;
                            $profit_class = $entry_profit_loss >= 0 ? "positive" : "negative";
                        ?>
                        <tr>
                            <td><?php echo number_format($entry['quantity'], 8); ?></td>
                            <td>$<?php echo number_format($entry['buy_price'], 2); ?></td>
                            <td><?php echo !empty($entry['purchase_date']) ? date("M d, Y", strtotime($entry['purchase_date'])) : 'N/A'; ?></td>
                            <td>$<?php echo number_format($entry_investment, 2); ?></td>
                            <td>$<?php echo number_format($entry_current_value, 2); ?></td>
                            <td class="<?php echo $profit_class; ?>">
                                $<?php echo number_format($entry_profit_loss, 2); ?> 
                                (<?php echo $entry_profit_loss >= 0 ? '+' : ''; ?><?php echo number_format($entry_profit_loss_percentage, 2); ?>%)
                            </td>
                            <td>
                                <a href="edit_coin.php?id=<?php echo $entry['id']; ?>" class="action-link" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="delete_coin.php?id=<?php echo $entry['id']; ?>&return=view" class="action-link delete" title="Delete" onclick="return confirm('Are you sure you want to delete this entry?');"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($historical_data)): ?>
    <script>
        // Prepare data for Chart.js
        const histData = <?php echo json_encode($historical_data); ?>;
        const labels = histData.map(item => {
            const date = new Date(item.time * 1000);
            return date.toLocaleDateString();
        });
        const prices = histData.map(item => item.close);

        // Create the chart
        const ctx = document.getElementById('priceChart').getContext('2d');
        const priceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Price (USD)',
                    data: prices,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    borderWidth: 2,
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: false,
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
                            color: 'rgba(255, 255, 255, 0.7)',
                            maxRotation: 45,
                            minRotation: 45
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

        // Function to update chart with different time periods
        function updateChart(days) {
            // Update active button
            document.querySelectorAll('.time-option').forEach(btn => {
                btn.classList.remove('active');
                if (btn.getAttribute('data-days') == days) {
                    btn.classList.add('active');
                }
            });

            // Fetch from server-side to avoid rate limiting
            fetch(`cryptocompare_history.php?symbol=<?php echo $symbol; ?>&days=${days}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.timestamps && data.prices) {
                        const newLabels = data.timestamps.map(timestamp => {
                            const date = new Date(timestamp);
                            return date.toLocaleDateString();
                        });
                        
                        // Update chart data
                        priceChart.data.labels = newLabels;
                        priceChart.data.datasets[0].data = data.prices;
                        priceChart.update();
                    } else {
                        console.error('Error fetching historical data');
                    }
                })
                .catch(error => console.error('Error fetching data:', error));
        }
    </script>
    <?php endif; ?>
</body>
</html>
