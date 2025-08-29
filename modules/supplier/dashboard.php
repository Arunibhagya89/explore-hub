<?php
// supplier/dashboard.php - Supplier Dashboard

session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if supplier is logged in
if (!isset($_SESSION['supplier_id'])) {
    header("Location: index.php");
    exit();
}

$conn = getDBConnection();
$supplier_id = $_SESSION['supplier_id'];

// Get supplier details
$supplier_query = "SELECT * FROM suppliers WHERE supplier_id = ?";
$stmt = $conn->prepare($supplier_query);
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$supplier_info = $stmt->get_result()->fetch_assoc();

// Get statistics
// Total items supplied
$items_query = "SELECT COUNT(*) as count FROM inventory WHERE supplier_id = ?";
$stmt = $conn->prepare($items_query);
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$total_items = $stmt->get_result()->fetch_assoc()['count'];

// Total payments received
$payments_query = "SELECT SUM(amount) as total FROM supplier_payments WHERE supplier_id = ?";
$stmt = $conn->prepare($payments_query);
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$total_payments = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Payments this month
$month_payments_query = "SELECT SUM(amount) as total FROM supplier_payments 
                        WHERE supplier_id = ? AND MONTH(payment_date) = MONTH(CURDATE()) 
                        AND YEAR(payment_date) = YEAR(CURDATE())";
$stmt = $conn->prepare($month_payments_query);
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$month_payments = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Pending orders (items with low stock)
$low_stock_query = "SELECT COUNT(*) as count FROM inventory 
                    WHERE supplier_id = ? AND quantity < 5 AND is_available = 1";
$stmt = $conn->prepare($low_stock_query);
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$low_stock_items = $stmt->get_result()->fetch_assoc()['count'];

// Get inventory items
$inventory_query = "SELECT * FROM inventory WHERE supplier_id = ? ORDER BY item_name";
$stmt = $conn->prepare($inventory_query);
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$inventory_result = $stmt->get_result();

// Get recent payments
$recent_payments_query = "SELECT * FROM supplier_payments 
                         WHERE supplier_id = ? 
                         ORDER BY payment_date DESC 
                         LIMIT 10";
$stmt = $conn->prepare($recent_payments_query);
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$recent_payments = $stmt->get_result();

// Get payment trend for chart (last 6 months)
$payment_trend = [];
for ($i = 5; $i >= 0; $i--) {
    $month_date = date('Y-m', strtotime("-$i months"));
    $trend_query = "SELECT SUM(amount) as total FROM supplier_payments 
                    WHERE supplier_id = ? AND DATE_FORMAT(payment_date, '%Y-%m') = ?";
    $stmt = $conn->prepare($trend_query);
    $stmt->bind_param("is", $supplier_id, $month_date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $payment_trend[] = [
        'month' => date('M Y', strtotime($month_date . '-01')),
        'amount' => $result['total'] ?? 0
    ];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Dashboard - <?php echo $supplier_info['supplier_name']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .navbar-supplier {
            background-color: #6610f2;
        }
        .sidebar {
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        .sidebar .nav-link {
            color: #333;
            padding: 10px 20px;
        }
        .sidebar .nav-link:hover {
            background-color: #e9ecef;
        }
        .sidebar .nav-link.active {
            background-color: #6610f2;
            color: white;
        }
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            color: white;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card-1 { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-card-2 { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .stat-card-3 { background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); }
        .stat-card-4 { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); }
        .inventory-item {
            border-left: 3px solid #6610f2;
            margin-bottom: 10px;
            padding: 15px;
            background: #f8f9fa;
            transition: all 0.3s;
        }
        .inventory-item:hover {
            background: #e9ecef;
        }
        .low-stock {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-supplier">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="bi bi-truck"></i> Supplier Portal
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo $supplier_info['contact_person']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-md-block sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="inventory.php">
                                <i class="bi bi-box-seam"></i> My Inventory
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php">
                                <i class="bi bi-cart"></i> Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="payments.php">
                                <i class="bi bi-cash-stack"></i> Payments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="bi bi-graph-up"></i> Reports
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-10 ms-sm-auto px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Welcome, <?php echo $supplier_info['supplier_name']; ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="badge bg-<?php echo $supplier_info['is_active'] ? 'success' : 'danger'; ?>">
                            <?php echo $supplier_info['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                </div>
                
                <!-- Supplier Info Card -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5><?php echo $supplier_info['supplier_name']; ?></h5>
                                <p class="mb-1"><strong>Type:</strong> <?php echo ucfirst($supplier_info['supplier_type']); ?></p>
                                <p class="mb-1"><strong>Contact Person:</strong> <?php echo $supplier_info['contact_person']; ?></p>
                                <p class="mb-0"><strong>Email:</strong> <?php echo $supplier_info['email']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Phone:</strong> <?php echo $supplier_info['phone']; ?></p>
                                <p class="mb-1"><strong>Address:</strong> <?php echo $supplier_info['address']; ?></p>
                                <p class="mb-0"><strong>Payment Terms:</strong> <?php echo $supplier_info['payment_terms']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card stat-card-1">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Total Items</h6>
                                    <h2 class="mb-0 mt-2"><?php echo $total_items; ?></h2>
                                </div>
                                <i class="bi bi-box-seam" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card stat-card-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Total Payments</h6>
                                    <h4 class="mb-0 mt-2">LKR <?php echo number_format($total_payments, 2); ?></h4>
                                </div>
                                <i class="bi bi-cash" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card stat-card-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">This Month</h6>
                                    <h4 class="mb-0 mt-2">LKR <?php echo number_format($month_payments, 2); ?></h4>
                                </div>
                                <i class="bi bi-calendar-month" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card stat-card-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Low Stock Items</h6>
                                    <h2 class="mb-0 mt-2"><?php echo $low_stock_items; ?></h2>
                                </div>
                                <i class="bi bi-exclamation-triangle" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Payment Trend Chart -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Payment Trend (Last 6 Months)</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="paymentChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <a href="inventory.php#add" class="btn btn-primary w-100 mb-2">
                                    <i class="bi bi-plus-circle"></i> Add New Item
                                </a>
                                <a href="orders.php" class="btn btn-info w-100 mb-2">
                                    <i class="bi bi-cart-check"></i> View Orders
                                </a>
                                <a href="payments.php" class="btn btn-success w-100 mb-2">
                                    <i class="bi bi-receipt"></i> View Payments
                                </a>
                                <a href="reports.php" class="btn btn-warning w-100">
                                    <i class="bi bi-download"></i> Download Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Inventory & Payments Tables -->
                <div class="row mt-4">
                    <!-- Inventory Items -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Inventory Items</h5>
                                <a href="inventory.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php 
                                $count = 0;
                                while ($item = $inventory_result->fetch_assoc()): 
                                    if ($count++ >= 5) break;
                                ?>
                                <div class="inventory-item <?php echo $item['quantity'] < 5 ? 'low-stock' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-0"><?php echo $item['item_name']; ?></h6>
                                            <small class="text-muted">
                                                Quantity: <?php echo $item['quantity']; ?> | 
                                                Price: LKR <?php echo number_format($item['unit_price'], 2); ?>
                                            </small>
                                        </div>
                                        <div>
                                            <?php if ($item['quantity'] < 5): ?>
                                                <span class="badge bg-danger">Low Stock</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">In Stock</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Payments -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Payments</h5>
                                <a href="payments.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th>Method</th>
                                                <th>Reference</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $count = 0;
                                            while ($payment = $recent_payments->fetch_assoc()): 
                                                if ($count++ >= 5) break;
                                            ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                                <td>LKR <?php echo number_format($payment['amount'], 2); ?></td>
                                                <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                                <td><?php echo $payment['reference_number'] ?: '-'; ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Payment Trend Chart
        const ctx = document.getElementById('paymentChart').getContext('2d');
        const paymentData = <?php echo json_encode($payment_trend); ?>;
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: paymentData.map(d => d.month),
                datasets: [{
                    label: 'Payments Received (LKR)',
                    data: paymentData.map(d => d.amount),
                    backgroundColor: 'rgba(102, 16, 242, 0.8)',
                    borderColor: 'rgba(102, 16, 242, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'LKR ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>