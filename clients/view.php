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

// Use prepared statements for security
$stmt = $conn->prepare("SELECT * FROM clients WHERE client_id=?");

if ($stmt) {
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
} else {
    // Handle prepare statement error
    echo "<p style='color:red;'>Error preparing statement: " . $conn->error . "</p>";
}

$conn->close();
?>
<div style="margin-left:220px; padding:32px; font-family:Arial,sans-serif; max-width:700px;">
    <h2 style="font-size: 1.8em; margin-bottom: 24px; color: #333;">Client Details</h2>

    <div style="background-color: #f9f9f9; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
        <?php if($row): ?>
            <h3 style="font-size: 1.4em; margin-bottom: 15px; color: #555;"><?= htmlspecialchars($row['client_name']) ?></h3>
            
            <p style="margin-bottom: 10px; line-height: 1.6;"><strong style="color: #333;">Contact Person:</strong> <?= htmlspecialchars($row['contact_person'] ?? 'N/A') ?></p>
            <p style="margin-bottom: 10px; line-height: 1.6;"><strong style="color: #333;">Email:</strong> <?= htmlspecialchars($row['email'] ?? 'N/A') ?></p>
            <p style="margin-bottom: 10px; line-height: 1.6;"><strong style="color: #333;">Phone:</strong> <?= htmlspecialchars($row['phone_number'] ?? 'N/A') ?></p>
            <p style="margin-bottom: 10px; line-height: 1.6;"><strong style="color: #333;">Address:</strong> <?= htmlspecialchars($row['address'] ?? 'N/A') ?></p>
            <p style="margin-bottom: 10px; line-height: 1.6;"><strong style="color: #333;">City:</strong> <?= htmlspecialchars($row['city'] ?? 'N/A') ?></p>
            <p style="margin-bottom: 10px; line-height: 1.6;"><strong style="color: #333;">State:</strong> <?= htmlspecialchars($row['state'] ?? 'N/A') ?></p>
            <p style="margin-bottom: 10px; line-height: 1.6;"><strong style="color: #333;">Zip Code:</strong> <?= htmlspecialchars($row['zip_code'] ?? 'N/A') ?></p>
            <p style="margin-bottom: 10px; line-height: 1.6;"><strong style="color: #333;">Tax ID:</strong> <?= htmlspecialchars($row['tax_id'] ?? 'N/A') ?></p>
            <p style="margin-bottom: 10px; line-height: 1.6;"><strong style="color: #333;">Notes:</strong> <?= htmlspecialchars($row['notes'] ?? 'N/A') ?></p>
            
            <div style="margin-top: 30px; display: flex; gap: 15px;">
                <a href="update.php?id=<?= $row['client_id'] ?>" style="background-color: #007bff; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Edit Client</a>
                <a href="index.php" style="background-color: #6c757d; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Back to Client List</a>
            </div>
        <?php else: ?>
            <p style="color: #dc3545; font-weight: bold; font-size: 1.1em;">Sorry, the client you are looking for was not found. Please check the ID and try again.</p>
            <div style="margin-top: 20px;">
                <a href="index.php" style="background-color: #6c757d; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Back to Client List</a>
            </div>
        <?php endif; ?>
    </div>
</div>
