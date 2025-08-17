<?php
// error.php - Error Handling Page

session_start();

// Get error code from URL parameter
$error_code = isset($_GET['code']) ? $_GET['code'] : '404';

// Define error messages
$errors = [
    '400' => [
        'title' => 'Bad Request',
        'message' => 'The request could not be understood by the server.',
        'icon' => 'exclamation-triangle'
    ],
    '401' => [
        'title' => 'Unauthorized',
        'message' => 'You need to be logged in to access this page.',
        'icon' => 'lock'
    ],
    '403' => [
        'title' => 'Access Denied',
        'message' => 'You do not have permission to access this resource.',
        'icon' => 'x-octagon'
    ],
    '404' => [
        'title' => 'Page Not Found',
        'message' => 'The page you are looking for could not be found.',
        'icon' => 'search'
    ],
    '500' => [
        'title' => 'Internal Server Error',
        'message' => 'Something went wrong on our end. Please try again later.',
        'icon' => 'server'
    ],
    'maintenance' => [
        'title' => 'Under Maintenance',
        'message' => 'We are currently performing scheduled maintenance. Please check back soon.',
        'icon' => 'tools'
    ]
];

// Get error details
$error = isset($errors[$error_code]) ? $errors[$error_code] : $errors['404'];

// HTTP response code
if (is_numeric($error_code)) {
    http_response_code($error_code);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $error['title']; ?> - Explore Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .error-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        .error-icon {
            font-size: 5rem;
            color: #667eea;
            margin-bottom: 20px;
        }
        .error-code {
            font-size: 6rem;
            font-weight: bold;
            color: #e3e3e3;
            margin: 0;
        }
        .error-title {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #333;
        }
        .error-message {
            color: #666;
            margin-bottom: 30px;
        }
        .btn-home {
            background-color: #667eea;
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-home:hover {
            background-color: #764ba2;
            color: white;
            transform: translateY(-2px);
        }
        .suggestions {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #e3e3e3;
        }
        .suggestions h5 {
            color: #666;
            margin-bottom: 15px;
        }
        .suggestions a {
            color: #667eea;
            text-decoration: none;
            margin: 0 10px;
        }
        .suggestions a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <i class="bi bi-<?php echo $error['icon']; ?>"></i>
        </div>
        
        <?php if (is_numeric($error_code)): ?>
        <h1 class="error-code"><?php echo $error_code; ?></h1>
        <?php endif; ?>
        
        <h2 class="error-title"><?php echo $error['title']; ?></h2>
        <p class="error-message"><?php echo $error['message']; ?></p>
        
        <?php if ($error_code == '401'): ?>
            <a href="modules/auth/login.php" class="btn-home">
                <i class="bi bi-box-arrow-in-right"></i> Go to Login
            </a>
        <?php else: ?>
            <a href="index.php" class="btn-home">
                <i class="bi bi-house"></i> Back to Home
            </a>
        <?php endif; ?>
        
        <div class="suggestions">
            <h5>Here are some helpful links:</h5>
            <div>
                <a href="index.php">Home</a>
                <a href="index.php#tours">Tours</a>
                <a href="index.php#contact">Contact</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="customer/dashboard.php">Dashboard</a>
                <?php else: ?>
                    <a href="modules/auth/login.php">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>