<?php
// includes/functions.php - Helper Functions

/**
 * Generate a unique booking reference
 */
function generateBookingReference() {
    return 'EH' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    return 'LKR ' . number_format($amount, 2);
}

/**
 * Check if user has permission
 */
function hasPermission($required_type) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    if (is_array($required_type)) {
        return in_array($_SESSION['user_type'], $required_type);
    }
    
    return $_SESSION['user_type'] === $required_type;
}

/**
 * Redirect with message
 */
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION[$type . '_message'] = $message;
    header("Location: $url");
    exit();
}

/**
 * Get status badge HTML
 */
function getStatusBadge($status, $type = 'booking') {
    $classes = [
        'booking' => [
            'pending' => 'warning',
            'confirmed' => 'success',
            'cancelled' => 'danger',
            'completed' => 'info'
        ],
        'payment' => [
            'pending' => 'danger',
            'partial' => 'warning',
            'paid' => 'success',
            'refunded' => 'secondary'
        ],
        'user' => [
            'active' => 'success',
            'inactive' => 'danger'
        ]
    ];
    
    $class = isset($classes[$type][$status]) ? $classes[$type][$status] : 'secondary';
    return '<span class="badge bg-' . $class . '">' . ucfirst($status) . '</span>';
}

/**
 * Calculate date difference in days
 */
function getDateDifference($start_date, $end_date) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    return $start->diff($end)->days;
}

/**
 * Validate date is in future
 */
function isFutureDate($date) {
    return strtotime($date) > strtotime('today');
}

/**
 * Get user's full name
 */
function getUserFullName($user_id, $conn) {
    $stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        return $user['first_name'] . ' ' . $user['last_name'];
    }
    
    return 'Unknown User';
}

/**
 * Log user activity
 */
function logActivity($user_id, $action, $module, $description = null, $conn) {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, module, description, ip_address) 
                           VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $action, $module, $description, $ip_address);
    return $stmt->execute();
}

/**
 * Send email notification (placeholder - implement with PHPMailer)
 */
function sendEmail($to, $subject, $message, $from = null) {
    // This is a placeholder function
    // In production, implement this with PHPMailer or similar
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: ' . ($from ?? 'noreply@explorehub.lk') . "\r\n";
    
    // For development, just return true
    // return mail($to, $subject, $message, $headers);
    return true;
}

/**
 * Generate booking confirmation email
 */
function sendBookingConfirmation($booking_id, $conn) {
    $query = "SELECT b.*, u.email, u.first_name, u.last_name 
              FROM bookings b 
              JOIN users u ON b.customer_id = u.user_id 
              WHERE b.booking_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    $subject = "Booking Confirmation - " . $booking['booking_reference'];
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .header { background-color: #2c3e50; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .details { background-color: #f8f9fa; padding: 15px; margin: 20px 0; }
            .footer { background-color: #2c3e50; color: white; padding: 10px; text-align: center; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>Explore Hub</h1>
            <p>Your Adventure Awaits!</p>
        </div>
        <div class='content'>
            <h2>Dear {$booking['first_name']},</h2>
            <p>Thank you for your booking! Your adventure is confirmed.</p>
            
            <div class='details'>
                <h3>Booking Details</h3>
                <p><strong>Booking Reference:</strong> {$booking['booking_reference']}</p>
                <p><strong>Travel Date:</strong> " . date('F d, Y', strtotime($booking['travel_date'])) . "</p>
                <p><strong>End Date:</strong> " . date('F d, Y', strtotime($booking['end_date'])) . "</p>
                <p><strong>Total Amount:</strong> LKR " . number_format($booking['total_amount'], 2) . "</p>
                <p><strong>Status:</strong> " . ucfirst($booking['booking_status']) . "</p>
            </div>
            
            <p>If you have any questions, please don't hesitate to contact us.</p>
            <p>Best regards,<br>The Explore Hub Team</p>
        </div>
        <div class='footer'>
            <p>&copy; 2025 Explore Hub. All rights reserved.</p>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($booking['email'], $subject, $message);
}

/**
 * Calculate customer loyalty level
 */
function getCustomerLoyaltyLevel($total_spent) {
    if ($total_spent >= 500000) {
        return ['level' => 'Platinum', 'color' => 'dark', 'discount' => 15];
    } elseif ($total_spent >= 250000) {
        return ['level' => 'Gold', 'color' => 'warning', 'discount' => 10];
    } elseif ($total_spent >= 100000) {
        return ['level' => 'Silver', 'color' => 'secondary', 'discount' => 5];
    } else {
        return ['level' => 'Bronze', 'color' => 'info', 'discount' => 0];
    }
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Validate Sri Lankan phone number
 */
function validateSriLankanPhone($phone) {
    // Remove spaces and dashes
    $phone = preg_replace('/[\s\-]/', '', $phone);
    
    // Check if it matches Sri Lankan phone format
    // Formats: +94XXXXXXXXX, 94XXXXXXXXX, 0XXXXXXXXX
    return preg_match('/^(\+94|94|0)?[0-9]{9}$/', $phone);
}

/**
 * Get tour difficulty icon
 */
function getTourDifficultyIcon($difficulty) {
    $icons = [
        'easy' => '<i class="bi bi-circle-fill text-success"></i>',
        'moderate' => '<i class="bi bi-circle-fill text-warning"></i>',
        'difficult' => '<i class="bi bi-circle-fill text-danger"></i>'
    ];
    
    return isset($icons[$difficulty]) ? $icons[$difficulty] : '<i class="bi bi-circle"></i>';
}

/**
 * Calculate tour availability
 */
function getTourAvailability($tour_id, $date, $conn) {
    $query = "SELECT 
                t.max_participants,
                COALESCE(SUM(bi.quantity), 0) as booked
              FROM tours t
              LEFT JOIN booking_items bi ON t.tour_id = bi.item_id AND bi.item_type = 'tour'
              LEFT JOIN bookings b ON bi.booking_id = b.booking_id
              WHERE t.tour_id = ?
              AND b.travel_date = ?
              AND b.booking_status IN ('pending', 'confirmed')
              GROUP BY t.tour_id";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $tour_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $available = $data['max_participants'] - $data['booked'];
        return max(0, $available);
    }
    
    return 0;
}

/**
 * Get room availability
 */
function getRoomAvailability($room_type_id, $start_date, $end_date, $conn) {
    $query = "SELECT 
                rt.available_rooms,
                COALESCE(SUM(bi.quantity), 0) as booked
              FROM room_types rt
              LEFT JOIN booking_items bi ON rt.room_type_id = bi.item_id AND bi.item_type = 'hotel'
              LEFT JOIN bookings b ON bi.booking_id = b.booking_id
              WHERE rt.room_type_id = ?
              AND b.booking_status IN ('pending', 'confirmed')
              AND (
                  (b.travel_date <= ? AND b.end_date >= ?) OR
                  (b.travel_date <= ? AND b.end_date >= ?) OR
                  (b.travel_date >= ? AND b.end_date <= ?)
              )
              GROUP BY rt.room_type_id";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("issssss", $room_type_id, $start_date, $start_date, 
                      $end_date, $end_date, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $available = $data['available_rooms'] - $data['booked'];
        return max(0, $available);
    }
    
    return 0;
}

/**
 * Sanitize input
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    
    return $input;
}

/**
 * Generate random password
 */
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return substr(str_shuffle($chars), 0, $length);
}

/**
 * Check if booking can be cancelled
 */
function canCancelBooking($booking_date, $travel_date) {
    $hours_until_travel = (strtotime($travel_date) - time()) / 3600;
    return $hours_until_travel >= 48; // 48 hours cancellation policy
}

/**
 * Calculate cancellation fee
 */
function calculateCancellationFee($total_amount, $hours_until_travel) {
    if ($hours_until_travel >= 168) { // 7 days or more
        return 0; // No fee
    } elseif ($hours_until_travel >= 72) { // 3-7 days
        return $total_amount * 0.25; // 25% fee
    } elseif ($hours_until_travel >= 48) { // 2-3 days
        return $total_amount * 0.50; // 50% fee
    } else {
        return $total_amount; // 100% fee
    }
}

/**
 * Get booking summary
 */
function getBookingSummary($booking_id, $conn) {
    $query = "SELECT 
                b.*,
                GROUP_CONCAT(
                    CASE 
                        WHEN bi.item_type = 'tour' THEN (SELECT tour_name FROM tours WHERE tour_id = bi.item_id)
                        WHEN bi.item_type = 'hotel' THEN (
                            SELECT CONCAT(h.hotel_name, ' - ', rt.room_type_name) 
                            FROM room_types rt 
                            JOIN hotels h ON rt.hotel_id = h.hotel_id 
                            WHERE rt.room_type_id = bi.item_id
                        )
                    END SEPARATOR ', '
                ) as items,
                u.first_name,
                u.last_name,
                u.email,
                u.phone
              FROM bookings b
              LEFT JOIN booking_items bi ON b.booking_id = bi.booking_id
              LEFT JOIN users u ON b.customer_id = u.user_id
              WHERE b.booking_id = ?
              GROUP BY b.booking_id";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

/**
 * Get time ago string
 */
function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return formatDate($datetime);
    }
}

/**
 * Validate image upload
 */
function validateImageUpload($file, $max_size = 5242880) { // 5MB default
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $allowed_extensions = ['jpeg', 'jpg', 'png', 'gif'];
    
    // Check if file was uploaded
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['valid' => false, 'error' => 'No file uploaded'];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        return ['valid' => false, 'error' => 'File size exceeds limit (' . formatFileSize($max_size) . ')'];
    }
    
    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        return ['valid' => false, 'error' => 'Invalid file type'];
    }
    
    // Check extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed_extensions)) {
        return ['valid' => false, 'error' => 'Invalid file extension'];
    }
    
    return ['valid' => true, 'extension' => $extension];
}

/**
 * Upload image file
 */
function uploadImage($file, $directory, $prefix = '') {
    $validation = validateImageUpload($file);
    
    if (!$validation['valid']) {
        return ['success' => false, 'error' => $validation['error']];
    }
    
    // Generate unique filename
    $filename = $prefix . uniqid() . '.' . $validation['extension'];
    $filepath = $directory . '/' . $filename;
    
    // Create directory if it doesn't exist
    if (!file_exists($directory)) {
        mkdir($directory, 0777, true);
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
    } else {
        return ['success' => false, 'error' => 'Failed to upload file'];
    }
}

/**
 * Get dashboard statistics based on user type
 */
function getDashboardStats($user_id, $user_type, $conn) {
    $stats = [];
    
    switch ($user_type) {
        case 'admin':
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
            break;
            
        case 'staff':
            // Today's bookings
            $result = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE DATE(booking_date) = CURDATE()");
            $stats['today_bookings'] = $result->fetch_assoc()['count'];
            
            // This month's revenue
            $result = $conn->query("SELECT SUM(paid_amount) as total FROM bookings 
                                   WHERE payment_status = 'paid' AND MONTH(booking_date) = MONTH(CURDATE())");
            $stats['month_revenue'] = $result->fetch_assoc()['total'] ?? 0;
            
            // Active tours
            $result = $conn->query("SELECT COUNT(*) as count FROM tours WHERE is_active = 1");
            $stats['active_tours'] = $result->fetch_assoc()['count'];
            
            // Low inventory items
            $result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE quantity < 5 AND is_available = 1");
            $stats['low_inventory'] = $result->fetch_assoc()['count'];
            break;
            
        case 'customer':
            // Total bookings
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE customer_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stats['total_bookings'] = $stmt->get_result()->fetch_assoc()['count'];
            
            // Active bookings
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings 
                                   WHERE customer_id = ? AND booking_status IN ('pending', 'confirmed')");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stats['active_bookings'] = $stmt->get_result()->fetch_assoc()['count'];
            
            // Completed trips
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings 
                                   WHERE customer_id = ? AND booking_status = 'completed'");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stats['completed_trips'] = $stmt->get_result()->fetch_assoc()['count'];
            
            // Total spent
            $stmt = $conn->prepare("SELECT SUM(paid_amount) as total FROM bookings 
                                   WHERE customer_id = ? AND payment_status = 'paid'");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stats['total_spent'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
            break;
    }
    
    return $stats;
}

/**
 * Export data to CSV
 */
function exportToCSV($data, $filename, $headers = null) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add headers if provided
    if ($headers) {
        fputcsv($output, $headers);
    }
    
    // Add data rows
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}

/**
 * Check if system is in maintenance mode
 */
function isMaintenanceMode() {
    // This could be controlled by a config file or database setting
    return false;
}

/**
 * Get system settings
 */
function getSystemSettings($conn) {
    // This is a placeholder - you would create a settings table
    return [
        'site_name' => 'Explore Hub',
        'currency' => 'LKR',
        'timezone' => 'Asia/Colombo',
        'booking_cancel_hours' => 48,
        'max_booking_days' => 365,
        'min_booking_days' => 1
    ];
}
?>