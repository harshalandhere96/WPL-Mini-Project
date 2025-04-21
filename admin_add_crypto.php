<?php

session_start();
include("includes/config.php");

// Check if the admin is logged in
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit();
}

$success_message = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $symbol = strtoupper(trim($_POST['symbol'])); // CryptoCompare uses uppercase symbols
    
    // Validate inputs
    if (empty($name) || empty($symbol)) {
        $error_message = "Both name and symbol are required.";
    } else {
        // Check if the crypto already exists
        $stmt = $conn->prepare("SELECT id FROM cryptocurrencies WHERE UPPER(symbol) = ?");
        $stmt->bind_param("s", $symbol);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error_message = "This cryptocurrency already exists in the database.";
        } else {
            // Check if the symbol exists in CryptoCompare API
            $apiUrl = "https://min-api.cryptocompare.com/data/price?fsym={$symbol}&tsyms=USD";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $response = curl_exec($ch);
            curl_close($ch);
            
            $price_data = json_decode($response, true);
            
            if (empty($price_data) || isset($price_data['Response']) && $price_data['Response'] === 'Error' || !isset($price_data['USD'])) {
                $error_message = "Warning: This symbol may not be recognized by CryptoCompare API. Please verify the symbol.";
                $warning = true;
            }
            
            // Insert new crypto into database (even if warning)
            if (empty($error_message) || isset($warning)) {
                $stmt = $conn->prepare("INSERT INTO cryptocurrencies (name, symbol) VALUES (?, ?)");
                $stmt->bind_param("ss", $name, $symbol);

                if ($stmt->execute()) {
                    $success_message = "Cryptocurrency added successfully!";
                    // If there was a warning, modify success message
                    if (isset($warning)) {
                        $success_message .= " Note: This symbol may not be recognized by the price API.";
                    }
                } else {
                    $error_message = "Error: " . $conn->error;
                }
            }
        }

        $stmt->close();
    }
}

// Get list of cryptocurrencies in the database
$cryptos = $conn->query("SELECT id, name, symbol, created_at FROM cryptocurrencies ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Cryptocurrencies - Admin</title>
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
                <li class="active"><a href="admin_add_crypto.php"><i class="fas fa-plus-circle"></i> Add Cryptocurrencies</a></li>
                <li><a href="admin_manage_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                <li><a href="admin_market_overview.php"><i class="fas fa-chart-line"></i> Market Overview</a></li>
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Visit Website</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <div class="main-content">
            <header>
                <h1>Add Cryptocurrencies</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
            </header>

            <div class="content-container">
                <div class="add-crypto-form">
                    <h2>Add New Cryptocurrency</h2>
                    
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
                    
                    <form method="post">
                        <div class="form-group">
                            <label for="name">Cryptocurrency Name</label>
                            <input type="text" id="name" name="name" placeholder="e.g. Bitcoin" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="symbol">Symbol</label>
                            <input type="text" id="symbol" name="symbol" placeholder="e.g. BTC" required>
                            <small>Make sure to use the correct symbol as used by CryptoCompare API</small>
                        </div>
                        
                        <button type="submit" class="btn"><i class="fas fa-plus-circle"></i> Add Cryptocurrency</button>
                    </form>
                </div>
                
                <div class="crypto-list">
                    <h2>Available Cryptocurrencies</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Symbol</th>
                                <th>Added Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($cryptos && $cryptos->num_rows > 0): ?>
                                <?php while ($crypto = $cryptos->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($crypto['name']); ?></td>
                                        <td><?php echo htmlspecialchars($crypto['symbol']); ?></td>
                                        <td><?php echo date("M d, Y", strtotime($crypto['created_at'])); ?></td>
                                        <td>
                                            <a href="admin_edit_crypto.php?id=<?php echo $crypto['id']; ?>" class="action-link" title="Edit"><i class="fas fa-edit"></i></a>
                                            <a href="admin_delete_crypto.php?id=<?php echo $crypto['id']; ?>" class="action-link delete" title="Delete" onclick="return confirm('Are you sure you want to delete this cryptocurrency? This will also remove all portfolio entries using this cryptocurrency.');"><i class="fas fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">No cryptocurrencies added yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
