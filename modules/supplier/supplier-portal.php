<?php
// supplier/index.php - Supplier Portal Login

session_start();
require_once '../config/config.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['supplier_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error_message = "Email and password are required";
    } else {
        $conn = getDBConnection();
        
        // For this example, we'll use email as username and a simple password system
        // In production, you should implement proper authentication
        $sql = "SELECT supplier_id, supplier_name, email, contact_person, is_active 
                FROM suppliers 
                WHERE email = ? AND is_active = 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $supplier = $result->fetch_assoc();
            
            // Simple password check (in production, use proper password hashing)
            // For demo: password is 'supplier' + supplier_id (e.g., supplier1, supplier2)
            $expected_password = 'supplier' . $supplier['supplier_id'];
            
            if ($password === $expected_password) {
                // Set session variables
                $_SESSION['supplier_id'] = $supplier['supplier_id'];
                $_SESSION['supplier_name'] = $supplier['supplier_name'];
                $_SESSION['contact_person'] = $supplier['contact_person'];
                $_SESSION['supplier_email'] = $supplier['email'];
                $_SESSION['user_type'] = 'supplier';
                
                header("Location: dashboard.php");
                exit();
            } else {
                $error_message = "Invalid email or password";
            }
        } else {
            $error_message = "Invalid email or password";
        }
        
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Portal - Explore Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            max-width: 400px;
            margin: 0 auto;
        }
        .login-card {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo i {
            font-size: 3rem;
            color: #667eea;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: #667eea;
            border-color: #667eea;
        }
        .btn-primary:hover {
            background: #764ba2;
            border-color: #764ba2;
        }
        .supplier-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-card">
                <div class="logo">
                    <i class="bi bi-truck"></i>
                    <h3 class="mt-3">Supplier Portal</h3>
                    <p class="text-muted">Explore Hub Partner Access</p>
                </div>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-envelope"></i>
                            </span>
                            <input type="email" class="form-control" id="email" name="email" required autofocus>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right"></i> Login to Portal
                    </button>
                </form>
                
                <div class="supplier-info">
                    <h6>Demo Access:</h6>
                    <small class="text-muted">
                        Use your registered email and password format: 'supplier' + your ID<br>
                        Example: Email: supplier@example.com, Password: supplier1
                    </small>
                </div>
                
                <div class="text-center mt-4">
                    <a href="../index.php" class="text-muted">
                        <i class="bi bi-arrow-left"></i> Back to Main Site
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>