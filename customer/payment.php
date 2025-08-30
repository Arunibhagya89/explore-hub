<?php
// customer/payment.php - Payment Processing Page

session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
header("Location: ../modules/auth/login.php");
exit();
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$booking_reference = isset($_GET['booking']) ? $_GET['booking'] : '';

// Get booking details
if ($booking_reference) {
$booking_query = "SELECT b.*,
GROUP_CONCAT(
CASE
WHEN bi.item_type = 'tour' THEN (SELECT tour_name FROM tours WHERE tour_id = bi.item_id)
WHEN bi.item_type = 'hotel' THEN (
SELECT CONCAT(h.hotel_name, ' - ', rt.room_type_name)
FROM room_types rt
JOIN hotels h ON rt.hotel_id = h.hotel_id
WHERE rt.room_type_id = bi.item_id
)
END SEPARATOR '<br>'
) as items
FROM bookings b
LEFT JOIN booking_items bi ON b.booking_id = bi.booking_id
WHERE b.booking_reference = ? AND b.customer_id = ?
GROUP BY b.booking_id";

$stmt = $conn->prepare($booking_query);
$stmt->bind_param("si", $booking_reference, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
$_SESSION['error_message'] = "Booking not found or access denied.";
header("Location: dashboard.php");
exit();
}

$booking = $result->fetch_assoc();
$amount_due = $booking['total_amount'] - $booking['paid_amount'];
} else {
header("Location: dashboard.php");
exit();
}

// Process payment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_payment'])) {
$payment_amount = $_POST['payment_amount'];
$payment_method = $_POST['payment_method'];
$card_number = isset($_POST['card_number']) ? $_POST['card_number'] : '';
$card_holder = isset($_POST['card_holder']) ? filter_input(INPUT_POST, 'card_holder', FILTER_SANITIZE_STRING) : '';

// Validate payment amount
if ($payment_amount <= 0 || $payment_amount > $amount_due) {
$_SESSION['error_message'] = "Invalid payment amount.";
} else {
// Generate transaction reference
$transaction_reference = 'PAY' . date('YmdHis') . rand(1000, 9999);

// Update booking payment
$new_paid_amount = $booking['paid_amount'] + $payment_amount;
$payment_status = ($new_paid_amount >= $booking['total_amount']) ? 'paid' : 'partial';

$conn->begin_transaction();

try {
// Update booking
$update_booking = "UPDATE bookings SET paid_amount = ?, payment_status = ? WHERE booking_id = ?";
$stmt = $conn->prepare($update_booking);
$stmt->bind_param("dsi", $new_paid_amount, $payment_status, $booking['booking_id']);
$stmt->execute();

// Insert payment record
$insert_payment = "INSERT INTO payments (booking_id, amount, payment_method, transaction_reference, payment_status, processed_by)
VALUES (?, ?, ?, ?, 'completed', ?)";
$stmt = $conn->prepare($insert_payment);
$stmt->bind_param("idssi", $booking['booking_id'], $payment_amount, $payment_method, $transaction_reference, $user_id);
$stmt->execute();

// If booking is fully paid and pending, auto-confirm it
if ($payment_status == 'paid' && $booking['booking_status'] == 'pending') {
$confirm_booking = "UPDATE bookings SET booking_status = 'confirmed' WHERE booking_id = ?";
$stmt = $conn->prepare($confirm_booking);
$stmt->bind_param("i", $booking['booking_id']);
$stmt->execute();
}

$conn->commit();

// Log activity
logActivity($user_id, 'payment', 'booking', "Payment of LKR $payment_amount for booking $booking_reference", $conn);

// Send confirmation email (placeholder)
// sendPaymentConfirmation($booking['booking_id'], $payment_amount, $conn);

$_SESSION['success_message'] = "Payment processed successfully! Transaction Reference: $transaction_reference";
header("Location: booking-details.php?id=" . $booking['booking_id']);
exit();

} catch (Exception $e) {
$conn->rollback();
$_SESSION['error_message'] = "Payment processing failed. Please try again.";
}
}
}

// Get payment history for this booking
$payment_history_query = "SELECT * FROM payments WHERE booking_id = ? ORDER BY payment_date DESC";
$stmt = $conn->prepare($payment_history_query);
$stmt->bind_param("i", $booking['booking_id']);
$stmt->execute();
$payment_history = $stmt->get_result();

$conn->close();
?>

<!DOCTYPE html>

<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Make Payment - Explore Hub</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
.navbar-custom {
background-color: #2c3e50;
}
.payment-container {
max-width: 800px;
margin: 0 auto;
padding: 20px;
}
.payment-summary {
background: #f8f9fa;
padding: 20px;
border-radius: 10px;
margin-bottom: 20px;
}
.payment-method-card {
border: 2px solid #dee2e6;
padding: 20px;
margin-bottom: 15px;
cursor: pointer;
transition: all 0.3s;
}
.payment-method-card:hover {
border-color: #007bff;
background: #f8f9fa;
}
.payment-method-card.selected {
border-color: #007bff;
background: #e7f3ff;
}
.card-input-group {
display: none;
}
.card-input-group.active {
display: block;
}
.amount-options {
display: flex;
gap: 10px;
margin-bottom: 15px;
}
.amount-option {
flex: 1;
padding: 10px;
text-align: center;
border: 1px solid #dee2e6;
border-radius: 5px;
cursor: pointer;
transition: all 0.3s;
}
.amount-option:hover {
background: #f8f9fa;
}
.amount-option.selected {
background: #007bff;
color: white;
border-color: #007bff;
}
.security-badges {
display: flex;
justify-content: center;
gap: 20px;
margin-top: 20px;
padding: 20px;
background: #f8f9fa;
border-radius: 5px;
}
.security-badge {
text-align: center;
color: #6c757d;
}
.security-badge i {
font-size: 2rem;
display: block;
margin-bottom: 5px;
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
<a class="nav-link" href="my-bookings.php">
<i class="bi bi-calendar-check"></i> My Bookings
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

```
<div class="container mt-4">
<div class="payment-container">
<h2 class="mb-4">Complete Your Payment</h2>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
<?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Booking Summary -->
<div class="payment-summary">
<h5>Booking Summary</h5>
<div class="row">
<div class="col-md-6">
<p><strong>Booking Reference:</strong> <?php echo $booking['booking_reference']; ?></p>
<p><strong>Travel Date:</strong> <?php echo date('F d, Y', strtotime($booking['travel_date'])); ?></p>
<p><strong>Services:</strong><br><?php echo $booking['items']; ?></p>
</div>
<div class="col-md-6">
<p><strong>Total Amount:</strong> LKR <?php echo number_format($booking['total_amount'], 2); ?></p>
<p><strong>Paid Amount:</strong> LKR <?php echo number_format($booking['paid_amount'], 2); ?></p>
<h4 class="text-primary"><strong>Amount Due:</strong> LKR <?php echo number_format($amount_due, 2); ?></h4>
</div>
</div>
</div>

<?php if ($amount_due > 0): ?>
<!-- Payment Form -->
<form method="POST" action="" id="paymentForm">
<div class="card">
<div class="card-header">
<h5 class="mb-0">Payment Details</h5>
</div>
<div class="card-body">
<!-- Amount Selection -->
<div class="mb-4">
<label class="form-label">Payment Amount</label>
<div class="amount-options">
<div class="amount-option" data-amount="<?php echo $amount_due * 0.5; ?>">
Pay 50%<br>
<small>LKR <?php echo number_format($amount_due * 0.5, 2); ?></small>
</div>
<div class="amount-option selected" data-amount="<?php echo $amount_due; ?>">
Pay Full<br>
<small>LKR <?php echo number_format($amount_due, 2); ?></small>
</div>
<div class="amount-option" data-amount="custom">
Custom<br>
<small>Amount</small>
</div>
</div>
<input type="number" class="form-control" id="payment_amount" name="payment_amount"
value="<?php echo $amount_due; ?>" max="<?php echo $amount_due; ?>"
step="0.01" required readonly>
</div>

<!-- Payment Method -->
<div class="mb-4">
<label class="form-label">Payment Method</label>

<div class="payment-method-card selected" data-method="card">
<div class="d-flex justify-content-between align-items-center">
<div>
<i class="bi bi-credit-card"></i> Credit/Debit Card
<small class="text-muted d-block">Secure payment via card</small>
</div>
<input type="radio" name="payment_method" value="card" checked>
</div>
</div>

<div class="payment-method-card" data-method="bank_transfer">
<div class="d-flex justify-content-between align-items-center">
<div>
<i class="bi bi-bank"></i> Bank Transfer
<small class="text-muted d-block">Direct bank transfer</small>
</div>
<input type="radio" name="payment_method" value="bank_transfer">
</div>
</div>

<div class="payment-method-card" data-method="online">
<div class="d-flex justify-content-between align-items-center">
<div>
<i class="bi bi-phone"></i> Online Banking
<small class="text-muted d-block">Pay via online banking</small>
</div>
<input type="radio" name="payment_method" value="online">
</div>
</div>
</div>

<!-- Card Details (shown for card payment) -->
<div class="card-input-group active" id="cardDetails">
<div class="row">
<div class="col-md-12 mb-3">
<label class="form-label">Card Number</label>
<input type="text" class="form-control" name="card_number"
placeholder="1234 5678 9012 3456" maxlength="19">
<small class="text-muted">Demo: Use any 16-digit number</small>
</div>
<div class="col-md-8 mb-3">
<label class="form-label">Card Holder Name</label>
<input type="text" class="form-control" name="card_holder"
placeholder="JOHN DOE">
</div>
<div class="col-md-2 mb-3">
<label class="form-label">Expiry</label>
<input type="text" class="form-control" name="expiry"
placeholder="MM/YY" maxlength="5">
</div>
<div class="col-md-2 mb-3">
<label class="form-label">CVV</label>
<input type="text" class="form-control" name="cvv"
placeholder="123" maxlength="3">
</div>
</div>
</div>

<!-- Bank Transfer Details (hidden by default) -->
<div class="card-input-group" id="bankDetails">
<div class="alert alert-info">
<h6>Bank Transfer Instructions:</h6>
<p class="mb-0">
Bank: Commercial Bank of Ceylon<br>
Account Name: Explore Hub (Pvt) Ltd<br>
Account Number: 1234567890<br>
Branch: Colombo
</p>
<p class="mt-2 mb-0">
<strong>Important:</strong> Use booking reference <?php echo $booking['booking_reference']; ?> as the reference.
</p>
</div>
</div>

<button type="submit" name="process_payment" class="btn btn-primary btn-lg w-100">
<i class="bi bi-lock"></i> Pay LKR <span id="payAmount"><?php echo number_format($amount_due, 2); ?></span>
</button>
</div>
</div>
</form>

<!-- Security Badges -->
<div class="security-badges">
<div class="security-badge">
<i class="bi bi-shield-check"></i>
<small>Secure Payment</small>
</div>
<div class="security-badge">
<i class="bi bi-lock"></i>
<small>SSL Encrypted</small>
</div>
<div class="security-badge">
<i class="bi bi-patch-check"></i>
<small>PCI Compliant</small>
</div>
</div>

<?php else: ?>
<div class="alert alert-success text-center">
<i class="bi bi-check-circle"></i> This booking has been fully paid. Thank you!
</div>
<?php endif; ?>

<!-- Payment History -->
<?php if ($payment_history->num_rows > 0): ?>
<div class="card mt-4">
<div class="card-header">
<h5 class="mb-0">Payment History</h5>
</div>
<div class="card-body">
<div class="table-responsive">
<table class="table">
<thead>
<tr>
<th>Date</th>
<th>Amount</th>
<th>Method</th>
<th>Reference</th>
<th>Status</th>
</tr>
</thead>
<tbody>
<?php while ($payment = $payment_history->fetch_assoc()): ?>
<tr>
<td><?php echo date('M d, Y H:i', strtotime($payment['payment_date'])); ?></td>
<td>LKR <?php echo number_format($payment['amount'], 2); ?></td>
<td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
<td><?php echo $payment['transaction_reference']; ?></td>
<td>
<?php
$status_class = $payment['payment_status'] == 'completed' ? 'success' :
($payment['payment_status'] == 'pending' ? 'warning' : 'danger');
?>
<span class="badge bg-<?php echo $status_class; ?>">
<?php echo ucfirst($payment['payment_status']); ?>
</span>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>
</div>
<?php endif; ?>

<div class="text-center mt-4">
<a href="my-bookings.php" class="btn btn-secondary">
<i class="bi bi-arrow-left"></i> Back to My Bookings
</a>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Payment method selection
document.querySelectorAll('.payment-method-card').forEach(card => {
card.addEventListener('click', function() {
// Remove selected class from all cards
document.querySelectorAll('.payment-method-card').forEach(c => c.classList.remove('selected'));
// Add selected class to clicked card
this.classList.add('selected');
// Check the radio button
this.querySelector('input[type="radio"]').checked = true;

// Show/hide payment details
document.querySelectorAll('.card-input-group').forEach(group => group.classList.remove('active'));
if (this.dataset.method === 'card') {
document.getElementById('cardDetails').classList.add('active');
} else if (this.dataset.method === 'bank_transfer') {
document.getElementById('bankDetails').classList.add('active');
}
});
});

// Amount selection
document.querySelectorAll('.amount-option').forEach(option => {
option.addEventListener('click', function() {
document.querySelectorAll('.amount-option').forEach(o => o.classList.remove('selected'));
this.classList.add('selected');

const amount = this.dataset.amount;
const amountInput = document.getElementById('payment_amount');

if (amount === 'custom') {
amountInput.removeAttribute('readonly');
amountInput.focus();
} else {
amountInput.setAttribute('readonly', true);
amountInput.value = amount;
document.getElementById('payAmount').textContent = parseFloat(amount).toLocaleString('en-US', {minimumFractionDigits: 2});
}
});
});

// Update pay button amount when custom amount changes
document.getElementById('payment_amount').addEventListener('input', function() {
const amount = parseFloat(this.value) || 0;
document.getElementById('payAmount').textContent = amount.toLocaleString('en-US', {minimumFractionDigits: 2});
});

// Card number formatting
document.querySelector('input[name="card_number"]').addEventListener('input', function(e) {
let value = e.target.value.replace(/\s/g, '');
let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
e.target.value = formattedValue;
});

// Expiry date formatting
document.querySelector('input[name="expiry"]').addEventListener('input', function(e) {
let value = e.target.value.replace(/\D/g, '');
if (value.length >= 2) {
value = value.substring(0, 2) + '/' + value.substring(2, 4);
}
e.target.value = value;
});

// CVV validation
document.querySelector('input[name="cvv"]').addEventListener('input', function(e) {
e.target.value = e.target.value.replace(/\D/g, '');
});
</script>
```

</body>
</html>
