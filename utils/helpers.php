<?php
/**
 * Helper Functions
 * Common utility functions used throughout the LocalConnect application
 */

// Define base URL function
function get_base_url() {
    // Get the server protocol (http or https)
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    
    // Get the server host name with port if non-standard
    $host = $_SERVER['HTTP_HOST'];
    
    // Get the directory of the script
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $baseDir = str_replace('\\', '/', $scriptDir);
    
    // If we're in a subdirectory of the project, adjust path accordingly
    if (strpos($baseDir, '/LocalConnect') !== false) {
        $baseDir = substr($baseDir, 0, strpos($baseDir, '/LocalConnect') + strlen('/LocalConnect'));
    } else {
        // We might be at the root already
        $baseDir = '/LocalConnect';
    }
    
    // Return the base URL (ensuring proper trailing slash)
    return rtrim($protocol . $host . $baseDir, '/') . '/';
}

// Start session if not already started
function start_session_if_not_exists() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Check if user is logged in
function is_logged_in() {
    start_session_if_not_exists();
    return isset($_SESSION['user_id']);
}

// Check if user has specific role
function has_role($role) {
    start_session_if_not_exists();
    if (!is_logged_in()) {
        return false;
    }
    return $_SESSION['user_role'] == $role;
}

// Check if user is admin
function is_admin() {
    return has_role('admin');
}

// Check if user is business owner
function is_business_owner() {
    $result = has_role('business_owner');
    // Debug log
    if (is_logged_in()) {
        error_log("is_business_owner check - User ID: " . $_SESSION['user_id'] . ", Role: " . ($_SESSION['user_role'] ?? 'not set') . ", Result: " . ($result ? "true" : "false"));
    }
    return $result;
}

// Check if user is customer
function is_customer() {
    return has_role('customer');
}

// Redirect to a URL
function redirect($url) {
    header("Location: $url");
    exit();
}

// Display error message
function display_error($message) {
    return "<div class='alert alert-danger' role='alert'>$message</div>";
}

// Display success message
function display_success($message) {
    return "<div class='alert alert-success' role='alert'>$message</div>";
}

// Generate a random token
function generate_token($length = 32) {
    return bin2hex(random_bytes($length));
}

// Format price
function format_price($price) {
    // Convert USD to INR (approximately 1 USD = 75 INR)
    $price_in_inr = $price * 75;
    return '₹' . number_format($price_in_inr, 2);
}

// Calculate discounted price
function calculate_discount($price, $discount_percentage) {
    return $price * (1 - ($discount_percentage / 100));
}

// Format discount percentage
function format_discount($percentage) {
    return round($percentage) . '% OFF';
}

// Truncate text to a specific length
function truncate_text($text, $length = 100, $append = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    $text = substr($text, 0, $length);
    $text = substr($text, 0, strrpos($text, ' '));
    return $text . $append;
}

// Validate email format
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Get current timestamp
function get_current_timestamp() {
    return date('Y-m-d H:i:s');
}

// Sanitize and validate a string
function sanitize_string($input) {
    // If the input is an array, return it unchanged
    if (is_array($input)) {
        return $input;
    }
    // Otherwise, sanitize it as a string
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Generate a URL-friendly slug from a string
function generate_slug($string) {
    return strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $string), '-'));
}

// Upload an image file
function upload_image($file, $destination_path) {
    // Create directory if it doesn't exist
    $directory = dirname($destination_path);
    if (!file_exists($directory)) {
        mkdir($directory, 0777, true);
    }
    
    // Check file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }
    
    // Move the uploaded file
    if (move_uploaded_file($file['tmp_name'], $destination_path)) {
        return true;
    }
    
    return false;
}

// Send email (basic implementation)
function send_email($to, $subject, $message) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: LocalConnect <noreply@localconnect.com>' . "\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Get average rating for a product
function get_product_rating($product_id, $conn) {
    $query = "SELECT AVG(rating) as avg_rating FROM product_reviews WHERE product_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    return round($data['avg_rating'] ?? 0, 1);
}

// Get review count for a product
function get_review_count($product_id, $conn) {
    $query = "SELECT COUNT(*) as count FROM product_reviews WHERE product_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    return $data['count'] ?? 0;
}

/**
 * Set a flash message to be displayed on the next page load
 * @param string $type The type of message (success, error, info, warning)
 * @param string $message The message to display
 */
function set_flash_message($type, $message) {
    start_session_if_not_exists();
    $_SESSION['flash_messages'][$type] = $message;
}

/**
 * Get flash messages and clear them from the session
 * @return array The flash messages
 */
function get_flash_messages() {
    start_session_if_not_exists();
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Display flash messages
 * @return string HTML output of flash messages
 */
function display_flash_messages() {
    $output = '';
    $messages = get_flash_messages();
    
    foreach ($messages as $type => $message) {
        $class = 'alert-info';
        if ($type === 'success') $class = 'alert-success';
        if ($type === 'error') $class = 'alert-danger';
        if ($type === 'warning') $class = 'alert-warning';
        
        $output .= "<div class='alert $class' role='alert'>$message</div>";
    }
    
    return $output;
}

/**
 * Get the list of business categories
 * @return array Associative array of business categories [code => label]
 */
function get_business_categories() {
    return [
        'retail' => 'Retail',
        'food' => 'Food & Beverages',
        'electronics' => 'Electronics',
        'fashion' => 'Fashion & Apparel',
        'health' => 'Health & Beauty',
        'home' => 'Home & Furniture',
        'art' => 'Art & Crafts',
        'books' => 'Books & Stationery',
        'toys' => 'Toys & Games',
        'sports' => 'Sports & Outdoors',
        'automotive' => 'Automotive',
        'services' => 'Services',
        'other' => 'Other'
    ];
}

/**
 * Get the label for a business category code
 * @param string $category_code The category code
 * @return string The category label or 'Unknown' if not found
 */
function get_business_category_label($category_code) {
    $categories = get_business_categories();
    return $categories[$category_code] ?? 'Unknown';
}
?> 