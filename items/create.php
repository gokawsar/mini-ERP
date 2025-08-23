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

$error_message = ''; // Initialize error message variable

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize form data
    $item_name = trim($_POST['item_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $unit_price = (float)($_POST['unit_price'] ?? 0);
    $unit_of_measure = trim($_POST['unit_of_measure'] ?? '');
    $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
    $reorder_level = (int)($_POST['reorder_level'] ?? 0);
    $supplier = trim($_POST['supplier'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    // Basic server-side validation
    if (empty($item_name)) {
        $error_message = "Item Name cannot be empty.";
    } elseif ($unit_price <= 0) {
        $error_message = "Unit Price must be a positive number.";
    } else {
        $stmt = $conn->prepare("INSERT INTO items (item_name, description, unit_price, unit_of_measure, stock_quantity, reorder_level, supplier, sku, category, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            $error_message = "Failed to prepare SQL statement: " . $conn->error;
        } else {
            $stmt->bind_param("ssdsiissss",
                $item_name, $description, $unit_price, $unit_of_measure,
                $stock_quantity, $reorder_level, $supplier, $sku, $category, $notes
            );
            
            if ($stmt->execute()) {
                $stmt->close();
                $conn->close();
                header("Location: index.php");
                exit;
            } else {
                if ($conn->errno == 1062 && strpos($conn->error, 'sku') !== false) {
                    $error_message = "Error: SKU '{$sku}' already exists. Please use a unique SKU.";
                } else {
                    $error_message = "Error creating item: " . $stmt->error;
                }
            }
            $stmt->close();
        }
    }
}
$conn->close();
?>
<div style="margin-left:220px; padding:32px; font-family:Arial,sans-serif; max-width:700px;">
    <h2 style="font-size: 1.8em; margin-bottom: 24px; color: #333;">Add New Inventory Item </h2>

    <div style="background-color: #f9f9f9; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
        <h3 style="font-size: 1.4em; margin-bottom: 15px; color: #555;">Item Details</h3>
        
        <?php if($error_message): ?>
            <p style="color:red; font-weight: bold; margin-bottom: 20px;"><?= htmlspecialchars($error_message) ?></p>
        <?php endif; ?>
        
        <form action="create.php" method="post" aria-label="Add Item Form" style="display: flex; flex-direction: column; gap: 15px;">
            
            <div>
                <label for="item_name" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Item Name:</label>
                <input type="text" id="item_name" name="item_name" required 
                       style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                       value="<?= htmlspecialchars($_POST['item_name'] ?? '') ?>">
            </div>
            
            <div>
                <label for="description" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Description:</label>
                <textarea id="description" name="description" rows="3"
                          style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box; resize: vertical;"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>
            
            <div>
                <label for="unit_price" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Unit Price:</label>
                <input type="number" step="0.01" id="unit_price" name="unit_price" required 
                       style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                       value="<?= htmlspecialchars($_POST['unit_price'] ?? '') ?>">
            </div>
            
            <div>
                <label for="unit_of_measure" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Unit of Measure:</label>
                <input type="text" id="unit_of_measure" name="unit_of_measure" 
                       style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                       value="<?= htmlspecialchars($_POST['unit_of_measure'] ?? '') ?>">
            </div>
            
            <div>
                <label for="stock_quantity" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Stock Quantity:</label>
                <input type="number" id="stock_quantity" name="stock_quantity" value="0"
                       style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                       value="<?= htmlspecialchars($_POST['stock_quantity'] ?? '') ?>">
            </div>
            
            <div>
                <label for="reorder_level" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Reorder Level:</label>
                <input type="number" id="reorder_level" name="reorder_level" value="0"
                       style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                       value="<?= htmlspecialchars($_POST['reorder_level'] ?? '') ?>">
            </div>
            
            <div>
                <label for="supplier" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Supplier:</label>
                <input type="text" id="supplier" name="supplier" 
                       style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                       value="<?= htmlspecialchars($_POST['supplier'] ?? '') ?>">
            </div>
            
            <div>
                <label for="sku" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">SKU:</label>
                <input type="text" id="sku" name="sku" 
                       style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                       value="<?= htmlspecialchars($_POST['sku'] ?? '') ?>">
            </div>
            
            <div>
                <label for="category" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Category:</label>
                <input type="text" id="category" name="category" 
                       style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                       value="<?= htmlspecialchars($_POST['category'] ?? '') ?>">
            </div>
            
            <div>
                <label for="notes" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Notes:</label>
                <textarea id="notes" name="notes" rows="4"
                          style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box; resize: vertical;"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
            </div>
            
            <div style="display: flex; gap: 15px; margin-top: 10px;">
                <button type="submit" style="background-color: #28a745; color: white; padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; transition: background-color 0.3s ease;">Create Item</button>
                <a href="index.php" style="background-color: #6c757d; color: white; padding: 12px 25px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Cancel</a>
            </div>
        </form>
    </div>
</div>
