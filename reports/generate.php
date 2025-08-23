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
include '../sidebar.php'; // Include the sidebar, assuming it's correctly located and functional

$conn = new mysqli('localhost', 'root', '', 'dbms');

// Check database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$report_type = $_GET['type'] ?? 'custom'; // Default to custom if no type specified
$report_title = "Report";
$report_data = [];
$sql_query_display = ''; // Variable to store SQL query for display

// Function to safely execute queries
// This function will now ALWAYS return an associative array:
// - On success: ['data' => mysqli_result_object]
// - On error:   ['error' => 'error message']
function executeQuery($conn, $sql, $params = [], $types = "") {
    global $sql_query_display;
    $sql_query_display = htmlspecialchars($sql); // Store SQL for display

    if (empty($params)) {
        // Direct query
        $result = $conn->query($sql);
        if ($result === false) {
            return ['error' => 'Query failed: ' . $conn->error];
        }
        return ['data' => $result]; // Wrap mysqli_result in 'data' key
    } else {
        // Prepared statement
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return ['error' => 'Failed to prepare statement: ' . $conn->error];
        }
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            $error_message = $stmt->error;
            $stmt->close();
            return ['error' => 'Statement execution failed: ' . $error_message];
        }
        $result = $stmt->get_result();
        $stmt->close();
        return ['data' => $result]; // Wrap mysqli_result in 'data' key
    }
}

// --- Report Generation Logic ---
switch ($report_type) {
    case 'total_sales':
        $report_title = "Total Sales Amount Report 💰";
        $sql = "SELECT SUM(total_amount) AS total_sales FROM bills WHERE payment_status = 'paid'";
        $query_result_wrapper = executeQuery($conn, $sql); // Get the structured array

        if (isset($query_result_wrapper['error'])) {
            $report_data = $query_result_wrapper; // Set report_data to the error array
        } elseif ($row = $query_result_wrapper['data']->fetch_assoc()) { // Access 'data' key for mysqli_result
            $report_data = ['Total Sales' => number_format($row['total_sales'] ?? 0, 2)];
        } else {
            $report_data = ['Total Sales' => '0.00'];
        }
        break;

    case 'items_sold':
        $report_title = "Top Selling Items by Quantity (All Time) 📦";
        $sql = "SELECT i.item_name, SUM(dsi.quantity) AS total_quantity_sold 
                FROM delivery_slip_items dsi
                JOIN items i ON dsi.item_id = i.item_id
                GROUP BY i.item_name
                ORDER BY total_quantity_sold DESC
                LIMIT 10"; // Limit to top 10 items
        $query_result_wrapper = executeQuery($conn, $sql);

        if (isset($query_result_wrapper['error'])) {
            $report_data = $query_result_wrapper;
        } elseif ($query_result_wrapper['data']->num_rows > 0) {
            while ($row = $query_result_wrapper['data']->fetch_assoc()) {
                $report_data[$row['item_name']] = $row['total_quantity_sold'];
            }
        } else {
            $report_data = ['No Items Sold' => '0'];
        }
        break;

    case 'revenue_by_client':
        $report_title = "Revenue by Client Report 📈";
        $sql = "SELECT c.client_name, SUM(b.total_amount) AS total_revenue
                FROM bills b
                JOIN delivery_slips ds ON b.delivery_slip_id = ds.delivery_slip_id
                JOIN quotations q ON ds.quotation_id = q.quotation_id
                JOIN clients c ON q.client_id = c.client_id
                WHERE b.payment_status = 'paid'
                GROUP BY c.client_name
                ORDER BY total_revenue DESC
                LIMIT 10"; // Top 10 clients by revenue
        $query_result_wrapper = executeQuery($conn, $sql);

        if (isset($query_result_wrapper['error'])) {
            $report_data = $query_result_wrapper;
        } elseif ($query_result_wrapper['data']->num_rows > 0) {
            while ($row = $query_result_wrapper['data']->fetch_assoc()) {
                $report_data[$row['client_name']] = number_format($row['total_revenue'], 2);
            }
        } else {
            $report_data = ['No Revenue Data' => '0.00'];
        }
        break;

    case 'revenue_by_category':
        $report_title = "Revenue by Item Category Report 📊";
        $sql = "SELECT i.category, SUM(dsi.subtotal) AS total_category_revenue
                FROM delivery_slip_items dsi
                JOIN items i ON dsi.item_id = i.item_id
                JOIN delivery_slips ds ON dsi.delivery_slip_id = ds.delivery_slip_id
                JOIN bills b ON ds.delivery_slip_id = b.delivery_slip_id
                WHERE b.payment_status = 'paid'
                GROUP BY i.category
                ORDER BY total_category_revenue DESC";
        $query_result_wrapper = executeQuery($conn, $sql);

        if (isset($query_result_wrapper['error'])) {
            $report_data = $query_result_wrapper;
        } elseif ($query_result_wrapper['data']->num_rows > 0) {
            while ($row = $query_result_wrapper['data']->fetch_assoc()) {
                $report_data[$row['category']] = number_format($row['total_category_revenue'], 2);
            }
        } else {
            $report_data = ['No Category Revenue Data' => '0.00'];
        }
        break;

    case 'approved_quotations':
        $report_title = "Approved Quotations Count ✅";
        $sql = "SELECT COUNT(quotation_id) AS num_approved_quotations FROM quotations WHERE status = 'accepted'";
        $query_result_wrapper = executeQuery($conn, $sql);

        if (isset($query_result_wrapper['error'])) {
            $report_data = $query_result_wrapper;
        } elseif ($row = $query_result_wrapper['data']->fetch_assoc()) {
            $report_data = ['Approved Quotations' => $row['num_approved_quotations'] ?? 0];
        } else {
            $report_data = ['Approved Quotations' => 0];
        }
        break;

    case 'total_clients':
        $report_title = "Total Number of Clients 👥";
        $sql = "SELECT COUNT(client_id) AS total_clients FROM clients";
        $query_result_wrapper = executeQuery($conn, $sql);

        if (isset($query_result_wrapper['error'])) {
            $report_data = $query_result_wrapper;
        } elseif ($row = $query_result_wrapper['data']->fetch_assoc()) {
            $report_data = ['Total Clients' => $row['total_clients'] ?? 0];
        } else {
            $report_data = ['Total Clients' => 0];
        }
        break;

    case 'total_items':
        $report_title = "Total Number of Items in Inventory 🛒";
        $sql = "SELECT COUNT(item_id) AS total_items FROM items";
        $query_result_wrapper = executeQuery($conn, $sql);

        if (isset($query_result_wrapper['error'])) {
            $report_data = $query_result_wrapper;
        } elseif ($row = $query_result_wrapper['data']->fetch_assoc()) {
            $report_data = ['Total Items' => $row['total_items'] ?? 0];
        } else {
            $report_data = ['Total Items' => 0];
        }
        break;
    
    // --- New Advanced Reports ---

    case 'quotation_conversion_rate':
        $report_title = "Quotation Conversion Rate 🎯";
        $sql_total = "SELECT COUNT(quotation_id) AS total_quotations FROM quotations";
        $sql_accepted = "SELECT COUNT(quotation_id) AS accepted_quotations FROM quotations WHERE status = 'accepted'";

        $query_result_total_wrapper = executeQuery($conn, $sql_total);
        $query_result_accepted_wrapper = executeQuery($conn, $sql_accepted);

        if (isset($query_result_total_wrapper['error'])) {
            $report_data = $query_result_total_wrapper;
        } elseif (isset($query_result_accepted_wrapper['error'])) {
            $report_data = $query_result_accepted_wrapper;
        } else {
            $total_quotations = ($row_total = $query_result_total_wrapper['data']->fetch_assoc()) ? ($row_total['total_quotations'] ?? 0) : 0;
            $accepted_quotations = ($row_accepted = $query_result_accepted_wrapper['data']->fetch_assoc()) ? ($row_accepted['accepted_quotations'] ?? 0) : 0;

            if ($total_quotations > 0) {
                $conversion_rate = ($accepted_quotations / $total_quotations) * 100;
                $report_data = [
                    'Total Quotations' => $total_quotations,
                    'Accepted Quotations' => $accepted_quotations,
                    'Conversion Rate' => number_format($conversion_rate, 2) . '%'
                ];
            } else {
                $report_data = ['Total Quotations' => 0, 'Accepted Quotations' => 0, 'Conversion Rate' => '0.00%'];
            }
            // Combine SQL queries for display
            $sql_query_display = htmlspecialchars($sql_total . "\n" . $sql_accepted);
        }
        break;

    case 'avg_order_value':
        $report_title = "Average Order Value (Paid Bills) 💲";
        $sql = "SELECT AVG(total_amount) AS avg_value FROM bills WHERE payment_status = 'paid'";
        $query_result_wrapper = executeQuery($conn, $sql);

        if (isset($query_result_wrapper['error'])) {
            $report_data = $query_result_wrapper;
        } elseif ($row = $query_result_wrapper['data']->fetch_assoc()) {
            $report_data = ['Average Order Value' => number_format($row['avg_value'] ?? 0, 2)];
        } else {
            $report_data = ['Average Order Value' => '0.00'];
        }
        break;

    case 'top_selling_items':
        $report_title = "Top 5 Selling Items by Revenue 🌟";
        // Sum subtotal from delivery_slip_items, linked to paid bills
        $sql = "SELECT i.item_name, SUM(dsi.subtotal) AS total_revenue
                FROM delivery_slip_items dsi
                JOIN items i ON dsi.item_id = i.item_id
                JOIN delivery_slips ds ON dsi.delivery_slip_id = ds.delivery_slip_id
                JOIN bills b ON ds.delivery_slip_id = b.delivery_slip_id
                WHERE b.payment_status = 'paid'
                GROUP BY i.item_name
                ORDER BY total_revenue DESC
                LIMIT 5";
        $query_result_wrapper = executeQuery($conn, $sql);

        if (isset($query_result_wrapper['error'])) {
            $report_data = $query_result_wrapper;
        } elseif ($query_result_wrapper['data']->num_rows > 0) {
            $rank = 1;
            while ($row = $query_result_wrapper['data']->fetch_assoc()) {
                $report_data["#" . $rank++ . " " . $row['item_name']] = number_format($row['total_revenue'], 2);
            }
        } else {
            $report_data = ['No Items Sold by Revenue' => '0.00'];
        }
        break;

    case 'monthly_sales_trend':
        $report_title = "Monthly Sales Trend (Paid Bills) 🗓️";
        $sql = "SELECT 
                    DATE_FORMAT(bill_date, '%Y-%m') AS sales_month,
                    SUM(total_amount) AS monthly_sales
                FROM bills
                WHERE payment_status = 'paid'
                GROUP BY sales_month
                ORDER BY sales_month ASC";
        $query_result_wrapper = executeQuery($conn, $sql);

        if (isset($query_result_wrapper['error'])) {
            $report_data = $query_result_wrapper;
        } elseif ($query_result_wrapper['data']->num_rows > 0) {
            while ($row = $query_result_wrapper['data']->fetch_assoc()) {
                $report_data[$row['sales_month']] = number_format($row['monthly_sales'], 2);
            }
        } else {
            $report_data = ['No Monthly Sales Data' => '0.00'];
        }
        break;
    
    case 'pending_bills_by_client':
        $report_title = "Pending Bills by Client 🚨";
        $sql = "SELECT 
                    c.client_name, 
                    SUM(b.total_amount) AS pending_amount
                FROM bills b
                JOIN delivery_slips ds ON b.delivery_slip_id = ds.delivery_slip_id
                JOIN quotations q ON ds.quotation_id = q.quotation_id
                JOIN clients c ON q.client_id = c.client_id
                WHERE b.payment_status = 'unpaid' OR b.payment_status = 'partial'
                GROUP BY c.client_name
                ORDER BY pending_amount DESC";
        $query_result_wrapper = executeQuery($conn, $sql);

        if (isset($query_result_wrapper['error'])) {
            $report_data = $query_result_wrapper;
        } elseif ($query_result_wrapper['data']->num_rows > 0) {
            while ($row = $query_result_wrapper['data']->fetch_assoc()) {
                $report_data[$row['client_name']] = number_format($row['pending_amount'], 2);
            }
        } else {
            $report_data = ['No Pending Bills' => '0.00'];
        }
        break;

    case 'stock_alert':
        $report_title = "Low Stock Alert Report ⚠️";
        $sql = "SELECT 
                    item_name, 
                    stock_quantity, 
                    reorder_level,
                    (reorder_level - stock_quantity) AS needed_quantity
                FROM items
                WHERE stock_quantity < reorder_level
                ORDER BY needed_quantity DESC";
        $query_result_wrapper = executeQuery($conn, $sql);

        if (isset($query_result_wrapper['error'])) {
            $report_data = $query_result_wrapper;
        } elseif ($query_result_wrapper['data']->num_rows > 0) {
            while ($row = $query_result_wrapper['data']->fetch_assoc()) {
                $report_data[$row['item_name']] = "Current: {$row['stock_quantity']}, Reorder: {$row['reorder_level']}, Need: {$row['needed_quantity']}";
            }
        } else {
            $report_data = ['All Stock Levels Good' => 'N/A'];
        }
        break;

    case 'job_status_summary':
        $report_title = "Job Status Summary 🛠️";
        $sql = "SELECT status, COUNT(job_id) AS job_count FROM jobs GROUP BY status ORDER BY job_count DESC";
        $query_result_wrapper = executeQuery($conn, $sql);

        if (isset($query_result_wrapper['error'])) {
            $report_data = $query_result_wrapper;
        } elseif ($query_result_wrapper['data']->num_rows > 0) {
            while ($row = $query_result_wrapper['data']->fetch_assoc()) {
                $report_data[$row['status']] = $row['job_count'];
            }
        } else {
            $report_data = ['No Jobs Recorded' => '0'];
        }
        break;

    case 'custom':
    default:
        $report_title = "Custom Report Generation ⚙️";
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($start_date) && !empty($end_date)) {
            $report_title = "Total Sales (Paid) from " . htmlspecialchars($start_date) . " to " . htmlspecialchars($end_date);
            
            $sql = "SELECT SUM(total_amount) AS total_sales 
                    FROM bills 
                    WHERE payment_status = 'paid' 
                    AND bill_date BETWEEN ? AND ?";
            $query_result_wrapper = executeQuery($conn, $sql, [$start_date, $end_date], "ss");

            if (isset($query_result_wrapper['error'])) {
                $report_data = $query_result_wrapper;
            } elseif ($row = $query_result_wrapper['data']->fetch_assoc()) {
                $report_data = ['Total Sales in Range' => number_format($row['total_sales'] ?? 0, 2)];
            } else {
                $report_data = ['Total Sales in Range' => '0.00'];
            }
        } else {
            // This case handles initial load of generate.php for custom report type, displaying the form
        }
        break;
}

$conn->close();
?>

<div style="margin-left:220px; padding:32px; font-family:Arial,sans-serif;">
    <h2 style="font-size: 1.8em; margin-bottom: 24px; color: #333;"><?= htmlspecialchars($report_title) ?></h2>

    <div style="background-color: #f9f9f9; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
        <h3 style="font-size: 1.4em; margin-bottom: 15px; color: #555;">Report Details</h3>
        <?php if ($report_type === 'custom' && empty($_POST['start_date'])): ?>
            <p style="color: #777; margin-bottom: 20px;">Use the form below to generate a custom sales report by date range.</p>
            <form action="generate.php?type=custom" method="POST" style="display: flex; flex-direction: column; gap: 15px; max-width: 400px;">
                <label for="start_date" style="font-weight: bold; color: #555;">Start Date:</label>
                <input type="date" id="start_date" name="start_date" required style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                
                <label for="end_date" style="font-weight: bold; color: #555;">End Date:</label>
                <input type="date" id="end_date" name="end_date" required style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                
                <button type="submit" style="background-color: #28a745; color: white; padding: 10px 18px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; transition: background-color 0.3s ease;">Generate Custom Report</button>
            </form>
        <?php elseif (!empty($report_data) && !isset($report_data['Error'])): ?>
            <table aria-label="Report Details" border="1" cellpadding="8" style="width:100%; border-collapse: collapse; margin-top: 15px;">
                <thead style="background:#e9e9e9;">
                    <tr>
                        <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Metric</th>
                        <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data as $metric => $value): ?>
                        <tr>
                            <td style="padding: 12px; border: 1px solid #ddd;"><?= htmlspecialchars($metric) ?></td>
                            <td style="padding: 12px; border: 1px solid #ddd;"><?= htmlspecialchars($value) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif (isset($report_data['Error'])): ?>
            <p style="color: #dc3545; font-weight: bold;">Error: <?= htmlspecialchars($report_data['Error']) ?></p>
        <?php else: ?>
            <p style="color: #777;">No data available for this report type or criteria.</p>
        <?php endif; ?>
    </div>

    <?php if (!empty($sql_query_display)): ?>
        <div style="background-color: #f0f8ff; border: 1px solid #b0e0e6; padding: 15px; border-radius: 8px; margin-top: 20px;">
            <h4 style="font-size: 1.1em; margin-bottom: 10px; color: #337ab7;">SQL Query Used <button onclick="document.getElementById('sql_query_code').style.display = document.getElementById('sql_query_code').style.display === 'none' ? 'block' : 'none';" style="background-color: #337ab7; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">Toggle View</button></h4>
            <pre id="sql_query_code" style="display: none; background-color: #e0eaf1; padding: 10px; border-radius: 5px; overflow-x: auto; font-family: 'Courier New', Courier, monospace; font-size: 0.9em; white-space: pre-wrap; word-wrap: break-word;"><code><?= $sql_query_display ?></code></pre>
        </div>
    <?php endif; ?>

    <p style="margin-top: 30px;"><a href="index.php" style="background-color: #6c757d; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Back to Reports Dashboard</a></p>
</div>
