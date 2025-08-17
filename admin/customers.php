<?php
// admin/customers.php - Customer Management

session_start();
require_once '../config/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../modules/auth/login.php");
    exit();
}

$conn = getDBConnection();

// Handle customer status update
if (isset($_POST['update_status'])) {
    $user_id = $_POST['user_id'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $update_sql = "UPDATE users SET is_active = ? WHERE user_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ii", $is_active, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Customer status updated successfully!";
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'recent';

// Build query
$query = "SELECT 
            u.*,
            COUNT(DISTINCT b.booking_id) as total_bookings,
            COALESCE(SUM(b.total_amount), 0) as total_spent,
            COALESCE(SUM(b.paid_amount), 0) as total_paid,
            MAX(b.booking_date) as last_booking_date,
            (SELECT COUNT(*) FROM bookings WHERE customer_id = u.user_id AND booking_status = 'completed') as completed_bookings
          FROM users u
          LEFT JOIN bookings b ON u.user_id = b.customer_id
          WHERE u.user_type = 'customer'";

$params = [];
$types = "";

if ($search) {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if ($status !== '') {
    $query .= " AND u.is_active = ?";
    $params[] = $status;
    $types .= "i";
}

$query .= " GROUP BY u.user_id";

// Apply sorting
switch ($sort) {
    case 'name':
        $query .= " ORDER BY u.first_name, u.last_name";
        break;
    case 'bookings':
        $query .= " ORDER BY total_bookings DESC";
        break;
    case 'revenue':
        $query .= " ORDER BY total_spent DESC";
        break;
    default:
        $query .= " ORDER BY u.created_at DESC";
}

if ($types) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $customers_result = $stmt->get_result();
} else {
    $customers_result = $conn->query($query);
}

// Get statistics
$total_customers = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'customer'")->fetch_assoc()['count'];
$active_customers = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'customer' AND is_active = 1")->fetch_assoc()['count'];
$new_customers_month = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'customer' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['count'];

$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
unset($_SESSION['success_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - Explore Hub Admin</title>
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
        .customer-card {
            transition: all 0.3s;
            cursor: pointer;
        }
        .customer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .loyalty-badge {
            position: absolute;
            top: 10px;
            right: 10px;
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
                        <p class="mb-0">Admin Panel</p>
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
                        <li class="nav-item">
                            <a class="nav-link active" href="customers.php">
                                <i class="bi bi-people"></i> Customers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="staff.php">
                                <i class="bi bi-person-badge"></i> Staff
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="suppliers.php">
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
                    <h1 class="h2">Customer Management</h1>
                </div>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h6 class="card-title">Total Customers</h6>
                                <h3 class="mb-0"><?php echo number_format($total_customers); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-title">Active Customers</h6>
                                <h3 class="mb-0"><?php echo number_format($active_customers); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6 class="card-title">New This Month</h6>
                                <h3 class="mb-0"><?php echo number_format($new_customers_month); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Name, email, or phone" value="<?php echo $search; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Customers</option>
                                    <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>Active</option>
                                    <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="sort" class="form-label">Sort By</label>
                                <select class="form-select" id="sort" name="sort">
                                    <option value="recent" <?php echo $sort == 'recent' ? 'selected' : ''; ?>>Recently Joined</option>
                                    <option value="name" <?php echo $sort == 'name' ? 'selected' : ''; ?>>Name</option>
                                    <option value="bookings" <?php echo $sort == 'bookings' ? 'selected' : ''; ?>>Most Bookings</option>
                                    <option value="revenue" <?php echo $sort == 'revenue' ? 'selected' : ''; ?>>Highest Revenue</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">Filter</button>
                                    <a href="customers.php" class="btn btn-secondary">Clear</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Customers Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Contact</th>
                                        <th>Joined</th>
                                        <th>Bookings</th>
                                        <th>Total Spent</th>
                                        <th>Last Booking</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($customer = $customers_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center me-3" 
                                                     style="width: 40px; height: 40px;">
                                                    <?php echo strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo $customer['first_name'] . ' ' . $customer['last_name']; ?></strong>
                                                    <?php if ($customer['total_spent'] > 100000): ?>
                                                        <span class="badge bg-warning ms-2">VIP</span>
                                                    <?php endif; ?>
                                                    <br>
                                                    <small class="text-muted">@<?php echo $customer['username']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo $customer['email']; ?><br>
                                            <small><?php echo $customer['phone']; ?></small>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $customer['total_bookings']; ?></span>
                                            <?php if ($customer['completed_bookings'] > 0): ?>
                                                <br><small class="text-muted"><?php echo $customer['completed_bookings']; ?> completed</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            LKR <?php echo number_format($customer['total_spent'], 2); ?>
                                            <?php if ($customer['total_spent'] > $customer['total_paid']): ?>
                                                <br><small class="text-warning">
                                                    LKR <?php echo number_format($customer['total_spent'] - $customer['total_paid'], 2); ?> pending
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($customer['last_booking_date']): ?>
                                                <?php echo date('M d, Y', strtotime($customer['last_booking_date'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">No bookings</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($customer['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                                    data-bs-target="#viewModal<?php echo $customer['user_id']; ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <a href="customer-bookings.php?id=<?php echo $customer['user_id']; ?>" 
                                               class="btn btn-sm btn-info">
                                                <i class="bi bi-calendar-check"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    
                                    <!-- View Modal -->
                                    <div class="modal fade" id="viewModal<?php echo $customer['user_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Customer Details - <?php echo $customer['first_name'] . ' ' . $customer['last_name']; ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h6 class="text-muted">Personal Information</h6>
                                                            <p><strong>Name:</strong> <?php echo $customer['first_name'] . ' ' . $customer['last_name']; ?></p>
                                                            <p><strong>Username:</strong> <?php echo $customer['username']; ?></p>
                                                            <p><strong>Email:</strong> <?php echo $customer['email']; ?></p>
                                                            <p><strong>Phone:</strong> <?php echo $customer['phone'] ?? 'Not provided'; ?></p>
                                                            <p><strong>Address:</strong> <?php echo nl2br($customer['address']) ?? 'Not provided'; ?></p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <h6 class="text-muted">Account Statistics</h6>
                                                            <p><strong>Member Since:</strong> <?php echo date('M d, Y', strtotime($customer['created_at'])); ?></p>
                                                            <p><strong>Total Bookings:</strong> <?php echo $customer['total_bookings']; ?></p>
                                                            <p><strong>Completed Trips:</strong> <?php echo $customer['completed_bookings']; ?></p>
                                                            <p><strong>Total Spent:</strong> LKR <?php echo number_format($customer['total_spent'], 2); ?></p>
                                                            <p><strong>Total Paid:</strong> LKR <?php echo number_format($customer['total_paid'], 2); ?></p>
                                                            <p><strong>Outstanding:</strong> LKR <?php echo number_format($customer['total_spent'] - $customer['total_paid'], 2); ?></p>
                                                        </div>
                                                    </div>
                                                    
                                                    <hr>
                                                    
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="user_id" value="<?php echo $customer['user_id']; ?>">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="is_active" 
                                                                   id="is_active<?php echo $customer['user_id']; ?>" 
                                                                   <?php echo $customer['is_active'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="is_active<?php echo $customer['user_id']; ?>">
                                                                Active Account
                                                            </label>
                                                        </div>
                                                        <button type="submit" name="update_status" class="btn btn-primary mt-3">Update Status</button>
                                                    </form>
                                                    
                                                    <?php
                                                    // Get recent bookings for this customer
                                                    $recent_bookings = $conn->prepare("SELECT * FROM bookings WHERE customer_id = ? ORDER BY booking_date DESC LIMIT 5");
                                                    $recent_bookings->bind_param("i", $customer['user_id']);
                                                    $recent_bookings->execute();
                                                    $bookings_result = $recent_bookings->get_result();
                                                    ?>
                                                    
                                                    <?php if ($bookings_result->num_rows > 0): ?>
                                                    <hr>
                                                    <h6 class="text-muted">Recent Bookings</h6>
                                                    <table class="table table-sm">
                                                        <thead>
                                                            <tr>
                                                                <th>Reference</th>
                                                                <th>Date</th>
                                                                <th>Amount</th>
                                                                <th>Status</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php while ($booking = $bookings_result->fetch_assoc()): ?>
                                                            <tr>
                                                                <td><?php echo $booking['booking_reference']; ?></td>
                                                                <td><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></td>
                                                                <td>LKR <?php echo number_format($booking['total_amount'], 2); ?></td>
                                                                <td>
                                                                    <?php
                                                                    $status_class = '';
                                                                    switch($booking['booking_status']) {
                                                                        case 'confirmed': $status_class = 'success'; break;
                                                                        case 'pending': $status_class = 'warning'; break;
                                                                        case 'cancelled': $status_class = 'danger'; break;
                                                                        case 'completed': $status_class = 'info'; break;
                                                                    }
                                                                    ?>
                                                                    <span class="badge bg-<?php echo $status_class; ?>">
                                                                        <?php echo ucfirst($booking['booking_status']); ?>
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                            <?php endwhile; ?>
                                                        </tbody>
                                                    </table>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <a href="customer-bookings.php?id=<?php echo $customer['user_id']; ?>" 
                                                       class="btn btn-info">View All Bookings</a>
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>