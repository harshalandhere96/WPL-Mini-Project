-- Create the database
CREATE DATABASE crypto_tracker;

USE crypto_tracker;

-- Create users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME NULL
);

-- Create cryptocurrencies table
CREATE TABLE cryptocurrencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    symbol VARCHAR(20) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create portfolio table
CREATE TABLE portfolio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    coin_name VARCHAR(100) NOT NULL,
    symbol VARCHAR(20) NOT NULL,
    quantity DECIMAL(18,8) NOT NULL,
    buy_price DECIMAL(18,2) NOT NULL,
    purchase_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default admin user
-- Default password is 'admin123' - change this in production!
INSERT INTO users (username, email, password, is_admin) 
VALUES ('admin', 'admin@example.com', '$2y$10$qo7xHBDA3SzCoB4lK8.CJeCxTJMb.fG/wgHD3PAjOY4Jp4tK/qJbG', 1);

-- Insert some common cryptocurrencies
INSERT INTO cryptocurrencies (name, symbol) VALUES 
('Bitcoin', 'BTC'),
('Ethereum', 'ETH'),
('Binance Coin', 'BNB'),
('Cardano', 'ADA'),
('Solana', 'SOL'),
('XRP', 'XRP'),
('Polkadot', 'DOT'),
('Dogecoin', 'DOGE'),
('Avalanche', 'AVAX'),
('Chainlink', 'LINK');
