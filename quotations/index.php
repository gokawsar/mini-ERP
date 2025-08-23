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
include '../sidebar.php';

// DB connection
$conn = new mysqli('localhost', 'root', '', 'dbms');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get quotations with client info and item count
// Using LEFT JOIN to ensure all quotations are listed even if client_id is somehow null
// and a subquery for item_count is efficient for this purpose.
$sql = "
    SELECT 
        q.quotation_id, 
        q.quotation_date, 
        q.expiry_date, 
        q.total_amount, 
        q.discount,
        q.status, 
        q.notes,
        c.client_name, 
        (SELECT COUNT(*) FROM quotation_items qi WHERE qi.quotation_id = q.quotation_id) as item_count 
    FROM quotations q 
    LEFT JOIN clients c ON q.client_id = c.client_id
    ORDER BY q.quotation_date DESC
";
$result = $conn->query($sql);

if (!$result) {
    die("Error fetching quotations: " . $conn->error);
}
?>
<div style="margin-left:220px; padding:32px; font-family:Arial,sans-serif;">
    <h2 style="font-size: 1.8em; margin-bottom: 24px; color: #333;">Sales Quotations</h2>

    <div style="background-color: #f9f9f9; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
        <h3 style="font-size: 1.4em; margin-bottom: 15px; color: #555;">Quotation Management Actions</h3>
        <p style="margin-bottom: 15px; color: #666;">Create, manage, and review client quotations.</p>
        <div style="display: flex; flex-wrap: wrap; gap: 15px;">
            <a href="create.php" style="background-color: #28a745; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Add New Quotation</a>
        </div>
    </div>

    <h3 style="font-size: 1.4em; margin-bottom: 15px; color: #555;">Current Quotation List</h3>
    <table aria-label="Quotations List" border="1" cellpadding="8" style="width:100%; margin-top:16px; border-collapse: collapse;">
        <thead style="background:#e9e9e9;">
            <tr>
                <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">ID</th>
                <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Client</th>
                <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Date</th>
                <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Expiry</th>
                <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Total</th>
                <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Status</th>
                <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Items</th>
                <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td style="padding: 12px; border: 1px solid #ddd;"><?= htmlspecialchars($row['quotation_id']) ?></td>
                    <td style="padding: 12px; border: 1px solid #ddd;"><?= htmlspecialchars($row['client_name'] ?? 'N/A') ?></td>
                    <td style="padding: 12px; border: 1px solid #ddd;"><?= htmlspecialchars($row['quotation_date']) ?></td>
                    <td style="padding: 12px; border: 1px solid #ddd;"><?= htmlspecialchars($row['expiry_date'] ?? 'N/A') ?></td>
                    <td style="padding: 12px; border: 1px solid #ddd;">BDT <?= htmlspecialchars(number_format($row['total_amount'], 2)) ?></td>
                    <td style="padding: 12px; border: 1px solid #ddd;"><?= htmlspecialchars($row['status']) ?></td>
                    <td style="padding: 12px; border: 1px solid #ddd;"><?= htmlspecialchars($row['item_count']) ?></td>
                    <td style="padding: 12px; border: 1px solid #ddd; white-space: nowrap;">
                        <a href="view.php?id=<?= $row['quotation_id'] ?>" style="color: #007bff; text-decoration: none; margin-right: 10px;">View</a>
                        <a href="update.php?id=<?= $row['quotation_id'] ?>" style="color: #28a745; text-decoration: none; margin-right: 10px;">Edit</a>
                        <a href="delete.php?id=<?= $row['quotation_id'] ?>" onclick="return confirm('Are you sure you want to delete quotation ID: <?= htmlspecialchars($row['quotation_id']) ?>?');" style="color: #dc3545; text-decoration: none;">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="padding: 12px; border: 1px solid #ddd; text-align: center; color: #777;">No quotations found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php $conn->close(); ?>
