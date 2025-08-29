<?php
// admin/dashboard.php - Admin Dashboard

session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../modules/auth/login.php");
    exit();
}

$conn = getDBConnection();

// Get comprehensive dashboard statistics
$stats = [];

// Total customers
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'customer'");
$stats['total_customers'] = $result->fetch_assoc()['count'];

// New customers this month
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'customer' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
$stats['new_customers_month'] = $result->fetch_assoc()['count'];

// Total bookings
$result = $conn->query("SELECT COUNT(*) as count FROM bookings");
$stats['total_bookings'] = $result->fetch_assoc()['count'];

// Bookings this month
$result = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE MONTH(booking_date) = MONTH(CURDATE()) AND YEAR(booking_date) = YEAR(CURDATE())");
$stats['bookings_month'] = $result->fetch_assoc()['count'];

// Total revenue
$result = $conn->query("SELECT SUM(paid_amount) as total FROM bookings WHERE payment_status = 'paid'");
$stats['total_revenue'] = $result->fetch_assoc()['total'] ?? 0;

// Revenue this month
$result = $conn->query("SELECT SUM(paid_amount) as total FROM bookings WHERE payment_status = 'paid' AND MONTH(booking_date) = MONTH(CURDATE()) AND YEAR(booking_date) = YEAR(CURDATE())");
$stats['revenue_month'] = $result->fetch_assoc()['total'] ?? 0;

// Pending bookings
$result = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE booking_status = 'pending'");
$stats['pending_bookings'] = $result->fetch_assoc()['count'];

// Unpaid amount
$result = $conn->query("SELECT SUM(total_amount - paid_amount) as pending FROM bookings WHERE payment_status IN ('pending', 'partial')");
$stats['unpaid_amount'] = $result->fetch_assoc()['pending'] ?? 0;

// Staff count
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'staff' AND is_active = 1");
$stats['active_staff'] = $result->fetch_assoc()['count'];

// Tours count
$result = $conn->query("SELECT COUNT(*) as count FROM tours WHERE is_active = 1");
$stats['active_tours'] = $result->fetch_assoc()['count'];

// Hotels count
$result = $conn->query("SELECT COUNT(*) as count FROM hotels WHERE is_active = 1");
$stats['active_hotels'] = $result->fetch_assoc()['count'];

// Low inventory items
$result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE quantity < 5 AND is_available = 1");
$stats['low_inventory'] = $result->fetch_assoc()['count'];

// Revenue chart data (last 7 days)
$revenue_chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $query = "SELECT COALESCE(SUM(paid_amount), 0) as total FROM bookings WHERE DATE(booking_date) = '$date' AND payment_status = 'paid'";
    $result = $conn->query($query);
    $revenue_chart_data[] = [
        'date' => date('M d', strtotime($date)),
        'amount' => $result->fetch_assoc()['total']
    ];
}

// Booking status distribution
$booking_status_query = "SELECT booking_status, COUNT(*) as count FROM bookings GROUP BY booking_status";
$booking_status_result = $conn->query($booking_status_query);
$booking_status_data = [];
while ($row = $booking_status_result->fetch_assoc()) {
    $booking_status_data[$row['booking_status']] = $row['count'];
}

// Top performing tours
$top_tours_query = "SELECT t.tour_name, COUNT(bi.booking_item_id) as booking_count, SUM(bi.subtotal) as revenue
                    FROM tours t
                    LEFT JOIN booking_items bi ON t.tour_id = bi.item_id AND bi.item_type = 'tour'
                    LEFT JOIN bookings b ON bi.booking_id = b.booking_id
                    WHERE b.payment_status = 'paid'
                    GROUP BY t.tour_id
                    ORDER BY revenue DESC
                    LIMIT 5";
$top_tours = $conn->query($top_tours_query);

// Recent bookings
$recent_bookings_sql = "SELECT b.booking_id, b.booking_reference, b.booking_date, b.total_amount, 
                              b.booking_status, b.payment_status, u.first_name, u.last_name 
                       FROM bookings b 
                       JOIN users u ON b.customer_id = u.user_id 
                       ORDER BY b.booking_date DESC 
                       LIMIT 10";
$recent_bookings = $conn->query($recent_bookings_sql);

// Recent activities
$activities_query = "SELECT al.*, u.first_name, u.last_name 
                    FROM activity_logs al 
                    JOIN users u ON al.user_id = u.user_id 
                    ORDER BY al.timestamp DESC 
                    LIMIT 10";
$recent_activities = $conn->query($activities_query);

// Upcoming tours (next 7 days)
$upcoming_tours_query = "SELECT DATE(b.travel_date) as travel_date, t.tour_name, 
                         COUNT(DISTINCT b.booking_id) as bookings, SUM(bi.quantity) as participants
                         FROM bookings b
                         JOIN booking_items bi ON b.booking_id = bi.booking_id
                         JOIN tours t ON bi.item_id = t.tour_id AND bi.item_type = 'tour'
                         WHERE b.travel_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                         AND b.booking_status IN ('confirmed', 'pending')
                         GROUP BY DATE(b.travel_date), t.tour_id
                         ORDER BY b.travel_date
                         LIMIT 10";
$upcoming_tours = $conn->query($upcoming_tours_query);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Explore Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .stat-card {
            border-left: 4px solid;
            transition: transform 0.2s;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .stat-card-1 { border-left-color: #007bff; }
        .stat-card-2 { border-left-color: #28a745; }
        .stat-card-3 { border-left-color: #ffc107; }
        .stat-card-4 { border-left-color: #dc3545; }
        .stat-card-5 { border-left-color: #17a2b8; }
        .stat-card-6 { border-left-color: #6610f2; }
        .metric-change {
            font-size: 0.875rem;
        }
        .metric-up { color: #28a745; }
        .metric-down { color: #dc3545; }
        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .quick-action {
            text-align: center;
            padding: 20px;
            border-radius: 5px;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .quick-action:hover {
            background: #f8f9fa;
            transform: translateY(-3px);
            text-decoration: none;
            color: inherit;
        }
        .quick-action i {
            font-size: 2rem;
            margin-bottom: 10px;
            display: block;
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
                            <a class="nav-link active" href="dashboard.php">
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
                    <h1 class="h2">Admin Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                                <i class="bi bi-printer"></i> Print
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-download"></i> Export
                            </button>
                        </div>
                        <span class="text-muted">Welcome, <?php echo $_SESSION['full_name']; ?></span>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <a href="../modules/booking/create_booking.php" class="quick-action">
                            <i class="bi bi-calendar-plus text-primary"></i>
                            <small>New Booking</small>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="tours.php#add" class="quick-action">
                            <i class="bi bi-map text-success"></i>
                            <small>Add Tour</small>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="staff.php#add" class="quick-action">
                            <i class="bi bi-person-plus text-info"></i>
                            <small>Add Staff</small>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="inventory.php#add" class="quick-action">
                            <i class="bi bi-box-seam text-warning"></i>
                            <small>Add Inventory</small>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="reports.php" class="quick-action">
                            <i class="bi bi-graph-up text-danger"></i>
                            <small>View Reports</small>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="settings.php" class="quick-action">
                            <i class="bi bi-gear text-secondary"></i>
                            <small>Settings</small>
                        </a>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card stat-card-1">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="text-muted mb-2">Total Customers</h6>
                                        <h3 class="mb-0"><?php echo number_format($stats['total_customers']); ?></h3>
                                        <small class="metric-up">
                                            <i class="bi bi-arrow-up"></i> +<?php echo $stats['new_customers_month']; ?> this month
                                        </small>
                                    </div>
                                    <div class="text-primary" style="font-size: 2.5rem;">
                                        <i class="bi bi-people-fill"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card stat-card-2">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="text-muted mb-2">Total Bookings</h6>
                                        <h3 class="mb-0"><?php echo number_format($stats['total_bookings']); ?></h3>
                                        <small class="metric-up">
                                            <i class="bi bi-arrow-up"></i> +<?php echo $stats['bookings_month']; ?> this month
                                        </small>
                                    </div>
                                    <div class="text-success" style="font-size: 2.5rem;">
                                        <i class="bi bi-calendar-check-fill"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card stat-card-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="text-muted mb-2">Total Revenue</h6>
                                        <h4 class="mb-0">LKR <?php echo number_format($stats['total_revenue'], 2); ?></h4>
                                        <small class="metric-up">
                                            <i class="bi bi-arrow-up"></i> LKR <?php echo number_format($stats['revenue_month'], 2); ?> this month
                                        </small>
                                    </div>
                                    <div class="text-warning" style="font-size: 2.5rem;">
                                        <i class="bi bi-currency-dollar"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card stat-card-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Pending Bookings</h6>
                                        <h3 class="mb-0"><?php echo number_format($stats['pending_bookings']); ?></h3>
                                    </div>
                                    <div class="text-danger">
                                        <i class="bi bi-clock-fill" style="font-size: 2rem;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card stat-card-5">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Unpaid Amount</h6>
                                        <h5 class="mb-0">LKR <?php echo number_format($stats['unpaid_amount'], 2); ?></h5>
                                    </div>
                                    <div class="text-info">
                                        <i class="bi bi-cash-stack" style="font-size: 2rem;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card stat-card-6">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Active Staff</h6>
                                        <h3 class="mb-0"><?php echo number_format($stats['active_staff']); ?></h3>
                                    </div>
                                    <div style="color: #6610f2;">
                                        <i class="bi bi-person-badge" style="font-size: 2rem;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <div class="row">
                                    <div class="col-4">
                                        <h4 class="mb-0"><?php echo $stats['active_tours']; ?></h4>
                                        <small class="text-muted">Tours</small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="mb-0"><?php echo $stats['active_hotels']; ?></h4>
                                        <small class="text-muted">Hotels</small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="mb-0 <?php echo $stats['low_inventory'] > 0 ? 'text-danger' : ''; ?>">
                                            <?php echo $stats['low_inventory']; ?>
                                        </h4>
                                        <small class="text-muted">Low Stock</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Revenue Trend (Last 7 Days)</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="revenueChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Booking Status Distribution</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="bookingStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tables Row -->
                <div class="row">
                    <!-- Recent Bookings -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Bookings</h5>
                                <a href="bookings.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Reference</th>
                                                <th>Customer</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($booking = $recent_bookings->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $booking['booking_reference']; ?></td>
                                                <td><?php echo $booking['first_name'] . ' ' . $booking['last_name']; ?></td>
                                                <td>LKR <?php echo number_format($booking['total_amount'], 2); ?></td>
                                                <td><?php echo getStatusBadge($booking['booking_status'], 'booking'); ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Upcoming Tours -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Upcoming Tours</h5>
                                <a href="tours.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Tour</th>
                                                <th>Bookings</th>
                                                <th>Participants</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($tour = $upcoming_tours->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('M d', strtotime($tour['travel_date'])); ?></td>
                                                <td><?php echo substr($tour['tour_name'], 0, 25) . '...'; ?></td>
                                                <td><?php echo $tour['bookings']; ?></td>
                                                <td><?php echo $tour['participants']; ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Top Tours & Recent Activities -->
                <div class="row mt-4">
                    <!-- Top Performing Tours -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Top Performing Tours</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Tour Name</th>
                                                <th>Bookings</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($tour = $top_tours->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $tour['tour_name']; ?></td>
                                                <td><?php echo $tour['booking_count']; ?></td>
                                                <td>LKR <?php echo number_format($tour['revenue'] ?? 0, 2); ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Activities -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Recent Activities</h5>
                            </div>
                            <div class="card-body">
                                <?php while ($activity = $recent_activities->fetch_assoc()): ?>
                                <div class="activity-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong><?php echo $activity['first_name'] . ' ' . $activity['last_name']; ?></strong>
                                            <span class="text-muted"><?php echo $activity['action']; ?></span>
                                            <?php if ($activity['description']): ?>
                                                <br><small class="text-muted"><?php echo $activity['description']; ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted"><?php echo getTimeAgo($activity['timestamp']); ?></small>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueData = <?php echo json_encode($revenue_chart_data); ?>;
        
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: revenueData.map(d => d.date),
                datasets: [{
                    label: 'Revenue (LKR)',
                    data: revenueData.map(d => d.amount),
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
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
        
        // Booking Status Chart
        const bookingStatusCtx = document.getElementById('bookingStatusChart').getContext('2d');
        const bookingStatusData = <?php echo json_encode($booking_status_data); ?>;
        
        new Chart(bookingStatusCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(bookingStatusData).map(s => s.charAt(0).toUpperCase() + s.slice(1)),
                datasets: [{
                    data: Object.values(bookingStatusData),
                    backgroundColor: [
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(220, 53, 69, 0.8)',
                        'rgba(23, 162, 184, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    </script>
</body>
</html><?php
// admin/dashboard.php - Admin Dashboard

session_start();
require_once '../config/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../modules/auth/login.php");
    exit();
}

$conn = getDBConnection();

// Get dashboard statistics
$stats = [];

// Total customers
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'customer'");
$stats['total_customers'] = $result->fetch_assoc()['count'];

// Total bookings
$result = $conn->query("SELECT COUNT(*) as count FROM bookings");
$stats['total_bookings'] = $result->fetch_assoc()['count'];

// Total revenue
$result = $conn->query("SELECT SUM(paid_amount) as total FROM bookings WHERE payment_status = 'paid'");
$stats['total_revenue'] = $result->fetch_assoc()['total'] ?? 0;

// Pending bookings
$result = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE booking_status = 'pending'");
$stats['pending_bookings'] = $result->fetch_assoc()['count'];

// Recent bookings
$recent_bookings_sql = "SELECT b.booking_id, b.booking_reference, b.booking_date, b.total_amount, 
                              b.booking_status, u.first_name, u.last_name 
                       FROM bookings b 
                       JOIN users u ON b.customer_id = u.user_id 
                       ORDER BY b.booking_date DESC 
                       LIMIT 5";
$recent_bookings = $conn->query($recent_bookings_sql);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Explore Hub</title>
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
        .stat-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card-1 { border-left-color: #007bff; }
        .stat-card-2 { border-left-color: #28a745; }
        .stat-card-3 { border-left-color: #ffc107; }
        .stat-card-4 { border-left-color: #dc3545; }
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
                            <a class="nav-link active" href="dashboard.php">
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