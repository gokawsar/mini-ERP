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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic quotation info
    $client_id = intval($_POST['client_id'] ?? 0);
    $quotation_date = trim($_POST['quotation_date'] ?? '');
    $expiry_date = trim($_POST['expiry_date'] ?? '');
    $discount = floatval($_POST['discount'] ?? 0.00);
    $status = trim($_POST['status'] ?? 'pending');
    $notes = trim($_POST['notes'] ?? '');
    $user_id = $_SESSION['user_id']; // Current logged-in user

    // Items data from form
    $item_ids = $_POST['item_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $unit_prices = $_POST['unit_price'] ?? [];
    $tax_ids = $_POST['tax_id'] ?? [];

    $grand_total_items = 0; // This will be the sum of all item totals before discount
    $processed_items = []; // To store items with calculated subtotals, taxes, and totals

    // Validate main quotation fields
    if (empty($client_id) || empty($quotation_date)) {
        $error_message = "Client and Quotation Date are required.";
    } elseif (count($item_ids) === 0 || (count($item_ids) === 1 && empty($item_ids[0]))) {
        $error_message = "At least one item is required for the quotation.";
    } else {
        foreach ($item_ids as $i => $item_id) {
            $qty = intval($quantities[$i] ?? 0);
            $price = floatval($unit_prices[$i] ?? 0);
            $tax_id_val = !empty($tax_ids[$i]) ? intval($tax_ids[$i]) : null;

            // Basic item validation
            if (empty($item_id) || $qty <= 0 || $price <= 0) {
                $error_message .= "Invalid item details for row " . ($i + 1) . ". Quantity and Unit Price must be positive, and Item must be selected. ";
                continue;
            }

            $subtotal = $qty * $price;
            $tax_rate = 0;
            if ($tax_id_val) {
                // Look up tax rate from pre-fetched taxes
                foreach ($all_taxes as $tax_option) {
                    if ($tax_option['tax_id'] === $tax_id_val) {
                        $tax_rate = floatval($tax_option['tax_rate']);
                        break;
                    }
                }
            }
            $tax_amount = $subtotal * $tax_rate / 100;
            $item_total = $subtotal + $tax_amount;
            $grand_total_items += $item_total;

            $processed_items[] = [
                'item_id' => $item_id,
                'quantity' => $qty,
                'unit_price' => $price,
                'subtotal' => $subtotal,
                'tax_id' => $tax_id_val,
                'tax_amount' => $tax_amount,
                'total' => $item_total
            ];
        }

        // Only proceed if no item-specific errors
        if (empty($error_message)) {
            $final_quotation_total = $grand_total_items - $discount;

            // Start transaction for atomicity
            $conn->begin_transaction();
            try {
                // Insert main quotation
                $stmt = $conn->prepare("INSERT INTO quotations (client_id, quotation_date, expiry_date, total_amount, discount, status, notes, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt) {
                    throw new Exception("Failed to prepare quotation insert statement: " . $conn->error);
                }
                $stmt->bind_param("issddssi", $client_id, $quotation_date, $expiry_date, $final_quotation_total, $discount, $status, $notes, $user_id);
                if (!$stmt->execute()) {
                    throw new Exception("Error creating quotation: " . $stmt->error);
                }
                $quotation_id = $stmt->insert_id;
                $stmt->close();

                // Insert quotation items
                $qi_stmt = $conn->prepare("INSERT INTO quotation_items (quotation_id, item_id, quantity, unit_price, subtotal, tax_id, tax_amount, total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$qi_stmt) {
                    throw new Exception("Failed to prepare quotation item insert statement: " . $conn->error);
                }

                foreach ($processed_items as $item_data) {
                    $qi_stmt->bind_param("iiiddidd", 
                        $quotation_id, 
                        $item_data['item_id'], 
                        $item_data['quantity'], 
                        $item_data['unit_price'], 
                        $item_data['subtotal'], 
                        $item_data['tax_id'], 
                        $item_data['tax_amount'], 
                        $item_data['total']
                    );
                    if (!$qi_stmt->execute()) {
                        throw new Exception("Error inserting quotation item: " . $qi_stmt->error);
                    }
                }
                $qi_stmt->close();

                $conn->commit(); // Commit transaction
                $conn->close();
                header("Location: view.php?id=$quotation_id");
                exit;

            } catch (Exception $e) {
                $conn->rollback(); // Rollback on error
                $error_message = $e->getMessage();
            }
        }
    }
}
$conn->close();
?>
<div style="margin-left:220px; padding:32px; font-family:Arial,sans-serif; max-width:900px;">
    <h2 style="font-size: 1.8em; margin-bottom: 24px; color: #333;">Add New Sales Quotation</h2>

    <div style="background-color: #f9f9f9; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
        <h3 style="font-size: 1.4em; margin-bottom: 15px; color: #555;">Quotation Details</h3>
        
        <?php if($error_message): ?>
            <p style="color:red; font-weight: bold; margin-bottom: 20px;"><?= htmlspecialchars($error_message) ?></p>
        <?php endif; ?>
        
        <form action="create.php" method="post" aria-label="Add Quotation Form" style="display: flex; flex-direction: column; gap: 15px;">
            
            <div style="display: flex; flex-wrap: wrap; gap: 20px;">
                <div style="flex: 1 1 calc(50% - 10px);">
                    <label for="client_id" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Client:</label>
                    <select id="client_id" name="client_id" required 
                            style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;">
                        <option value="">Select Client</option>
                        <?php foreach($all_clients as $c): ?>
                            <option value="<?= htmlspecialchars($c['client_id']) ?>" <?= (($_POST['client_id'] ?? '') == $c['client_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['client_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="flex: 1 1 calc(50% - 10px);">
                    <label for="quotation_date" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Quotation Date:</label>
                    <input type="date" id="quotation_date" name="quotation_date" required 
                           style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                           value="<?= htmlspecialchars($_POST['quotation_date'] ?? date('Y-m-d')) ?>">
                </div>

                <div style="flex: 1 1 calc(50% - 10px);">
                    <label for="expiry_date" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Expiry Date:</label>
                    <input type="date" id="expiry_date" name="expiry_date" 
                           style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                           value="<?= htmlspecialchars($_POST['expiry_date'] ?? '') ?>">
                </div>
                
                <div style="flex: 1 1 calc(50% - 10px);">
                    <label for="discount" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Discount (BDT):</label>
                    <input type="number" step="0.01" id="discount" name="discount" value="<?= htmlspecialchars($_POST['discount'] ?? '0.00') ?>"
                           style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;" oninput="updateGrandTotal()">
                </div>

                <div style="flex: 1 1 calc(50% - 10px);">
                    <label for="status" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Status:</label>
                    <select id="status" name="status"
                            style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;">
                        <option value="pending" <?= (($_POST['status'] ?? 'pending')=='pending')?'selected':'' ?>>Pending</option>
                        <option value="accepted" <?= (($_POST['status'] ?? '')=='accepted')?'selected':'' ?>>Accepted</option>
                        <option value="rejected" <?= (($_POST['status'] ?? '')=='rejected')?'selected':'' ?>>Rejected</option>
                    </select>
                </div>
            </div>

            <div>
                <label for="notes" style="font-weight: bold; color: #555; margin-bottom: 5px; display: block;">Notes:</label>
                <textarea id="notes" name="notes" rows="4"
                          style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box; resize: vertical;"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
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
                    <!-- Initial empty row for new quotations -->
                    <tr class="item-row">
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
                <button type="submit" style="background-color: #28a745; color: white; padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; transition: background-color 0.3s ease;">Create Quotation</button>
                <a href="index.php" style="background-color: #6c757d; color: white; padding: 12px 25px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease;">Cancel</a>
            </div>
        </form>
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
    var taxRate = getTaxRate(selectedTaxId);
    
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
        var itemUnitPrice = getItemUnitPrice(selectedItemId);
        unitPriceInput.value = itemUnitPrice.toFixed(2);
    } else {
        unitPriceInput.value = '0.00';
    }
    updateRowTotal(selectElement);
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
    // Attach event listeners for the initial row
    document.querySelectorAll('#items-table .item-row input[name="quantity[]"], #items-table .item-row input[name="unit_price[]"]').forEach(function(inp) {
        inp.addEventListener('input', function() { updateRowTotal(inp); });
    });
    document.querySelectorAll('#items-table .item-row select[name="tax_id[]"]').forEach(function(sel) {
        sel.addEventListener('change', function() { updateRowTotal(sel); });
    });
    document.querySelectorAll('#items-table .item-row select[name="item_id[]"]').forEach(function(sel) {
        sel.addEventListener('change', function() { updateRowItemDetails(sel); });
    });

    // Initial calculation when page loads
    updateGrandTotal();
});

function addItemRow() {
    var tableBody = document.getElementById('items-table');
    var newRow = document.createElement('tr');
    newRow.classList.add('item-row');
    newRow.innerHTML = `
        <td>
            <select name="item_id[]" required 
                    style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;"
                    onchange="updateRowItemDetails(this)">
                <option value="">Select Item</option>
                ${ALL_ITEMS.map(i => `<option value="${i.item_id}" data-unit-price="${i.unit_price}">${i.item_name}</option>`).join('')}
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
                ${ALL_TAXES.map(t => `<option value="${t.tax_id}" data-rate="${t.tax_rate}">${t.tax_name} (${t.tax_rate}%)</option>`).join('')}
            </select>
        </td>
        <td class="taxamount-cell" style="padding: 12px; border: 1px solid #ddd;">0.00</td>
        <td class="total-cell" style="padding: 12px; border: 1px solid #ddd;">0.00</td>
        <td style="padding: 12px; border: 1px solid #ddd;">
            <button type="button" onclick="removeItemRow(this)" style="background-color: #dc3545; color: white; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-weight: bold;">Remove</button>
        </td>
    `;
    tableBody.appendChild(newRow);
    // Ensure new elements have their event listeners re-attached
    newRow.querySelector('input[name="quantity[]"]').addEventListener('input', function() { updateRowTotal(this); });
    newRow.querySelector('input[name="unit_price[]"]').addEventListener('input', function() { updateRowTotal(this); });
    newRow.querySelector('select[name="tax_id[]"]').addEventListener('change', function() { updateRowTotal(this); });
    newRow.querySelector('select[name="item_id[]"]').addEventListener('change', function() { updateRowItemDetails(this); });
    updateGrandTotal();
}

function removeItemRow(button) {
    var row = button.closest('tr');
    // Ensure at least one row remains
    var tableBody = document.getElementById('items-table');
    if (tableBody.rows.length > 1) {
        row.remove();
        updateGrandTotal();
    } else {
        alert("A quotation must have at least one item.");
    }
}
</script>
