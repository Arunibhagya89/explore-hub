<?php
// customer/profile.php - Customer Profile Management

session_start();
require_once '../config/config.php';

// Check if user is logged in and is customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    header("Location: ../modules/auth/login.php");
    exit();
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle profile update
if (isset($_POST['update_profile'])) {
    $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
    $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
    
    // Check if email is already taken by another user
    $email_check = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $email_check->bind_param("si", $email, $user_id);
    $email_check->execute();
    $result = $email_check->get_result();
    
    if ($result->num_rows > 0) {
        $error_message = "Email address is already in use by another account.";
    } else {
        $update_sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ? WHERE user_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sssssi", $first_name, $last_name, $email, $phone, $address, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['full_name'] = $first_name . ' ' . $last_name;
            $success_message = "Profile updated successfully!";
        } else {
            $error_message = "Error updating profile. Please try again.";
        }
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    $pass_check = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $pass_check->bind_param("i", $user_id);
    $pass_check->execute();
    $result = $pass_check->get_result();
    $user = $result->fetch_assoc();
    
    if (!password_verify($current_password, $user['password_hash'])) {
        $error_message = "Current password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } else {
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update_pass = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $update_pass->bind_param("si", $password_hash, $user_id);
        
        if ($update_pass->execute()) {
            $success_message = "Password changed successfully!";
        } else {
            $error_message = "Error changing password. Please try again.";
        }
    }
}

// Get user information
$user_query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_info = $stmt->get_result()->fetch_assoc();

// Get booking statistics
$stats_query = "SELECT 
                COUNT(*) as total_bookings,
                SUM(CASE WHEN booking_status = 'completed' THEN 1 ELSE 0 END) as completed_trips,
                SUM(total_amount) as total_spent,
                SUM(paid_amount) as total_paid
                FROM bookings WHERE customer_id = ?";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Explore Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .navbar-custom {
            background-color: #2c3e50;
        }
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 30px;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: white;
            color: #667eea;
            font-size: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .stat-box {
            text-align: center;
            padding: 20px;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            margin: 10px;
        }
        .nav-pills .nav-link {
            color: #6c757d;
        }
        .nav-pills .nav-link.active {
            background-color: #667eea;
        }
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
                        <a class="nav-link" href="dashboard.php">
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
                        <a class="nav-link active" href="profile.php">
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
    
    <!-- Profile Header -->
    <div class="profile-header">
        <div class="container text-center">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($user_info['first_name'], 0, 1) . substr($user_info['last_name'], 0, 1)); ?>
            </div>
            <h2><?php echo $user_info['first_name'] . ' ' . $user_info['last_name']; ?></h2>
            <p>Member since <?php echo date('F Y', strtotime($user_info['created_at'])); ?></p>
            
            <div class="row justify-content-center mt-4">
                <div class="col-md-2">
                    <div class="stat-box">
                        <h4><?php echo $stats['total_bookings'] ?? 0; ?></h4>
                        <small>Total Bookings</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-box">
                        <h4><?php echo $stats['completed_trips'] ?? 0; ?></h4>
                        <small>Completed Trips</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <h5>LKR <?php echo number_format($stats['total_spent'] ?? 0, 2); ?></h5>
                        <small>Total Spent</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container">
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
        
        <div class="row">
            <div class="col-md-3">
                <!-- Profile Navigation -->
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-pills flex-column" id="profileTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="info-tab" data-bs-toggle="tab" href="#info" role="tab">
                                    <i class="bi bi-person"></i> Personal Information
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="security-tab" data-bs-toggle="tab" href="#security" role="tab">
                                    <i class="bi bi-shield-lock"></i> Security
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="preferences-tab" data-bs-toggle="tab" href="#preferences" role="tab">
                                    <i class="bi bi-gear"></i> Preferences
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-9">
                <div class="tab-content" id="profileTabContent">
                    <!-- Personal Information Tab -->
                    <div class="tab-pane fade show active" id="info" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Personal Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="first_name" class="form-label">First Name</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                                   value="<?php echo $user_info['first_name']; ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="last_name" class="form-label">Last Name</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                                   value="<?php echo $user_info['last_name']; ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo $user_info['email']; ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo $user_info['phone']; ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo $user_info['address']; ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control" value="<?php echo $user_info['username']; ?>" disabled>
                                        <small class="text-muted">Username cannot be changed</small>
                                    </div>
                                    
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="bi bi-check-circle"></i> Update Profile
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Security Tab -->
                    <div class="tab-pane fade" id="security" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Change Password</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" 
                                               name="current_password" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" 
                                               name="new_password" required>
                                        <small class="text-muted">Minimum 8 characters</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" 
                                               name="confirm_password" required>
                                    </div>
                                    
                                    <button type="submit" name="change_password" class="btn btn-primary">
                                        <i class="bi bi-key"></i> Change Password
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0">Account Security</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h6 class="mb-0">Two-Factor Authentication</h6>
                                        <small class="text-muted">Add an extra layer of security to your account</small>
                                    </div>
                                    <button class="btn btn-outline-primary btn-sm" disabled>Coming Soon</button>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0">Login Notifications</h6>
                                        <small class="text-muted">Get notified when someone logs into your account</small>
                                    </div>
                                    <button class="btn btn-outline-primary btn-sm" disabled>Coming Soon</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Preferences Tab -->
                    <div class="tab-pane fade" id="preferences" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Email Preferences</h5>
                            </div>
                            <div class="card-body">
                                <form>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="booking_emails" checked>
                                        <label class="form-check-label" for="booking_emails">
                                            <strong>Booking Confirmations</strong><br>
                                            <small class="text-muted">Receive emails about your bookings and payments</small>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="promo_emails" checked>
                                        <label class="form-check-label" for="promo_emails">
                                            <strong>Promotions & Offers</strong><br>
                                            <small class="text-muted">Get exclusive deals and special offers</small>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="newsletter_emails">
                                        <label class="form-check-label" for="newsletter_emails">
                                            <strong>Newsletter</strong><br>
                                            <small class="text-muted">Monthly updates about new tours and destinations</small>
                                        </label>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary" disabled>
                                        <i class="bi bi-check-circle"></i> Save Preferences (Coming Soon)
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card mt-4">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0">Danger Zone</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Delete Account</strong></p>
                                <p class="text-muted">Once you delete your account, there is no going back. Please be certain.</p>
                                <button class="btn btn-danger" disabled>
                                    <i class="bi bi-trash"></i> Delete Account (Contact Support)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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