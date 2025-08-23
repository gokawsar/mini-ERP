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
$error_message = ''; // Initialize error message variable
$success_message = ''; // Initialize success message variable

// Fetch clients, items, taxes for dropdowns
$clients_result = $conn->query("SELECT client_id, client_name FROM clients ORDER BY client_name");
$items_result = $conn->query("SELECT item_id, item_name, unit_price FROM items ORDER BY item_name");
$taxes_result = $conn->query("SELECT tax_id, tax_name, tax_rate FROM taxes ORDER BY tax_name");

// Store dropdown options for JavaScript cloning and re-populating after POST
$all_clients = [];
if ($clients_result) {
    while ($c = $clients_result->fetch_assoc()) {
        $all_clients[] = $c;
    }
    $clients_result->data_seek(0); // Reset pointer for later use if needed
}

$all_items = [];
if ($items_result) {
    while ($i = $items_result->fetch_assoc()) {
        $all_items[] = $i;
    }
    $items_result->data_seek(0);
}

$all_taxes = [];
if ($taxes_result) {
    while ($t = $taxes_result->fetch_assoc()) {
        $all_taxes[] = $t;
    }
    $taxes_result->data_seek(0);
}


// Handle form submission for updating the quotation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize form data
    $client_id = intval($_POST['client_id'] ?? 0);
    $quotation_date = trim($_POST['quotation_date'] ?? '');
    $expiry_date = trim($_POST['expiry_date'] ?? '');
    $discount = floatval($_POST['discount'] ?? 0.00);
    $status = trim($_POST['status'] ?? 'pending');
    $notes = trim($_POST['notes'] ?? '');
    $user_id = $_SESSION['user_id']; // User who created/updated

    $item_ids = $_POST['item_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $unit_prices = $_POST['unit_price'] ?? [];
    $tax_ids = $_POST['tax_id'] ?? [];
    $quotation_item_ids = $_POST['quotation_item_id'] ?? []; // Existing item IDs from form

    $total_quotation_amount = 0;
    $new_qitems_for_db = []; // Array to store processed quotation items for DB insert

    // Validate main quotation fields
    if (empty($client_id) || empty($quotation_date)) {
        $error_message = "Client and Quotation Date are required.";
    } elseif (count($item_ids) === 0) {
        $error_message = "At least one item is required for the quotation.";
    } else {
        foreach ($item_ids as $index => $current_item_id) {
            $qty = intval($quantities[$index] ?? 0);
            $price = floatval($unit_prices[$index] ?? 0);
            $tax_id_val = !empty($tax_ids[$index]) ? intval($tax_ids[$index]) : null;
            
            // Re-calculate based on submitted data
            $subtotal = $qty * $price;
            $tax_rate = 0;
            if ($tax_id_val) {
                // Find tax rate from our pre-fetched taxes data
                foreach ($all_taxes as $tax_option) {
                    if ($tax_option['tax_id'] === $tax_id_val) {
                        $tax_rate = floatval($tax_option['tax_rate']);
                        break;
                    }
                }
            }
            $tax_amount = $subtotal * $tax_rate / 100;
            $item_total = $subtotal + $tax_amount;
            $total_quotation_amount += $item_total;

            $new_qitems_for_db[] = [
                'quotation_item_id' => intval($quotation_item_ids[$index] ?? 0), // Use existing ID if present
                'item_id' => $current_item_id,
                'quantity' => $qty,
                'unit_price' => $price,
                'subtotal' => $subtotal,
                'tax_id' => $tax_id_val,
                'tax_amount' => $tax_amount,
                'total' => $item_total
            ];
        }

        $final_total = $total_quotation_amount - $discount;

        // Start transaction
        $conn->begin_transaction();
        try {
            // Update main quotation
            $stmt_update_quotation = $conn->prepare("UPDATE quotations SET client_id=?, quotation_date=?, expiry_date=?, total_amount=?, discount=?, status=?, notes=?, user_id=? WHERE quotation_id=?");
            if (!$stmt_update_quotation) {
                throw new Exception("Failed to prepare quotation update statement: " . $conn->error);
            }
            $stmt_update_quotation->bind_param("issddssii", $client_id, $quotation_date, $expiry_date, $final_total, $discount, $status, $notes, $user_id, $id);
            if (!$stmt_update_quotation->execute()) {
                throw new Exception("Error updating quotation: " . $stmt_update_quotation->error);
            }
            $stmt_update_quotation->close();

            // Handle quotation items (delete existing, insert new/updated)
            // First, get current item IDs from DB to identify deletions
            $current_db_item_ids_res = $conn->query("SELECT quotation_item_id FROM quotation_items WHERE quotation_id = $id");
            $current_db_item_ids = [];
            if ($current_db_item_ids_res) {
                while($db_item = $current_db_item_ids_res->fetch_assoc()) {
                    $current_db_item_ids[] = $db_item['quotation_item_id'];
                }
            }
            
            $submitted_item_ids = array_column($new_qitems_for_db, 'quotation_item_id');
            $items_to_delete = array_diff($current_db_item_ids, $submitted_item_ids);

            if (!empty($items_to_delete)) {
                $placeholders = implode(',', array_fill(0, count($items_to_delete), '?'));
                $stmt_delete_items = $conn->prepare("DELETE FROM quotation_items WHERE quotation_item_id IN ($placeholders)");
                if (!$stmt_delete_items) {
                    throw new Exception("Failed to prepare item deletion statement: " . $conn->error);
                }
                $types = str_repeat('i', count($items_to_delete));
                $stmt_delete_items->bind_param($types, ...$items_to_delete);
                if (!$stmt_delete_items->execute()) {
                    throw new Exception("Error deleting old quotation items: " . $stmt_delete_items->error);
                }
                $stmt_delete_items->close();
            }

            // Insert/Update new/existing items
            $stmt_insert_item = $conn->prepare("INSERT INTO quotation_items (quotation_id, item_id, quantity, unit_price, subtotal, tax_id, tax_amount, total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_update_item = $conn->prepare("UPDATE quotation_items SET item_id=?, quantity=?, unit_price=?, subtotal=?, tax_id=?, tax_amount=?, total=? WHERE quotation_item_id=? AND quotation_id=?");

            if (!$stmt_insert_item || !$stmt_update_item) {
                throw new Exception("Failed to prepare item insert/update statement: " . $conn->error);
            }

            foreach ($new_qitems_for_db as $q_item) {
                if ($q_item['quotation_item_id'] > 0) { // If it's an existing item, update
                    $stmt_update_item->bind_param("iiddiddii", 
                        $q_item['item_id'], $q_item['quantity'], $q_item['unit_price'], $q_item['subtotal'], 
                        $q_item['tax_id'], $q_item['tax_amount'], $q_item['total'], 
                        $q_item['quotation_item_id'], $id
                    );
                    if (!$stmt_update_item->execute()) {
                        throw new Exception("Error updating quotation item ID {$q_item['quotation_item_id']}: " . $stmt_update_item->error);
                    }
                } else { // If it's a new item, insert
                    $stmt_insert_item->bind_param("iiiddidd", 
                        $id, $q_item['item_id'], $q_item['quantity'], $q_item['unit_price'], 
                        $q_item['subtotal'], $q_item['tax_id'], $q_item['tax_amount'], $q_item['total']
                    );
                    if (!$stmt_insert_item->execute()) {
                        throw new Exception("Error inserting new quotation item: " . $stmt_insert_item->error);
                    }
                }
            }
            $stmt_insert_item->close();
            $stmt_update_item->close();

            $conn->commit();
            $success_message = "Quotation updated successfully!";
            // Redirect to view page after successful update
            $conn->close();
            header("Location: view.php?id=$id");
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}

// Fetch existing quotation data for initial form load or after failed POST
$stmt_select_quotation = $conn->prepare("SELECT * FROM quotations WHERE quotation_id=?");
if ($stmt_select_quotation) {
    $stmt_select_quotation->bind_param("i", $id);
    $stmt_select_quotation->execute();
    $result_quotation = $stmt_select_quotation->get_result();
    $row = $result_quotation->fetch_assoc();
    $stmt_select_quotation->close();
} else {
    $error_message = "Error fetching main quotation data: " . $conn->error;
}

// Fetch quotation items associated with this quotation
if ($row) {
    $stmt_select_qitems = $conn->prepare("SELECT * FROM quotation_items WHERE quotation_id=?");
    if ($stmt_select_qitems) {
        $stmt_select_qitems->bind_param("i", $id);
        $stmt_select_qitems->execute();
        $result_qitems = $stmt_select_qitems->get_result();
        while ($qi = $result_qitems->fetch_assoc()) {
            $qitems_data[] = $qi;
        }
        $stmt_select_qitems->close();
    } else {
        $error_message = "Error fetching quotation items: " . $conn->error;
    }
}

$conn->close();
?>
<div style="margin-left:220px; padding:32px; font-family:Arial,sans-serif; max-width:900px;">
    <h2 style="font-size: 1.8em; margin-bottom: 24px; color: #333;">Edit Sales Quotation</h2>

    <div style="background-color: #f9f9f9; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
        <?php if($row): // Display form only if quotation is found ?>
            <h3 style="font-size: 1.4em; margin-bottom: 15px; color: #555;">Quotation ID: <?= htmlspecialchars($row['quotation_id']) ?></h3>

            <?php if($success_message): ?>
                <p style="color:green; font-weight: bold; margin-bottom: 20px;"><?= htmlspecialchars($success_message) ?></p>
            <?php endif; ?>
            <?php if($error_message): ?>
                <p style="color:red; font-weight: bold; margin-bottom: 20px;"><?= htmlspecialchars($error_message) ?></p>
            <?php endif; ?>
            
            <form action="update.php?id=<?= $id ?>" method="post" aria-label="Edit Quotation Form" style="display: flex; flex-direction: column; gap: 15px;">
                
                <div style="display: flex; flex-wrap: wrap; gap: 20px;">
                    <div style="flex: 1 1 calc(50% - 10px);">
                        <label for="client_id" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Client:</label>
                        <select id="client_id" name="client_id" required 
                                style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;">
                            <option value="">Select Client</option>
                            <?php foreach($all_clients as $c): ?>
                                <option value="<?= htmlspecialchars($c['client_id']) ?>" <?= ($c['client_id']==($row['client_id'] ?? ($_POST['client_id'] ?? '')))?'selected':'' ?>>
                                    <?= htmlspecialchars($c['client_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div style="flex: 1 1 calc(50% - 10px);">
                        <label for="quotation_date" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Quotation Date:</label>
                        <input type="date" id="quotation_date" name="quotation_date" required 
                               style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                               value="<?= htmlspecialchars($_POST['quotation_date'] ?? $row['quotation_date']) ?>">
                    </div>

                    <div style="flex: 1 1 calc(50% - 10px);">
                        <label for="expiry_date" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Expiry Date:</label>
                        <input type="date" id="expiry_date" name="expiry_date" 
                               style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                               value="<?= htmlspecialchars($_POST['expiry_date'] ?? $row['expiry_date']) ?>">
                    </div>
                    
                    <div style="flex: 1 1 calc(50% - 10px);">
                        <label for="discount" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Discount (BDT):</label>
                        <input type="number" step="0.01" id="discount" name="discount" 
                               style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                               value="<?= htmlspecialchars($_POST['discount'] ?? $row['discount']) ?>" oninput="updateGrandTotal()">
                    </div>

                    <div style="flex: 1 1 calc(50% - 10px);">
                        <label for="status" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Status:</label>
                        <select id="status" name="status"
                                style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;">
                            <option value="pending" <?= (($_POST['status'] ?? $row['status'])=='pending')?'selected':'' ?>>Pending</option>
                            <option value="accepted" <?= (($_POST['status'] ?? $row['status'])=='accepted')?'selected':'' ?>>Accepted</option>
                            <option value="rejected" <?= (($_POST['status'] ?? $row['status'])=='rejected')?'selected':'' ?>>Rejected</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="notes" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Notes:</label>
                    <textarea id="notes" name="notes" rows="4"
                              style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box; resize: vertical;"><?= htmlspecialchars($_POST['notes'] ?? $row['notes']) ?></textarea>
                </div>
                
                <h3 style="font-size: 1.4em; margin-top: 20px; margin-bottom: 15px; color: #555;">Quotation Items</h3>
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
                            <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="items-table">
                        <?php if (!empty($qitems_data)): ?>
                            <?php foreach($qitems_data as $qi): ?>
                                <tr data-quotation-item-id="<?= htmlspecialchars($qi['quotation_item_id']) ?>">
                                    <input type="hidden" name="quotation_item_id[]" value="<?= htmlspecialchars($qi['quotation_item_id']) ?>">
                                    <td>
                                        <select name="item_id[]" required 
                                                style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                                                onchange="updateRowItemDetails(this)">
                                            <option value="">Select Item</option>
                                            <?php foreach($all_items as $i): ?>
                                                <option value="<?= htmlspecialchars($i['item_id']) ?>" 
                                                        data-unit-price="<?= htmlspecialchars($i['unit_price']) ?>"
                                                        <?= ($i['item_id']==($qi['item_id'] ?? ''))?'selected':'' ?>>
                                                    <?= htmlspecialchars($i['item_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input type="number" name="quantity[]" min="1" value="<?= htmlspecialchars($qi['quantity']) ?>" required oninput="updateRowTotal(this)"
                                            style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"></td>
                                    <td><input type="number" name="unit_price[]" step="0.01" value="<?= htmlspecialchars($qi['unit_price']) ?>" required oninput="updateRowTotal(this)"
                                            style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"></td>
                                    <td class="subtotal-cell" style="padding: 12px; border: 1px solid #ddd;"><?= number_format($qi['subtotal'],2) ?></td>
                                    <td>
                                        <select name="tax_id[]" onchange="updateRowTotal(this)"
                                                style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;">
                                            <option value="">None</option>
                                            <?php foreach($all_taxes as $t): ?>
                                                <option value="<?= htmlspecialchars($t['tax_id']) ?>" 
                                                        data-rate="<?= htmlspecialchars($t['tax_rate']) ?>" 
                                                        <?= (($qi['tax_id'] ?? '')==$t['tax_id'])?'selected':'' ?>>
                                                    <?= htmlspecialchars($t['tax_name']) ?> (<?= htmlspecialchars($t['tax_rate']) ?>%)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="taxamount-cell" style="padding: 12px; border: 1px solid #ddd;"><?= number_format($qi['tax_amount'],2) ?></td>
                                    <td class="total-cell" style="padding: 12px; border: 1px solid #ddd;"><?= number_format($qi['total'],2) ?></td>
                                    <td style="padding: 12px; border: 1px solid #ddd;">
                                        <button type="button" onclick="removeItemRow(this)" style="background-color: #dc3545; color: white; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-weight: bold;">Remove</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <!-- Empty row template for new items -->
                            <tr data-quotation-item-id="0" class="item-row-template" style="display: none;">
                                <input type="hidden" name="quotation_item_id[]" value="0">
                                <td>
                                    <select name="item_id[]" required 
                                            style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                                            onchange="updateRowItemDetails(this)">
                                        <option value="">Select Item</option>
                                        <?php foreach($all_items as $i): ?>
                                            <option value="<?= htmlspecialchars($i['item_id']) ?>" 
                                                    data-unit-price="<?= htmlspecialchars($i['unit_price']) ?>">
                                                <?= htmlspecialchars($i['item_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="number" name="quantity[]" min="1" value="1" required oninput="updateRowTotal(this)"
                                        style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"></td>
                                <td><input type="number" name="unit_price[]" step="0.01" value="0.00" required oninput="updateRowTotal(this)"
                                        style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"></td>
                                <td class="subtotal-cell" style="padding: 12px; border: 1px solid #ddd;">0.00</td>
                                <td>
                                    <select name="tax_id[]" onchange="updateRowTotal(this)"
                                            style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;">
                                        <option value="">None</option>
                                        <?php foreach($all_taxes as $t): ?>
                                            <option value="<?= htmlspecialchars($t['tax_id']) ?>" 
                                                    data-rate="<?= htmlspecialchars($t['tax_rate']) ?>">
                                                <?= htmlspecialchars($t['tax_name']) ?> (<?= htmlspecialchars($t['tax_rate']) ?>%)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="taxamount-cell" style="padding: 12px; border: 1px solid #ddd;">0.00</td>
                                <td class="total-cell" style="padding: 12px; border: 1px solid #ddd;">0.00</td>
                                <td style="padding: 12px; border: 1px solid #ddd;">
                                    <button type="button" onclick="removeItemRow(this)" style="background-color: #dc3545; color: white; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-weight: bold;">Remove</button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6" style="text-align:right; padding: 12px; border: 1px solid #ddd;"><strong>Gross Total:</strong></td>
                            <td id="gross-total-cell" style="padding: 12px; border: 1px solid #ddd;">0.00</td>
                            <td style="padding: 12px; border: 1px solid #ddd;"></td>
                        </tr>
                        <tr>
                            <td colspan="6" style="text-align:right; padding: 12px; border: 1px solid #ddd;"><strong>Discount:</strong></td>
                            <td id="discount-display-cell" style="padding: 12px; border: 1px solid #ddd;">0.00</td>
                            <td style="padding: 12px; border: 1px solid #ddd;"></td>
                        </tr>
                        <tr>
                            <td colspan="6" style="text-align:right; padding: 12px; border: 1px solid #ddd; background-color: #e9e9e9;"><strong>Grand Total:</strong></td>
                            <td id="grand-total-cell" style="padding: 12px; border: 1px solid #ddd; background-color: #e9e9e9; font-weight: bold;">0.00</td>
                            <td style="padding: 12px; border: 1px solid #ddd; background-color: #e9e9e9;"></td>
                        </tr>
                    </tfoot>
                </table>
                <button type="button" onclick="addItemRow()" style="background-color: #6c757d; color: white; padding: 10px 18px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; align-self: flex-start;">Add Item Row</button>
                
                <div style="display: flex; gap: 15px; margin-top: 20px;">
                    <button type="submit" style="background-color: #28a745; color: white; padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; transition: background-color 0.3s ease;">Update Quotation</button>
                    <a href="view.php?id=<?= $id ?>" style="background-color: #6c757d; color: white; padding: 12px 25px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Cancel</a>
                </div>
            </form>
        <?php else: ?>
            <p style="color: #dc3545; font-weight: bold; font-size: 1.1em;">Sorry, the quotation you are trying to edit was not found. Please check the ID and try again.</p>
            <div style="margin-top: 20px;">
                <a href="index.php" style="background-color: #6c757d; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Back to Quotation List</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// PHP data embedded for JavaScript use
const ALL_ITEMS = <?= json_encode($all_items) ?>;
const ALL_TAXES = <?= json_encode($all_taxes) ?>;

function getTaxRate(taxId) {
    if (!taxId) return 0;
    const tax = ALL_TAXES.find(t => t.tax_id == taxId);
    return tax ? parseFloat(tax.tax_rate) : 0;
}

function getItemUnitPrice(itemId) {
    if (!itemId) return 0;
    const item = ALL_ITEMS.find(i => i.item_id == itemId);
    return item ? parseFloat(item.unit_price) : 0;
}

function updateRowTotal(el) {
    var row = el.closest('tr');
    var qty = parseFloat(row.querySelector('input[name="quantity[]"]').value) || 0;
    var price = parseFloat(row.querySelector('input[name="unit_price[]"]').value) || 0;
    
    var subtotal = qty * price;
    
    var taxSelect = row.querySelector('select[name="tax_id[]"]');
    var selectedTaxId = taxSelect ? parseInt(taxSelect.value) : 0;
    var taxRate = getTaxRate(selectedTaxId); // Use getTaxRate function
    
    var taxAmount = subtotal * taxRate / 100;
    var total = subtotal + taxAmount;

    row.querySelector('.subtotal-cell').textContent = subtotal.toFixed(2);
    row.querySelector('.taxamount-cell').textContent = taxAmount.toFixed(2);
    row.querySelector('.total-cell').textContent = total.toFixed(2);
    updateGrandTotal();
}

function updateRowItemDetails(selectElement) {
    var row = selectElement.closest('tr');
    var selectedItemId = selectElement.value;
    var unitPriceInput = row.querySelector('input[name="unit_price[]"]');

    if (selectedItemId) {
        var itemUnitPrice = getItemUnitPrice(selectedItemId); // Use getItemUnitPrice
        unitPriceInput.value = itemUnitPrice.toFixed(2);
    } else {
        unitPriceInput.value = '0.00';
    }
    updateRowTotal(selectElement); // Recalculate row total after price update
}


function updateGrandTotal() {
    var grossTotal = 0;
    document.querySelectorAll('#items-table .total-cell').forEach(function(cell) {
        var val = parseFloat(cell.textContent) || 0;
        grossTotal += val;
    });

    var discount = parseFloat(document.getElementById('discount').value) || 0;
    var grandTotal = grossTotal - discount;

    document.getElementById('gross-total-cell').textContent = grossTotal.toFixed(2);
    document.getElementById('discount-display-cell').textContent = discount.toFixed(2);
    document.getElementById('grand-total-cell').textContent = grandTotal.toFixed(2);
}

document.addEventListener('DOMContentLoaded', function() {
    // Attach event listeners for existing rows
    document.querySelectorAll('#items-table input[name="quantity[]"], #items-table input[name="unit_price[]"]').forEach(function(inp) {
        inp.addEventListener('input', function() { updateRowTotal(inp); });
    });
    document.querySelectorAll('#items-table select[name="tax_id[]"]').forEach(function(sel) {
        sel.addEventListener('change', function() { updateRowTotal(sel); });
    });
    document.querySelectorAll('#items-table select[name="item_id[]"]').forEach(function(sel) {
        sel.addEventListener('change', function() { updateRowItemDetails(sel); });
    });

    // Initial calculation when page loads
    updateGrandTotal();
});

function addItemRow() {
    var tableBody = document.getElementById('items-table');
    // Check if there's an existing row or use the template
    var templateRow = document.querySelector('.item-row-template');
    var newRow;

    if (templateRow) {
        newRow = templateRow.cloneNode(true);
        newRow.style.display = 'table-row'; // Make it visible
        newRow.classList.remove('item-row-template'); // Remove template class
    } else if (tableBody.rows.length > 0) {
        newRow = tableBody.rows[0].cloneNode(true);
    } else {
        // Fallback if no template and no existing rows (should not happen with the template present)
        console.error("No template row found and no existing rows to clone.");
        return;
    }

    // Reset values for the new row and clear any existing quotation_item_id
    newRow.querySelector('input[name="quotation_item_id[]"]').value = '0';
    newRow.querySelector('select[name="item_id[]"]').value = '';
    newRow.querySelector('input[name="quantity[]"]').value = '1';
    newRow.querySelector('input[name="unit_price[]"]').value = '0.00';
    newRow.querySelector('.subtotal-cell').textContent = '0.00';
    newRow.querySelector('select[name="tax_id[]"]').value = '';
    newRow.querySelector('.taxamount-cell').textContent = '0.00';
    newRow.querySelector('.total-cell').textContent = '0.00';

    // Re-attach event listeners for the new row
    newRow.querySelector('input[name="quantity[]"]').addEventListener('input', function() { updateRowTotal(this); });
    newRow.querySelector('input[name="unit_price[]"]').addEventListener('input', function() { updateRowTotal(this); });
    newRow.querySelector('select[name="tax_id[]"]').addEventListener('change', function() { updateRowTotal(this); });
    newRow.querySelector('select[name="item_id[]"]').addEventListener('change', function() { updateRowItemDetails(this); });

    tableBody.appendChild(newRow);
    updateGrandTotal(); // Update totals with the new row
}

function removeItemRow(button) {
    var row = button.closest('tr');
    row.remove();
    updateGrandTotal(); // Update totals after removing a row
}
</script>
