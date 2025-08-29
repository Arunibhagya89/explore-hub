$recent_adjustments = $conn->query($recent_adjustments_query);

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
        .stock-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .stock-normal { background-color: #28a745; }
        .stock-low { background-color: #ffc107; }
        .stock-out { background-color: #dc3545; }
        .stock-over { background-color: #17a2b8; }
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            height: 100%;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .adjustment-log {
            font-size: 0.875rem;
            padding: 10px;
            border-left: 3px solid #007bff;
            margin-bottom: 10px;
            background: #f8f9fa;
        }
        .table-actions {
            white-space: nowrap;
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
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                                <i class="bi bi-plus-circle"></i> Add Item
                            </button>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkImportModal">
                                <i class="bi bi-upload"></i> Bulk Import
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="exportInventory()">
                                <i class="bi bi-download"></i> Export
                            </button>
                        </div>
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
                    <div class="col-md-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Total Items</h6>
                                    <h3 class="mb-0"><?php echo number_format($total_items); ?></h3>
                                </div>
                                <i class="bi bi-box-seam" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Total Value</h6>
                                    <h4 class="mb-0">LKR <?php echo number_format($total_value, 2); ?></h4>
                                </div>
                                <i class="bi bi-currency-dollar" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-warning text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Low Stock Items</h6>
                                    <h3 class="mb-0"><?php echo number_format($low_stock_count); ?></h3>
                                </div>
                                <i class="bi bi-exclamation-triangle" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-danger text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Out of Stock</h6>
                                    <h3 class="mb-0"><?php echo number_format($out_of_stock_count); ?></h3>
                                </div>
                                <i class="bi bi-x-octagon" style="font-size: 2.5rem; opacity: 0.5;"></i>
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
                                       placeholder="Item name, description, location" value="<?php echo $search; ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="type" class="form-label">Item Type</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">All Types</option>
                                    <option value="equipment" <?php echo $filter_type == 'equipment' ? 'selected' : ''; ?>>Equipment</option>
                                    <option value="vehicle" <?php echo $filter_type == 'vehicle' ? 'selected' : ''; ?>>Vehicle</option>
                                    <option value="other" <?php echo $filter_type == 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-2">
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
                            <div class="col-md-2">
                                <label for="status" class="form-label">Stock Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="normal" <?php echo $filter_status == 'normal' ? 'selected' : ''; ?>>Normal Stock</option>
                                    <option value="low_stock" <?php echo $filter_status == 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                                    <option value="out_of_stock" <?php echo $filter_status == 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                                    <option value="overstock" <?php echo $filter_status == 'overstock' ? 'selected' : ''; ?>>Overstock</option>
                                    <option value="available" <?php echo $filter_status == 'available' ? 'selected' : ''; ?>>Available</option>
                                    <option value="unavailable" <?php echo $filter_status == 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
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
                
                <div class="row">
                    <!-- Inventory Table -->
                    <div class="col-md-9">
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Status</th>
                                                <th>Item Name</th>
                                                <th>Type</th>
                                                <th>Location</th>
                                                <th>Stock</th>
                                                <th>Unit Price</th>
                                                <th>Total Value</th>
                                                <th>Supplier</th>
                                                <th>Condition</th>
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
                                                    <span class="stock-indicator stock-<?php echo str_replace('_', '-', $item['stock_status']); ?>"></span>
                                                </td>
                                                <td>
                                                    <strong><?php echo $item['item_name']; ?></strong>
                                                    <?php if (!$item['is_available']): ?>
                                                        <span class="badge bg-secondary">Unavailable</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo ucfirst($item['item_type']); ?></td>
                                                <td><?php echo $item['location'] ?: '-'; ?></td>
                                                <td>
                                                    <strong><?php echo $item['quantity']; ?></strong>
                                                    <?php if ($item['min_stock_level']): ?>
                                                        <br><small class="text-muted">Min: <?php echo $item['min_stock_level']; ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>LKR <?php echo number_format($item['unit_price'], 2); ?></td>
                                                <td>LKR <?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                                                <td><?php echo $item['supplier_name'] ?? 'N/A'; ?></td>
                                                <td>
                                                    <span class="condition-<?php echo $item['condition_status']; ?>">
                                                        <?php echo ucfirst($item['condition_status']); ?>
                                                    </span>
                                                </td>
                                                <td class="table-actions">
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
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Item Details - <?php echo $item['item_name']; ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <p><strong>Item Type:</strong> <?php echo ucfirst($item['item_type']); ?></p>
                                                                    <p><strong>Current Stock:</strong> <?php echo $item['quantity']; ?></p>
                                                                    <p><strong>Stock Levels:</strong> 
                                                                        Min: <?php echo $item['min_stock_level'] ?? '5'; ?> | 
                                                                        Max: <?php echo $item['max_stock_level'] ?? '100'; ?>
                                                                    </p>
                                                                    <p><strong>Unit Price:</strong> LKR <?php echo number_format($item['unit_price'], 2); ?></p>
                                                                    <p><strong>Total Value:</strong> LKR <?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></p>
                                                                    <p><strong>Location:</strong> <?php echo $item['location'] ?: 'Not specified'; ?></p>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <p><strong>Supplier:</strong> <?php echo $item['supplier_name'] ?? 'N/A'; ?></p>
                                                                    <p><strong>Condition:</strong> 
                                                                        <span class="condition-<?php echo $item['condition_status']; ?>">
                                                                            <?php echo ucfirst($item['condition_status']); ?>
                                                                        </span>
                                                                    </p>
                                                                    <?php if ($item['last_maintenance_date']): ?>
                                                                    <p><strong>Last Maintenance:</strong> <?php echo date('Y-m-d', strtotime($item['last_maintenance_date'])); ?></p>
                                                                    <?php endif; ?>
                                                                    <p><strong>Status:</strong> 
                                                                        <?php if ($item['is_available']): ?>
                                                                            <span class="badge bg-success">Available</span>
                                                                        <?php else: ?>
                                                                            <span class="badge bg-danger">Unavailable</span>
                                                                        <?php endif; ?>
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <?php if ($item['description']): ?>
                                                            <hr>
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
                                                                    <div class="col-md-4 mb-3">
                                                                        <label class="form-label">Quantity</label>
                                                                        <input type="number" class="form-control" name="quantity" 
                                                                               value="<?php echo $item['quantity']; ?>" min="0" required>
                                                                    </div>
                                                                    <div class="col-md-4 mb-3">
                                                                        <label class="form-label">Min Stock Level</label>
                                                                        <input type="number" class="form-control" name="min_stock_level" 
                                                                               value="<?php echo $item['min_stock_level'] ?? 5; ?>" min="0">
                                                                    </div>
                                                                    <div class="col-md-4 mb-3">
                                                                        <label class="form-label">Max Stock Level</label>
                                                                        <input type="number" class="form-control" name="max_stock_level" 
                                                                               value="<?php echo $item['max_stock_level'] ?? 100; ?>" min="0">
                                                                    </div>
                                                                </div>
                                                                
                                                                <div class="row">
                                                                    <div class="col-md-6 mb-3">
                                                                        <label class="form-label">Unit Price</label>
                                                                        <input type="number" class="form-control" name="unit_price" 
                                                                               value="<?php echo $item['unit_price']; ?>" step="0.01" required>
                                                                    </div>
                                                                    <div class="col-md-6 mb-3">
                                                                        <label class="form-label">Location</label>
                                                                        <input type="text" class="form-control" name="location" 
                                                                               value="<?php echo $item['location']; ?>" 
                                                                               placeholder="e.g., Warehouse A, Shelf B3">
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
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Reason for Adjustment</label>
                                                                    <textarea class="form-control" name="reason" rows="2" required 
                                                                              placeholder="e.g., Restocking, Damaged items, Tour usage"></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><?php
// admin/inventory.php - Enhanced Inventory Management

session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

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
    $min_stock_level = $_POST['min_stock_level'] ?? 5;
    $max_stock_level = $_POST['max_stock_level'] ?? 100;
    $location = filter_input(INPUT_POST, 'location', FILTER_SANITIZE_STRING);
    
    $insert_sql = "INSERT INTO inventory (item_name, item_type, quantity, unit_price, supplier_id, 
                                        description, condition_status, min_stock_level, max_stock_level, location) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("ssidissiis", $item_name, $item_type, $quantity, $unit_price, $supplier_id, 
                      $description, $condition_status, $min_stock_level, $max_stock_level, $location);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Inventory item added successfully!";
        logActivity($_SESSION['user_id'], 'add_item', 'inventory', "Added item: $item_name", $conn);
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
    $min_stock_level = $_POST['min_stock_level'] ?? 5;
    $max_stock_level = $_POST['max_stock_level'] ?? 100;
    $location = filter_input(INPUT_POST, 'location', FILTER_SANITIZE_STRING);
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    
    $update_sql = "UPDATE inventory SET item_name = ?, item_type = ?, quantity = ?, unit_price = ?, 
                   supplier_id = ?, description = ?, condition_status = ?, last_maintenance_date = ?, 
                   min_stock_level = ?, max_stock_level = ?, location = ?, is_available = ? 
                   WHERE inventory_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ssidisssiisii", $item_name, $item_type, $quantity, $unit_price, $supplier_id, 
                      $description, $condition_status, $last_maintenance_date, $min_stock_level, 
                      $max_stock_level, $location, $is_available, $inventory_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Inventory item updated successfully!";
        logActivity($_SESSION['user_id'], 'update_item', 'inventory', "Updated item: $item_name", $conn);
    } else {
        $_SESSION['error_message'] = "Error updating inventory item.";
    }
}

// Handle quantity adjustment with tracking
if (isset($_POST['adjust_quantity'])) {
    $inventory_id = $_POST['inventory_id'];
    $adjustment_type = $_POST['adjustment_type'];
    $adjustment_quantity = $_POST['adjustment_quantity'];
    $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING);
    
    // Get current quantity
    $get_quantity = $conn->prepare("SELECT quantity, item_name FROM inventory WHERE inventory_id = ?");
    $get_quantity->bind_param("i", $inventory_id);
    $get_quantity->execute();
    $result = $get_quantity->get_result();
    $current = $result->fetch_assoc();
    
    if ($adjustment_type == 'add') {
        $new_quantity = $current['quantity'] + $adjustment_quantity;
        $change = "+$adjustment_quantity";
    } else {
        $new_quantity = $current['quantity'] - $adjustment_quantity;
        if ($new_quantity < 0) $new_quantity = 0;
        $change = "-$adjustment_quantity";
    }
    
    // Update quantity
    $update_quantity = $conn->prepare("UPDATE inventory SET quantity = ? WHERE inventory_id = ?");
    $update_quantity->bind_param("ii", $new_quantity, $inventory_id);
    
    if ($update_quantity->execute()) {
        // Log the adjustment
        $log_sql = "INSERT INTO inventory_adjustments (inventory_id, adjustment_type, quantity_change, 
                    old_quantity, new_quantity, reason, adjusted_by, adjustment_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bind_param("isiissi", $inventory_id, $adjustment_type, $adjustment_quantity, 
                             $current['quantity'], $new_quantity, $reason, $_SESSION['user_id']);
        $log_stmt->execute();
        
        $_SESSION['success_message'] = "Inventory quantity adjusted successfully!";
        logActivity($_SESSION['user_id'], 'adjust_quantity', 'inventory', 
                   "Adjusted {$current['item_name']} by $change. Reason: $reason", $conn);
    }
}

// Handle bulk import
if (isset($_POST['bulk_import']) && isset($_FILES['import_file'])) {
    $file = $_FILES['import_file']['tmp_name'];
    
    if (($handle = fopen($file, "r")) !== FALSE) {
        // Skip header row
        fgetcsv($handle);
        
        $imported = 0;
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $item_name = $data[0];
            $item_type = $data[1];
            $quantity = $data[2];
            $unit_price = $data[3];
            $supplier_id = $data[4];
            $condition_status = $data[5] ?? 'good';
            
            $import_sql = "INSERT INTO inventory (item_name, item_type, quantity, unit_price, supplier_id, condition_status) 
                          VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($import_sql);
            $stmt->bind_param("ssidis", $item_name, $item_type, $quantity, $unit_price, $supplier_id, $condition_status);
            
            if ($stmt->execute()) {
                $imported++;
            }
        }
        fclose($handle);
        
        $_SESSION['success_message'] = "Successfully imported $imported items!";
        logActivity($_SESSION['user_id'], 'bulk_import', 'inventory', "Bulk imported $imported items", $conn);
    }
}

// Get filter parameters
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_supplier = isset($_GET['supplier']) ? $_GET['supplier'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query with enhanced filtering
$query = "SELECT i.*, s.supplier_name,
          CASE 
            WHEN i.quantity = 0 THEN 'out_of_stock'
            WHEN i.quantity <= i.min_stock_level THEN 'low_stock'
            WHEN i.quantity >= i.max_stock_level THEN 'overstock'
            ELSE 'normal'
          END as stock_status
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

if ($filter_status) {
    switch ($filter_status) {
        case 'low_stock':
            $query .= " AND i.quantity <= i.min_stock_level AND i.quantity > 0";
            break;
        case 'out_of_stock':
            $query .= " AND i.quantity = 0";
            break;
        case 'overstock':
            $query .= " AND i.quantity >= i.max_stock_level";
            break;
        case 'available':
            $query .= " AND i.is_available = 1";
            break;
        case 'unavailable':
            $query .= " AND i.is_available = 0";
            break;
    }
}

if ($search) {
    $query .= " AND (i.item_name LIKE ? OR i.description LIKE ? OR i.location LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
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

// Calculate inventory statistics
$total_value_query = "SELECT SUM(quantity * unit_price) as total_value FROM inventory WHERE is_available = 1";
$total_value_result = $conn->query($total_value_query);
$total_value = $total_value_result->fetch_assoc()['total_value'] ?? 0;

// Low stock items
$low_stock_query = "SELECT COUNT(*) as low_count FROM inventory 
                    WHERE quantity <= min_stock_level AND quantity > 0 AND is_available = 1";
$low_stock_result = $conn->query($low_stock_query);
$low_stock_count = $low_stock_result->fetch_assoc()['low_count'];

// Out of stock items
$out_of_stock_query = "SELECT COUNT(*) as out_count FROM inventory WHERE quantity = 0 AND is_available = 1";
$out_of_stock_result = $conn->query($out_of_stock_query);
$out_of_stock_count = $out_of_stock_result->fetch_assoc()['out_count'];

// Total items
$total_items_query = "SELECT COUNT(*) as count FROM inventory WHERE is_available = 1";
$total_items_result = $conn->query($total_items_query);
$total_items = $total_items_result->fetch_assoc()['count'];

// Get recent adjustments
$recent_adjustments_query = "SELECT ia.*, i.item_name, u.first_name, u.last_name 
                            FROM inventory_adjustments ia
                            JOIN inventory i ON ia.inventory_id = i.inventory_id
                            JOIN users u ON ia.adjusted_by = u.user_id
                            ORDER BY ia.adjustment_date DESC
                            LIMIT 5";
$recent_adjustments = $conn-><?php
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