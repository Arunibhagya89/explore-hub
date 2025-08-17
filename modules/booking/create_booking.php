<?php
// modules/booking/create_booking.php - Create New Booking

session_start();
require_once '../../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$conn = getDBConnection();

// Get available tours and hotels
$tours = $conn->query("SELECT tour_id, tour_name, base_price FROM tours WHERE is_active = 1");
$hotels = $conn->query("SELECT h.hotel_id, h.hotel_name, h.location, rt.room_type_id, rt.room_type_name, rt.price_per_night 
                      FROM hotels h 
                      JOIN room_types rt ON h.hotel_id = rt.hotel_id 
                      WHERE h.is_active = 1");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $booking_type = $_POST['booking_type'];
    $travel_date = $_POST['travel_date'];
    $end_date = $_POST['end_date'];
    $special_requests = filter_input(INPUT_POST, 'special_requests', FILTER_SANITIZE_STRING);
    
    // Generate booking reference
    $booking_reference = 'EH' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    
    // Calculate total amount based on selections
    $total_amount = 0;
    $booking_items = [];
    
    if ($booking_type == 'tour' && isset($_POST['tour_id'])) {
        $tour_id = $_POST['tour_id'];
        $participants = $_POST['participants'];
        
        // Get tour price
        $tour_query = $conn->prepare("SELECT base_price FROM tours WHERE tour_id = ?");
        $tour_query->bind_param("i", $tour_id);
        $tour_query->execute();
        $tour_result = $tour_query->get_result();
        $tour = $tour_result->fetch_assoc();
        
        $subtotal = $tour['base_price'] * $participants;
        $total_amount += $subtotal;
        
        $booking_items[] = [
            'item_type' => 'tour',
            'item_id' => $tour_id,
            'quantity' => $participants,
            'unit_price' => $tour['base_price'],
            'subtotal' => $subtotal
        ];
    } elseif ($booking_type == 'hotel' && isset($_POST['room_type_id'])) {
        $room_type_id = $_POST['room_type_id'];
        $rooms = $_POST['rooms'];
        
        // Get room price
        $room_query = $conn->prepare("SELECT price_per_night FROM room_types WHERE room_type_id = ?");
        $room_query->bind_param("i", $room_type_id);
        $room_query->execute();
        $room_result = $room_query->get_result();
        $room = $room_result->fetch_assoc();
        
        // Calculate nights
        $start = new DateTime($travel_date);
        $end = new DateTime($end_date);
        $nights = $start->diff($end)->days;
        
        $subtotal = $room['price_per_night'] * $rooms * $nights;
        $total_amount += $subtotal;
        
        $booking_items[] = [
            'item_type' => 'hotel',
            'item_id' => $room_type_id,
            'quantity' => $rooms,
            'unit_price' => $room['price_per_night'] * $nights,
            'subtotal' => $subtotal
        ];
    }
    
    // Insert booking
    $customer_id = $_SESSION['user_id'];
    $insert_booking = $conn->prepare("INSERT INTO bookings (booking_reference, customer_id, booking_type, 
                                                          travel_date, end_date, total_amount, special_requests) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?)");
    $insert_booking->bind_param("sisssds", $booking_reference, $customer_id, $booking_type, 
                               $travel_date, $end_date, $total_amount, $special_requests);
    
    if ($insert_booking->execute()) {
        $booking_id = $conn->insert_id;
        
        // Insert booking items
        foreach ($booking_items as $item) {
            $insert_item = $conn->prepare("INSERT INTO booking_items (booking_id, item_type, item_id, 
                                                                     quantity, unit_price, subtotal) 
                                         VALUES (?, ?, ?, ?, ?, ?)");
            $insert_item->bind_param("isiidd", $booking_id, $item['item_type'], $item['item_id'], 
                                   $item['quantity'], $item['unit_price'], $item['subtotal']);
            $insert_item->execute();
        }
        
        $_SESSION['success_message'] = "Booking created successfully! Reference: " . $booking_reference;
        header("Location: booking_confirmation.php?id=" . $booking_id);
        exit();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Booking - Explore Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .booking-form {
            max-width: 800px;
            margin: 0 auto;
        }
        .step-indicator {
            margin-bottom: 30px;
        }
        .step {
            display: inline-block;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #ddd;
            text-align: center;
            line-height: 30px;
            margin-right: 10px;
        }
        .step.active {
            background-color: #007bff;
            color: white;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Explore Hub</a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text">Welcome, <?php echo $_SESSION['full_name']; ?></span>
                <a class="nav-link" href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container mt-5">
        <div class="booking-form">
            <h2 class="mb-4">Create New Booking</h2>
            
            <div class="step-indicator">
                <span class="step active">1</span>
                <span class="step">2</span>
                <span class="step">3</span>
            </div>
            
            <form method="POST" action="">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Booking Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="booking_type" class="form-label">Booking Type</label>
                            <select class="form-select" id="booking_type" name="booking_type" required>
                                <option value="">Select Type</option>
                                <option value="tour">Tour Package</option>
                                <option value="hotel">Hotel Accommodation</option>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="travel_date" class="form-label">Travel Date</label>
                                <input type="date" class="form-control" id="travel_date" name="travel_date" 
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tour Selection -->
                <div class="card mb-4" id="tour_selection" style="display: none;">
                    <div class="card-header">
                        <h5>Select Tour</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="tour_id" class="form-label">Tour Package</label>
                            <select class="form-select" id="tour_id" name="tour_id">
                                <option value="">Select Tour</option>
                                <?php while ($tour = $tours->fetch_assoc()): ?>
                                    <option value="<?php echo $tour['tour_id']; ?>" 
                                            data-price="<?php echo $tour['base_price']; ?>">
                                        <?php echo $tour['tour_name']; ?> - LKR <?php echo number_format($tour['base_price'], 2); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="participants" class="form-label">Number of Participants</label>
                            <input type="number" class="form-control" id="participants" name="participants" 
                                   min="1" value="1">
                        </div>
                    </div>
                </div>
                
                <!-- Hotel Selection -->
                <div class="card mb-4" id="hotel_selection" style="display: none;">
                    <div class="card-header">
                        <h5>Select Hotel</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="room_type_id" class="form-label">Hotel & Room Type</label>
                            <select class="form-select" id="room_type_id" name="room_type_id">
                                <option value="">Select Hotel Room</option>
                                <?php while ($hotel = $hotels->fetch_assoc()): ?>
                                    <option value="<?php echo $hotel['room_type_id']; ?>" 
                                            data-price="<?php echo $hotel['price_per_night']; ?>">
                                        <?php echo $hotel['hotel_name']; ?> - <?php echo $hotel['room_type_name']; ?> 
                                        (<?php echo $hotel['location']; ?>) - LKR <?php echo number_format($hotel['price_per_night'], 2); ?>/night
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="rooms" class="form-label">Number of Rooms</label>
                            <input type="number" class="form-control" id="rooms" name="rooms" min="1" value="1">
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Additional Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="special_requests" class="form-label">Special Requests</label>
                            <textarea class="form-control" id="special_requests" name="special_requests" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-body">
                        <h5>Total Amount: LKR <span id="total_amount">0.00</span></h5>
                    </div>
                </div>
                
                <div class="text-end">
                    <a href="../../customer/dashboard.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Booking</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show/hide sections based on booking type
        document.getElementById('booking_type').addEventListener('change', function() {
            var tourSection = document.getElementById('tour_selection');
            var hotelSection = document.getElementById('hotel_selection');
            
            if (this.value === 'tour') {
                tourSection.style.display = 'block';
                hotelSection.style.display = 'none';
            } else if (this.value === 'hotel') {
                tourSection.style.display = 'none';
                hotelSection.style.display = 'block';
            } else {
                tourSection.style.display = 'none';
                hotelSection.style.display = 'none';
            }
            
            calculateTotal();
        });
        
        // Calculate total amount
        function calculateTotal() {
            var total = 0;
            var bookingType = document.getElementById('booking_type').value;
            
            if (bookingType === 'tour') {
                var tourSelect = document.getElementById('tour_id');
                var participants = document.getElementById('participants').value || 1;
                
                if (tourSelect.selectedIndex > 0) {
                    var price = parseFloat(tourSelect.options[tourSelect.selectedIndex].getAttribute('data-price'));
                    total = price * participants;
                }
            } else if (bookingType === 'hotel') {
                var roomSelect = document.getElementById('room_type_id');
                var rooms = document.getElementById('rooms').value || 1;
                
                if (roomSelect.selectedIndex > 0) {
                    var pricePerNight = parseFloat(roomSelect.options[roomSelect.selectedIndex].getAttribute('data-price'));
                    var startDate = new Date(document.getElementById('travel_date').value);
                    var endDate = new Date(document.getElementById('end_date').value);
                    
                    if (startDate && endDate && endDate > startDate) {
                        var nights = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24));
                        total = pricePerNight * rooms * nights;
                    }
                }
            }
            
            document.getElementById('total_amount').textContent = total.toFixed(2);
        }
        
        // Add event listeners for calculation
        document.getElementById('tour_id').addEventListener('change', calculateTotal);
        document.getElementById('participants').addEventListener('input', calculateTotal);
        document.getElementById('room_type_id').addEventListener('change', calculateTotal);
        document.getElementById('rooms').addEventListener('input', calculateTotal);
        document.getElementById('travel_date').addEventListener('change', calculateTotal);
        document.getElementById('end_date').addEventListener('change', calculateTotal);
    </script>
</body>
</html>