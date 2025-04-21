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

// Get crypto info before deleting
$stmt = $conn->prepare("SELECT name, symbol FROM cryptocurrencies WHERE id = ?");
$stmt->bind_param("i", $crypto_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $crypto = $result->fetch_assoc();
    
    // First delete all portfolio entries using this cryptocurrency
    $stmt = $conn->prepare("DELETE FROM portfolio WHERE symbol = ?");
    $stmt->bind_param("s", $crypto['symbol']);
    $stmt->execute();
    $portfolio_deleted = $stmt->affected_rows;
    
    // Now delete the cryptocurrency
    $stmt = $conn->prepare("DELETE FROM cryptocurrencies WHERE id = ?");
    $stmt->bind_param("i", $crypto_id);
    $stmt->execute();
    
    // Set success message
    $_SESSION['admin_message'] = "Cryptocurrency " . htmlspecialchars($crypto['name']) . " (" . htmlspecialchars($crypto['symbol']) . ") has been deleted.";
    if ($portfolio_deleted > 0) {
        $_SESSION['admin_message'] .= " " . $portfolio_deleted . " portfolio entries were also removed.";
    }
    $_SESSION['admin_message_type'] = 'success';
} else {
    // Cryptocurrency not found
    $_SESSION['admin_message'] = "Cryptocurrency not found.";
    $_SESSION['admin_message_type'] = 'error';
}

header("Location: admin_add_crypto.php");
exit();
