<?php
// admin/inventory.php - Inventory Management

session_start();
require_once '../config/config.php';

// Check if user is logged in and is admin or staff
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'staff'])) {
    header("Location: ../modules/auth/login.php");
    exit();
}

$conn = getDBConnection();

// Handle add new inventory item
if (isset($_POST['add_item'])) {
    $item_name = filter_input(INPUT_POST, 'item_name', FILTER_SANITIZE_STRING);
    $item_type = $_POST['item_type'];
    $quantity = $_POST['quantity'];
    $unit_price = $_POST['unit_price'];
    $supplier_id = $_POST['supplier_id'];
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $condition_status = $_POST['condition_status'];
    
    $insert_sql = "INSERT INTO inventory (item_name, item_type, quantity, unit_price, supplier_id, 
                                        description, condition_status) 
                   VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("ssidiss", $item_name, $item_type, $quantity, $unit_price, $supplier_id, 
                      $description, $condition_status);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Inventory item added successfully!";
    } else {
        $_SESSION['error_message'] = "Error adding inventory item.";
    }
}

// Handle update inventory item
if (isset($_POST['update_item'])) {
    $inventory_id = $_POST['inventory_id'];
    $item_name = filter_input(INPUT_POST, 'item_name', FILTER_SANITIZE_STRING);
    $item_type = $_POST['item_type'];
    $quantity = $_POST['quantity'];
    $unit_price = $_POST['unit_price'];
    $supplier_id = $_POST['supplier_id'];
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $condition_status = $_POST['condition_status'];
    $last_maintenance_date = $_POST['last_maintenance_date'];
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    
    $update_sql = "UPDATE inventory SET item_name = ?, item_type = ?, quantity = ?, unit_price = ?, 
                   supplier_id = ?, description = ?, condition_status = ?, last_maintenance_date = ?, 
                   is_available = ? WHERE inventory_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ssidisssii", $item_name, $item_type, $quantity, $unit_price, $supplier_id, 
                      $description, $condition_status, $last_maintenance_date, $is_available, $inventory_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Inventory item updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating inventory item.";
    }
}

// Handle quantity adjustment
if (isset($_POST['adjust_quantity'])) {
    $inventory_id = $_POST['inventory_id'];
    $adjustment_type = $_POST['adjustment_type'];
    $adjustment_quantity = $_POST['adjustment_quantity'];
    
    // Get current quantity
    $get_quantity = $conn->prepare("SELECT quantity FROM inventory WHERE inventory_id = ?");
    $get_quantity->bind_param("i", $inventory_id);
    $get_quantity->execute();
    $result = $get_quantity->get_result();
    $current = $result->fetch_assoc();
    
    if ($adjustment_type == 'add') {
        $new_quantity = $current['quantity'] + $adjustment_quantity;
    } else {
        $new_quantity = $current['quantity'] - $adjustment_quantity;
        if ($new_quantity < 0) $new_quantity = 0;
    }
    
    $update_quantity = $conn->prepare("UPDATE inventory SET quantity = ? WHERE inventory_id = ?");
    $update_quantity->bind_param("ii", $new_quantity, $inventory_id);
    
    if ($update_quantity->execute()) {
        $_SESSION['success_message'] = "Inventory quantity adjusted successfully!";
    }
}

// Get filter parameters
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_supplier = isset($_GET['supplier']) ? $_GET['supplier'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$query = "SELECT i.*, s.supplier_name 
          FROM inventory i 
          LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id 
          WHERE 1=1";

$params = [];
$types = "";

if ($filter_type) {
    $query .= " AND i.item_type = ?";
    $params[] = $filter_type;
    $types .= "s";
}

if ($filter_supplier) {
    $query .= " AND i.supplier_id = ?";
    $params[] = $filter_supplier;
    $types .= "i";
}

if ($search) {
    $query .= " AND (i.item_name LIKE ? OR i.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$query .= " ORDER BY i.item_name";

if ($types) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $inventory_result = $stmt->get_result();
} else {
    $inventory_result = $conn->query($query);
}

// Get suppliers for dropdown
$suppliers = $conn->query("SELECT supplier_id, supplier_name FROM suppliers WHERE is_active = 1 ORDER BY supplier_name");

// Calculate inventory value
$total_value_query = "SELECT SUM(quantity * unit_price) as total_value FROM inventory WHERE is_available = 1";
$total_value_result = $conn->query($total_value_query);
$total_value = $total_value_result->fetch_assoc()['total_value'] ?? 0;

// Low stock items
$low_stock_query = "SELECT COUNT(*) as low_count FROM inventory WHERE quantity < 5 AND is_available = 1";
$low_stock_result = $conn->query($low_stock_query);
$low_stock_count = $low_stock_result->fetch_assoc()['low_count'];

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
    <title>Inventory Management - Explore Hub</title>
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
        .condition-new { color: #28a745; }
        .condition-good { color: #007bff; }
        .condition-fair { color: #ffc107; }
        .condition-poor { color: #dc3545; }
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
                            <a class="nav-link" href="suppliers.php">
                                <i class="bi bi-truck"></i> Suppliers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="inventory.php">
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
                    <h1 class="h2">Inventory Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                            <i class="bi bi-plus-circle"></i> Add New Item
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
                
                <!-- Inventory Stats -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h5 class="card-title">Total Inventory Value</h5>
                                <h3>LKR <?php echo number_format($total_value, 2); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <h5 class="card-title">Total Items</h5>
                                <h3><?php echo $inventory_result->num_rows; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <h5 class="card-title">Low Stock Items</h5>
                                <h3><?php echo $low_stock_count; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Item name or description" value="<?php echo $search; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="type" class="form-label">Item Type</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">All Types</option>
                                    <option value="equipment" <?php echo $filter_type == 'equipment' ? 'selected' : ''; ?>>Equipment</option>
                                    <option value="vehicle" <?php echo $filter_type == 'vehicle' ? 'selected' : ''; ?>>Vehicle</option>
                                    <option value="other" <?php echo $filter_type == 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="supplier" class="form-label">Supplier</label>
                                <select class="form-select" id="supplier" name="supplier">
                                    <option value="">All Suppliers</option>
                                    <?php 
                                    $suppliers->data_seek(0);
                                    while ($supplier = $suppliers->fetch_assoc()): ?>
                                        <option value="<?php echo $supplier['supplier_id']; ?>" 
                                                <?php echo $filter_supplier == $supplier['supplier_id'] ? 'selected' : ''; ?>>
                                            <?php echo $supplier['supplier_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">Filter</button>
                                    <a href="inventory.php" class="btn btn-secondary">Clear</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Inventory Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Item Name</th>
                                        <th>Type</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Total Value</th>
                                        <th>Supplier</th>
                                        <th>Condition</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $inventory_result->data_seek(0);
                                    while ($item = $inventory_result->fetch_assoc()): 
                                    ?>
                                    <tr>
                                        <td>
                                            <?php echo $item['item_name']; ?>
                                            <?php if ($item['quantity'] < 5): ?>
                                                <span class="badge bg-warning">Low Stock</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo ucfirst($item['item_type']); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td>LKR <?php echo number_format($item['unit_price'], 2); ?></td>
                                        <td>LKR <?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                                        <td><?php echo $item['supplier_name'] ?? 'N/A'; ?></td>
                                        <td>
                                            <span class="condition-<?php echo $item['condition_status']; ?>">
                                                <?php echo ucfirst($item['condition_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($item['is_available']): ?>
                                                <span class="badge bg-success">Available</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Unavailable</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                                    data-bs-target="#viewModal<?php echo $item['inventory_id']; ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" 
                                                    data-bs-target="#editModal<?php echo $item['inventory_id']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-info" data-bs-toggle="modal" 
                                                    data-bs-target="#adjustModal<?php echo $item['inventory_id']; ?>">
                                                <i class="bi bi-arrow-up-down"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    
                                    <!-- View Modal -->
                                    <div class="modal fade" id="viewModal<?php echo $item['inventory_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Item Details - <?php echo $item['item_name']; ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p><strong>Item Type:</strong> <?php echo ucfirst($item['item_type']); ?></p>
                                                    <p><strong>Quantity:</strong> <?php echo $item['quantity']; ?></p>
                                                    <p><strong>Unit Price:</strong> LKR <?php echo number_format($item['unit_price'], 2); ?></p>
                                                    <p><strong>Total Value:</strong> LKR <?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></p>
                                                    <p><strong>Supplier:</strong> <?php echo $item['supplier_name'] ?? 'N/A'; ?></p>
                                                    <p><strong>Condition:</strong> 
                                                        <span class="condition-<?php echo $item['condition_status']; ?>">
                                                            <?php echo ucfirst($item['condition_status']); ?>
                                                        </span>
                                                    </p>
                                                    <?php if ($item['last_maintenance_date']): ?>
                                                    <p><strong>Last Maintenance:</strong> <?php echo date('Y-m-d', strtotime($item['last_maintenance_date'])); ?></p>
                                                    <?php endif; ?>
                                                    <?php if ($item['description']): ?>
                                                    <p><strong>Description:</strong> <?php echo nl2br($item['description']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal<?php echo $item['inventory_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Item</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="inventory_id" value="<?php echo $item['inventory_id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Item Name</label>
                                                            <input type="text" class="form-control" name="item_name" 
                                                                   value="<?php echo $item['item_name']; ?>" required>
                                                        </div>
                                                        
                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Item Type</label>
                                                                <select class="form-select" name="item_type" required>
                                                                    <option value="equipment" <?php echo $item['item_type'] == 'equipment' ? 'selected' : ''; ?>>Equipment</option>
                                                                    <option value="vehicle" <?php echo $item['item_type'] == 'vehicle' ? 'selected' : ''; ?>>Vehicle</option>
                                                                    <option value="other" <?php echo $item['item_type'] == 'other' ? 'selected' : ''; ?>>Other</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Condition</label>
                                                                <select class="form-select" name="condition_status" required>
                                                                    <option value="new" <?php echo $item['condition_status'] == 'new' ? 'selected' : ''; ?>>New</option>
                                                                    <option value="good" <?php echo $item['condition_status'] == 'good' ? 'selected' : ''; ?>>Good</option>
                                                                    <option value="fair" <?php echo $item['condition_status'] == 'fair' ? 'selected' : ''; ?>>Fair</option>
                                                                    <option value="poor" <?php echo $item['condition_status'] == 'poor' ? 'selected' : ''; ?>>Poor</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Quantity</label>
                                                                <input type="number" class="form-control" name="quantity" 
                                                                       value="<?php echo $item['quantity']; ?>" min="0" required>
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Unit Price</label>
                                                                <input type="number" class="form-control" name="unit_price" 
                                                                       value="<?php echo $item['unit_price']; ?>" step="0.01" required>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Supplier</label>
                                                                <select class="form-select" name="supplier_id">
                                                                    <option value="">No Supplier</option>
                                                                    <?php 
                                                                    $suppliers->data_seek(0);
                                                                    while ($supplier = $suppliers->fetch_assoc()): ?>
                                                                        <option value="<?php echo $supplier['supplier_id']; ?>" 
                                                                                <?php echo $item['supplier_id'] == $supplier['supplier_id'] ? 'selected' : ''; ?>>
                                                                            <?php echo $supplier['supplier_name']; ?>
                                                                        </option>
                                                                    <?php endwhile; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Last Maintenance Date</label>
                                                                <input type="date" class="form-control" name="last_maintenance_date" 
                                                                       value="<?php echo $item['last_maintenance_date']; ?>">
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Description</label>
                                                            <textarea class="form-control" name="description" rows="2"><?php echo $item['description']; ?></textarea>
                                                        </div>
                                                        
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="is_available" 
                                                                   id="is_available<?php echo $item['inventory_id']; ?>" 
                                                                   <?php echo $item['is_available'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="is_available<?php echo $item['inventory_id']; ?>">
                                                                Available for Use
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="update_item" class="btn btn-primary">Update Item</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Adjust Quantity Modal -->
                                    <div class="modal fade" id="adjustModal<?php echo $item['inventory_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Adjust Quantity - <?php echo $item['item_name']; ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="inventory_id" value="<?php echo $item['inventory_id']; ?>">
                                                        
                                                        <p><strong>Current Quantity:</strong> <?php echo $item['quantity']; ?></p>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Adjustment Type</label>
                                                            <select class="form-select" name="adjustment_type" required>
                                                                <option value="add">Add to Stock</option>
                                                                <option value="remove">Remove from Stock</option>
                                                            </select>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Quantity</label>
                                                            <input type="number" class="form-control" name="adjustment_quantity" 
                                                                   min="1" required>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="adjust_quantity" class="btn btn-info">Adjust Quantity</button>
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
            </main>
        </div>
    </div>
    
    <!-- Add Item Modal -->
    <div class="modal fade" id="addItemModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Inventory Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Item Name</label>
                            <input type="text" class="form-control" name="item_name" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Item Type</label>
                                <select class="form-select" name="item_type" required>
                                    <option value="">Select Type</option>
                                    <option value="equipment">Equipment</option>
                                    <option value="vehicle">Vehicle</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Condition</label>
                                <select class="form-select" name="condition_status" required>
                                    <option value="new">New</option>
                                    <option value="good">Good</option>
                                    <option value="fair">Fair</option>
                                    <option value="poor">Poor</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Quantity</label>
                                <input type="number" class="form-control" name="quantity" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Unit Price</label>
                                <input type="number" class="form-control" name="unit_price" step="0.01" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Supplier</label>
                            <select class="form-select" name="supplier_id">
                                <option value="">No Supplier</option>
                                <?php 
                                $suppliers->data_seek(0);
                                while ($supplier = $suppliers->fetch_assoc()): ?>
                                    <option value="<?php echo $supplier['supplier_id']; ?>">
                                        <?php echo $supplier['supplier_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_item" class="btn btn-primary">Add Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>