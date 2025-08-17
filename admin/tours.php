<?php
// admin/tours.php - Tours Management

session_start();
require_once '../config/config.php';

// Check if user is logged in and is admin or staff
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'staff'])) {
    header("Location: ../modules/auth/login.php");
    exit();
}

$conn = getDBConnection();

// Handle add new tour
if (isset($_POST['add_tour'])) {
    $tour_name = filter_input(INPUT_POST, 'tour_name', FILTER_SANITIZE_STRING);
    $tour_type = $_POST['tour_type'];
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $duration_days = $_POST['duration_days'];
    $base_price = $_POST['base_price'];
    $max_participants = $_POST['max_participants'];
    $difficulty_level = $_POST['difficulty_level'];
    $includes = filter_input(INPUT_POST, 'includes', FILTER_SANITIZE_STRING);
    $excludes = filter_input(INPUT_POST, 'excludes', FILTER_SANITIZE_STRING);
    $itinerary = filter_input(INPUT_POST, 'itinerary', FILTER_SANITIZE_STRING);
    
    $insert_sql = "INSERT INTO tours (tour_name, tour_type, description, duration_days, base_price, 
                                    max_participants, difficulty_level, includes, excludes, itinerary) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("sssisissss", $tour_name, $tour_type, $description, $duration_days, $base_price, 
                      $max_participants, $difficulty_level, $includes, $excludes, $itinerary);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Tour added successfully!";
    } else {
        $_SESSION['error_message'] = "Error adding tour.";
    }
}

// Handle update tour
if (isset($_POST['update_tour'])) {
    $tour_id = $_POST['tour_id'];
    $tour_name = filter_input(INPUT_POST, 'tour_name', FILTER_SANITIZE_STRING);
    $tour_type = $_POST['tour_type'];
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $duration_days = $_POST['duration_days'];
    $base_price = $_POST['base_price'];
    $max_participants = $_POST['max_participants'];
    $difficulty_level = $_POST['difficulty_level'];
    $includes = filter_input(INPUT_POST, 'includes', FILTER_SANITIZE_STRING);
    $excludes = filter_input(INPUT_POST, 'excludes', FILTER_SANITIZE_STRING);
    $itinerary = filter_input(INPUT_POST, 'itinerary', FILTER_SANITIZE_STRING);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $update_sql = "UPDATE tours SET tour_name = ?, tour_type = ?, description = ?, duration_days = ?, 
                   base_price = ?, max_participants = ?, difficulty_level = ?, includes = ?, 
                   excludes = ?, itinerary = ?, is_active = ? WHERE tour_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sssisissssii", $tour_name, $tour_type, $description, $duration_days, $base_price, 
                      $max_participants, $difficulty_level, $includes, $excludes, $itinerary, $is_active, $tour_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Tour updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating tour.";
    }
}

// Get filter parameters
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_difficulty = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$query = "SELECT t.*, 
          (SELECT COUNT(*) FROM booking_items bi 
           JOIN bookings b ON bi.booking_id = b.booking_id 
           WHERE bi.item_type = 'tour' AND bi.item_id = t.tour_id 
           AND b.booking_status != 'cancelled') as total_bookings
          FROM tours t WHERE 1=1";

$params = [];
$types = "";

if ($filter_type) {
    $query .= " AND t.tour_type = ?";
    $params[] = $filter_type;
    $types .= "s";
}

if ($filter_difficulty) {
    $query .= " AND t.difficulty_level = ?";
    $params[] = $filter_difficulty;
    $types .= "s";
}

if ($search) {
    $query .= " AND (t.tour_name LIKE ? OR t.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$query .= " ORDER BY t.tour_name";

if ($types) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $tours_result = $stmt->get_result();
} else {
    $tours_result = $conn->query($query);
}

// Get statistics
$active_tours = $conn->query("SELECT COUNT(*) as count FROM tours WHERE is_active = 1")->fetch_assoc()['count'];
$total_revenue = $conn->query("SELECT SUM(bi.subtotal) as total FROM booking_items bi 
                              JOIN bookings b ON bi.booking_id = b.booking_id 
                              WHERE bi.item_type = 'tour' AND b.payment_status = 'paid'")->fetch_assoc()['total'] ?? 0;

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
    <title>Tours Management - Explore Hub</title>
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
        .difficulty-easy { color: #28a745; }
        .difficulty-moderate { color: #ffc107; }
        .difficulty-difficult { color: #dc3545; }
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
                            <a class="nav-link active" href="tours.php">
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
                    <h1 class="h2">Tours Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTourModal">
                            <i class="bi bi-plus-circle"></i> Add New Tour
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
                
                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h5 class="card-title">Active Tours</h5>
                                <h3><?php echo $active_tours; ?></h3>
                            </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Duration (Days)</label>
                                <input type="number" class="form-control" name="duration_days" min="1" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Base Price (LKR)</label>
                                <input type="number" class="form-control" name="base_price" step="0.01" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Max Participants</label>
                                <input type="number" class="form-control" name="max_participants" min="1">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Itinerary</label>
                            <textarea class="form-control" name="itinerary" rows="3" 
                                      placeholder="Day 1: ...&#10;Day 2: ..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Includes</label>
                                <textarea class="form-control" name="includes" rows="3" 
                                          placeholder="• Transportation&#10;• Meals&#10;• Guide"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Excludes</label>
                                <textarea class="form-control" name="excludes" rows="3" 
                                          placeholder="• Personal expenses&#10;• Tips"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_tour" class="btn btn-primary">Add Tour</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h5 class="card-title">Total Tour Revenue</h5>
                                <h3>LKR <?php echo number_format($total_revenue, 2); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <h5 class="card-title">Total Tours</h5>
                                <h3><?php echo $tours_result->num_rows; ?></h3>
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
                                       placeholder="Tour name or description" value="<?php echo $search; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="type" class="form-label">Tour Type</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">All Types</option>
                                    <option value="adventure" <?php echo $filter_type == 'adventure' ? 'selected' : ''; ?>>Adventure</option>
                                    <option value="eco" <?php echo $filter_type == 'eco' ? 'selected' : ''; ?>>Eco</option>
                                    <option value="nature" <?php echo $filter_type == 'nature' ? 'selected' : ''; ?>>Nature</option>
                                    <option value="cultural" <?php echo $filter_type == 'cultural' ? 'selected' : ''; ?>>Cultural</option>
                                    <option value="custom" <?php echo $filter_type == 'custom' ? 'selected' : ''; ?>>Custom</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="difficulty" class="form-label">Difficulty</label>
                                <select class="form-select" id="difficulty" name="difficulty">
                                    <option value="">All Levels</option>
                                    <option value="easy" <?php echo $filter_difficulty == 'easy' ? 'selected' : ''; ?>>Easy</option>
                                    <option value="moderate" <?php echo $filter_difficulty == 'moderate' ? 'selected' : ''; ?>>Moderate</option>
                                    <option value="difficult" <?php echo $filter_difficulty == 'difficult' ? 'selected' : ''; ?>>Difficult</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">Filter</button>
                                    <a href="tours.php" class="btn btn-secondary">Clear</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tours Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tour Name</th>
                                        <th>Type</th>
                                        <th>Duration</th>
                                        <th>Price</th>
                                        <th>Max Participants</th>
                                        <th>Difficulty</th>
                                        <th>Bookings</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($tour = $tours_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $tour['tour_name']; ?></td>
                                        <td><?php echo ucfirst($tour['tour_type']); ?></td>
                                        <td><?php echo $tour['duration_days']; ?> days</td>
                                        <td>LKR <?php echo number_format($tour['base_price'], 2); ?></td>
                                        <td><?php echo $tour['max_participants']; ?></td>
                                        <td>
                                            <span class="difficulty-<?php echo $tour['difficulty_level']; ?>">
                                                <?php echo ucfirst($tour['difficulty_level']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $tour['total_bookings']; ?></td>
                                        <td>
                                            <?php if ($tour['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                                    data-bs-target="#viewModal<?php echo $tour['tour_id']; ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" 
                                                    data-bs-target="#editModal<?php echo $tour['tour_id']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    
                                    <!-- View Modal -->
                                    <div class="modal fade" id="viewModal<?php echo $tour['tour_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Tour Details - <?php echo $tour['tour_name']; ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <p><strong>Tour Type:</strong> <?php echo ucfirst($tour['tour_type']); ?></p>
                                                            <p><strong>Duration:</strong> <?php echo $tour['duration_days']; ?> days</p>
                                                            <p><strong>Base Price:</strong> LKR <?php echo number_format($tour['base_price'], 2); ?></p>
                                                            <p><strong>Max Participants:</strong> <?php echo $tour['max_participants']; ?></p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <p><strong>Difficulty Level:</strong> 
                                                                <span class="difficulty-<?php echo $tour['difficulty_level']; ?>">
                                                                    <?php echo ucfirst($tour['difficulty_level']); ?>
                                                                </span>
                                                            </p>
                                                            <p><strong>Total Bookings:</strong> <?php echo $tour['total_bookings']; ?></p>
                                                            <p><strong>Status:</strong> 
                                                                <?php if ($tour['is_active']): ?>
                                                                    <span class="badge bg-success">Active</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-danger">Inactive</span>
                                                                <?php endif; ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if ($tour['description']): ?>
                                                    <hr>
                                                    <h6>Description</h6>
                                                    <p><?php echo nl2br($tour['description']); ?></p>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($tour['itinerary']): ?>
                                                    <hr>
                                                    <h6>Itinerary</h6>
                                                    <p><?php echo nl2br($tour['itinerary']); ?></p>
                                                    <?php endif; ?>
                                                    
                                                    <div class="row">
                                                        <?php if ($tour['includes']): ?>
                                                        <div class="col-md-6">
                                                            <hr>
                                                            <h6>Includes</h6>
                                                            <p><?php echo nl2br($tour['includes']); ?></p>
                                                        </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($tour['excludes']): ?>
                                                        <div class="col-md-6">
                                                            <hr>
                                                            <h6>Excludes</h6>
                                                            <p><?php echo nl2br($tour['excludes']); ?></p>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal<?php echo $tour['tour_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Tour</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="tour_id" value="<?php echo $tour['tour_id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Tour Name</label>
                                                            <input type="text" class="form-control" name="tour_name" 
                                                                   value="<?php echo $tour['tour_name']; ?>" required>
                                                        </div>
                                                        
                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Tour Type</label>
                                                                <select class="form-select" name="tour_type" required>
                                                                    <option value="adventure" <?php echo $tour['tour_type'] == 'adventure' ? 'selected' : ''; ?>>Adventure</option>
                                                                    <option value="eco" <?php echo $tour['tour_type'] == 'eco' ? 'selected' : ''; ?>>Eco</option>
                                                                    <option value="nature" <?php echo $tour['tour_type'] == 'nature' ? 'selected' : ''; ?>>Nature</option>
                                                                    <option value="cultural" <?php echo $tour['tour_type'] == 'cultural' ? 'selected' : ''; ?>>Cultural</option>
                                                                    <option value="custom" <?php echo $tour['tour_type'] == 'custom' ? 'selected' : ''; ?>>Custom</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Difficulty Level</label>
                                                                <select class="form-select" name="difficulty_level">
                                                                    <option value="">Not Specified</option>
                                                                    <option value="easy" <?php echo $tour['difficulty_level'] == 'easy' ? 'selected' : ''; ?>>Easy</option>
                                                                    <option value="moderate" <?php echo $tour['difficulty_level'] == 'moderate' ? 'selected' : ''; ?>>Moderate</option>
                                                                    <option value="difficult" <?php echo $tour['difficulty_level'] == 'difficult' ? 'selected' : ''; ?>>Difficult</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Description</label>
                                                            <textarea class="form-control" name="description" rows="3"><?php echo $tour['description']; ?></textarea>
                                                        </div>
                                                        
                                                        <div class="row">
                                                            <div class="col-md-4 mb-3">
                                                                <label class="form-label">Duration (Days)</label>
                                                                <input type="number" class="form-control" name="duration_days" 
                                                                       value="<?php echo $tour['duration_days']; ?>" min="1" required>
                                                            </div>
                                                            <div class="col-md-4 mb-3">
                                                                <label class="form-label">Base Price (LKR)</label>
                                                                <input type="number" class="form-control" name="base_price" 
                                                                       value="<?php echo $tour['base_price']; ?>" step="0.01" required>
                                                            </div>
                                                            <div class="col-md-4 mb-3">
                                                                <label class="form-label">Max Participants</label>
                                                                <input type="number" class="form-control" name="max_participants" 
                                                                       value="<?php echo $tour['max_participants']; ?>" min="1">
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Itinerary</label>
                                                            <textarea class="form-control" name="itinerary" rows="3"><?php echo $tour['itinerary']; ?></textarea>
                                                        </div>
                                                        
                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Includes</label>
                                                                <textarea class="form-control" name="includes" rows="3"><?php echo $tour['includes']; ?></textarea>
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Excludes</label>
                                                                <textarea class="form-control" name="excludes" rows="3"><?php echo $tour['excludes']; ?></textarea>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="is_active" 
                                                                   id="is_active<?php echo $tour['tour_id']; ?>" 
                                                                   <?php echo $tour['is_active'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="is_active<?php echo $tour['tour_id']; ?>">
                                                                Active Tour
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="update_tour" class="btn btn-primary">Update Tour</button>
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
    
    <!-- Add Tour Modal -->
    <div class="modal fade" id="addTourModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Tour</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Tour Name</label>
                            <input type="text" class="form-control" name="tour_name" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tour Type</label>
                                <select class="form-select" name="tour_type" required>
                                    <option value="">Select Type</option>
                                    <option value="adventure">Adventure</option>
                                    <option value="eco">Eco</option>
                                    <option value="nature">Nature</option>
                                    <option value="cultural">Cultural</option>
                                    <option value="custom">Custom</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Difficulty Level</label>
                                <select class="form-select" name="difficulty_level">
                                    <option value="">Not Specified</option>
                                    <option value="easy">Easy</option>
                                    <option value="moderate">Moderate</option>
                                    <option value="difficult">Difficult</option>
                                </select>
                            </div>
                        </div>
                