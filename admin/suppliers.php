<?php
// admin/suppliers.php - Supplier Management

session_start();
require_once '../config/config.php';

// Check if user is logged in and is admin or staff
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'staff'])) {
    header("Location: ../modules/auth/login.php");
    exit();
}

$conn = getDBConnection();

// Handle add new supplier
if (isset($_POST['add_supplier'])) {
    $supplier_name = filter_input(INPUT_POST, 'supplier_name', FILTER_SANITIZE_STRING);
    $supplier_type = $_POST['supplier_type'];
    $contact_person = filter_input(INPUT_POST, 'contact_person', FILTER_SANITIZE_STRING);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
    $payment_terms = filter_input(INPUT_POST, 'payment_terms', FILTER_SANITIZE_STRING);
    
    $insert_sql = "INSERT INTO suppliers (supplier_name, supplier_type, contact_person, phone, email, address, payment_terms) 
                   VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("sssssss", $supplier_name, $supplier_type, $contact_person, $phone, $email, $address, $payment_terms);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Supplier added successfully!";
    } else {
        $_SESSION['error_message'] = "Error adding supplier.";
    }
}

// Handle update supplier
if (isset($_POST['update_supplier'])) {
    $supplier_id = $_POST['supplier_id'];
    $supplier_name = filter_input(INPUT_POST, 'supplier_name', FILTER_SANITIZE_STRING);
    $supplier_type = $_POST['supplier_type'];
    $contact_person = filter_input(INPUT_POST, 'contact_person', FILTER_SANITIZE_STRING);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
    $payment_terms = filter_input(INPUT_POST, 'payment_terms', FILTER_SANITIZE_STRING);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $update_sql = "UPDATE suppliers SET supplier_name = ?, supplier_type = ?, contact_person = ?, 
                   phone = ?, email = ?, address = ?, payment_terms = ?, is_active = ? 
                   WHERE supplier_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sssssssii", $supplier_name, $supplier_type, $contact_person, 
                      $phone, $email, $address, $payment_terms, $is_active, $supplier_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Supplier updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating supplier.";
    }
}

// Handle supplier payment
if (isset($_POST['add_payment'])) {
    $supplier_id = $_POST['supplier_id'];
    $payment_date = $_POST['payment_date'];
    $amount = $_POST['amount'];
    $payment_method = $_POST['payment_method'];
    $reference_number = filter_input(INPUT_POST, 'reference_number', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    
    $insert_payment = "INSERT INTO supplier_payments (supplier_id, payment_date, amount, payment_method, 
                      reference_number, description, processed_by) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_payment);
    $stmt->bind_param("isdsssi", $supplier_id, $payment_date, $amount, $payment_method, 
                      $reference_number, $description, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Payment recorded successfully!";
    } else {
        $_SESSION['error_message'] = "Error recording payment.";
    }
}

// Get suppliers with total payments
$suppliers_query = "SELECT s.*, 
                   COALESCE(SUM(sp.amount), 0) as total_payments,
                   COUNT(DISTINCT i.inventory_id) as item_count
                   FROM suppliers s
                   LEFT JOIN supplier_payments sp ON s.supplier_id = sp.supplier_id
                   LEFT JOIN inventory i ON s.supplier_id = i.supplier_id
                   GROUP BY s.supplier_id
                   ORDER BY s.supplier_name";
$suppliers_result = $conn->query($suppliers_query);

// Get recent payments
$recent_payments_query = "SELECT sp.*, s.supplier_name 
                         FROM supplier_payments sp
                         JOIN suppliers s ON sp.supplier_id = s.supplier_id
                         ORDER BY sp.payment_date DESC
                         LIMIT 10";
$recent_payments = $conn->query($recent_payments_query);

$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Management - Explore Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
        }
        .sidebar .nav-link {
            color: #fff;
            padding: 10px 20px;
        }
        .sidebar .nav-link:hover {
            background-color: #495057;
        }
        .sidebar .nav-link.active {
            background-color: #007bff;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-md-block sidebar">
                <div class="position-sticky pt-3">
                    <div class="text-center text-white mb-4">
                        <h4>Explore Hub</h4>
                        <p class="mb-0"><?php echo ucfirst($_SESSION['user_type']); ?> Panel</p>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bookings.php">
                                <i class="bi bi-calendar-check"></i> Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="tours.php">
                                <i class="bi bi-map"></i> Tours
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="hotels.php">
                                <i class="bi bi-building"></i> Hotels
                            </a>
                        </li>
                        <?php if ($_SESSION['user_type'] == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="customers.php">
                                <i class="bi bi-people"></i> Customers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="staff.php">
                                <i class="bi bi-person-badge"></i> Staff
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="suppliers.php">
                                <i class="bi bi-truck"></i> Suppliers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="inventory.php">
                                <i class="bi bi-box-seam"></i> Inventory
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="bi bi-graph-up"></i> Reports
                            </a>
                        </li>
                        <hr class="text-white">
                        <li class="nav-item">
                            <a class="nav-link" href="../modules/auth/logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <!-- Main content -->
            <main class="col-md-10 ms-sm-auto px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Supplier Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                            <i class="bi bi-plus-circle"></i> Add New Supplier
                        </button>
                    </div>
                </div>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Suppliers Table -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Suppliers</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Supplier Name</th>
                                        <th>Type</th>
                                        <th>Contact</th>
                                        <th>Items</th>
                                        <th>Total Payments</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($supplier = $suppliers_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php echo $supplier['supplier_name']; ?><br>
                                            <small class="text-muted"><?php echo $supplier['contact_person']; ?></small>
                                        </td>
                                        <td><?php echo ucfirst($supplier['supplier_type']); ?></td>
                                        <td>
                                            <?php echo $supplier['email']; ?><br>
                                            <small><?php echo $supplier['phone']; ?></small>
                                        </td>
                                        <td><?php echo $supplier['item_count']; ?></td>
                                        <td>LKR <?php echo number_format($supplier['total_payments'], 2); ?></td>
                                        <td>
                                            <?php if ($supplier['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                                    data-bs-target="#viewModal<?php echo $supplier['supplier_id']; ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" 
                                                    data-bs-target="#editModal<?php echo $supplier['supplier_id']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-success" data-bs-toggle="modal" 
                                                    data-bs-target="#paymentModal<?php echo $supplier['supplier_id']; ?>">
                                                <i class="bi bi-cash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    
                                    <!-- View Modal -->
                                    <div class="modal fade" id="viewModal<?php echo $supplier['supplier_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Supplier Details - <?php echo $supplier['supplier_name']; ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <p><strong>Supplier Type:</strong> <?php echo ucfirst($supplier['supplier_type']); ?></p>
                                                            <p><strong>Contact Person:</strong> <?php echo $supplier['contact_person']; ?></p>
                                                            <p><strong>Email:</strong> <?php echo $supplier['email']; ?></p>
                                                            <p><strong>Phone:</strong> <?php echo $supplier['phone']; ?></p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <p><strong>Address:</strong> <?php echo nl2br($supplier['address']); ?></p>
                                                            <p><strong>Payment Terms:</strong> <?php echo $supplier['payment_terms']; ?></p>
                                                            <p><strong>Total Items:</strong> <?php echo $supplier['item_count']; ?></p>
                                                            <p><strong>Total Payments:</strong> LKR <?php echo number_format($supplier['total_payments'], 2); ?></p>
                                                        </div>
                                                    </div>
                                                    <hr>
                                                    <p><strong>Created:</strong> <?php echo date('Y-m-d H:i', strtotime($supplier['created_at'])); ?></p>
                                                    <p><strong>Status:</strong> 
                                                        <?php if ($supplier['is_active']): ?>
                                                            <span class="badge bg-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Inactive</span>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal<?php echo $supplier['supplier_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Supplier</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="supplier_id" value="<?php echo $supplier['supplier_id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Supplier Name</label>
                                                            <input type="text" class="form-control" name="supplier_name" 
                                                                   value="<?php echo $supplier['supplier_name']; ?>" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Supplier Type</label>
                                                            <select class="form-select" name="supplier_type" required>
                                                                <option value="transport" <?php echo $supplier['supplier_type'] == 'transport' ? 'selected' : ''; ?>>Transport</option>
                                                                <option value="accommodation" <?php echo $supplier['supplier_type'] == 'accommodation' ? 'selected' : ''; ?>>Accommodation</option>
                                                                <option value="equipment" <?php echo $supplier['supplier_type'] == 'equipment' ? 'selected' : ''; ?>>Equipment</option>
                                                                <option value="food" <?php echo $supplier['supplier_type'] == 'food' ? 'selected' : ''; ?>>Food</option>
                                                                <option value="other" <?php echo $supplier['supplier_type'] == 'other' ? 'selected' : ''; ?>>Other</option>
                                                            </select>
                                                        </div>
                                                        
                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Contact Person</label>
                                                                <input type="text" class="form-control" name="contact_person" 
                                                                       value="<?php echo $supplier['contact_person']; ?>">
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Phone</label>
                                                                <input type="tel" class="form-control" name="phone" 
                                                                       value="<?php echo $supplier['phone']; ?>">
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Email</label>
                                                            <input type="email" class="form-control" name="email" 
                                                                   value="<?php echo $supplier['email']; ?>">
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Address</label>
                                                            <textarea class="form-control" name="address" rows="2"><?php echo $supplier['address']; ?></textarea>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Payment Terms</label>
                                                            <input type="text" class="form-control" name="payment_terms" 
                                                                   value="<?php echo $supplier['payment_terms']; ?>">
                                                        </div>
                                                        
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="is_active" 
                                                                   id="is_active<?php echo $supplier['supplier_id']; ?>" 
                                                                   <?php echo $supplier['is_active'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="is_active<?php echo $supplier['supplier_id']; ?>">
                                                                Active Supplier
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="update_supplier" class="btn btn-primary">Update Supplier</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Payment Modal -->
                                    <div class="modal fade" id="paymentModal<?php echo $supplier['supplier_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Record Payment - <?php echo $supplier['supplier_name']; ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="supplier_id" value="<?php echo $supplier['supplier_id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Payment Date</label>
                                                            <input type="date" class="form-control" name="payment_date" 
                                                                   value="<?php echo date('Y-m-d'); ?>" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Amount</label>
                                                            <input type="number" class="form-control" name="amount" 
                                                                   step="0.01" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Payment Method</label>
                                                            <select class="form-select" name="payment_method" required>
                                                                <option value="cash">Cash</option>
                                                                <option value="cheque">Cheque</option>
                                                                <option value="bank_transfer">Bank Transfer</option>
                                                            </select>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Reference Number</label>
                                                            <input type="text" class="form-control" name="reference_number">
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Description</label>
                                                            <textarea class="form-control" name="description" rows="2"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="add_payment" class="btn btn-success">Record Payment</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Payments -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Supplier Payments</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Supplier</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Reference</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($payment = $recent_payments->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?></td>
                                        <td><?php echo $payment['supplier_name']; ?></td>
                                        <td>LKR <?php echo number_format($payment['amount'], 2); ?></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                        <td><?php echo $payment['reference_number']; ?></td>
                                        <td><?php echo $payment['description']; ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Add Supplier Modal -->
    <div class="modal fade" id="addSupplierModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Supplier</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Supplier Name</label>
                            <input type="text" class="form-control" name="supplier_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Supplier Type</label>
                            <select class="form-select" name="supplier_type" required>
                                <option value="">Select Type</option>
                                <option value="transport">Transport</option>
                                <option value="accommodation">Accommodation</option>
                                <option value="equipment">Equipment</option>
                                <option value="food">Food</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Person</label>
                                <input type="text" class="form-control" name="contact_person">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Terms</label>
                            <input type="text" class="form-control" name="payment_terms" 
                                   placeholder="e.g., Net 30 days">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_supplier" class="btn btn-primary">Add Supplier</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>