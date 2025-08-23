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

$success = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    
    // Check for file upload errors
    if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error = "File upload error: " . $_FILES['csv_file']['error'];
    } elseif (!is_uploaded_file($file)) {
        $error = "Invalid file upload. Possible attack.";
    } elseif (($handle = fopen($file, "r")) !== FALSE) {
        // Read header row
        $header = fgetcsv($handle);
        $expected = ['item_name', 'description', 'unit_price', 'unit_of_measure', 'stock_quantity', 'reorder_level', 'supplier', 'sku', 'category', 'notes'];
        
        // Trim header values to remove potential whitespace
        $header = array_map('trim', $header);

        if ($header !== $expected) {
            $error = "CSV header does not match expected columns. Expected: " . implode(', ', $expected) . ". Got: " . implode(', ', $header);
        } else {
            $stmt = $conn->prepare("INSERT INTO items (item_name, description, unit_price, unit_of_measure, stock_quantity, reorder_level, supplier, sku, category, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if (!$stmt) {
                $error = "Failed to prepare SQL statement: " . $conn->error;
            } else {
                $imported_count = 0;
                $row_number = 1; // Start from 1 for data rows after header
                while (($data = fgetcsv($handle)) !== FALSE) {
                    $row_number++;
                    // Skip empty rows or rows with insufficient columns
                    if (count($data) < 10 || count(array_filter($data)) === 0) { // array_filter checks if all values are empty
                        continue;
                    }

                    // Trim data values and handle potential NULLs or defaults
                    $item_name = trim($data[0]);
                    $description = trim($data[1]);
                    $unit_price = (float)trim($data[2]);
                    $unit_of_measure = trim($data[3]);
                    $stock_quantity = (int)trim($data[4]);
                    $reorder_level = (int)trim($data[5]);
                    $supplier = trim($data[6]);
                    $sku = trim($data[7]);
                    $category = trim($data[8]);
                    $notes = trim($data[9]);

                    // Basic validation
                    if (empty($item_name) || empty($unit_price) || $unit_price <= 0) {
                        $error .= "Skipped row $row_number due to invalid 'item_name' or 'unit_price'. ";
                        continue;
                    }

                    $stmt->bind_param("ssdsiissss",
                        $item_name, $description, $unit_price, $unit_of_measure,
                        $stock_quantity, $reorder_level, $supplier, $sku, $category, $notes
                    );
                    
                    if ($stmt->execute()) {
                        $imported_count++;
                    } else {
                        $error .= "Error importing row $row_number: " . $stmt->error . " | ";
                    }
                }
                $stmt->close();
                if (empty($error)) { 
                    $success = true;
                    $success_message = "Successfully imported $imported_count items.";
                } else {
                    $error = "Import completed with errors. Imported $imported_count items. Errors: " . $error;
                }
            }
        }
        fclose($handle);
    } else {
        $error = "Failed to open uploaded file. Check file permissions or if file is actually uploaded.";
    }
}
?>
<div style="margin-left:220px; padding:32px; font-family:Arial,sans-serif; max-width:700px;">
    <h2 style="font-size: 1.8em; margin-bottom: 24px; color: #333;">Bulk Import Items from CSV</h2>

    <div style="background-color: #f9f9f9; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
        <h3 style="font-size: 1.4em; margin-bottom: 15px; color: #555;">Upload CSV File</h3>
        
        <?php if($success): ?>
            <p style="color:green; font-weight: bold; margin-bottom: 20px;"><?= htmlspecialchars($success_message ?? "Items imported successfully.") ?></p>
            <a href="index.php" style="background-color: #007bff; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Back to Item List</a>
        <?php else: ?>
            <?php if($error): ?>
                <p style="color:red; font-weight: bold; margin-bottom: 20px;"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <form action="bulk_import.php" method="post" enctype="multipart/form-data" aria-label="Bulk Import Form" style="display: flex; flex-direction: column; gap: 15px;">
                <div>
                    <label for="csv_file" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Select CSV File:</label>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 100%;">
                </div>
                <button type="submit" style="background-color: #28a745; color: white; padding: 10px 18px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; transition: background-color 0.3s ease; align-self: flex-start;">Import Items</button>
            </form>
            <p style="margin-top:25px; color: #666; line-height: 1.5;">
                <strong>Expected CSV Format:</strong><br>
                Ensure your CSV file has the exact header row as specified below, and the data columns are in the correct order.
                <br><br>
                <code style="background-color: #e0eaf1; padding: 8px 12px; border-radius: 4px; font-family: 'Courier New', Courier, monospace; display: block; white-space: pre-wrap; word-wrap: break-word; border: 1px solid #cceeff;">
                item_name,description,unit_price,unit_of_measure,stock_quantity,reorder_level,supplier,sku,category,notes
                </code>
            </p>
            <a href="index.php" style="background-color: #6c757d; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease; margin-top: 20px; display: inline-block;">Back to Item List</a>
        <?php endif; ?>
    </div>
</div>
<?php $conn->close(); ?>
