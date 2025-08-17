<?php
// index.php - Public Homepage

session_start();
require_once 'config/config.php';

$conn = getDBConnection();

// Get featured tours
$featured_tours = $conn->query("SELECT * FROM tours WHERE is_active = 1 ORDER BY RAND() LIMIT 6");

// Get popular destinations (locations with most hotels)
$destinations = $conn->query("SELECT location, COUNT(*) as hotel_count, MIN(star_rating) as min_rating, MAX(star_rating) as max_rating 
                            FROM hotels WHERE is_active = 1 
                            GROUP BY location 
                            ORDER BY hotel_count DESC 
                            LIMIT 4");

// Get testimonials (you would need to create a testimonials table in production)
$testimonials = [
    ['name' => 'Sarah Johnson', 'text' => 'Amazing experience! The tour guides were knowledgeable and friendly.', 'rating' => 5],
    ['name' => 'Mike Chen', 'text' => 'Great value for money. Will definitely book again!', 'rating' => 5],
    ['name' => 'Emma Wilson', 'text' => 'Professional service from start to finish. Highly recommended!', 'rating' => 5]
];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore Hub - Your Adventure Starts Here</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Hero Section */
        .hero {
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 600"><rect fill="%232c3e50" width="1200" height="600"/><path fill="%2334495e" d="M0 300L50 325L100 300L150 275L200 300L250 325L300 300L350 275L400 300L450 325L500 300L550 275L600 300L650 325L700 300L750 275L800 300L850 325L900 300L950 275L1000 300L1050 325L1100 300L1150 275L1200 300V600H0V300Z"/></svg>');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 0;
            text-align: center;
        }
        
        .hero h1 {
            font-size: 3.5rem;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .search-box {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-top: 30px;
        }
        
        /* Cards */
        .tour-card {
            transition: transform 0.3s;
            height: 100%;
            cursor: pointer;
        }
        
        .tour-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .destination-card {
            position: relative;
            overflow: hidden;
            border-radius: 10px;
            height: 250px;
            cursor: pointer;
        }
        
        .destination-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .destination-card:hover img {
            transform: scale(1.1);
        }
        
        .destination-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            color: white;
            padding: 20px;
        }
        
        /* Features */
        .feature-icon {
            font-size: 3rem;
            color: #3498db;
            margin-bottom: 20px;
        }
        
        /* Testimonials */
        .testimonial-card {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
        }
        
        .star-rating {
            color: #ffc107;
        }
        
        /* CTA Section */
        .cta-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0;
            text-align: center;
        }
        
        /* Navbar */
        .navbar-custom {
            background-color: #2c3e50;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .difficulty-easy { color: #28a745; }
        .difficulty-moderate { color: #ffc107; }
        .difficulty-difficult { color: #dc3545; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top">
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
                        <a class="nav-link active" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#tours">Tours</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#destinations">Destinations</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="customer/dashboard.php">
                                <i class="bi bi-person-circle"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="modules/auth/logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="modules/auth/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-primary text-white ms-2" href="modules/auth/register.php">Sign Up</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="container">
            <h1>Discover Sri Lanka's Hidden Gems</h1>
            <p class="lead">Adventure, Eco, Nature & Cultural Tours Tailored Just for You</p>
            
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="search-box">
                        <form action="search.php" method="GET">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <select class="form-select" name="type">
                                        <option value="">All Tour Types</option>
                                        <option value="adventure">Adventure</option>
                                        <option value="eco">Eco</option>
                                        <option value="nature">Nature</option>
                                        <option value="cultural">Cultural</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <input type="date" class="form-control" name="date" placeholder="Travel Date">
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-search"></i> Search Tours
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Features Section -->
    <section class="py-5">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3 mb-4">
                    <i class="bi bi-shield-check feature-icon"></i>
                    <h4>Safe & Secure</h4>
                    <p>Trusted by thousands of travelers</p>
                </div>
                <div class="col-md-3 mb-4">
                    <i class="bi bi-people feature-icon"></i>
                    <h4>Expert Guides</h4>
                    <p>Professional and knowledgeable guides</p>
                </div>
                <div class="col-md-3 mb-4">
                    <i class="bi bi-tag feature-icon"></i>
                    <h4>Best Prices</h4>
                    <p>Competitive prices for all tours</p>
                </div>
                <div class="col-md-3 mb-4">
                    <i class="bi bi-headset feature-icon"></i>
                    <h4>24/7 Support</h4>
                    <p>Always here to help you</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Featured Tours Section -->
    <section class="py-5 bg-light" id="tours">
        <div class="container">
            <div class="text-center mb-5">
                <h2>Featured Tours</h2>
                <p class="lead">Explore our most popular adventures</p>
            </div>
            
            <div class="row">
                <?php while ($tour = $featured_tours->fetch_assoc()): ?>
                <div class="col-md-4 mb-4">
                    <div class="card tour-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title"><?php echo $tour['tour_name']; ?></h5>
                                <span class="badge bg-primary"><?php echo ucfirst($tour['tour_type']); ?></span>
                            </div>
                            <p class="card-text text-muted"><?php echo substr($tour['description'], 0, 100); ?>...</p>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span><i class="bi bi-calendar"></i> <?php echo $tour['duration_days']; ?> days</span>
                                <span class="difficulty-<?php echo $tour['difficulty_level']; ?>">
                                    <?php echo ucfirst($tour['difficulty_level'] ?? 'N/A'); ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="text-primary mb-0">LKR <?php echo number_format($tour['base_price'], 2); ?></h5>
                                <a href="tour-details.php?id=<?php echo $tour['tour_id']; ?>" class="btn btn-outline-primary btn-sm">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            
            <div class="text-center mt-4">
                <a href="all-tours.php" class="btn btn-primary">View All Tours</a>
            </div>
        </div>
    </section>
    
    <!-- Destinations Section -->
    <section class="py-5" id="destinations">
        <div class="container">
            <div class="text-center mb-5">
                <h2>Popular Destinations</h2>
                <p class="lead">Find the perfect hotel in these amazing locations</p>
            </div>
            
            <div class="row">
                <?php while ($dest = $destinations->fetch_assoc()): ?>
                <div class="col-md-3 mb-4">
                    <div class="destination-card">
                        <img src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='400' height='250'><rect width='400' height='250' fill='%23<?php echo substr(md5($dest['location']), 0, 6); ?>'/><text x='50%' y='50%' text-anchor='middle' dy='.3em' fill='white' font-size='24'><?php echo $dest['location']; ?></text></svg>" alt="<?php echo $dest['location']; ?>">
                        <div class="destination-overlay">
                            <h4><?php echo $dest['location']; ?></h4>
                            <p><?php echo $dest['hotel_count']; ?> Hotels • <?php echo $dest['min_rating']; ?>-<?php echo $dest['max_rating']; ?> Stars</p>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>
    
    <!-- Testimonials Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2>What Our Customers Say</h2>
                <p class="lead">Real experiences from real travelers</p>
            </div>
            
            <div class="row">
                <?php foreach ($testimonials as $testimonial): ?>
                <div class="col-md-4 mb-4">
                    <div class="testimonial-card">
                        <div class="star-rating mb-3">
                            <?php for ($i = 0; $i < $testimonial['rating']; $i++): ?>
                                <i class="bi bi-star-fill"></i>
                            <?php endfor; ?>
                        </div>
                        <p>"<?php echo $testimonial['text']; ?>"</p>
                        <h6 class="mt-3">- <?php echo $testimonial['name']; ?></h6>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    
    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <h2>Ready for Your Next Adventure?</h2>
            <p class="lead mb-4">Join thousands of happy travelers who have explored Sri Lanka with us</p>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="modules/booking/create_booking.php" class="btn btn-white btn-lg">Book Your Tour Now</a>
            <?php else: ?>
                <a href="modules/auth/register.php" class="btn btn-white btn-lg">Sign Up Today</a>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-5" id="contact">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5>About Explore Hub</h5>
                    <p>Your trusted partner for discovering the beauty of Sri Lanka through carefully curated tours and accommodations.</p>
                </div>
                <div class="col-md-4 mb-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-white-50">About Us</a></li>
                        <li><a href="#" class="text-white-50">Contact</a></li>
                        <li><a href="#" class="text-white-50">Terms & Conditions</a></li>
                        <li><a href="#" class="text-white-50">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-4">
                    <h5>Contact Info</h5>
                    <p><i class="bi bi-geo-alt"></i> Kataragama, Uva Province, Sri Lanka</p>
                    <p><i class="bi bi-phone"></i> +94 123 456 789</p>
                    <p><i class="bi bi-envelope"></i> info@explorehub.lk</p>
                    <div class="mt-3">
                        <a href="#" class="text-white me-3"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="text-white me-3"><i class="bi bi-twitter"></i></a>
                        <a href="#" class="text-white me-3"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="text-white"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>
            </div>
            <hr class="bg-white">
            <div class="text-center">
                <p class="mb-0">&copy; 2025 Explore Hub. All rights reserved. Developed by Udeshika Aruni</p>
            </div>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Add padding for fixed navbar
        document.addEventListener('DOMContentLoaded', function() {
            const navbar = document.querySelector('.navbar');
            const navbarHeight = navbar.offsetHeight;
            document.body.style.paddingTop = navbarHeight + 'px';
        });
    </script>
</body>
</html>
