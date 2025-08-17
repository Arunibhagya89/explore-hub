<?php
// admin/bookings.php - Bookings Management for Admin

session_start();
require_once '../config/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../modules/auth/login.php");
    exit();
}

$conn = getDBConnection();

// Handle status update
if (isset($_POST['update_status'])) {
    $booking_id = $_POST['booking_id'];
    $new_status = $_POST['booking_status'];
    
    $update_sql = "UPDATE bookings SET booking_status = ? WHERE booking_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_status, $booking_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['success_message'] = "Booking status updated successfully!";
    }
}

// Handle payment update
if (isset($_POST['update_payment'])) {
    $booking_id = $_POST['booking_id'];
    $payment_amount = $_POST['payment_amount'];
    $payment_method = $_POST['payment_method'];
    
    // Get current booking details
    $booking_query = "SELECT total_amount, paid_amount FROM bookings WHERE booking_id = ?";
    $booking_stmt = $conn->prepare($booking_query);
    $booking_stmt->bind_param("i", $booking_id);
    $booking_stmt->execute();
    $booking_result = $booking_stmt->get_result();
    $booking = $booking_result->fetch_assoc();
    
    $new_paid_amount = $booking['paid_amount'] + $payment_amount;
    $payment_status = ($new_paid_amount >= $booking['total_amount']) ? 'paid' : 'partial';
    
    // Update booking
    $update_booking = "UPDATE bookings SET paid_amount = ?, payment_status = ? WHERE booking_id = ?";
    $update_stmt = $conn->prepare($update_booking);
    $update_stmt->bind_param("dsi", $new_paid_amount, $payment_status, $booking_id);
    $update_stmt->execute();
    
    // Insert payment record
    $insert_payment = "INSERT INTO payments (booking_id, amount, payment_method, processed_by) VALUES (?, ?, ?, ?)";
    $payment_stmt = $conn->prepare($insert_payment);
    $payment_stmt->bind_param("idsi", $booking_id, $payment_amount, $payment_method, $_SESSION['user_id']);
    $payment_stmt->execute();
    
    $_SESSION['success_message'] = "Payment recorded successfully!";
}

// Get filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$query = "SELECT b.*, u.first_name, u.last_name, u.email, u.phone 
          FROM bookings b 
          JOIN users u ON b.customer_id = u.user_id 
          WHERE 1=1";

$params = [];
$types = "";

if ($filter_status) {
    $query .= " AND b.booking_status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if ($filter_date) {
    $query .= " AND DATE(b.booking_date) = ?";
    $params[] = $filter_date;
    $types .= "s";
}

if ($search) {
    $query .= " AND (b.booking_reference LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

$query .= " ORDER BY b.booking_date DESC";

if ($types) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
unset($_SESSION['success_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings Management - Explore Hub Admin</title>
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
                        <p class="mb-0">Admin Panel</p>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="bookings.php">
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
                            <a class="nav-link" href="customers.php">
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
                    <h1 class="h2">Bookings Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="../modules/booking/create_booking.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> New Booking
                        </a>
                    </div>
                </div>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Reference, Name, Email" value="<?php echo $search; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $filter_status == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="cancelled" <?php echo $filter_status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date" class="form-label">Booking Date</label>
                                <input type="date" class="form-control" id="date" name="date" value="<?php echo $filter_date; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">Filter</button>
                                    <a href="bookings.php" class="btn btn-secondary">Clear</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Bookings Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Reference</th>
                                        <th>Customer</th>
                                        <th>Type</th>
                                        <th>Travel Date</th>
                                        <th>Amount</th>
                                        <th>Paid</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($booking = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $booking['booking_reference']; ?></td>
                                        <td>
                                            <?php echo $booking['first_name'] . ' ' . $booking['last_name']; ?><br>
                                            <small class="text-muted"><?php echo $booking['email']; ?></small>
                                        </td>
                                        <td><?php echo ucfirst($booking['booking_type']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($booking['travel_date'])); ?></td>
                                        <td>LKR <?php echo number_format($booking['total_amount'], 2); ?></td>
                                        <td>LKR <?php echo number_format($booking['paid_amount'], 2); ?></td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            switch($booking['booking_status']) {
                                                case 'confirmed': $status_class = 'success'; break;
                                                case 'pending': $status_class = 'warning'; break;
                                                case 'cancelled': $status_class = 'danger'; break;
                                                case 'completed': $status_class = 'info'; break;
                                                default: $status_class = 'secondary';
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $status_class; ?>">
                                                <?php echo ucfirst($booking['booking_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                                    data-bs-target="#viewModal<?php echo $booking['booking_id']; ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" 
                                                    data-bs-target="#statusModal<?php echo $booking['booking_id']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php if ($booking['payment_status'] != 'paid'): ?>
                                            <button class="btn btn-sm btn-success" data-bs-toggle="modal" 
                                                    data-bs-target="#paymentModal<?php echo $booking['booking_id']; ?>">
                                                <i class="bi bi-cash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    
                                    <!-- View Modal -->
                                    <div class="modal fade" id="viewModal<?php echo $booking['booking_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Booking Details - <?php echo $booking['booking_reference']; ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <p><strong>Customer:</strong> <?php echo $booking['first_name'] . ' ' . $booking['last_name']; ?></p>
                                                            <p><strong>Email:</strong> <?php echo $booking['email']; ?></p>
                                                            <p><strong>Phone:</strong> <?php echo $booking['phone']; ?></p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <p><strong>Booking Type:</strong> <?php echo ucfirst($booking['booking_type']); ?></p>
                                                            <p><strong>Travel Date:</strong> <?php echo date('Y-m-d', strtotime($booking['travel_date'])); ?></p>
                                                            <p><strong>End Date:</strong> <?php echo date('Y-m-d', strtotime($booking['end_date'])); ?></p>
                                                        </div>
                                                    </div>
                                                    <hr>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <p><strong>Total Amount:</strong> LKR <?php echo number_format($booking['total_amount'], 2); ?></p>
                                                            <p><strong>Paid Amount:</strong> LKR <?php echo number_format($booking['paid_amount'], 2); ?></p>
                                                            <p><strong>Balance:</strong> LKR <?php echo number_format($booking['total_amount'] - $booking['paid_amount'], 2); ?></p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <p><strong>Booking Status:</strong> 
                                                                <span class="badge bg-<?php echo $status_class; ?>">
                                                                    <?php echo ucfirst($booking['booking_status']); ?>
                                                                </span>
                                                            </p>
                                                            <p><strong>Payment Status:</strong> 
                                                                <span class="badge bg-<?php echo $booking['payment_status'] == 'paid' ? 'success' : 'warning'; ?>">
                                                                    <?php echo ucfirst($booking['payment_status']); ?>
                                                                </span>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <?php if ($booking['special_requests']): ?>
                                                    <hr>
                                                    <p><strong>Special Requests:</strong></p>
                                                    <p><?php echo nl2br($booking['special_requests']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Status Update Modal -->
                                    <div class="modal fade" id="statusModal<?php echo $booking['booking_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Update Booking Status</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                                        <div class="mb-3">
                                                            <label for="booking_status" class="form-label">Status</label>
                                                            <select class="form-select" name="booking_status" required>
                                                                <option value="pending" <?php echo $booking['booking_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                <option value="confirmed" <?php echo $booking['booking_status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                                <option value="cancelled" <?php echo $booking['booking_status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                                <option value="completed" <?php echo $booking['booking_status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Payment Modal -->
                                    <?php if ($booking['payment_status'] != 'paid'): ?>
                                    <div class="modal fade" id="paymentModal<?php echo $booking['booking_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Record Payment</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                                        <p><strong>Balance Due:</strong> LKR <?php echo number_format($booking['total_amount'] - $booking['paid_amount'], 2); ?></p>
                                                        <div class="mb-3">
                                                            <label for="payment_amount" class="form-label">Payment Amount</label>
                                                            <input type="number" class="form-control" name="payment_amount" 
                                                                   step="0.01" max="<?php echo $booking['total_amount'] - $booking['paid_amount']; ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="payment_method" class="form-label">Payment Method</label>
                                                            <select class="form-select" name="payment_method" required>
                                                                <option value="cash">Cash</option>
                                                                <option value="card">Card</option>
                                                                <option value="bank_transfer">Bank Transfer</option>
                                                                <option value="online">Online</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="update_payment" class="btn btn-success">Record Payment</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
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