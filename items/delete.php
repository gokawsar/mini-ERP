<?php
include '../sidebar.php';
$conn = new mysqli('localhost', 'root', '', 'dbms');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$id = intval($_GET['id']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $stmt = $conn->prepare("DELETE FROM items WHERE item_id=?");
  $stmt->bind_param("i", $id);
  if ($stmt->execute()) {
    header("Location: index.php");
    exit;
  } else {
    echo "<p style='color:red;'>Error: " . $stmt->error . "</p>";
  }
  $stmt->close();
}
?>
<div style="margin-left:220px; padding:32px; font-family:Arial,sans-serif; max-width:600px;">
  <h2>Delete Item</h2>
  <p>Are you sure you want to delete this item?</p>
  <form action="delete.php?id=<?= $id ?>" method="post">
    <button type="submit">Yes, Delete</button>
    <a href="index.php" style="margin-left:16px;">Cancel</a>
  </form>
</div>
<?php $conn->close(); ?>
