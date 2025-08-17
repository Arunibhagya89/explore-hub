<?php
// admin/reports.php - Reports Module

session_start();
require_once '../config/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../modules/auth/login.php");
    exit();
}

$conn = getDBConnection();

// Get report parameters
$report_type = isset($_GET['type']) ? $_GET['type'] : 'revenue';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Initialize report data
$report_data = [];
$chart_labels = [];
$chart_data = [];

switch ($report_type) {
    case 'revenue':
        // Revenue Report
        $revenue_query = "SELECT 
                            DATE(b.booking_date) as date,
                            COUNT(b.booking_id) as booking_count,
                            SUM(b.total_amount) as total_revenue,
                            SUM(b.paid_amount) as paid_amount,
                            SUM(b.total_amount - b.paid_amount) as pending_amount
                         FROM bookings b
                         WHERE b.booking_date BETWEEN ? AND ?
                         GROUP BY DATE(b.booking_date)
                         ORDER BY date";
        
        $stmt = $conn->prepare($revenue_query);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $report_data[] = $row;
            $chart_labels[] = date('M d', strtotime($row['date']));
            $chart_data[] = $row['paid_amount'];
        }
        
        // Get summary
        $summary_query = "SELECT 
                            COUNT(DISTINCT b.booking_id) as total_bookings,
                            SUM(b.total_amount) as total_revenue,
                            SUM(b.paid_amount) as total_paid,
                            COUNT(DISTINCT b.customer_id) as unique_customers
                         FROM bookings b
                         WHERE b.booking_date BETWEEN ? AND ?";
        
        $stmt = $conn->prepare($summary_query);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $summary = $stmt->get_result()->fetch_assoc();
        break;
        
    case 'bookings':
        // Bookings Report
        $bookings_query = "SELECT 
                            b.booking_status,
                            COUNT(b.booking_id) as count,
                            SUM(b.total_amount) as total_amount,
                            AVG(b.total_amount) as avg_amount
                         FROM bookings b
                         WHERE b.booking_date BETWEEN ? AND ?
                         GROUP BY b.booking_status";
        
        $stmt = $conn->prepare($bookings_query);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $report_data[] = $row;
            $chart_labels[] = ucfirst($row['booking_status']);
            $chart_data[] = $row['count'];
        }
        
        // Get booking trends
        $trends_query = "SELECT 
                            DATE_FORMAT(booking_date, '%Y-%m') as month,
                            COUNT(*) as booking_count
                         FROM bookings
                         WHERE booking_date BETWEEN ? AND ?
                         GROUP BY month
                         ORDER BY month";
        
        $stmt = $conn->prepare($trends_query);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $trends = $stmt->get_result();
        break;
        
    case 'tours':
        // Tours Performance Report
        $tours_query = "SELECT 
                            t.tour_name,
                            t.tour_type,
                            COUNT(bi.booking_item_id) as booking_count,
                            SUM(bi.quantity) as total_participants,
                            SUM(bi.subtotal) as total_revenue,
                            AVG(bi.subtotal) as avg_revenue
                         FROM tours t
                         LEFT JOIN booking_items bi ON t.tour_id = bi.item_id AND bi.item_type = 'tour'
                         LEFT JOIN bookings b ON bi.booking_id = b.booking_id
                         WHERE (b.booking_date BETWEEN ? AND ? OR b.booking_date IS NULL)
                         GROUP BY t.tour_id
                         ORDER BY total_revenue DESC";
        
        $stmt = $conn->prepare($tours_query);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $report_data[] = $row;
            if (count($chart_labels) < 10) { // Top 10 tours
                $chart_labels[] = substr($row['tour_name'], 0, 20) . '...';
                $chart_data[] = $row['total_revenue'] ?? 0;
            }
        }
        break;
        
    case 'customers':
        // Customer Report
        $customers_query = "SELECT 
                            u.user_id,
                            u.first_name,
                            u.last_name,
                            u.email,
                            COUNT(b.booking_id) as booking_count,
                            SUM(b.total_amount) as total_spent,
                            MAX(b.booking_date) as last_booking
                         FROM users u
                         LEFT JOIN bookings b ON u.user_id = b.customer_id
                         WHERE u.user_type = 'customer'
                         AND (b.booking_date BETWEEN ? AND ? OR b.booking_date IS NULL)
                         GROUP BY u.user_id
                         ORDER BY total_spent DESC
                         LIMIT 20";
        
        $stmt = $conn->prepare($customers_query);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $report_data[] = $row;
        }
        
        // Customer acquisition
        $acquisition_query = "SELECT 
                                DATE_FORMAT(created_at, '%Y-%m') as month,
                                COUNT(*) as new_customers
                             FROM users
                             WHERE user_type = 'customer'
                             AND created_at BETWEEN ? AND ?
                             GROUP BY month
                             ORDER BY month";
        
        $stmt = $conn->prepare($acquisition_query);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $acquisition = $stmt->get_result();
        
        while ($row = $acquisition->fetch_assoc()) {
            $chart_labels[] = $row['month'];
            $chart_data[] = $row['new_customers'];
        }
        break;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Explore Hub Admin</title>
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
            border-left: 4px solid #007bff;
            padding: 20px;
            background: #f8f9fa;
            margin-bottom: 20px;
        }
        .report-tabs .nav-link {
            color: #6c757d;
            border: none;
            border-bottom: 2px solid transparent;
        }
        .report-tabs .nav-link.active {
            color: #007bff;
            border-bottom-color: #007bff;
        }
        @media print {
            .sidebar, .no-print {
                display: none !important;
            }
            .col-md-10 {
                width: 100% !important;
                margin: 0 !important;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-md-block sidebar no-print">
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
                            <a class="nav-link active" href="reports.php">
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
                    <h1 class="h2">Reports</h1>
                    <div class="btn-toolbar mb-2 mb-md-0 no-print">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                            <i class="bi bi-printer"></i> Print Report
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="exportBtn">
                            <i class="bi bi-download"></i> Export CSV
                        </button>
                    </div>
                </div>
                
                <!-- Report Type Tabs -->
                <ul class="nav nav-tabs report-tabs no-print" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $report_type == 'revenue' ? 'active' : ''; ?>" 
                           href="?type=revenue&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">
                            Revenue Report
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $report_type == 'bookings' ? 'active' : ''; ?>" 
                           href="?type=bookings&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">
                            Bookings Report
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $report_type == 'tours' ? 'active' : ''; ?>" 
                           href="?type=tours&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">
                            Tours Performance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $report_type == 'customers' ? 'active' : ''; ?>" 
                           href="?type=customers&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">
                            Customer Analysis
                        </a>
                    </li>
                </ul>
                
                <!-- Date Range Filter -->
                <div class="card mt-3 no-print">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <input type="hidden" name="type" value="<?php echo $report_type; ?>">
                            <div class="col-md-4">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                       value="<?php echo $start_date; ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                       value="<?php echo $end_date; ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">Generate Report</button>
                                    <button type="button" class="btn btn-secondary" onclick="setQuickDate('month')">This Month</button>
                                    <button type="button" class="btn btn-secondary" onclick="setQuickDate('year')">This Year</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Report Content -->
                <div class="mt-4">
                    <?php if ($report_type == 'revenue'): ?>
                        <!-- Revenue Report -->
                        <h3>Revenue Report</h3>
                        <p class="text-muted">Period: <?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?></p>
                        
                        <!-- Summary Cards -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <h6 class="text-muted">Total Bookings</h6>
                                    <h3><?php echo number_format($summary['total_bookings']); ?></h3>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <h6 class="text-muted">Total Revenue</h6>
                                    <h3>LKR <?php echo number_format($summary['total_revenue'], 2); ?></h3>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <h6 class="text-muted">Amount Collected</h6>
                                    <h3>LKR <?php echo number_format($summary['total_paid'], 2); ?></h3>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <h6 class="text-muted">Unique Customers</h6>
                                    <h3><?php echo number_format($summary['unique_customers']); ?></h3>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Revenue Chart -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5>Daily Revenue Trend</h5>
                                <canvas id="revenueChart" height="100"></canvas>
                            </div>
                        </div>
                        
                        <!-- Detailed Table -->
                        <div class="card">
                            <div class="card-body">
                                <h5>Daily Breakdown</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm" id="reportTable">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Bookings</th>
                                                <th>Total Amount</th>
                                                <th>Paid Amount</th>
                                                <th>Pending</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data as $row): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                                <td><?php echo $row['booking_count']; ?></td>
                                                <td>LKR <?php echo number_format($row['total_revenue'], 2); ?></td>
                                                <td>LKR <?php echo number_format($row['paid_amount'], 2); ?></td>
                                                <td>LKR <?php echo number_format($row['pending_amount'], 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="fw-bold">
                                                <td>Total</td>
                                                <td><?php echo array_sum(array_column($report_data, 'booking_count')); ?></td>
                                                <td>LKR <?php echo number_format(array_sum(array_column($report_data, 'total_revenue')), 2); ?></td>
                                                <td>LKR <?php echo number_format(array_sum(array_column($report_data, 'paid_amount')), 2); ?></td>
                                                <td>LKR <?php echo number_format(array_sum(array_column($report_data, 'pending_amount')), 2); ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($report_type == 'bookings'): ?>
                        <!-- Bookings Report -->
                        <h3>Bookings Report</h3>
                        <p class="text-muted">Period: <?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?></p>
                        
                        <!-- Booking Status Chart -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h5>Booking Status Distribution</h5>
                                        <canvas id="statusChart"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h5>Booking Trends</h5>
                                        <canvas id="trendsChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Status Breakdown Table -->
                        <div class="card">
                            <div class="card-body">
                                <h5>Status Breakdown</h5>
                                <div class="table-responsive">
                                    <table class="table" id="reportTable">
                                        <thead>
                                            <tr>
                                                <th>Status</th>
                                                <th>Count</th>
                                                <th>Total Amount</th>
                                                <th>Average Amount</th>
                                                <th>Percentage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $total_bookings = array_sum(array_column($report_data, 'count'));
                                            foreach ($report_data as $row): 
                                            ?>
                                            <tr>
                                                <td>
                                                    <?php
                                                    $badge_class = '';
                                                    switch($row['booking_status']) {
                                                        case 'confirmed': $badge_class = 'success'; break;
                                                        case 'pending': $badge_class = 'warning'; break;
                                                        case 'cancelled': $badge_class = 'danger'; break;
                                                        case 'completed': $badge_class = 'info'; break;
                                                    }
                                                    ?>
                                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                                        <?php echo ucfirst($row['booking_status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $row['count']; ?></td>
                                                <td>LKR <?php echo number_format($row['total_amount'], 2); ?></td>
                                                <td>LKR <?php echo number_format($row['avg_amount'], 2); ?></td>
                                                <td><?php echo round(($row['count'] / $total_bookings) * 100, 1); ?>%</td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($report_type == 'tours'): ?>
                        <!-- Tours Performance Report -->
                        <h3>Tours Performance Report</h3>
                        <p class="text-muted">Period: <?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?></p>
                        
                        <!-- Top Tours Chart -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5>Top 10 Tours by Revenue</h5>
                                <canvas id="toursChart" height="100"></canvas>
                            </div>
                        </div>
                        
                        <!-- Tours Table -->
                        <div class="card">
                            <div class="card-body">
                                <h5>Tour Performance Details</h5>
                                <div class="table-responsive">
                                    <table class="table" id="reportTable">
                                        <thead>
                                            <tr>
                                                <th>Tour Name</th>
                                                <th>Type</th>
                                                <th>Bookings</th>
                                                <th>Participants</th>
                                                <th>Total Revenue</th>
                                                <th>Avg Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data as $row): ?>
                                            <tr>
                                                <td><?php echo $row['tour_name']; ?></td>
                                                <td><?php echo ucfirst($row['tour_type']); ?></td>
                                                <td><?php echo $row['booking_count'] ?? 0; ?></td>
                                                <td><?php echo $row['total_participants'] ?? 0; ?></td>
                                                <td>LKR <?php echo number_format($row['total_revenue'] ?? 0, 2); ?></td>
                                                <td>LKR <?php echo number_format($row['avg_revenue'] ?? 0, 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($report_type == 'customers'): ?>
                        <!-- Customer Analysis Report -->
                        <h3>Customer Analysis Report</h3>
                        <p class="text-muted">Period: <?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?></p>
                        
                        <!-- Customer Acquisition Chart -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5>New Customer Acquisition</h5>
                                <canvas id="customerChart" height="100"></canvas>
                            </div>
                        </div>
                        
                        <!-- Top Customers Table -->
                        <div class="card">
                            <div class="card-body">
                                <h5>Top 20 Customers by Revenue</h5>
                                <div class="table-responsive">
                                    <table class="table" id="reportTable">
                                        <thead>
                                            <tr>
                                                <th>Customer Name</th>
                                                <th>Email</th>
                                                <th>Bookings</th>
                                                <th>Total Spent</th>
                                                <th>Last Booking</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data as $row): ?>
                                            <tr>
                                                <td><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></td>
                                                <td><?php echo $row['email']; ?></td>
                                                <td><?php echo $row['booking_count'] ?? 0; ?></td>
                                                <td>LKR <?php echo number_format($row['total_spent'] ?? 0, 2); ?></td>
                                                <td><?php echo $row['last_booking'] ? date('M d, Y', strtotime($row['last_booking'])) : 'N/A'; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Quick date selection
        function setQuickDate(period) {
            const today = new Date();
            let startDate, endDate;
            
            if (period === 'month') {
                startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            } else if (period === 'year') {
                startDate = new Date(today.getFullYear(), 0, 1);
                endDate = new Date(today.getFullYear(), 11, 31);
            }
            
            document.getElementById('start_date').value = startDate.toISOString().split('T')[0];
            document.getElementById('end_date').value = endDate.toISOString().split('T')[0];
        }
        
        // Chart initialization
        const chartLabels = <?php echo json_encode($chart_labels); ?>;
        const chartData = <?php echo json_encode($chart_data); ?>;
        
        <?php if ($report_type == 'revenue'): ?>
        // Revenue Chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Daily Revenue (LKR)',
                    data: chartData,
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
        
        <?php elseif ($report_type == 'bookings'): ?>
        // Booking Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: chartLabels,
                datasets: [{
                    data: chartData,
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
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
        
        <?php elseif ($report_type == 'tours'): ?>
        // Tours Revenue Chart
        const toursCtx = document.getElementById('toursChart').getContext('2d');
        new Chart(toursCtx, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Revenue (LKR)',
                    data: chartData,
                    backgroundColor: 'rgba(54, 162, 235, 0.8)'
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
        
        <?php elseif ($report_type == 'customers'): ?>
        // Customer Acquisition Chart
        const customerCtx = document.getElementById('customerChart').getContext('2d');
        new Chart(customerCtx, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'New Customers',
                    data: chartData,
                    backgroundColor: 'rgba(153, 102, 255, 0.8)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
        <?php endif; ?>
        
        // Export to CSV
        document.getElementById('exportBtn').addEventListener('click', function() {
            const table = document.getElementById('reportTable');
            let csv = [];
            
            // Get headers
            const headers = [];
            table.querySelectorAll('thead th').forEach(th => {
                headers.push(th.textContent.trim());
            });
            csv.push(headers.join(','));
            
            // Get data rows
            table.querySelectorAll('tbody tr').forEach(tr => {
                const row = [];
                tr.querySelectorAll('td').forEach(td => {
                    let text = td.textContent.trim();
                    // Remove currency formatting for CSV
                    text = text.replace(/LKR\s*/g, '').replace(/,/g, '');
                    // Wrap in quotes if contains comma
                    if (text.includes(',')) {
                        text = '"' + text + '"';
                    }
                    row.push(text);
                });
                csv.push(row.join(','));
            });
            
            // Download CSV
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'report_<?php echo $report_type; ?>_<?php echo date('Y-m-d'); ?>.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        });
    </script>
</body>
</html>