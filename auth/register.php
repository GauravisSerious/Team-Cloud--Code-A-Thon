<?php
/**
 * User Registration
 * Handles user registration for LocalConnect
 */

// Include required files
require_once('../config/database.php');
require_once('../utils/helpers.php');

// Start session
start_session_if_not_exists();

// Get base URL
$base_url = get_base_url();

// Check if user is already logged in
if (is_logged_in()) {
    redirect('../index.php');
}

// Determine registration type
$registration_type = isset($_GET['type']) && $_GET['type'] === 'business' ? 'business' : 'customer';
$page_title = $registration_type === 'business' ? 'Business Registration' : 'Customer Registration';
$redirect_login = $registration_type === 'business' ? 'business-login.php' : 'login.php';

// Initialize variables
$first_name = '';
$last_name = '';
$email = '';
$phone = '';
$error = '';
$success = '';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get database connection
    $conn = connect_db();
    
    // Get form data
    $first_name = sanitize_string($_POST['first_name']);
    $last_name = sanitize_string($_POST['last_name']);
    $email = sanitize_string($_POST['email']);
    $phone = sanitize_string($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $registration_type = $_POST['registration_type'];
    
    // Validate input
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!preg_match('/^[0-9]+$/', $phone)) {
        $error = "Phone number must contain only numeric digits.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if email already exists
        $query = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Email already in use. Please use a different email or login to your account.";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Generate username
            $username = strtolower($first_name . '.' . $last_name . rand(100, 999));
            
            // Set role based on registration type
            $role = $registration_type === 'business' ? 'business_owner' : 'customer';
            
            // Generate verification token
            $verification_token = md5(uniqid(rand(), true));
            
            // Insert user into database
            $query = "INSERT INTO users (first_name, last_name, email, phone, username, password, user_role, verification_token, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssssssss', $first_name, $last_name, $email, $phone, $username, $hashed_password, $role, $verification_token);
            
            if ($stmt->execute()) {
                // Send verification email
                $user_id = $conn->insert_id;
                $verification_link = $base_url . 'auth/verify.php?token=' . $verification_token;
                
                // For demo purposes, just display success message with verification link
                $success = "Registration successful! Please verify your email to activate your account. <a href='$verification_link'>Click here to verify</a>";
                
                // Clear form data
                $first_name = '';
                $last_name = '';
                $email = '';
                $phone = '';
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
    
    // Close database connection
    close_db($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - LocalConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .register-banner {
            background: linear-gradient(135deg, <?php echo $registration_type === 'business' ? '#198754 0%, #20c997' : '#0d6efd 0%, #0dcaf0'; ?> 100%);
            color: white;
            padding: 2rem;
            border-top-left-radius: 0.25rem;
            border-bottom-left-radius: 0.25rem;
        }
        @media (max-width: 767.98px) {
            .register-banner {
                border-top-right-radius: 0.25rem;
                border-top-left-radius: 0.25rem;
                border-bottom-left-radius: 0;
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include_once('../includes/header.php'); ?>
    
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow">
                    <div class="row g-0">
                        <div class="col-md-5">
                            <div class="register-banner h-100 d-flex flex-column justify-content-center">
                                <h2 class="mb-3"><?php echo $registration_type === 'business' ? 'Join as a Business' : 'Create an Account'; ?></h2>
                                <p class="mb-4">
                                    <?php if ($registration_type === 'business'): ?>
                                        Register your business with LocalConnect and reach customers in your community.
                                    <?php else: ?>
                                        Join LocalConnect to discover and shop from local businesses in your area.
                                    <?php endif; ?>
                                </p>
                                <div class="mb-3">
                                    <i class="bi bi-<?php echo $registration_type === 'business' ? 'shop' : 'cart-check'; ?> fs-3 me-2"></i>
                                    <span>
                                        <?php echo $registration_type === 'business' ? 'Showcase your products' : 'Shop local products'; ?>
                                    </span>
                                </div>
                                <div class="mb-3">
                                    <i class="bi bi-<?php echo $registration_type === 'business' ? 'graph-up' : 'star'; ?> fs-3 me-2"></i>
                                    <span>
                                        <?php echo $registration_type === 'business' ? 'Grow your business' : 'Rate and review purchases'; ?>
                                    </span>
                                </div>
                                <div>
                                    <i class="bi bi-<?php echo $registration_type === 'business' ? 'people' : 'geo-alt'; ?> fs-3 me-2"></i>
                                    <span>
                                        <?php echo $registration_type === 'business' ? 'Connect with customers' : 'Support your community'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="card-body p-4">
                                <div class="text-center mb-4">
                                    <h3><?php echo $page_title; ?></h3>
                                    <p class="text-muted">Create your account to get started</p>
                                </div>
                                
                                <?php if (!empty($error)): ?>
                                    <div class="alert alert-danger"><?php echo $error; ?></div>
                                <?php endif; ?>
                                
                                <?php if (!empty($success)): ?>
                                    <div class="alert alert-success"><?php echo $success; ?></div>
                                <?php endif; ?>
                                
                                <form method="POST" action="">
                                    <input type="hidden" name="registration_type" value="<?php echo $registration_type; ?>">
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="first_name" class="form-label">First Name</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo $first_name; ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="last_name" class="form-label">Last Name</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo $last_name; ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo $email; ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo $phone; ?>" pattern="[0-9]+" title="Please enter only numbers" required>
                                        <div class="form-text">Enter only numeric digits (no spaces or special characters)</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="password" name="password" minlength="8" required>
                                        <div class="form-text">Password must be at least 8 characters long.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-<?php echo $registration_type === 'business' ? 'success' : 'primary'; ?> btn-lg">Create Account</button>
                                    </div>
                                </form>
                                
                                <div class="mt-4 text-center">
                                    <p>Already have an account? <a href="<?php echo $redirect_login; ?>">Login</a></p>
                                    <?php if ($registration_type === 'business'): ?>
                                        <hr>
                                        <p class="text-muted">Looking to shop? <a href="register.php">Register as a Customer</a></p>
                                    <?php else: ?>
                                        <hr>
                                        <p class="text-muted">Are you a business owner? <a href="register.php?type=business">Register Your Business</a></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include_once('../includes/footer.php'); ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Ensure phone field only accepts numeric input
        document.getElementById('phone').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html> 