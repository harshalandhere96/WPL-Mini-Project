<?php
session_start();
include("includes/config.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$entry_id = $_GET['id'];

// Check if the entry belongs to the user before deleting
$stmt = $conn->prepare("SELECT symbol FROM portfolio WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $entry_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $entry = $result->fetch_assoc();
    $symbol = $entry['symbol'];
    
    // Delete the entry
    $stmt = $conn->prepare("DELETE FROM portfolio WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $entry_id, $user_id);
    $stmt->execute();
    
    // Check if we should redirect to the coin view page or dashboard
    if (isset($_GET['return']) && $_GET['return'] == 'view') {
        header("Location: view_coin.php?symbol=" . strtolower($symbol));
    } else {
        header("Location: dashboard.php");
    }
} else {
    // Entry doesn't belong to user or doesn't exist
    header("Location: dashboard.php");
}
exit();