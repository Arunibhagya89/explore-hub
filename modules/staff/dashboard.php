<?php
// staff/dashboard.php - Staff Dashboard

session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header("Location: ../modules/auth/login.php");
    exit();
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get staff information
$staff_query = "SELECT s.*, u.first_name, u.last_name, u.email 
                FROM staff s 
                JOIN users u ON s.user_id = u.user_id 
                WHERE s.user_id = ?";
$stmt = $conn->prepare($staff_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$staff_info = $stmt->get_result()->fetch_assoc();

// Get today's statistics
$today = date('Y-m-d');

// Today's bookings
$today_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE DATE(booking_date) = '$today'")->fetch_assoc()['count'];

// Pending bookings to process
$pending_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE booking_status = 'pending'")->fetch_assoc()['count'];

// Today's check-ins (travel date is today)
$today_checkins = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE travel_date = '$today' AND booking_status = 'confirmed'")->fetch_assoc()['count'];

// Low inventory items
$low_inventory = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE quantity < 5 AND is_available = 1")->fetch_assoc()['count'];

// Unpaid bookings
$unpaid_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE payment_status IN ('pending', 'partial')")->fetch_assoc()['count'];

// Active tours count
$active_tours = $conn->query("SELECT COUNT(*) as count FROM tours WHERE is_active = 1")->fetch_assoc()['count'];

// Recent bookings
$recent_bookings_query = "SELECT b.*, u.first_name, u.last_name, u.email, u.phone 
                         FROM bookings b 
                         JOIN users u ON b.customer_id = u.user_id 
                         ORDER BY b.booking_date DESC 
                         LIMIT 10";
$recent_bookings = $conn->query($recent_bookings_query);

// Today's tours
$today_tours_query = "SELECT DISTINCT t.tour_name, t.tour_id, 
                      COUNT(DISTINCT b.booking_id) as booking_count,
                      SUM(bi.quantity) as total_participants
                      FROM bookings b
                      JOIN booking_items bi ON b.booking_id = bi.booking_id
                      JOIN tours t ON bi.item_id = t.tour_id AND bi.item_type = 'tour'
                      WHERE b.travel_date = ?
                      AND b.booking_status IN ('confirmed', 'pending')
                      GROUP BY t.tour_id";
$stmt = $conn->prepare($today_tours_query);
$stmt->bind_param("s", $today);
$stmt->execute();
$today_tours = $stmt->get_result();

// Tasks/reminders for staff
$tasks = [
    ['icon' => 'bi-exclamation-circle', 'text' => "$pending_bookings pending bookings need confirmation", 'link' => '../admin/bookings.php?status=pending'],
    ['icon' => 'bi-credit-card', 'text' => "$unpaid_bookings bookings with pending payments", 'link' => '../admin/bookings.php?payment=pending'],
    ['icon' => 'bi-box-seam', 'text' => "$low_inventory inventory items running low", 'link' => '../admin/inventory.php'],
    ['icon' => 'bi-calendar-check', 'text' => "$today_checkins check-ins expected today", 'link' => '../admin/bookings.php?date=' . $today]
];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Explore Hub</title>
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
            border-radius: 10px;
            padding: 20px;
            height: 100%;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card-1 { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-card-2 { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-card-3 { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-card-4 { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .task-item {
            padding: 15px;
            border-left: 3px solid #007bff;
            margin-bottom: 10px;
            background: #f8f9fa;
            transition: all 0.3s;
        }
        .task-item:hover {
            background: #e9ecef;
            border-left-color: #0056b3;
        }
        .tour-schedule {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 10px;
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
                        <p class="mb-0">Staff Panel</p>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/bookings.php">
                                <i class="bi bi-calendar-check"></i> Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/tours.php">
                                <i class="bi bi-map"></i> Tours
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/hotels.php">
                                <i class="bi bi-building"></i> Hotels
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/suppliers.php">
                                <i class="bi bi-truck"></i> Suppliers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/inventory.php">
                                <i class="bi bi-box-seam"></i> Inventory
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my-schedule.php">
                                <i class="bi bi-calendar3"></i> My Schedule
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="bi bi-person"></i> My Profile
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
                    <h1 class="h2">Staff Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="text-muted">Welcome, <?php echo $staff_info['first_name'] . ' ' . $staff_info['last_name']; ?></span>
                    </div>
                </div>
                
                <!-- Employee Info Card -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-2">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 80px; height: 80px; font-size: 2rem;">
                                    <?php echo strtoupper(substr($staff_info['first_name'], 0, 1) . substr($staff_info['last_name'], 0, 1)); ?>
                                </div>
                            </div>
                            <div class="col-md-10">
                                <h4><?php echo $staff_info['first_name'] . ' ' . $staff_info['last_name']; ?></h4>
                                <p class="mb-0">
                                    <strong>Employee Code:</strong> <?php echo $staff_info['employee_code']; ?> | 
                                    <strong>Department:</strong> <?php echo $staff_info['department']; ?> | 
                                    <strong>Position:</strong> <?php echo $staff_info['position']; ?>
                                </p>
                                <p class="mb-0 text-muted">
                                    <i class="bi bi-envelope"></i> <?php echo $staff_info['email']; ?> | 
                                    <i class="bi bi-calendar"></i> Joined: <?php echo date('M d, Y', strtotime($staff_info['hire_date'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card stat-card-1 text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Today's Bookings</h6>
                                    <h2 class="mb-0 mt-2"><?php echo $today_bookings; ?></h2>
                                </div>
                                <i class="bi bi-calendar-plus" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card stat-card-2 text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Pending Confirmations</h6>
                                    <h2 class="mb-0 mt-2"><?php echo $pending_bookings; ?></h2>
                                </div>
                                <i class="bi bi-clock" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card stat-card-3 text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Today's Check-ins</h6>
                                    <h2 class="mb-0 mt-2"><?php echo $today_checkins; ?></h2>
                                </div>
                                <i class="bi bi-person-check" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card stat-card-4 text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Active Tours</h6>
                                    <h2 class="mb-0 mt-2"><?php echo $active_tours; ?></h2>
                                </div>
                                <i class="bi bi-map" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Tasks & Reminders -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Tasks & Reminders</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($tasks as $task): ?>
                                <a href="<?php echo $task['link']; ?>" class="text-decoration-none text-dark">
                                    <div class="task-item">
                                        <i class="<?php echo $task['icon']; ?> me-2"></i>
                                        <?php echo $task['text']; ?>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Today's Tours -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Today's Tour Schedule</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($today_tours->num_rows > 0): ?>
                                    <?php while ($tour = $today_tours->fetch_assoc()): ?>
                                    <div class="tour-schedule">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?php echo $tour['tour_name']; ?></h6>
                                                <p class="mb-0 text-muted">
                                                    <i class="bi bi-people"></i> <?php echo $tour['total_participants']; ?> participants | 
                                                    <i class="bi bi-calendar-check"></i> <?php echo $tour['booking_count']; ?> bookings
                                                </p>
                                            </div>
                                            <a href="../admin/tour-details.php?id=<?php echo $tour['tour_id']; ?>&date=<?php echo $today; ?>" 
                                               class="btn btn-sm btn-primary">View Details</a>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center py-3">No tours scheduled for today</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Bookings -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Bookings</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Booking Ref</th>
                                        <th>Customer</th>
                                        <th>Contact</th>
                                        <th>Travel Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($booking = $recent_bookings->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo $booking['booking_reference']; ?></strong></td>
                                        <td><?php echo $booking['first_name'] . ' ' . $booking['last_name']; ?></td>
                                        <td>
                                            <small>
                                                <?php echo $booking['email']; ?><br>
                                                <?php echo $booking['phone']; ?>
                                            </small>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($booking['travel_date'])); ?></td>
                                        <td>LKR <?php echo number_format($booking['total_amount'], 2); ?></td>
                                        <td><?php echo getStatusBadge($booking['booking_status'], 'booking'); ?></td>
                                        <td>
                                            <a href="../admin/booking-details.php?id=<?php echo $booking['booking_id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>