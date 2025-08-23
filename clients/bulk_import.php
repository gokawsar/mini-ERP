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

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) { // Changed 'csv' to 'csv_file' for consistency with items/bulk_import
    $file = $_FILES['csv_file']['tmp_name'];
    
    // Check for file upload errors
    if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error_message = "File upload error: " . $_FILES['csv_file']['error'];
    } elseif (!is_uploaded_file($file)) {
        $error_message = "Invalid file upload. Possible attack.";
    } elseif (($handle = fopen($file, "r")) !== FALSE) {
        // Read header row
        $header = fgetcsv($handle);
        $expected = ['client_name', 'contact_person', 'email', 'phone_number', 'address', 'city', 'state', 'zip_code', 'tax_id', 'notes'];
        
        // Trim header values to remove potential whitespace
        $header = array_map('trim', $header);

        if ($header !== $expected) {
            $error_message = "CSV header does not match expected columns. Expected: " . implode(', ', $expected) . ". Got: " . implode(', ', $header);
        } else {
            $stmt = $conn->prepare("INSERT INTO clients (client_name, contact_person, email, phone_number, address, city, state, zip_code, tax_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if (!$stmt) {
                $error_message = "Failed to prepare SQL statement: " . $conn->error;
            } else {
                $imported_count = 0;
                $row_number = 1; // Start from 1 for data rows after header
                while (($data = fgetcsv($handle)) !== FALSE) {
                    $row_number++;
                    // Skip empty rows or rows with insufficient columns
                    if (count($data) < 10 || count(array_filter($data, 'strlen')) === 0) { // array_filter with 'strlen' checks if all values are empty
                        continue;
                    }

                    // Trim data values and handle potential NULLs or defaults
                    $client_name = trim($data[0] ?? '');
                    $contact_person = trim($data[1] ?? '');
                    $email = trim($data[2] ?? '');
                    $phone_number = trim($data[3] ?? '');
                    $address = trim($data[4] ?? '');
                    $city = trim($data[5] ?? '');
                    $state = trim($data[6] ?? '');
                    $zip_code = trim($data[7] ?? '');
                    $tax_id = trim($data[8] ?? '');
                    $notes = trim($data[9] ?? '');

                    // Basic validation for required fields
                    if (empty($client_name)) {
                        $error_message .= "Skipped row $row_number: 'client_name' cannot be empty. | ";
                        continue;
                    }
                    // Validate email format if not empty
                    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $error_message .= "Skipped row $row_number: Invalid email format for '{$email}'. | ";
                        continue;
                    }

                    $stmt->bind_param("ssssssssss",
                        $client_name, $contact_person, $email, $phone_number,
                        $address, $city, $state, $zip_code, $tax_id, $notes
                    );
                    
                    if ($stmt->execute()) {
                        $imported_count++;
                    } else {
                       
                        if ($conn->errno == 1062 && strpos($conn->error, 'email') !== false) {
                            $error_message .= "Error importing row $row_number: Email '{$email}' already exists. | ";
                        } else {
                            $error_message .= "Error importing row $row_number: " . $stmt->error . " | ";
                        }
                    }
                }
                $stmt->close();
                if (empty($error_message)) { 
                    $success_message = "Successfully imported $imported_count clients.";
                } else {
                    $error_message = "Import completed with " . ($imported_count > 0 ? "$imported_count clients imported, but " : "") . "errors occurred: " . $error_message;
                }
            }
        }
        fclose($handle);
    } else {
        $error_message = "Failed to open uploaded file. Check file permissions or if file is actually uploaded.";
    }
}
$conn->close();
?>
<div style="margin-left:220px; padding:32px; font-family:Arial,sans-serif; max-width:700px;">
    <h2 style="font-size: 1.8em; margin-bottom: 24px; color: #333;">Bulk Import Clients from CSV </h2>

    <div style="background-color: #f9f9f9; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
        <h3 style="font-size: 1.4em; margin-bottom: 15px; color: #555;">Upload Client CSV File</h3>
        
        <?php if(!empty($success_message)): ?>
            <p style="color:green; font-weight: bold; margin-bottom: 20px;"><?= htmlspecialchars($success_message) ?></p>
            <a href="index.php" style="background-color: #007bff; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Back to Client List</a>
        <?php else: ?>
            <?php if(!empty($error_message)): ?>
                <p style="color:red; font-weight: bold; margin-bottom: 20px;"><?= htmlspecialchars($error_message) ?></p>
            <?php endif; ?>
            <form action="bulk_import.php" method="post" enctype="multipart/form-data" aria-label="Bulk Import Form" style="display: flex; flex-direction: column; gap: 15px;">
                <div>
                    <label for="csv_file" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Select CSV File:</label>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 100%;">
                </div>
                <button type="submit" style="background-color: #28a745; color: white; padding: 10px 18px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; transition: background-color 0.3s ease; align-self: flex-start;">Import Clients</button>
            </form>
            <p style="margin-top:25px; color: #666; line-height: 1.5;">
                <strong>Expected CSV Format:</strong><br>
                Ensure your CSV file has the exact header row as specified below, and the data columns are in the correct order.
                <br><br>
                <code style="background-color: #e0eaf1; padding: 8px 12px; border-radius: 4px; font-family: 'Courier New', Courier, monospace; display: block; white-space: pre-wrap; word-wrap: break-word; border: 1px solid #cceeff;">
                client_name,contact_person,email,phone_number,address,city,state,zip_code,tax_id,notes
                </code>
            </p>
            <a href="index.php" style="background-color: #6c757d; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease; margin-top: 20px; display: inline-block;">Back to Client List</a>
        <?php endif; ?>
    </div>
</div>
