<?php

session_start();
include("includes/config.php");

// Check if the user is an admin
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit();
}

// Get all cryptocurrencies from database
$cryptos = $conn->query("SELECT symbol FROM cryptocurrencies ORDER BY name ASC");
$symbols = [];

while ($crypto = $cryptos->fetch_assoc()) {
    $symbols[] = strtoupper($crypto['symbol']);
}

// Get market data from CryptoCompare
$market_data = [];
$error_message = '';

if (!empty($symbols)) {
    $symbols_list = implode(",", $symbols);
    
    // CryptoCompare API endpoint for multiple coins with full market data
    $apiUrl = "https://min-api.cryptocompare.com/data/pricemultifull?fsyms={$symbols_list}&tsyms=USD";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['RAW'])) {
            $market_data = $data['RAW'];
        } else {
            $error_message = "Failed to parse market data from CryptoCompare API.";
        }
    } else {
        $error_message = "Failed to fetch market data from CryptoCompare API.";
    }
}

// Get global market data
$global_data = [];
$globalUrl = "https://min-api.cryptocompare.com/data/top/totalvolfull?limit=10&tsym=USD";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $globalUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$global_response = curl_exec($ch);
curl_close($ch);

if ($global_response) {
    $global_data = json_decode($global_response, true);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Overview - Admin</title>
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
                <li><a href="admin_manage_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                <li class="active"><a href="admin_market_overview.php"><i class="fas fa-chart-line"></i> Market Overview</a></li>
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Visit Website</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <div class="main-content">
            <header>
                <h1>Market Overview</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
            </header>

            <?php if ($error_message): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($global_data) && isset($global_data['Data'])): 
                $top_coins = $global_data['Data'];
                
                // Calculate total market cap and volume
                $total_market_cap = 0;
                $total_volume = 0;
                foreach ($top_coins as $coin) {
                    if (isset($coin['RAW']['USD'])) {
                        $total_market_cap += $coin['RAW']['USD']['MKTCAP'] ?? 0;
                        $total_volume += $coin['RAW']['USD']['VOLUME24HOUR'] ?? 0;
                    }
                }
                
                // Calculate BTC dominance if BTC is in the list
                $btc_dominance = 0;
                foreach ($top_coins as $coin) {
                    if ($coin['CoinInfo']['Name'] === 'BTC' && isset($coin['RAW']['USD']['MKTCAP'])) {
                        $btc_dominance = ($coin['RAW']['USD']['MKTCAP'] / $total_market_cap) * 100;
                        break;
                    }
                }
            ?>
            <div class="global-stats">
                <div class="stats-cards">
                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-globe"></i>
                        </div>
                        <div class="card-info">
                            <h3>Top 10 Market Cap</h3>
                            <p>$<?php echo number_format($total_market_cap, 0); ?></p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="card-info">
                            <h3>24h Volume</h3>
                            <p>$<?php echo number_format($total_volume, 0); ?></p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-icon">
                            <i class="fab fa-bitcoin"></i>
                        </div>
                        <div class="card-info">
                            <h3>BTC Dominance</h3>
                            <p><?php echo number_format($btc_dominance, 2); ?>%</p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="card-info">
                            <h3>Tracked Cryptocurrencies</h3>
                            <p><?php echo count($symbols); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
                
            <div class="market-data-table">
                <h3>Market Data for Tracked Cryptocurrencies</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Symbol</th>
                                <th>Price (USD)</th>
                                <th>24h Change</th>
                                <th>Market Cap</th>
                                <th>Volume (24h)</th>
                                <th>Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($symbols as $symbol): 
                                if (isset($market_data[$symbol]) && isset($market_data[$symbol]['USD'])): 
                                    $data = $market_data[$symbol]['USD'];
                                    $price = $data['PRICE'];
                                    $change_24h = $data['CHANGEPCT24HOUR'];
                                    $market_cap = $data['MKTCAP'];
                                    $volume = $data['VOLUME24HOUR'];
                                    $last_updated = $data['LASTUPDATE'];
                                    
                                    $change_class = $change_24h >= 0 ? 'positive' : 'negative';
                            ?>
                            <tr>
                                <td><?php echo $symbol; ?></td>
                                <td>$<?php echo number_format($price, 2); ?></td>
                                <td class="<?php echo $change_class; ?>">
                                    <?php echo $change_24h >= 0 ? '+' : ''; ?><?php echo number_format($change_24h, 2); ?>%
                                </td>
                                <td>$<?php echo number_format($market_cap, 0); ?></td>
                                <td>$<?php echo number_format($volume, 0); ?></td>
                                <td><?php echo date('Y-m-d H:i:s', $last_updated); ?></td>
                            </tr>
                            <?php endif; endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if (empty($market_data) && !empty($symbols)): ?>
            <div style="margin: 20px; padding: 15px; background: rgba(255,0,0,0.1); border-left: 4px solid #ff0000;">
                <h3>Debug Information:</h3>
                <p>API URL: <?php echo htmlspecialchars($apiUrl); ?></p>
                <p>Symbols: <?php echo htmlspecialchars(implode(", ", $symbols)); ?></p>
                <p>API Response: <?php echo htmlspecialchars(substr($response, 0, 300) . (strlen($response) > 300 ? '...' : '')); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
