<?php
// Updated index.php - Uses CryptoCompare API instead of CoinGecko
session_start();
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

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Get market data for display
$market_data = [];
$symbols = ["BTC", "ETH", "BNB", "ADA", "SOL"]; // Top 5 cryptos

// Create the symbols string for API call
$symbols_list = implode(",", $symbols);

// CryptoCompare API endpoint for multiple prices/data in one call
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
        $market_data = $data['RAW'];
    }
}

// Get total stats from database
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_cryptos = $conn->query("SELECT COUNT(*) as count FROM cryptocurrencies")->fetch_assoc()['count'];
$total_portfolios = $conn->query("SELECT COUNT(*) as count FROM portfolio")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TripleH Crypto - Track Your Cryptocurrency Investments</title>
    <link rel="stylesheet" href="home_styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="landing-container">
    <div class="bg-overlay"></div>
    <div class="stars"></div>
        <header class="main-header">
            <div class="logo">
                <i class="fas fa-chart-line"></i>
                <h1>TripleH Crypto</h1>
            </div>
            <nav>
                <ul>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#market">Market</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="login.php" class="btn login-btn">Login</a></li>
                    <li><a href="register.php" class="btn register-btn">Register</a></li>
                </ul>
            </nav>
        </header>

        <!-- Hero Section -->
        <section class="hero-section">
            <div class="hero-content">
                <h2>Track, Analyze & Optimize Your Crypto Portfolio</h2>
                <p>Monitor your cryptocurrency investments, track profit/loss, and visualize performance with powerful charts and analytics.</p>
                <div class="cta-buttons">
                    <a href="register.php" class="btn primary-btn">Get Started</a>
                    <a href="#features" class="btn secondary-btn">Learn More</a>
                </div>
            </div>
            <div class="hero-image">
    <img src="cryptoimage.png" alt="Cryptocurrency Illustration" onerror="this.src='https://via.placeholder.com/500x300?text=Cryptocurrency+Trading'">
</div>
        </section>

        <!-- Stats Counter -->
        <section class="stats-counter">
            <div class="stat-item">
                <span class="stat-number"><?php echo $total_users; ?>+</span>
                <span class="stat-label">Active Users</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo $total_cryptos; ?>+</span>
                <span class="stat-label">Cryptocurrencies</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo $total_portfolios; ?>+</span>
                <span class="stat-label">Portfolios Tracked</span>
            </div>
        </section>

        <!-- Market Overview -->
        <section id="market" class="market-overview">
            <h2>Latest Market Prices</h2>
            <div class="crypto-ticker">
                <?php if (!empty($market_data)): ?>
                    <?php foreach($symbols as $symbol):
                        if (isset($market_data[$symbol]) && isset($market_data[$symbol]['USD'])):
                            $data = $market_data[$symbol]['USD'];
                            $price = $data['PRICE'];
                            $change = $data['CHANGEPCT24HOUR'];
                            $changeClass = $change >= 0 ? 'positive' : 'negative';
                    ?>
                        <div class="ticker-item">
                            <div class="coin-name"><?php echo $symbol; ?></div>
                            <div class="coin-price">$<?php echo number_format($price, 2); ?></div>
                            <div class="coin-change <?php echo $changeClass; ?>">
                                <?php echo $change >= 0 ? '+' : ''; ?><?php echo number_format($change, 2); ?>%
                            </div>
                        </div>
                    <?php endif; endforeach; ?>
                <?php else: ?>
                    <div class="ticker-item">
                        <div class="coin-name">Market data currently unavailable</div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="features-section">
            <h2>Key Features</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <h3>Portfolio Tracking</h3>
                    <p>Add all your cryptocurrency holdings in one place for easy tracking and management.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Real-time Charts</h3>
                    <p>Visualize performance with interactive price charts and historical data.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <h3>Profit/Loss Analysis</h3>
                    <p>Automatically calculate profit and loss for each coin and your entire portfolio.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <h3>Real-time Updates</h3>
                    <p>Get the latest cryptocurrency prices and market data from trusted sources.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3>Mobile Friendly</h3>
                    <p>Access your portfolio from any device with our responsive design.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Secure & Private</h3>
                    <p>Your data is encrypted and never shared with third parties.</p>
                </div>
            </div>
        </section>

        <!-- How It Works -->
        <section class="how-it-works">
            <h2>How It Works</h2>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Create an Account</h3>
                    <p>Register for free to get started with TripleH Crypto</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Add Your Holdings</h3>
                    <p>Input your cryptocurrency assets with purchase details</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Track & Analyze</h3>
                    <p>Monitor performance and make better investment decisions</p>
                </div>
            </div>
            <div class="cta-center">
                <a href="register.php" class="btn primary-btn">Start Tracking Now</a>
            </div>
        </section>

        <!-- About Section -->
        <section id="about" class="about-section">
            <h2>About TripleH Crypto</h2>
            <div class="about-content">
                <p>TripleH Crypto is a comprehensive cryptocurrency portfolio management platform designed to help investors track, analyze, and optimize their digital asset investments.</p>
                <p>Whether you're a beginner just starting with cryptocurrencies or an experienced trader managing multiple assets, our platform provides the tools you need to stay on top of your investments and make informed decisions.</p>
                <p>Our data is sourced from the CryptoCompare API, providing reliable and up-to-date information on thousands of cryptocurrencies.</p>
            </div>
        </section>

        <!-- Footer -->
        <footer class="main-footer">
            <div class="footer-content">
                <div class="footer-logo">
                    <i class="fas fa-chart-line"></i>
                    <h3>TripleH Crypto</h3>
                </div>
                <div class="footer-links">
                    <div class="footer-column">
                        <h4>Navigation</h4>
                        <ul>
                            <li><a href="#features">Features</a></li>
                            <li><a href="#market">Market</a></li>
                            <li><a href="#about">About</a></li>
                        </ul>
                    </div>
                    <div class="footer-column">
                        <h4>Account</h4>
                        <ul>
                            <li><a href="login.php">Login</a></li>
                            <li><a href="register.php">Register</a></li>
                        </ul>
                    </div>
                    <div class="footer-column">
                        <h4>Resources</h4>
                        <ul>
                            <li><a href="#">Help Center</a></li>
                            <li><a href="#">Privacy Policy</a></li>
                            <li><a href="#">Terms of Service</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> TripleH Crypto. All rights reserved.</p>
                <p>Data provided by <a href="https://www.cryptocompare.com/" target="_blank">CryptoCompare API</a></p>
            </div>
        </footer>
    </div>
</body>
</html>
                
