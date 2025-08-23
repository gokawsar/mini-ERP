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
        // Only validate email format if it's not empty
        $error_message = "Please enter a valid email address, or leave it empty.";
    } else {
        $stmt = $conn->prepare("INSERT INTO clients (client_name, contact_person, email, phone_number, address, city, state, zip_code, tax_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            $error_message = "Failed to prepare SQL statement: " . $conn->error;
        } else {
            $stmt->bind_param("ssssssssss",
                $client_name, $contact_person, $email, $phone_number,
                $address, $city, $state, $zip_code, $tax_id, $notes
            );
            
            if ($stmt->execute()) {
                $stmt->close();
                $conn->close();
                header("Location: index.php"); // Redirect to client list on success
                exit;
            } else {
                // Check for unique constraint violation for email
                if ($conn->errno == 1062 && strpos($conn->error, 'email') !== false) {
                    $error_message = "Error: Email '{$email}' already exists. Please use a unique email.";
                } else {
                    $error_message = "Error creating client: " . $stmt->error;
                }
            }
            $stmt->close();
        }
    }
}
$conn->close();
?>
<div style="margin-left:220px; padding:32px; font-family:Arial,sans-serif; max-width:700px;">
    <h2 style="font-size: 1.8em; margin-bottom: 24px; color: #333;">Add New Client </h2>

    <div style="background-color: #f9f9f9; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
        <h3 style="font-size: 1.4em; margin-bottom: 15px; color: #555;">Client Details</h3>
        
        <?php if($error_message): ?>
            <p style="color:red; font-weight: bold; margin-bottom: 20px;"><?= htmlspecialchars($error_message) ?></p>
        <?php endif; ?>
        
        <form action="create.php" method="post" aria-label="Add Client Form" style="display: flex; flex-direction: column; gap: 15px;">
            
            <div>
                <label for="client_name" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Client Name:</label>
                <input type="text" id="client_name" name="client_name" required 
                       style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                       value="<?= htmlspecialchars($_POST['client_name'] ?? '') ?>">
            </div>
            
            <div>
                <label for="contact_person" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Contact Person:</label>
                <input type="text" id="contact_person" name="contact_person" 
                       style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                       value="<?= htmlspecialchars($_POST['contact_person'] ?? '') ?>">
            </div>
            
            <div>
                <label for="email" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Email:</label>
                <input type="email" id="email" name="email" 
                       style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            
            <div>
                <label for="phone_number" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Phone Number:</label>
                <input type="text" id="phone_number" name="phone_number" 
                       style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                       value="<?= htmlspecialchars($_POST['phone_number'] ?? '') ?>">
            </div>
            
            <div>
                <label for="address" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Address:</label>
                <textarea id="address" name="address" rows="3"
                          style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box; resize: vertical;"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
            </div>
            
            <div>
                <label for="city" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">City:</label>
                <input type="text" id="city" name="city" 
                       style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                       value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
            </div>
            
            <div>
                <label for="state" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">State:</label>
                <input type="text" id="state" name="state" 
                       style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                       value="<?= htmlspecialchars($_POST['state'] ?? '') ?>">
            </div>
            
            <div>
                <label for="zip_code" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Zip Code:</label>
                <input type="text" id="zip_code" name="zip_code" 
                       style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                       value="<?= htmlspecialchars($_POST['zip_code'] ?? '') ?>">
            </div>
            
            <div>
                <label for="tax_id" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Tax ID:</label>
                <input type="text" id="tax_id" name="tax_id" 
                       style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                       value="<?= htmlspecialchars($_POST['tax_id'] ?? '') ?>">
            </div>
            
            <div>
                <label for="notes" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Notes:</label>
                <textarea id="notes" name="notes" rows="4"
                          style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box; resize: vertical;"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
            </div>
            
            <div style="display: flex; gap: 15px; margin-top: 10px;">
                <button type="submit" style="background-color: #28a745; color: white; padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; transition: background-color 0.3s ease;">Create Client</button>
                <a href="index.php" style="background-color: #6c757d; color: white; padding: 12px 25px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Cancel</a>
            </div>
        </form>
    </div>
</div>
