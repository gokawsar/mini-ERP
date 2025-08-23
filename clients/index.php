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

// Fetch all clients from the database
$result = $conn->query("SELECT client_id, client_name, contact_person, email, phone_number FROM clients ORDER BY client_name ASC");

// Check for query errors
if (!$result) {
    die("Error fetching clients: " . $conn->error);
}
?>
<div style="margin-left:220px; padding:32px; font-family:Arial,sans-serif;">
    <h2 style="font-size: 1.8em; margin-bottom: 24px; color: #333;">Client Management</h2>

    <div style="background-color: #f9f9f9; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
        <h3 style="font-size: 1.4em; margin-bottom: 15px; color: #555;">Client Management Actions</h3>
        <p style="margin-bottom: 15px; color: #666;">Perform actions related to your client records.</p>
        <div style="display: flex; flex-wrap: wrap; gap: 15px;">
            <a href="create.php" style="background-color: #28a745; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Add New Client</a>
            <a href="bulk_import.php" style="background-color: #007bff; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Bulk Import Clients</a>
            <!-- Add other client-related actions here if needed -->
        </div>
    </div>

    <h3 style="font-size: 1.4em; margin-bottom: 15px; color: #555;">Current Client List</h3>
    <table aria-label="Clients List" border="1" cellpadding="8" style="width:100%; margin-top:16px; border-collapse: collapse;">
        <thead style="background:#e9e9e9;">
            <tr>
                <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Name</th>
                <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Contact Person</th>
                <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Email</th>
                <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Phone</th>
                <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td style="padding: 12px; border: 1px solid #ddd;"><?= htmlspecialchars($row['client_name']) ?></td>
                    <td style="padding: 12px; border: 1px solid #ddd;"><?= htmlspecialchars($row['contact_person'] ?? 'N/A') ?></td>
                    <td style="padding: 12px; border: 1px solid #ddd;"><?= htmlspecialchars($row['email'] ?? 'N/A') ?></td>
                    <td style="padding: 12px; border: 1px solid #ddd;"><?= htmlspecialchars($row['phone_number'] ?? 'N/A') ?></td>
                    <td style="padding: 12px; border: 1px solid #ddd;">
                        <a href="view.php?id=<?= $row['client_id'] ?>" style="color: #007bff; text-decoration: none; margin-right: 10px;">View</a>
                        <a href="update.php?id=<?= $row['client_id'] ?>" style="color: #28a745; text-decoration: none; margin-right: 10px;">Edit</a>
                        <a href="delete.php?id=<?= $row['client_id'] ?>" onclick="return confirm('Are you sure you want to delete client: <?= htmlspecialchars($row['client_name']) ?>?');" style="color: #dc3545; text-decoration: none;">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="padding: 12px; border: 1px solid #ddd; text-align: center; color: #777;">No clients found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php $conn->close(); ?>
