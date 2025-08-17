-- Explore Hub Tour Operator Management System Database Schema
-- Version 1.0
-- Date: 2025

-- Create database
CREATE DATABASE IF NOT EXISTS explore_hub_db;
USE explore_hub_db;

-- 1. Users table (for authentication)
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    user_type ENUM('customer', 'staff', 'admin') DEFAULT 'customer',
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- 2. Staff table (additional info for staff members)
CREATE TABLE staff (
    staff_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    employee_code VARCHAR(20) UNIQUE NOT NULL,
    department VARCHAR(50),
    position VARCHAR(50),
    hire_date DATE,
    salary DECIMAL(10,2),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- 3. Tours table
CREATE TABLE tours (
    tour_id INT PRIMARY KEY AUTO_INCREMENT,
    tour_name VARCHAR(200) NOT NULL,
    tour_type ENUM('adventure', 'eco', 'nature', 'cultural', 'custom') NOT NULL,
    description TEXT,
    duration_days INT NOT NULL,
    base_price DECIMAL(10,2) NOT NULL,
    max_participants INT,
    difficulty_level ENUM('easy', 'moderate', 'difficult'),
    includes TEXT,
    excludes TEXT,
    itinerary TEXT,
    image_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 4. Hotels table
CREATE TABLE hotels (
    hotel_id INT PRIMARY KEY AUTO_INCREMENT,
    hotel_name VARCHAR(200) NOT NULL,
    location VARCHAR(200) NOT NULL,
    star_rating INT CHECK (star_rating >= 1 AND star_rating <= 5),
    description TEXT,
    amenities TEXT,
    contact_number VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    image_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE
);

-- 5. Room types table
CREATE TABLE room_types (
    room_type_id INT PRIMARY KEY AUTO_INCREMENT,
    hotel_id INT,
    room_type_name VARCHAR(100) NOT NULL,
    capacity INT NOT NULL,
    price_per_night DECIMAL(10,2) NOT NULL,
    description TEXT,
    available_rooms INT DEFAULT 0,
    FOREIGN KEY (hotel_id) REFERENCES hotels(hotel_id) ON DELETE CASCADE
);

-- 6. Suppliers table
CREATE TABLE suppliers (
    supplier_id INT PRIMARY KEY AUTO_INCREMENT,
    supplier_name VARCHAR(200) NOT NULL,
    supplier_type ENUM('transport', 'accommodation', 'equipment', 'food', 'other') NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    payment_terms VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. Inventory table
CREATE TABLE inventory (
    inventory_id INT PRIMARY KEY AUTO_INCREMENT,
    item_name VARCHAR(200) NOT NULL,
    item_type ENUM('equipment', 'vehicle', 'other') NOT NULL,
    quantity INT DEFAULT 0,
    unit_price DECIMAL(10,2),
    supplier_id INT,
    description TEXT,
    condition_status ENUM('new', 'good', 'fair', 'poor') DEFAULT 'good',
    last_maintenance_date DATE,
    is_available BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id)
);

-- 8. Bookings table
CREATE TABLE bookings (
    booking_id INT PRIMARY KEY AUTO_INCREMENT,
    booking_reference VARCHAR(20) UNIQUE NOT NULL,
    customer_id INT,
    booking_type ENUM('tour', 'hotel', 'package') NOT NULL,
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    travel_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0,
    payment_status ENUM('pending', 'partial', 'paid', 'refunded') DEFAULT 'pending',
    booking_status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    special_requests TEXT,
    FOREIGN KEY (customer_id) REFERENCES users(user_id)
);

-- 9. Booking items table (for multiple items in one booking)
CREATE TABLE booking_items (
    booking_item_id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT,
    item_type ENUM('tour', 'hotel') NOT NULL,
    item_id INT NOT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE
);

-- 10. Payments table
CREATE TABLE payments (
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'card', 'bank_transfer', 'online') NOT NULL,
    transaction_reference VARCHAR(100),
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'completed',
    processed_by INT,
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id),
    FOREIGN KEY (processed_by) REFERENCES users(user_id)
);

-- 11. Supplier payments table
CREATE TABLE supplier_payments (
    supplier_payment_id INT PRIMARY KEY AUTO_INCREMENT,
    supplier_id INT,
    payment_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'cheque', 'bank_transfer') NOT NULL,
    reference_number VARCHAR(100),
    description TEXT,
    processed_by INT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id),
    FOREIGN KEY (processed_by) REFERENCES users(user_id)
);

-- 12. Activity log table (for tracking system activities)
CREATE TABLE activity_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(50),
    description TEXT,
    ip_address VARCHAR(45),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Create indexes for better performance
CREATE INDEX idx_booking_date ON bookings(booking_date);
CREATE INDEX idx_travel_date ON bookings(travel_date);
CREATE INDEX idx_user_type ON users(user_type);
CREATE INDEX idx_booking_status ON bookings(booking_status);

-- Insert default admin user
INSERT INTO users (username, email, password_hash, user_type, first_name, last_name) 
VALUES ('admin', 'admin@explorehub.com', '$2y$10$YourHashedPasswordHere', 'admin', 'System', 'Administrator');

-- Sample data for testing
INSERT INTO tours (tour_name, tour_type, description, duration_days, base_price, max_participants, difficulty_level) 
VALUES 
('Sigiriya Rock Fortress Adventure', 'adventure', 'Climb the ancient rock fortress and explore the surrounding areas', 1, 5000.00, 20, 'moderate'),
('Yala National Park Safari', 'nature', 'Wildlife safari experience in Sri Lanka\'s famous national park', 2, 12000.00, 15, 'easy'),
('Kandy Cultural Tour', 'cultural', 'Explore the cultural capital of Sri Lanka', 1, 3500.00, 25, 'easy');

INSERT INTO hotels (hotel_name, location, star_rating, description, contact_number) 
VALUES 
('Cinnamon Grand Colombo', 'Colombo', 5, 'Luxury hotel in the heart of Colombo', '+94112437437'),
('Jetwing Yala', 'Yala', 4, 'Safari lodge near Yala National Park', '+94472239700'),
('Earl\'s Regency', 'Kandy', 5, 'Luxury hotel with mountain views', '+94812422122');