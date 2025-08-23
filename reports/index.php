<?php
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/login.php");
    exit;
}

// Include the sidebar, assuming it's correctly located and functional
include '../sidebar.php';

$conn = new mysqli('localhost', 'root', '', 'dbms');

// Check database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch existing reports for the table, ordered by most recent
$result = $conn->query("SELECT report_id, report_date, report_name FROM reports ORDER BY report_date DESC");

// Check for query errors
if (!$result) {
    die("Error fetching reports: " . $conn->error);
}
?>
<div style="margin-left:220px; padding:32px; font-family:Arial,sans-serif;">
    <h2 style="font-size: 1.8em; margin-bottom: 24px; color: #333;">Reports Dashboard </h2>



    <div style="background-color: #f9f9f9; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
        <h3 style="font-size: 1.4em; margin-bottom: 15px; color: #555;">Client & Quotation Insights</h3>
        <p style="margin-bottom: 15px; color: #666;">Understand your client base and quotation effectiveness.</p>
        <div style="display: flex; flex-wrap: wrap; gap: 15px;">
            <a href="generate.php?type=total_clients" style="background-color: #6610f2; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Total Clients Count</a>
            <a href="generate.php?type=approved_quotations" style="background-color: #28a745; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Approved Quotations</a>
            <a href="generate.php?type=quotation_conversion_rate" style="background-color: #fd7e14; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Quotation Conversion Rate</a>
            <a href="generate.php?type=pending_bills_by_client" style="background-color: #ffc107; color: #333; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Pending Bills by Client</a>
        </div>
    </div>



    <div style="background-color: #f9f9f9; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
        <h3 style="font-size: 1.4em; margin-bottom: 15px; color: #555;">Operational & Inventory Reports </h3>
        <p style="margin-bottom: 15px; color: #666;">Monitor inventory levels, job progress, and item performance.</p>
        <div style="display: flex; flex-wrap: wrap; gap: 15px;">
            <a href="generate.php?type=items_sold" style="background-color: #008CBA; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Total Items Sold</a>
            <a href="generate.php?type=top_selling_items" style="background-color: #6f42c1; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Top 5 Selling Items</a>
            <a href="generate.php?type=stock_alert" style="background-color: #dc3545; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Stock Alert Report</a>
            <a href="generate.php?type=job_status_summary" style="background-color: #20c997; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Job Status Summary</a>
        </div>
    </div>


 <!--
    <h3 style="font-size: 1.4em; margin-bottom: 15px; color: #555;">Previously Generated Reports </h3>
    <a href="generate.php?type=custom" style="background-color: #007bff; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; margin-bottom:16px; display:inline-block; transition: background-color 0.3s ease;">Generate New Custom Report</a>
    
    <table aria-label="Reports List" border="1" cellpadding="8" style="width:100%; margin-top:16px; border-collapse: collapse;">
        <thead style="background:#e9e9e9;">
            <tr>
                <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">ID</th>
                <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Date</th>
                <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Name</th>
                <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Actions</th>
            </tr>
        </thead>
    </table>
 -->


 <!--    <div style="background-color: #f9f9f9; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
        <h3 style="font-size: 1.4em; margin-bottom: 15px; color: #555;">Financial & Sales Insights </h3>
        <p style="margin-bottom: 15px; color: #666;">Get quick overviews of your sales and revenue.</p>
        <div style="display: flex; flex-wrap: wrap; gap: 15px;">
            <a href="generate.php?type=total_sales" style="background-color: #4CAF50; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Total Sales Amount</a>
            <a href="generate.php?type=revenue_by_client" style="background-color: #f44336; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Revenue by Client</a>
            <a href="generate.php?type=revenue_by_category" style="background-color: #ff9800; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Revenue by Item Category</a>
            <a href="generate.php?type=avg_order_value" style="background-color: #8A2BE2; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Average Order Value</a>
            <a href="generate.php?type=monthly_sales_trend" style="background-color: #17a2b8; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Monthly Sales Trend</a>
        </div>
    </div>
 -->



</div>
<?php $conn->close(); ?>
