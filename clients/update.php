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
$row = null; // Initialize $row to null
$error_message = ''; // Initialize error message variable
$success_message = ''; // Initialize success message variable

// Handle form submission for updating the client
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize form data
    $client_name = trim($_POST['client_name'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zip_code = trim($_POST['zip_code'] ?? '');
    $tax_id = trim($_POST['tax_id'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    // Basic server-side validation
    if (empty($client_name)) {
        $error_message = "Client Name cannot be empty.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($email)) {
        $error_message = "Please enter a valid email address, or leave it empty.";
    } else {
        $stmt = $conn->prepare("UPDATE clients SET client_name=?, contact_person=?, email=?, phone_number=?, address=?, city=?, state=?, zip_code=?, tax_id=?, notes=? WHERE client_id=?");
        
        if (!$stmt) {
            $error_message = "Failed to prepare SQL statement: " . $conn->error;
        } else {
            $stmt->bind_param("ssssssssssi",
                $client_name, $contact_person, $email, $phone_number,
                $address, $city, $state, $zip_code, $tax_id, $notes, $id
            );
            
            if ($stmt->execute()) {
                $success_message = "Client updated successfully!";
                // After successful update, we don't redirect immediately so the user sees the message
                // The form will be re-populated with the newly updated data from the database below
            } else {
                // Check for unique constraint violation for email
                if ($conn->errno == 1062 && strpos($conn->error, 'email') !== false) {
                    $error_message = "Error: Email '{$email}' already exists for another client. Please use a unique email.";
                } else {
                    $error_message = "Error updating client: " . $stmt->error;
                }
            }
            $stmt->close(); // Close statement for the UPDATE operation
        }
    }
}

// Fetch existing client data (either initial load or after failed/successful update attempt)
// This second prepared statement ensures we always display the correct/latest data
// or the data as it was before a failed update.
$stmt_select = $conn->prepare("SELECT * FROM clients WHERE client_id=?");
if ($stmt_select) {
    $stmt_select->bind_param("i", $id);
    $stmt_select->execute();
    $result = $stmt_select->get_result();
    $row = $result->fetch_assoc();
    $stmt_select->close();
} else {
    // This error would be critical and likely mean connection issues or schema problems
    $error_message = "Error fetching client data for display: " . $conn->error;
}

$conn->close();
?>
<div style="margin-left:220px; padding:32px; font-family:Arial,sans-serif; max-width:700px;">
    <h2 style="font-size: 1.8em; margin-bottom: 24px; color: #333;">Edit Client Details</h2>

    <div style="background-color: #f9f9f9; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
        <?php if($row): // Display form only if client is found ?>
            <h3 style="font-size: 1.4em; margin-bottom: 15px; color: #555;"><?= htmlspecialchars($row['client_name']) ?></h3>

            <?php if($success_message): ?>
                <p style="color:green; font-weight: bold; margin-bottom: 20px;"><?= htmlspecialchars($success_message) ?></p>
            <?php endif; ?>
            <?php if($error_message): ?>
                <p style="color:red; font-weight: bold; margin-bottom: 20px;"><?= htmlspecialchars($error_message) ?></p>
            <?php endif; ?>
            
            <form action="update.php?id=<?= $id ?>" method="post" aria-label="Edit Client Form" style="display: flex; flex-direction: column; gap: 15px;">
                
                <div>
                    <label for="client_name" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Client Name:</label>
                    <input type="text" id="client_name" name="client_name" required 
                           style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                           value="<?= htmlspecialchars($_POST['client_name'] ?? $row['client_name']) ?>">
                </div>
                
                <div>
                    <label for="contact_person" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Contact Person:</label>
                    <input type="text" id="contact_person" name="contact_person" 
                           style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                           value="<?= htmlspecialchars($_POST['contact_person'] ?? $row['contact_person']) ?>">
                </div>
                
                <div>
                    <label for="email" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Email:</label>
                    <input type="email" id="email" name="email" 
                           style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                           value="<?= htmlspecialchars($_POST['email'] ?? $row['email']) ?>">
                </div>
                
                <div>
                    <label for="phone_number" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Phone Number:</label>
                    <input type="text" id="phone_number" name="phone_number" 
                           style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                           value="<?= htmlspecialchars($_POST['phone_number'] ?? $row['phone_number']) ?>">
                </div>
                
                <div>
                    <label for="address" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Address:</label>
                    <textarea id="address" name="address" rows="3"
                              style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box; resize: vertical;"><?= htmlspecialchars($_POST['address'] ?? $row['address']) ?></textarea>
                </div>
                
                <div>
                    <label for="city" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">City:</label>
                    <input type="text" id="city" name="city" 
                           style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                           value="<?= htmlspecialchars($_POST['city'] ?? $row['city']) ?>">
                </div>
                
                <div>
                    <label for="state" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">State:</label>
                    <input type="text" id="state" name="state" 
                           style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                           value="<?= htmlspecialchars($_POST['state'] ?? $row['state']) ?>">
                </div>
                
                <div>
                    <label for="zip_code" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Zip Code:</label>
                    <input type="text" id="zip_code" name="zip_code" 
                           style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                           value="<?= htmlspecialchars($_POST['zip_code'] ?? $row['zip_code']) ?>">
                </div>
                
                <div>
                    <label for="tax_id" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Tax ID:</label>
                    <input type="text" id="tax_id" name="tax_id" 
                           style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                           value="<?= htmlspecialchars($_POST['tax_id'] ?? $row['tax_id']) ?>">
                </div>
                
                <div>
                    <label for="notes" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Notes:</label>
                    <textarea id="notes" name="notes" rows="4"
                              style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box; resize: vertical;"><?= htmlspecialchars($_POST['notes'] ?? $row['notes']) ?></textarea>
                </div>
                
                <div style="display: flex; gap: 15px; margin-top: 10px;">
                    <button type="submit" style="background-color: #28a745; color: white; padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; transition: background-color 0.3s ease;">Update Client</button>
                    <a href="view.php?id=<?= $id ?>" style="background-color: #6c757d; color: white; padding: 12px 25px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Cancel</a>
                </div>
            </form>
        <?php else: ?>
            <p style="color: #dc3545; font-weight: bold; font-size: 1.1em;">Sorry, the client you are trying to edit was not found. Please check the ID and try again.</p>
            <div style="margin-top: 20px;">
                <a href="index.php" style="background-color: #6c757d; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Back to Client List</a>
            </div>
        <?php endif; ?>
    </div>
</div>
