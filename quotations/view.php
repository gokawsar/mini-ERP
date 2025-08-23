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

$conn = new mysqli('localhost', 'root', '', 'dbms');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$id = intval($_GET['id']); // Ensure ID is an integer for security
$row = null; // Initialize $row for the main quotation details
$qitems_data = []; // Initialize array for quotation items

// Fetch main quotation data with client and user info
$stmt_quotation = $conn->prepare("
    SELECT q.*, c.client_name, u.username 
    FROM quotations q 
    LEFT JOIN clients c ON q.client_id = c.client_id 
    LEFT JOIN users u ON q.user_id = u.user_id
    WHERE q.quotation_id=?
");

if ($stmt_quotation) {
    $stmt_quotation->bind_param("i", $id);
    $stmt_quotation->execute();
    $result_quotation = $stmt_quotation->get_result();
    $row = $result_quotation->fetch_assoc();
    $stmt_quotation->close();
} else {
    echo "<p style='color:red;'>Error preparing quotation statement: " . $conn->error . "</p>";
}

// Fetch quotation items associated with this quotation
if ($row) { // Only attempt to fetch items if the main quotation was found
    $stmt_qitems = $conn->prepare("
        SELECT qi.*, it.item_name, t.tax_name, t.tax_rate 
        FROM quotation_items qi 
        LEFT JOIN items it ON qi.item_id = it.item_id 
        LEFT JOIN taxes t ON qi.tax_id = t.tax_id
        WHERE qi.quotation_id = ?
    ");
    if ($stmt_qitems) {
        $stmt_qitems->bind_param("i", $id);
        $stmt_qitems->execute();
        $result_qitems = $stmt_qitems->get_result();
        while ($qi = $result_qitems->fetch_assoc()) {
            $qitems_data[] = $qi;
        }
        $stmt_qitems->close();
    } else {
        echo "<p style='color:red;'>Error preparing quotation items statement: " . $conn->error . "</p>";
    }
}

$conn->close();
?>
<div style="margin-left:220px; padding:32px; font-family:Arial,sans-serif; max-width:900px;">
    <h2 style="font-size: 1.8em; margin-bottom: 24px; color: #333;">Quotation Details</h2>

    <div style="background-color: #f9f9f9; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
        <?php if($row): ?>
            <h3 style="font-size: 1.4em; margin-bottom: 20px; color: #555;">Quotation ID: <?= htmlspecialchars($row['quotation_id']) ?> - <?= htmlspecialchars($row['client_name'] ?? 'N/A') ?></h3>
            
            <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px;">
                <p style="flex: 1 1 calc(50% - 10px); margin: 0; line-height: 1.6;"><strong style="color: #333;">Client:</strong> <?= htmlspecialchars($row['client_name'] ?? 'N/A') ?></p>
                <p style="flex: 1 1 calc(50% - 10px); margin: 0; line-height: 1.6;"><strong style="color: #333;">Quotation Date:</strong> <?= htmlspecialchars($row['quotation_date'] ?? 'N/A') ?></p>
                <p style="flex: 1 1 calc(50% - 10px); margin: 0; line-height: 1.6;"><strong style="color: #333;">Expiry Date:</strong> <?= htmlspecialchars($row['expiry_date'] ?? 'N/A') ?></p>
                <p style="flex: 1 1 calc(50% - 10px); margin: 0; line-height: 1.6;"><strong style="color: #333;">Status:</strong> <span style="font-weight: bold; color: <?= ($row['status'] == 'accepted' ? '#28a745' : ($row['status'] == 'rejected' ? '#dc3545' : '#ffc107')) ?>;"><?= htmlspecialchars(ucfirst($row['status'] ?? 'N/A')) ?></span></p>
                <p style="flex: 1 1 calc(50% - 10px); margin: 0; line-height: 1.6;"><strong style="color: #333;">Discount:</strong> BDT <?= htmlspecialchars(number_format($row['discount'] ?? 0, 2)) ?></p>
                <p style="flex: 1 1 calc(50% - 10px); margin: 0; line-height: 1.6;"><strong style="color: #333;">Created By:</strong> <?= htmlspecialchars($row['username'] ?? 'N/A') ?></p>
            </div>

            <p style="margin-bottom: 20px; line-height: 1.6;"><strong style="color: #333;">Notes:</strong> <?= nl2br(htmlspecialchars($row['notes'] ?? 'N/A')) ?></p>
            
            <h3 style="font-size: 1.4em; margin-top: 30px; margin-bottom: 15px; color: #555;">Quotation Items Details</h3>
            <table aria-label="Quotation Items" style="width:100%; margin-bottom:16px; border-collapse: collapse;">
                <thead style="background:#e9e9e9;">
                    <tr>
                        <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Item</th>
                        <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Quantity</th>
                        <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Unit Price</th>
                        <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Subtotal</th>
                        <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Tax</th>
                        <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Tax Amount</th>
                        <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $gross_total_items = 0; // Total before discount
                    if (!empty($qitems_data)):
                        foreach($qitems_data as $item): 
                            $gross_total_items += $item['total'];
                    ?>
                        <tr>
                            <td style="padding: 12px; border: 1px solid #ddd;"><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></td>
                            <td style="padding: 12px; border: 1px solid #ddd;"><?= htmlspecialchars($item['quantity'] ?? 0) ?></td>
                            <td style="padding: 12px; border: 1px solid #ddd;">BDT <?= htmlspecialchars(number_format($item['unit_price'] ?? 0, 2)) ?></td>
                            <td style="padding: 12px; border: 1px solid #ddd;">BDT <?= htmlspecialchars(number_format($item['subtotal'] ?? 0, 2)) ?></td>
                            <td style="padding: 12px; border: 1px solid #ddd;">
                                <?= ($item['tax_name'] ? htmlspecialchars($item['tax_name']) . " (" . htmlspecialchars($item['tax_rate']) . "%)" : "None") ?>
                            </td>
                            <td style="padding: 12px; border: 1px solid #ddd;">BDT <?= htmlspecialchars(number_format($item['tax_amount'] ?? 0, 2)) ?></td>
                            <td style="padding: 12px; border: 1px solid #ddd;">BDT <?= htmlspecialchars(number_format($item['total'] ?? 0, 2)) ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr>
                            <td colspan="7" style="padding: 12px; border: 1px solid #ddd; text-align: center; color: #777;">No items in this quotation.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" style="text-align:right; padding: 12px; border: 1px solid #ddd;"><strong>Gross Total:</strong></td>
                        <td style="padding: 12px; border: 1px solid #ddd;">BDT <?= htmlspecialchars(number_format($gross_total_items, 2)) ?></td>
                    </tr>
                    <tr>
                        <td colspan="6" style="text-align:right; padding: 12px; border: 1px solid #ddd;"><strong>Discount:</strong></td>
                        <td style="padding: 12px; border: 1px solid #ddd;">BDT <?= htmlspecialchars(number_format($row['discount'] ?? 0, 2)) ?></td>
                    </tr>
                    <tr>
                        <td colspan="6" style="text-align:right; padding: 12px; border: 1px solid #ddd; background-color: #e9e9e9;"><strong>Grand Total:</strong></td>
                        <td style="padding: 12px; border: 1px solid #ddd; background-color: #e9e9e9; font-weight: bold;">BDT <?= htmlspecialchars(number_format($row['total_amount'] ?? 0, 2)) ?></td>
                    </tr>
                </tfoot>
            </table>
            
            <div style="margin-top: 30px; display: flex; gap: 15px;">
                <a href="update.php?id=<?= $row['quotation_id'] ?>" style="background-color: #007bff; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Edit Quotation</a>
                <a href="index.php" style="background-color: #6c757d; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Back to List</a>
            </div>
        <?php else: ?>
            <p style="color: #dc3545; font-weight: bold; font-size: 1.1em;">Sorry, the quotation you are looking for was not found. Please check the ID and try again.</p>
            <div style="margin-top: 20px;">
                <a href="index.php" style="background-color: #6c757d; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Back to Quotation List</a>
            </div>
        <?php endif; ?>
    </div>
</div>
