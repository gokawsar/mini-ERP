<?php
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header("Location: users/login.php");
    exit;
}

include 'sidebar.php'; // Include the sidebar, assuming it's correctly located and functional

$conn = new mysqli('localhost', 'root', '', 'dbms');

// Check database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch username for display
$username = 'Guest';
$uid = $_SESSION['user_id'];
$res = $conn->query("SELECT username FROM users WHERE user_id=$uid");
if ($res && $u = $res->fetch_assoc()) {
    $username = htmlspecialchars($u['username']);
}

// --- Dashboard Summary Data Fetching ---

// 1. Number of listed items
$total_items = 0;
$result_items = $conn->query("SELECT COUNT(item_id) AS count FROM items");
if ($result_items && $row = $result_items->fetch_assoc()) {
    $total_items = $row['count'];
}

// 2. Number of listed clients
$total_clients = 0;
$result_clients = $conn->query("SELECT COUNT(client_id) AS count FROM clients");
if ($result_clients && $row = $result_clients->fetch_assoc()) {
    $total_clients = $row['count'];
}

// 3. Number of approved quotations
$approved_quotations_count = 0;
$result_approved_quotations = $conn->query("SELECT COUNT(quotation_id) AS count FROM quotations WHERE status = 'accepted'");
if ($result_approved_quotations && $row = $result_approved_quotations->fetch_assoc()) {
    $approved_quotations_count = $row['count'];
}

// 4. Total amount of approved quotations
$total_approved_quotations_amount = 0.00;
$result_approved_amount = $conn->query("SELECT SUM(total_amount) AS total_amount FROM quotations WHERE status = 'accepted'");
if ($result_approved_amount && $row = $result_approved_amount->fetch_assoc()) {
    $total_approved_quotations_amount = $row['total_amount'] ?? 0.00;
}

$conn->close();
?>

<div style="margin-left:220px; padding:10px; font-family:Arial,sans-serif;">
    <div style="float:right; margin-bottom: 10px;">
        Logged in as <strong><?= $username ?></strong>
        | <a href="users/login.php?logout=1" style="color: #007bff; text-decoration: none;">Logout</a>
    </div>
    <h1 style="font-size: 2.2em; margin-bottom: 10px; color: #333;">👋 Welcome to Mini-ERP Dashboard</h1>
    <p style="color: #666; line-height: 1.5;">Welcome to Mini ERP database management system frontend. This dashboard provides a quick overview of key business metrics.
        <br>
        <p style="background-color: #f0f0f0; padding: 15px; border-radius: 8px; margin-top: 10px; font-size: 0.9em; border: 1px solid #ddd;">
 Lab project: [Database Lab (CSE 210) 232_D4, Summer 2025] <br>
 Presented to: Mr. Mozdaher Abdul Quader, <br>
               Lecturer, Dept. of CSE, <br>
               Green University of Bangladesh.
               <br>
               Overleaf link: <a href="https://www.overleaf.com/read/jzjfdfzwbqcz#e3fdf8" target="_blank" style="color: #007bff; text-decoration: none;">https://www.overleaf.com/read/jzjfdfzwbqcz#e3fdf8</a>
               <br>
               GitHub link: <a href="https://github.com/gokawsar/mini-ERP" target="_blank" style="color: #007bff; text-decoration: none;">https://github.com/gokawsar/mini-ERP</a>
        </p>
    </p>


    <section aria-label="Quick Links" style="margin-top:15px;">
        <h2 style="font-size: 1.8em; margin-bottom: 15px; color: #333;">🚀 Quick Navigation</h2>
        <div style="display: flex; flex-wrap: wrap; gap: 5px;">
            <a href="clients/index.php" style="background-color: #007bff; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Manage Clients</a>
            <a href="items/index.php" style="background-color: #007bff; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Manage Items</a>
            <a href="quotations/index.php" style="background-color: #007bff; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Manage Quotations</a>
            <a href="reports/index.php" style="background-color: #007bff; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">View Reports</a>
        </div>
    </section>


    <h2 style="font-size: 1.8em; margin-top: 10px; margin-bottom: 10px; color: #333;">📊 Dashboard Summary</h2>
    <div style="display: flex; flex-wrap: wrap; gap: 10px;">

        <!-- Widget: Number of Listed Items -->
        <div style="flex: 1 1 calc(20% - 15px); min-width: 200px; background-color: #fff; border: 1px solid #e0e0e0; border-radius: 15px; padding: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.05);">
            <h3 style="font-size: 1.3em; margin-bottom: 10px; color: #008CBA;">Total Listed Items</h3>
            <p style="font-size: 2.5em; font-weight: bold; color: #333; margin: 0;"><?= htmlspecialchars($total_items) ?></p>
            <p style="color: #777; margin-top: 10px;">Currently in inventory.</p>
        </div>

        <!-- Widget: Number of Listed Clients -->
        <div style="flex: 1 1 calc(20% - 15px); min-width: 200px; background-color: #fff; border: 1px solid #e0e0e0; border-radius: 15px; padding: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.05);">
            <h3 style="font-size: 1.3em; margin-bottom: 10px; color: #f44336;">Total Clients</h3>
            <p style="font-size: 2.5em; font-weight: bold; color: #333; margin: 0;"><?= htmlspecialchars($total_clients) ?></p>
            <p style="color: #777; margin-top: 10px;">Managed in the system.</p>
        </div>

        <!-- Widget: Number of Approved Quotations -->
        <div style="flex: 1 1 calc(20% - 15px); min-width: 200px; background-color: #fff; border: 1px solid #e0e0e0; border-radius: 15px; padding: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.05);">
            <h3 style="font-size: 1.3em; margin-bottom: 10px; color: #28a745;">Approved Quotations</h3>
            <p style="font-size: 2.5em; font-weight: bold; color: #333; margin: 0;"><?= htmlspecialchars($approved_quotations_count) ?></p>
            <p style="color: #777; margin-top: 10px;">Ready for processing or delivery.</p>
        </div>

        <!-- Widget: Total Amount of Approved Quotations -->
        <div style="flex: 1 1 calc(20% - 15px); min-width: 200px; background-color: #fff; border: 1px solid #e0e0e0; border-radius: 15px; padding: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.05);">
            <h3 style="font-size: 1.3em; margin-bottom: 10px; color: #ff9800;">Approved Quote Value</h3>
            <p style="font-size: 2.5em; font-weight: bold; color: #333; margin: 0;">BDT <?= number_format($total_approved_quotations_amount, 2) ?></p>
            <p style="color: #777; margin-top: 10px;">Total value of accepted proposals.</p>
        </div>

    </div>

</div>
