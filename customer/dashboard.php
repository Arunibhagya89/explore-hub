<?php
// customer/dashboard.php - Customer Dashboard

session_start();
require_once '../config/config.php';

// Check if user is logged in and is customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    header("Location: ../modules/auth/login.php");
    exit();
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get customer statistics
$stats = [];

// Total bookings
$result = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE customer_id = $user_id");
$stats['total_bookings'] = $result->fetch_assoc()['count'];

// Active bookings
$result = $conn->query("SELECT COUNT(*) as count FROM bookings 
                       WHERE customer_id = $user_id AND booking_status IN ('pending', 'confirmed')");
$stats['active_bookings'] = $result->fetch_assoc()['count'];

// Completed trips
$result = $conn->query("SELECT COUNT(*) as count FROM bookings 
                       WHERE customer_id = $user_id AND booking_status = 'completed'");
$stats['completed_trips'] = $result->fetch_assoc()['count'];

// Total spent
$result = $conn->query("SELECT SUM(paid_amount) as total FROM bookings 
                       WHERE customer_id = $user_id AND payment_status = 'paid'");
$stats['total_spent'] = $result->fetch_assoc()['total'] ?? 0;

// Get customer's bookings
$bookings_query = "SELECT b.*, 
                  GROUP_CONCAT(
                    CASE 
                      WHEN bi.item_type = 'tour' THEN (SELECT tour_name FROM tours WHERE tour_id = bi.item_id)
                      WHEN bi.item_type = 'hotel' THEN (SELECT CONCAT(h.hotel_name, ' - ', rt.room_type_name) 
                                                       FROM room_types rt 
                                                       JOIN hotels h ON rt.hotel_id = h.hotel_id 
                                                       WHERE rt.room_type_id = bi.item_id)
                    END SEPARATOR ', '
                  ) as items
                  FROM bookings b
                  LEFT JOIN booking_items bi ON b.booking_id = bi.booking_id
                  WHERE b.customer_id = ?
                  GROUP BY b.booking_id
                  ORDER BY b.booking_date DESC";

$stmt = $conn->prepare($bookings_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookings_result = $stmt->get_result();

// Get available tours for quick booking
$tours = $conn->query("SELECT tour_id, tour_name, tour_type, duration_days, base_price, difficulty_level 
                      FROM tours WHERE is_active = 1 ORDER BY tour_name LIMIT 6");

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Explore Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .navbar-custom {
            background-color: #2c3e50;
        }
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            color: white;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .bg-gradient-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .bg-gradient-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        .bg-gradient-info {
            background: linear-gradient(135deg, #17a2b8 0%, #007bff 100%);
        }
        .bg-gradient-warning {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
        }
        .tour-card {
            transition: transform 0.2s;
            height: 100%;
        }
        .tour-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .difficulty-easy { color: #28a745; }
        .difficulty-moderate { color: #ffc107; }
        .difficulty-difficult { color: #dc3545; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-compass"></i> Explore Hub
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="bi bi-house"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">
                            <i class="bi bi-map"></i> Browse Tours
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my-bookings.php">
                            <i class="bi bi-calendar-check"></i> My Bookings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="bi bi-person"></i> Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../modules/auth/logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-12">
                <h2>Welcome back, <?php echo $_SESSION['full_name']; ?>!</h2>
                <p class="text-muted">Manage your bookings and explore new adventures.</p>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card bg-gradient-primary">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Total Bookings</h6>
                            <h3 class="mb-0 mt-2"><?php echo $stats['total_bookings']; ?></h3>
                        </div>
                        <i class="bi bi-calendar-check-fill" style="font-size: 2.5rem; opacity: 0.5;"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card bg-gradient-success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Active Bookings</h6>
                            <h3 class="mb-0 mt-2"><?php echo $stats['active_bookings']; ?></h3>
                        </div>
                        <i class="bi bi-clock-fill" style="font-size: 2.5rem; opacity: 0.5;"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card bg-gradient-info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Completed Trips</h6>
                            <h3 class="mb-0 mt-2"><?php echo $stats['completed_trips']; ?></h3>
                        </div>
                        <i class="bi bi-check-circle-fill" style="font-size: 2.5rem; opacity: 0.5;"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card bg-gradient-warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Total Spent</h6>
                            <h4 class="mb-0 mt-2">LKR <?php echo number_format($stats['total_spent'], 2); ?></h4>
                        </div>
                        <i class="bi bi-currency-dollar" style="font-size: 2.5rem; opacity: 0.5;"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Bookings -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Bookings</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($bookings_result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Booking Ref</th>
                                        <th>Services</th>
                                        <th>Travel Date</th>
                                        <th>Amount</th>
                                        <th>Payment Status</th>
                                        <th>Booking Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($booking = $bookings_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo $booking['booking_reference']; ?></strong></td>
                                        <td><?php echo $booking['items']; ?></td>
                                        <td><?php echo date('d M Y', strtotime($booking['travel_date'])); ?></td>
                                        <td>LKR <?php echo number_format($booking['total_amount'], 2); ?></td>
                                        <td>
                                            <?php
                                            $payment_class = $booking['payment_status'] == 'paid' ? 'success' : 
                                                           ($booking['payment_status'] == 'partial' ? 'warning' : 'danger');
                                            ?>
                                            <span class="badge bg-<?php echo $payment_class; ?>">
                                                <?php echo ucfirst($booking['payment_status']); ?>
                                            </span>
                                        </td>
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
                                            <a href="booking-details.php?id=<?php echo $booking['booking_id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            <?php if ($booking['payment_status'] != 'paid'): ?>
                                            <a href="payment.php?booking=<?php echo $booking['booking_reference']; ?>" 
                                               class="btn btn-sm btn-success">
                                                <i class="bi bi-credit-card"></i> Pay
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-calendar-x" style="font-size: 3rem; color: #dee2e6;"></i>
                            <p class="mt-2 text-muted">No bookings found. Start exploring our tours!</p>
                            <a href="../modules/booking/create_booking.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Make a Booking
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Popular Tours -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4>Popular Tours</h4>
                    <a href="../index.php" class="btn btn-outline-primary">View All Tours</a>
                </div>
            </div>
        </div>
        
        <div class="row">
            <?php while ($tour = $tours->fetch_assoc()): ?>
            <div class="col-md-4 mb-4">
                <div class="card tour-card">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $tour['tour_name']; ?></h5>
                        <p class="text-muted mb-2">
                            <i class="bi bi-tag"></i> <?php echo ucfirst($tour['tour_type']); ?> • 
                            <i class="bi bi-calendar"></i> <?php echo $tour['duration_days']; ?> days
                        </p>
                        <p class="mb-2">
                            Difficulty: 
                            <span class="difficulty-<?php echo $tour['difficulty_level']; ?>">
                                <?php echo ucfirst($tour['difficulty_level'] ?? 'N/A'); ?>
                            </span>
                        </p>
                        <h6 class="text-primary mb-3">LKR <?php echo number_format($tour['base_price'], 2); ?></h6>
                        <a href="../modules/booking/create_booking.php?tour=<?php echo $tour['tour_id']; ?>" 
                           class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-calendar-plus"></i> Book Now
                        </a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-dark text-white mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Explore Hub</h5>
                    <p>Your gateway to amazing adventures in Sri Lanka</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>&copy; 2025 Explore Hub. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>