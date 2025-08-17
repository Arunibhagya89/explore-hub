<?php
// admin/hotels.php - Hotels Management

session_start();
require_once '../config/config.php';

// Check if user is logged in and is admin or staff
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'staff'])) {
    header("Location: ../modules/auth/login.php");
    exit();
}

$conn = getDBConnection();

// Handle add new hotel
if (isset($_POST['add_hotel'])) {
    $hotel_name = filter_input(INPUT_POST, 'hotel_name', FILTER_SANITIZE_STRING);
    $location = filter_input(INPUT_POST, 'location', FILTER_SANITIZE_STRING);
    $star_rating = $_POST['star_rating'];
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $amenities = filter_input(INPUT_POST, 'amenities', FILTER_SANITIZE_STRING);
    $contact_number = filter_input(INPUT_POST, 'contact_number', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
    
    $insert_sql = "INSERT INTO hotels (hotel_name, location, star_rating, description, amenities, 
                                     contact_number, email, address) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("ssisssss", $hotel_name, $location, $star_rating, $description, $amenities, 
                      $contact_number, $email, $address);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Hotel added successfully!";
    } else {
        $_SESSION['error_message'] = "Error adding hotel.";
    }
}

// Handle add room type
if (isset($_POST['add_room_type'])) {
    $hotel_id = $_POST['hotel_id'];
    $room_type_name = filter_input(INPUT_POST, 'room_type_name', FILTER_SANITIZE_STRING);
    $capacity = $_POST['capacity'];
    $price_per_night = $_POST['price_per_night'];
    $room_description = filter_input(INPUT_POST, 'room_description', FILTER_SANITIZE_STRING);
    $available_rooms = $_POST['available_rooms'];
    
    $insert_room = "INSERT INTO room_types (hotel_id, room_type_name, capacity, price_per_night, 
                                          description, available_rooms) 
                    VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_room);
    $stmt->bind_param("isidsi", $hotel_id, $room_type_name, $capacity, $price_per_night, 
                      $room_description, $available_rooms);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Room type added successfully!";
    } else {
        $_SESSION['error_message'] = "Error adding room type.";
    }
}

// Handle update hotel
if (isset($_POST['update_hotel'])) {
    $hotel_id = $_POST['hotel_id'];
    $hotel_name = filter_input(INPUT_POST, 'hotel_name', FILTER_SANITIZE_STRING);
    $location = filter_input(INPUT_POST, 'location', FILTER_SANITIZE_STRING);
    $star_rating = $_POST['star_rating'];
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $amenities = filter_input(INPUT_POST, 'amenities', FILTER_SANITIZE_STRING);
    $contact_number = filter_input(INPUT_POST, 'contact_number', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $update_sql = "UPDATE hotels SET hotel_name = ?, location = ?, star_rating = ?, description = ?, 
                   amenities = ?, contact_number = ?, email = ?, address = ?, is_active = ? 
                   WHERE hotel_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ssisssssii", $hotel_name, $location, $star_rating, $description, $amenities, 
                      $contact_number, $email, $address, $is_active, $hotel_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Hotel updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating hotel.";
    }
}

// Handle update room type
if (isset($_POST['update_room_type'])) {
    $room_type_id = $_POST['room_type_id'];
    $room_type_name = filter_input(INPUT_POST, 'room_type_name', FILTER_SANITIZE_STRING);
    $capacity = $_POST['capacity'];
    $price_per_night = $_POST['price_per_night'];
    $room_description = filter_input(INPUT_POST, 'room_description', FILTER_SANITIZE_STRING);
    $available_rooms = $_POST['available_rooms'];
    
    $update_room = "UPDATE room_types SET room_type_name = ?, capacity = ?, price_per_night = ?, 
                    description = ?, available_rooms = ? WHERE room_type_id = ?";
    $stmt = $conn->prepare($update_room);
    $stmt->bind_param("sidsii", $room_type_name, $capacity, $price_per_night, 
                      $room_description, $available_rooms, $room_type_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Room type updated successfully!";
    }
}

// Get filter parameters
$filter_location = isset($_GET['location']) ? $_GET['location'] : '';
$filter_rating = isset($_GET['rating']) ? $_GET['rating'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$query = "SELECT h.*, COUNT(rt.room_type_id) as room_types_count,
          (SELECT COUNT(*) FROM booking_items bi 
           JOIN bookings b ON bi.booking_id = b.booking_id 
           WHERE bi.item_type = 'hotel' AND bi.item_id IN 
           (SELECT room_type_id FROM room_types WHERE hotel_id = h.hotel_id)
           AND b.booking_status != 'cancelled') as total_bookings
          FROM hotels h
          LEFT JOIN room_types rt ON h.hotel_id = rt.hotel_id
          WHERE 1=1";

$params = [];
$types = "";

if ($filter_location) {
    $query .= " AND h.location LIKE ?";
    $location_param = "%$filter_location%";
    $params[] = $location_param;
    $types .= "s";
}

if ($filter_rating) {
    $query .= " AND h.star_rating = ?";
    $params[] = $filter_rating;
    $types .= "i";
}

if ($search) {
    $query .= " AND (h.hotel_name LIKE ? OR h.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$query .= " GROUP BY h.hotel_id ORDER BY h.hotel_name";

if ($types) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $hotels_result = $stmt->get_result();
} else {
    $hotels_result = $conn->query($query);
}

// Get all room types for modals
$room_types_query = "SELECT rt.*, h.hotel_name FROM room_types rt 
                    JOIN hotels h ON rt.hotel_id = h.hotel_id 
                    ORDER BY h.hotel_name, rt.room_type_name";
$all_room_types = $conn->query($room_types_query);

// Get statistics
$active_hotels = $conn->query("SELECT COUNT(*) as count FROM hotels WHERE is_active = 1")->fetch_assoc()['count'];
$total_rooms = $conn->query("SELECT SUM(available_rooms) as total FROM room_types")->fetch_assoc()['total'] ?? 0;

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
    <title>Hotels Management - Explore Hub</title>
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
        .star-rating {
            color: #ffc107;
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
                            <a class="nav-link active" href="hotels.php">
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
                    <h1 class="h2">Hotels Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHotelModal">
                            <i class="bi bi-plus-circle"></i> Add New Hotel
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
                                <h5 class="card-title">Active Hotels</h5>
                                <h3><?php echo $active_hotels; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <h5 class="card-title">Total Rooms</h5>
                                <h3><?php echo $total_rooms; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h5 class="card-title">Total Hotels</h5>
                                <h3><?php echo $hotels_result->num_rows; ?></h3>
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
                                       placeholder="Hotel name or description" value="<?php echo $search; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       placeholder="City or area" value="<?php echo $filter_location; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="rating" class="form-label">Star Rating</label>
                                <select class="form-select" id="rating" name="rating">
                                    <option value="">All Ratings</option>
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $filter_rating == $i ? 'selected' : ''; ?>>
                                            <?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">Filter</button>
                                    <a href="hotels.php" class="btn btn-secondary">Clear</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Hotels Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Hotel Name</th>
                                        <th>Location</th>
                                        <th>Rating</th>
                                        <th>Room Types</th>
                                        <th>Contact</th>
                                        <th>Bookings</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($hotel = $hotels_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $hotel['hotel_name']; ?></td>
                                        <td><?php echo $hotel['location']; ?></td>
                                        <td>
                                            <span class="star-rating">
                                                <?php for ($i = 0; $i < $hotel['star_rating']; $i++): ?>
                                                    <i class="bi bi-star-fill"></i>
                                                <?php endfor; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $hotel['room_types_count']; ?></td>
                                        <td>
                                            <?php echo $hotel['contact_number']; ?><br>
                                            <small><?php echo $hotel['email']; ?></small>
                                        </td>
                                        <td><?php echo $hotel['total_bookings']; ?></td>
                                        <td>
                                            <?php if ($hotel['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                                    data-bs-target="#viewModal<?php echo $hotel['hotel_id']; ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" 
                                                    data-bs-target="#editModal<?php echo $hotel['hotel_id']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-info" data-bs-toggle="modal" 
                                                    data-bs-target="#roomsModal<?php echo $hotel['hotel_id']; ?>">
                                                <i class="bi bi-door-open"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    
                                    <!-- View Modal -->
                                    <div class="modal fade" id="viewModal<?php echo $hotel['hotel_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Hotel Details - <?php echo $hotel['hotel_name']; ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <p><strong>Location:</strong> <?php echo $hotel['location']; ?></p>
                                                            <p><strong>Star Rating:</strong> 
                                                                <span class="star-rating">
                                                                    <?php for ($i = 0; $i < $hotel['star_rating']; $i++): ?>
                                                                        <i class="bi bi-star-fill"></i>
                                                                    <?php endfor; ?>
                                                                </span>
                                                            </p>
                                                            <p><strong>Contact Number:</strong> <?php echo $hotel['contact_number']; ?></p>
                                                            <p><strong>Email:</strong> <?php echo $hotel['email']; ?></p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <p><strong>Address:</strong> <?php echo nl2br($hotel['address']); ?></p>
                                                            <p><strong>Room Types:</strong> <?php echo $hotel['room_types_count']; ?></p>
                                                            <p><strong>Total Bookings:</strong> <?php echo $hotel['total_bookings']; ?></p>
                                                            <p><strong>Status:</strong> 
                                                                <?php if ($hotel['is_active']): ?>
                                                                    <span class="badge bg-success">Active</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-danger">Inactive</span>
                                                                <?php endif; ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if ($hotel['description']): ?>
                                                    <hr>
                                                    <h6>Description</h6>
                                                    <p><?php echo nl2br($hotel['description']); ?></p>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($hotel['amenities']): ?>
                                                    <hr>
                                                    <h6>Amenities</h6>
                                                    <p><?php echo nl2br($hotel['amenities']); ?></p>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Room Types for this hotel -->
                                                    <?php
                                                    $room_query = "SELECT * FROM room_types WHERE hotel_id = ? ORDER BY room_type_name";
                                                    $room_stmt = $conn->prepare($room_query);
                                                    $room_stmt->bind_param("i", $hotel['hotel_id']);
                                                    $room_stmt->execute();
                                                    $room_result = $room_stmt->get_result();
                                                    ?>
                                                    
                                                    <?php if ($room_result->num_rows > 0): ?>
                                                    <hr>
                                                    <h6>Room Types</h6>
                                                    <table class="table table-sm">
                                                        <thead>
                                                            <tr>
                                                                <th>Room Type</th>
                                                                <th>Capacity</th>
                                                                <th>Price/Night</th>
                                                                <th>Available</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php while ($room = $room_result->fetch_assoc()): ?>
                                                            <tr>
                                                                <td><?php echo $room['room_type_name']; ?></td>
                                                                <td><?php echo $room['capacity']; ?> persons</td>
                                                                <td>LKR <?php echo number_format($room['price_per_night'], 2); ?></td>
                                                                <td><?php echo $room['available_rooms']; ?> rooms</td>
                                                            </tr>
                                                            <?php endwhile; ?>
                                                        </tbody>
                                                    </table>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal<?php echo $hotel['hotel_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Hotel</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="hotel_id" value="<?php echo $hotel['hotel_id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Hotel Name</label>
                                                            <input type="text" class="form-control" name="hotel_name" 
                                                                   value="<?php echo $hotel['hotel_name']; ?>" required>
                                                        </div>
                                                        
                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Location</label>
                                                                <input type="text" class="form-control" name="location" 
                                                                       value="<?php echo $hotel['location']; ?>" required>
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Star Rating</label>
                                                                <select class="form-select" name="star_rating" required>
                                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                        <option value="<?php echo $i; ?>" 
                                                                                <?php echo $hotel['star_rating'] == $i ? 'selected' : ''; ?>>
                                                                            <?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?>
                                                                        </option>
                                                                    <?php endfor; ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Description</label>
                                                            <textarea class="form-control" name="description" rows="3"><?php echo $hotel['description']; ?></textarea>