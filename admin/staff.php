<?php
// admin/staff.php - Staff Management

session_start();
require_once '../config/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../modules/auth/login.php");
    exit();
}

$conn = getDBConnection();

// Handle add new staff
if (isset($_POST['add_staff'])) {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
    $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $employee_code = filter_input(INPUT_POST, 'employee_code', FILTER_SANITIZE_STRING);
    $department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING);
    $position = filter_input(INPUT_POST, 'position', FILTER_SANITIZE_STRING);
    $hire_date = $_POST['hire_date'];
    $salary = $_POST['salary'];
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert into users table
        $insert_user = "INSERT INTO users (username, email, password_hash, user_type, first_name, last_name, phone) 
                       VALUES (?, ?, ?, 'staff', ?, ?, ?)";
        $stmt = $conn->prepare($insert_user);
        $stmt->bind_param("ssssss", $username, $email, $password_hash, $first_name, $last_name, $phone);
        $stmt->execute();
        
        $user_id = $conn->insert_id;
        
        // Insert into staff table
        $insert_staff = "INSERT INTO staff (user_id, employee_code, department, position, hire_date, salary) 
                        VALUES (?, ?, ?, ?, ?, ?)";
        $stmt2 = $conn->prepare($insert_staff);
        $stmt2->bind_param("issssd", $user_id, $employee_code, $department, $position, $hire_date, $salary);
        $stmt2->execute();
        
        $conn->commit();
        $_SESSION['success_message'] = "Staff member added successfully!";
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error adding staff member: " . $e->getMessage();
    }
}

// Handle update staff
if (isset($_POST['update_staff'])) {
    $staff_id = $_POST['staff_id'];
    $user_id = $_POST['user_id'];
    $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
    $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING);
    $position = filter_input(INPUT_POST, 'position', FILTER_SANITIZE_STRING);
    $salary = $_POST['salary'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $conn->begin_transaction();
    
    try {
        // Update users table
        $update_user = "UPDATE users SET first_name = ?, last_name = ?, phone = ?, is_active = ? WHERE user_id = ?";
        $stmt = $conn->prepare($update_user);
        $stmt->bind_param("sssii", $first_name, $last_name, $phone, $is_active, $user_id);
        $stmt->execute();
        
        // Update staff table
        $update_staff = "UPDATE staff SET department = ?, position = ?, salary = ? WHERE staff_id = ?";
        $stmt2 = $conn->prepare($update_staff);
        $stmt2->bind_param("ssdi", $department, $position, $salary, $staff_id);
        $stmt2->execute();
        
        $conn->commit();
        $_SESSION['success_message'] = "Staff member updated successfully!";
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error updating staff member: " . $e->getMessage();
    }
}

// Get all staff members
$staff_query = "SELECT s.*, u.username, u.email, u.first_name, u.last_name, u.phone, u.is_active, u.created_at 
                FROM staff s 
                JOIN users u ON s.user_id = u.user_id 
                ORDER BY u.first_name, u.last_name";
$staff_result = $conn->query($staff_query);

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
    <title>Staff Management - Explore Hub Admin</title>
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
                            <a class="nav-link active" href="staff.php">
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
                    <h1 class="h2">Staff Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                            <i class="bi bi-plus-circle"></i> Add New Staff
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
                
                <!-- Staff Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee Code</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Position</th>
                                        <th>Contact</th>
                                        <th>Hire Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($staff = $staff_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $staff['employee_code']; ?></td>
                                        <td>
                                            <?php echo $staff['first_name'] . ' ' . $staff['last_name']; ?><br>
                                            <small class="text-muted">@<?php echo $staff['username']; ?></small>
                                        </td>
                                        <td><?php echo $staff['department']; ?></td>
                                        <td><?php echo $staff['position']; ?></td>
                                        <td>
                                            <?php echo $staff['email']; ?><br>
                                            <small><?php echo $staff['phone']; ?></small>
                                        </td>
                                        <td><?php echo date('Y-m-d', strtotime($staff['hire_date'])); ?></td>
                                        <td>
                                            <?php if ($staff['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                                    data-bs-target="#viewModal<?php echo $staff['staff_id']; ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" 
                                                    data-bs-target="#editModal<?php echo $staff['staff_id']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    
                                    <!-- View Modal -->
                                    <div class="modal fade" id="viewModal<?php echo $staff['staff_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Staff Details - <?php echo $staff['first_name'] . ' ' . $staff['last_name']; ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <p><strong>Employee Code:</strong> <?php echo $staff['employee_code']; ?></p>
                                                            <p><strong>Username:</strong> <?php echo $staff['username']; ?></p>
                                                            <p><strong>Email:</strong> <?php echo $staff['email']; ?></p>
                                                            <p><strong>Phone:</strong> <?php echo $staff['phone']; ?></p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <p><strong>Department:</strong> <?php echo $staff['department']; ?></p>
                                                            <p><strong>Position:</strong> <?php echo $staff['position']; ?></p>
                                                            <p><strong>Hire Date:</strong> <?php echo date('Y-m-d', strtotime($staff['hire_date'])); ?></p>
                                                            <p><strong>Salary:</strong> LKR <?php echo number_format($staff['salary'], 2); ?></p>
                                                        </div>
                                                    </div>
                                                    <hr>
                                                    <p><strong>Account Created:</strong> <?php echo date('Y-m-d H:i', strtotime($staff['created_at'])); ?></p>
                                                    <p><strong>Status:</strong> 
                                                        <?php if ($staff['is_active']): ?>
                                                            <span class="badge bg-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Inactive</span>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal<?php echo $staff['staff_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Staff Member</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="staff_id" value="<?php echo $staff['staff_id']; ?>">
                                                        <input type="hidden" name="user_id" value="<?php echo $staff['user_id']; ?>">
                                                        
                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">First Name</label>
                                                                <input type="text" class="form-control" name="first_name" 
                                                                       value="<?php echo $staff['first_name']; ?>" required>
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Last Name</label>
                                                                <input type="text" class="form-control" name="last_name" 
                                                                       value="<?php echo $staff['last_name']; ?>" required>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Phone</label>
                                                            <input type="tel" class="form-control" name="phone" 
                                                                   value="<?php echo $staff['phone']; ?>">
                                                        </div>
                                                        
                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Department</label>
                                                                <input type="text" class="form-control" name="department" 
                                                                       value="<?php echo $staff['department']; ?>" required>
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Position</label>
                                                                <input type="text" class="form-control" name="position" 
                                                                       value="<?php echo $staff['position']; ?>" required>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Salary</label>
                                                            <input type="number" class="form-control" name="salary" 
                                                                   value="<?php echo $staff['salary']; ?>" step="0.01" required>
                                                        </div>
                                                        
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="is_active" 
                                                                   id="is_active<?php echo $staff['staff_id']; ?>" 
                                                                   <?php echo $staff['is_active'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="is_active<?php echo $staff['staff_id']; ?>">
                                                                Active Account
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="update_staff" class="btn btn-primary">Update Staff</button>
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
    
    <!-- Add Staff Modal -->
    <div class="modal fade" id="addStaffModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Staff Member</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                            <small class="form-text text-muted">Minimum 8 characters</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Employee Code</label>
                            <input type="text" class="form-control" name="employee_code" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department</label>
                                <input type="text" class="form-control" name="department" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Position</label>
                                <input type="text" class="form-control" name="position" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Hire Date</label>
                                <input type="date" class="form-control" name="hire_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Salary</label>
                                <input type="number" class="form-control" name="salary" step="0.01" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_staff" class="btn btn-primary">Add Staff</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>