<?php
session_start();
include("includes/config.php");

// Check if the user is an admin
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: admin_add_crypto.php");
    exit();
}

$crypto_id = $_GET['id'];

// Get cryptocurrency details
$stmt = $conn->prepare("SELECT id, name, symbol FROM cryptocurrencies WHERE id = ?");
$stmt->bind_param("i", $crypto_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: admin_add_crypto.php");
    exit();
}

$crypto = $result->fetch_assoc();
$stmt->close();

$success_message = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $symbol = strtoupper(trim($_POST['symbol']));
    
    // Validate inputs
    if (empty($name) || empty($symbol)) {
        $error_message = "Both name and symbol are required.";
    } else {
        // Check if updated symbol already exists (but ignore the current crypto)
        $stmt = $conn->prepare("SELECT id FROM cryptocurrencies WHERE symbol = ? AND id != ?");
        $stmt->bind_param("si", $symbol, $crypto_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error_message = "Another cryptocurrency with this symbol already exists.";
        } else {
            // Update cryptocurrency
            $stmt = $conn->prepare("UPDATE cryptocurrencies SET name = ?, symbol = ? WHERE id = ?");
            $stmt->bind_param("ssi", $name, $symbol, $crypto_id);
            
            if ($stmt->execute()) {
                // Also update the symbol in portfolio table if it changed
                if ($symbol !== $crypto['symbol']) {
                    $stmt = $conn->prepare("UPDATE portfolio SET symbol = ? WHERE symbol = ?");
                    $stmt->bind_param("ss", $symbol, $crypto['symbol']);
                    $stmt->execute();
                }
                
                $success_message = "Cryptocurrency updated successfully!";
                $crypto['name'] = $name;
                $crypto['symbol'] = $symbol;
            } else {
                $error_message = "Error updating cryptocurrency: " . $conn->error;
            }
        }
        
        $stmt->close();
    }
}

// Check cryptocurrency usage in portfolio
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM portfolio WHERE symbol = ?");
$stmt->bind_param("s", $crypto['symbol']);
$stmt->execute();
$usage = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Cryptocurrency - Admin</title>
    <link rel="stylesheet" href="admin_styles.css">
    <link rel="stylesheet" href="form_styles.css">
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
                <li><a href="index.php"><i class="fas fa-home"></i> Visit Website</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <div class="main-content">
            <header>
                <h1>Edit Cryptocurrency</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
            </header>

            <div class="form-container">
                <div class="breadcrumb">
                    <a href="admin_add_crypto.php">Cryptocurrencies</a> &gt; 
                    <span>Edit <?php echo htmlspecialchars($crypto['name']); ?></span>
                </div>
                
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
                        <label for="name">Cryptocurrency Name</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($crypto['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="symbol">Symbol</label>
                        <input type="text" id="symbol" name="symbol" value="<?php echo htmlspecialchars($crypto['symbol']); ?>" required <?php echo $usage > 0 ? 'data-original="'.htmlspecialchars($crypto['symbol']).'"' : ''; ?>>
                        <small>The symbol is used to fetch price data from the API</small>
                        
                        <?php if ($usage > 0): ?>
                            <div class="warning-message">
                                <i class="fas fa-exclamation-triangle"></i> This cryptocurrency is used in <?php echo $usage; ?> portfolio entries. Changing the symbol will update all related portfolio entries.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn"><i class="fas fa-save"></i> Update Cryptocurrency</button>
                        <a href="admin_add_crypto.php" class="btn secondary">Cancel</a>
                        <a href="admin_delete_crypto.php?id=<?php echo $crypto_id; ?>" class="btn delete" onclick="return confirm('Are you sure you want to delete this cryptocurrency? This will also remove all portfolio entries using this cryptocurrency.');">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Warning when changing symbol for cryptocurrencies in use
        const symbolInput = document.getElementById('symbol');
        if (symbolInput.hasAttribute('data-original')) {
            const originalValue = symbolInput.getAttribute('data-original');
            
            symbolInput.addEventListener('input', function() {
                const warning = document.querySelector('.warning-message');
                if (this.value.toUpperCase() !== originalValue) {
                    warning.style.color = '#f39c12';
                    warning.style.fontWeight = 'bold';
                } else {
                    warning.style.color = '';
                    warning.style.fontWeight = '';
                }
            });
        }
    </script>
</body>
</html>
