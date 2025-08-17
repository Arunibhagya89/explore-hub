<?php
// Site Configuration
define('SITE_NAME', 'Explore Hub');
define('SITE_URL', 'http://localhost/explore-hub/');
define('ADMIN_EMAIL', 'admin@explorehub.com');

// Session Configuration
session_start();

// Timezone
date_default_timezone_set('Asia/Colombo');

// Include database configuration
require_once 'database.php';
?>