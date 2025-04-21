<?php
// Updated edit_coin.php - Uses CryptoCompare API instead of CoinGecko
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

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$entry_id = $_GET['id'];

// Check if the entry belongs to the user
$stmt = $conn->prepare("SELECT id, coin_name, symbol, quantity, buy_price FROM portfolio WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $entry_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: dashboard.php");
    exit();
}

$entry = $result->fetch_assoc();
$stmt->close();

$success_message = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $quantity = $_POST['quantity'];
    $buy_price = $_POST['buy_price'];
    
    if ($quantity <= 0) {
        $error_message = "Quantity must be greater than zero.";
    } else if ($buy_price <= 0) {
        $error_message = "Buy price must be greater than zero.";
    } else {
        $stmt = $conn->prepare("UPDATE portfolio SET quantity = ?, buy_price = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ddii", $quantity, $buy_price, $entry_id, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "Portfolio entry updated successfully!";
            // Update the entry data
            $entry['quantity'] = $quantity;
            $entry['buy_price'] = $buy_price;
        } else {
            $error_message = "Error updating entry: " . $conn->error;
        }
        
        $stmt->close();
    }
}

// Get current price from CryptoCompare
$symbol = strtoupper($entry['symbol']);
$api_url = "https://min-api.cryptocompare.com/data/price?fsym={$symbol}&tsyms=USD";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
curl_close($ch);

$current_price = 'N/A';
if ($response) {
    $price_data = json_decode($response, true);
    if (isset($price_data['USD'])) {
        $current_price = $price_data['USD'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Portfolio Entry - Crypto Tracker</title>
    <link rel="stylesheet" href="dashboardstyles.css">
    <link rel="stylesheet" href="form_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <header>
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a> &gt; 
                <span>Edit <?php echo htmlspecialchars($entry['coin_name']); ?></span>
            </div>
            <div class="actions">
                <a href="dashboard.php" class="btn"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="logout.php" class="btn logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </header>

        <div class="form-container">
            <h1>Edit Portfolio Entry</h1>
            
            <?php if ($success_message): ?>
                <div class="alert success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert error">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="coin-info">
                <h2><?php echo htmlspecialchars($entry['coin_name']); ?> (<?php echo strtoupper($entry['symbol']); ?>)</h2>
                <p class="current-price">
                    Current Price: 
                    <span class="price">$<?php echo is_numeric($current_price) ? number_format($current_price, 2) : $current_price; ?></span>
                </p>
            </div>
            
            <form method="post" class="edit-form">
                <div class="form-group">
                    <label for="quantity">Quantity</label>
                    <input type="number" id="quantity" name="quantity" step="any" value="<?php echo htmlspecialchars($entry['quantity']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="buy_price">Buy Price (USD)</label>
                    <input type="number" id="buy_price" name="buy_price" step="any" value="<?php echo htmlspecialchars($entry['buy_price']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Total Investment</label>
                    <div class="calculated-value" id="total-investment">$<?php echo number_format($entry['quantity'] * $entry['buy_price'], 2); ?></div>
                </div>
                
                <?php if (is_numeric($current_price)): ?>
                <div class="form-group">
                    <label>Current Value</label>
                    <div class="calculated-value" id="current-value">$<?php echo number_format($entry['quantity'] * $current_price, 2); ?></div>
                </div>
                
                <div class="form-group">
                    <label>Profit/Loss</label>
                    <?php
                        $profit_loss = ($entry['quantity'] * $current_price) - ($entry['quantity'] * $entry['buy_price']);
                        $profit_loss_percentage = ($profit_loss / ($entry['quantity'] * $entry['buy_price'])) * 100;
                        $profit_class = $profit_loss >= 0 ? "positive" : "negative";
                    ?>
                    <div class="calculated-value <?php echo $profit_class; ?>" id="profit-loss">
                        $<?php echo number_format($profit_loss, 2); ?> 
                        (<?php echo $profit_loss >= 0 ? '+' : ''; ?><?php echo number_format($profit_loss_percentage, 2); ?>%)
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="form-actions">
                    <button type="submit" class="btn">Update Entry</button>
                    <a href="view_coin.php?symbol=<?php echo urlencode(strtoupper($entry['symbol'])); ?>" class="btn secondary">Cancel</a>
                    <a href="delete_coin.php?id=<?php echo $entry_id; ?>" class="btn delete" onclick="return confirm('Are you sure you want to delete this entry?');">Delete Entry</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Calculate values on input change
        document.getElementById('quantity').addEventListener('input', calculateValues);
        document.getElementById('buy_price').addEventListener('input', calculateValues);
        
        function calculateValues() {
            const quantity = parseFloat(document.getElementById('quantity').value) || 0;
            const buyPrice = parseFloat(document.getElementById('buy_price').value) || 0;
            const currentPrice = <?php echo is_numeric($current_price) ? $current_price : 0; ?>;
            
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
            }
        }
    </script>
</body>
</html>
