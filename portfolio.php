<?php
// Updated portfolio.php - Uses CryptoCompare API instead of CoinGecko
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

// Fetch available cryptocurrencies
$result = $conn->query("SELECT * FROM cryptocurrencies ORDER BY name ASC");
$cryptos = $result->fetch_all(MYSQLI_ASSOC);

$success_message = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $crypto_id = $_POST['crypto_id'];
    $quantity = $_POST['quantity'];
    $buy_price = $_POST['buy_price'];
    $buy_date = $_POST['buy_date'];

    // Validate inputs
    if ($quantity <= 0) {
        $error_message = "Quantity must be greater than zero.";
    } else if ($buy_price <= 0) {
        $error_message = "Buy price must be greater than zero.";
    } else {
        // Get crypto details
        $stmt = $conn->prepare("SELECT name, symbol FROM cryptocurrencies WHERE id = ?");
        $stmt->bind_param("i", $crypto_id);
        $stmt->execute();
        $crypto = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($crypto) {
            // Direct insert
            $date_query_part = "";
            $stmt_types = "issdd";
            $stmt_params = [$user_id, $crypto['name'], $crypto['symbol'], $quantity, $buy_price];
            
            if (!empty($buy_date)) {
                $date_query_part = ", purchase_date";
                $stmt_types .= "s";
                $stmt_params[] = $buy_date;
            }
    
            $stmt = $conn->prepare("INSERT INTO portfolio (user_id, coin_name, symbol, quantity, buy_price" . $date_query_part . ") VALUES (?, ?, ?, ?, ?" . (!empty($date_query_part) ? ", ?" : "") . ")");
            $stmt->bind_param($stmt_types, ...$stmt_params);
    
            if ($stmt->execute()) {
                $success_message = "Added " . $quantity . " " . $crypto['symbol'] . " at $" . $buy_price . " to your portfolio!";
            } else {
                $error_message = "Error: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error_message = "Invalid cryptocurrency selection.";
        }
    }
}

// Get current prices from CryptoCompare for preview
$preview_data = [];
if (!empty($cryptos)) {
    $symbols = [];
    
    foreach ($cryptos as $crypto) {
        $symbols[] = strtoupper($crypto['symbol']);
    }
    
    // Convert symbols array to comma-separated string
    $symbols_list = implode(",", $symbols);
    
    // CryptoCompare API endpoint for multiple prices
    $api_url = "https://min-api.cryptocompare.com/data/pricemulti?fsyms={$symbols_list}&tsyms=USD";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $preview_data = json_decode($response, true);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add to Portfolio - Crypto Tracker</title>
    <link rel="stylesheet" href="dashboardstyles.css">
    <link rel="stylesheet" href="form_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <header>
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a> &gt; 
                <span>Add to Portfolio</span>
            </div>
            <div class="actions">
                <a href="dashboard.php" class="btn"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="logout.php" class="btn logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </header>

        <div class="form-container">
            <h1>Add Cryptocurrency to Portfolio</h1>
            
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

            <form method="post" class="edit-form">
                <div class="form-group">
                    <label for="crypto_id">Select Cryptocurrency</label>
                    <select id="crypto_id" name="crypto_id" required>
                        <option value="">-- Select Cryptocurrency --</option>
                        <?php foreach ($cryptos as $crypto): 
                            $symbol = strtoupper($crypto['symbol']);
                            $current_price = isset($preview_data[$symbol]['USD']) ? '$' . number_format($preview_data[$symbol]['USD'], 2) : 'N/A';
                        ?>
                            <option value="<?php echo $crypto['id']; ?>" data-price="<?php echo isset($preview_data[$symbol]['USD']) ? $preview_data[$symbol]['USD'] : ''; ?>">
                                <?php echo htmlspecialchars($crypto['name']); ?> (<?php echo strtoupper($crypto['symbol']); ?>) - <?php echo $current_price; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="quantity">Quantity</label>
                    <input type="number" id="quantity" name="quantity" step="any" required placeholder="e.g. 0.5">
                </div>
                
                <div class="form-group">
                    <label for="buy_price">Buy Price (USD)</label>
                    <input type="number" id="buy_price" name="buy_price" step="any" required placeholder="e.g. 35000.00">
                </div>
                
                <div class="form-group">
                    <label for="buy_date">Purchase Date (Optional)</label>
                    <input type="date" id="buy_date" name="buy_date" max="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label>Total Investment</label>
                    <div class="calculated-value" id="total-investment">$0.00</div>
                </div>
                
                <div class="form-group">
                    <label>Current Value (Estimated)</label>
                    <div class="calculated-value" id="current-value">$0.00</div>
                </div>
                
                <div class="form-group">
                    <label>Profit/Loss (Estimated)</label>
                    <div class="calculated-value" id="profit-loss">$0.00 (0.00%)</div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn">Add to Portfolio</button>
                    <a href="dashboard.php" class="btn secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Auto-fill current price when cryptocurrency is selected
        document.getElementById('crypto_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const currentPrice = selectedOption.getAttribute('data-price');
            
            if (currentPrice) {
                document.getElementById('buy_price').value = currentPrice;
            } else {
                document.getElementById('buy_price').value = '';
            }
            
            calculateValues();
        });
        
        // Calculate values on input change
        document.getElementById('quantity').addEventListener('input', calculateValues);
        document.getElementById('buy_price').addEventListener('input', calculateValues);
        
        function calculateValues() {
            const quantity = parseFloat(document.getElementById('quantity').value) || 0;
            const buyPrice = parseFloat(document.getElementById('buy_price').value) || 0;
            const selectedOption = document.getElementById('crypto_id').options[document.getElementById('crypto_id').selectedIndex];
            const currentPrice = parseFloat(selectedOption.getAttribute('data-price')) || 0;
            
            const totalInvestment = quantity * buyPrice;
            document.getElementById('total-investment').textContent = '$' + totalInvestment.toFixed(2);
            
            if (currentPrice > 0) {
                const currentValue = quantity * currentPrice;
                document.getElementById('current-value').textContent = '$' + currentValue.toFixed(2);
                
                const profitLoss = currentValue - totalInvestment;
                const profitLossPercentage = totalInvestment > 0 ? (profitLoss / totalInvestment) * 100 : 0;
                
                const profitLossElement = document.getElementById('profit-loss');
                profitLossElement.textContent = '$' + profitLoss.toFixed(2) + ' (' + (profitLoss >= 0 ? '+' : '') + profitLossPercentage.toFixed(2) + '%)';
                
                if (profitLoss >= 0) {
                    profitLossElement.classList.remove('negative');
                    profitLossElement.classList.add('positive');
                } else {
                    profitLossElement.classList.remove('positive');
                    profitLossElement.classList.add('negative');
                }
            } else {
                document.getElementById('current-value').textContent = '$0.00';
                document.getElementById('profit-loss').textContent = '$0.00 (0.00%)';
                document.getElementById('profit-loss').classList.remove('positive', 'negative');
            }
        }
        
        // Set default date to today
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('buy_date').value = today;
        });
    </script>
</body>
</html>
