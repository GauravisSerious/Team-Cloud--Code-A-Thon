<?php
/**
 * Customer Login
 * Handles customer authentication for LocalConnect
 */

// Include required files
require_once('../config/database.php');
require_once('../utils/helpers.php');

// Start session
start_session_if_not_exists();

// Get base URL
$base_url = get_base_url();

// Initialize variables
$error = '';
$success = '';

// Check if user is already logged in
if (is_logged_in()) {
    if (is_business_owner()) {
        redirect('../business/index.php');
    } else {
        redirect('../index.php');
    }
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get database connection
    $conn = connect_db();
    
    // Get form data
    $email = sanitize_string($_POST['email']);
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']);
    
    // Validate input
    if (empty($email) || empty($password)) {
        $error = "Email and password are required.";
    } else {
        // Check if email exists
        $query = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Check if account is verified
                if ($user['is_verified'] == 0) {
                    $error = "Please verify your email address before logging in.";
                } else {
                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'] ?? '';
                    $_SESSION['user_role'] = $user['user_role'];
                    $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    
                    // Set remember me cookie if checked
                    if ($remember_me) {
                        $token = generate_token();
                        setcookie('remember_me', $token, time() + (30 * 24 * 60 * 60), '/');
                        
                        // Store token in database
                        $user_id = $user['user_id'];
                        $expiry = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));
                        $update_query = "UPDATE users SET remember_token = ? WHERE user_id = ?";
                        $update_stmt = $conn->prepare($update_query);
                        $update_stmt->bind_param('si', $token, $user_id);
                        $update_stmt->execute();
                    }
                    
                    // Redirect based on role
                    if ($user['user_role'] == 'admin') {
                        redirect('../admin/dashboard.php');
                    } elseif ($user['user_role'] == 'business_owner') {
                        redirect('../business/index.php');
                    } else {
                        // Check for redirect URL in localStorage
                        echo "<script>
                            const redirectUrl = localStorage.getItem('redirectAfterLogin');
                            if (redirectUrl) {
                                localStorage.removeItem('redirectAfterLogin');
                                window.location.href = redirectUrl;
                            } else {
                                window.location.href = '../index.php';
                            }
                        </script>";
                        exit;
                    }
                }
            } else {
                $error = "Invalid password. Please try again.";
            }
        } else {
            $error = "Email not found.";
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
    <title>Customer Login - LocalConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .login-banner {
            background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%);
            color: white;
            padding: 2rem;
            border-top-left-radius: 0.25rem;
            border-bottom-left-radius: 0.25rem;
        }
        @media (max-width: 767.98px) {
            .login-banner {
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
                            <div class="login-banner h-100 d-flex flex-column justify-content-center">
                                <h2 class="mb-3">Welcome Back!</h2>
                                <p class="mb-4">Sign in to access your account and discover products from local businesses in your community.</p>
                                <div class="mb-3">
                                    <i class="bi bi-cart-check fs-3 me-2"></i>
                                    <span>Shop local products</span>
                                </div>
                                <div class="mb-3">
                                    <i class="bi bi-star fs-3 me-2"></i>
                                    <span>Review your purchases</span>
                                </div>
                                <div>
                                    <i class="bi bi-geo-alt fs-3 me-2"></i>
                                    <span>Support your community</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="card-body p-4">
                                <div class="text-center mb-4">
                                    <h3>Customer Login</h3>
                                    <p class="text-muted">Enter your credentials to access your account</p>
                                </div>
                                
                                <?php if (!empty($error)): ?>
                                    <div class="alert alert-danger"><?php echo $error; ?></div>
                                <?php endif; ?>
                                
                                <?php if (!empty($success)): ?>
                                    <div class="alert alert-success"><?php echo $success; ?></div>
                                <?php endif; ?>
                                
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                            <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                                            <input type="password" class="form-control" id="password" name="password" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                                        <label class="form-check-label" for="remember_me">Remember me</label>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary btn-lg">Sign In</button>
                                    </div>
                                </form>
                                
                                <div class="mt-4 text-center">
                                    <p><a href="forgot-password.php">Forgot Password?</a></p>
                                    <p>Don't have an account? <a href="register.php">Register Now</a></p>
                                    <hr>
                                    <p class="text-muted">Are you a business owner? <a href="business-login.php">Login to your Business Account</a></p>
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
</body>
</html> 