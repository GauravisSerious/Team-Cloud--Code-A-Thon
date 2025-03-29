<?php
/**
 * Add Product Page
 * Allows business owners to add new products to their store
 */

// Set error reporting to show all errors except notices and warnings 
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Include required files
require_once('../config/database.php');
require_once('../utils/helpers.php');

// Connect to database
$conn = connect_db();

// Check if products table exists
$products_check = $conn->query("SHOW TABLES LIKE 'products'");
if ($products_check->num_rows === 0) {
    echo "<h3>Database Structure Error</h3>";
    echo "<p>The products table doesn't exist. Please run the db_setup.php script to create proper tables.</p>";
    echo "<p><a href='../db_setup.php' class='btn btn-primary'>Run Database Setup</a></p>";
    die();
}

// Check required columns in products table
$required_columns = ['business_id', 'name', 'description', 'price', 'discount', 'category_id', 'image'];
$missing_columns = [];

foreach ($required_columns as $column) {
    $column_check = $conn->query("SHOW COLUMNS FROM products LIKE '$column'");
    if ($column_check->num_rows === 0) {
        $missing_columns[] = $column;
    }
}

if (!empty($missing_columns)) {
    echo "<h3>Database Structure Error</h3>";
    echo "<p>The products table is missing the following required columns: " . implode(', ', $missing_columns) . "</p>";
    echo "<p>Please run the db_setup.php script to fix the table structure.</p>";
    echo "<p><a href='../db_setup.php' class='btn btn-primary'>Run Database Setup</a></p>";
    die();
}

// Start session if not already started
start_session_if_not_exists();

// Check if user is logged in
if (!is_logged_in()) {
    set_flash_message('error', 'You must be logged in to add a product');
    redirect('../auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Check if user is a business owner
if (!is_business_owner()) {
    set_flash_message('error', 'You must have a business account to add products');
    redirect('../index.php');
    exit;
}

// Check if business has a profile registered
$user_id = $_SESSION['user_id'];

// First try with business_id (most likely column name based on error message)
$check_business_query = "SELECT business_id FROM businesses WHERE user_id = ?";
$check_business_stmt = $conn->prepare($check_business_query);

// Check if prepare statement failed, try with id as fallback
if (!$check_business_stmt) {
    // Try with 'id' column
    $check_business_query = "SELECT id FROM businesses WHERE user_id = ?";
    $check_business_stmt = $conn->prepare($check_business_query);
    
    // If both fail, show detailed error
    if (!$check_business_stmt) {
        echo "<h3>Database Structure Error</h3>";
        echo "<p>Cannot find the right column name in businesses table. Please run the db_setup.php script to create proper tables.</p>";
        echo "<p>Error: " . $conn->error . "</p>";
        echo "<p><a href='../db_setup.php' class='btn btn-primary'>Run Database Setup</a></p>";
        die();
    }
}

$check_business_stmt->bind_param('i', $user_id);
$check_business_stmt->execute();
$check_business_result = $check_business_stmt->get_result();

if ($check_business_result->num_rows === 0) {
    // No business profile found, redirect to register
    set_flash_message('info', 'You need to complete your business profile before adding products');
    redirect('register.php?redirect=add_product');
    exit;
}

$business_row = $check_business_result->fetch_assoc();

// Check which column name was used (id or business_id)
if (isset($business_row['id'])) {
    $business_id = $business_row['id'];
} else if (isset($business_row['business_id'])) {
    $business_id = $business_row['business_id'];
} else {
    die("Could not determine business ID column. Please run db_setup.php to fix database structure.");
}

// Initialize variables
$name = $description = $category = $price = '';
$discount = 0;
$errors = [];
$success_message = '';

// Get categories grouped by parent
$column_check = $conn->query("SHOW COLUMNS FROM categories LIKE 'id'");
if ($column_check->num_rows > 0) {
    $id_column = 'id';
    $name_column = 'name';
} else {
    $id_column = 'category_id';
    $name_column = 'category_name';
}

// Get main categories (those with no parent_id)
$parent_id_check = $conn->query("SHOW COLUMNS FROM categories LIKE 'parent_id'");
$has_parent_id = ($parent_id_check && $parent_id_check->num_rows > 0);

// Show warning if parent_id column doesn't exist
$missing_parent_id = false;
if (!$has_parent_id) {
    $missing_parent_id = true;
    $errors[] = 'The category hierarchy structure is not set up properly. Please run the Setup Categories script.';
}

$main_categories_query = "SELECT $id_column, $name_column FROM categories WHERE " . 
                         ($has_parent_id ? "parent_id IS NULL" : "1=1") . 
                         " ORDER BY $name_column ASC";
$main_categories_result = $conn->query($main_categories_query);

// Array to store all categories by parent
$categories_by_parent = [];

// Get all subcategories
if ($main_categories_result && $main_categories_result->num_rows > 0) {
    while ($main_category = $main_categories_result->fetch_assoc()) {
        $parent_id = $main_category[$id_column];
        $parent_name = $main_category[$name_column];
        
        // Get subcategories for this parent
        $subcategories = [];
        
        if ($has_parent_id) {
            // A simpler approach without prepared statements to avoid bind_param errors
            $safe_parent_id = $conn->real_escape_string($parent_id);
            $direct_query = "SELECT $id_column, $name_column FROM categories WHERE parent_id = $safe_parent_id ORDER BY $name_column ASC";
            
            $subcategories_result = $conn->query($direct_query);
            if ($subcategories_result) {
                while ($subcategory = $subcategories_result->fetch_assoc()) {
                    $subcategories[] = $subcategory;
                }
            } else {
                error_log("Failed to get subcategories: " . $conn->error);
            }
        }
        
        $categories_by_parent[] = [
            'parent' => $main_category,
            'subcategories' => $subcategories
        ];
    }
}

// Check if uploads directory exists and is writable
$upload_dir = '../uploads/products/';
if (!file_exists($upload_dir)) {
    if (!@mkdir($upload_dir, 0777, true)) {
        $errors[] = 'Upload directory does not exist and could not be created. Please check server permissions.';
    }
} elseif (!is_writable($upload_dir)) {
    $errors[] = 'Upload directory exists but is not writable. Please check server permissions.';
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $name = htmlspecialchars(trim($_POST['name']));
    $description = htmlspecialchars(trim($_POST['description']));
    $category = intval($_POST['category']);
    $price = floatval($_POST['price']);
    $discount = isset($_POST['discount']) ? floatval($_POST['discount']) : 0;
    
    // Validate product data
    if (empty($name)) {
        $errors[] = 'Product name is required';
    }
    
    if (empty($description)) {
        $errors[] = 'Product description is required';
    }
    
    if ($category <= 0) {
        $errors[] = 'Please select a valid category';
    }
    
    if ($price <= 0) {
        $errors[] = 'Price must be greater than zero';
    }
    
    if ($discount < 0 || $discount > 100) {
        $errors[] = 'Discount must be between 0 and 100 percent';
    }
    
    // Check if image is uploaded
    if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Product image is required';
    } else {
        $image = $_FILES['image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        
        if (!in_array($image['type'], $allowed_types)) {
            $errors[] = 'Only JPG, JPEG and PNG images are allowed';
        }
        
        if ($image['size'] > 5000000) { // 5MB limit
            $errors[] = 'Image size must be less than 5MB';
        }
    }
    
    // If no errors, process the submission
    if (empty($errors)) {
        // Upload image
        $image_name = time() . '_' . str_replace(' ', '_', $image['name']);
        $upload_path = $upload_dir . $image_name;
        
        if (move_uploaded_file($image['tmp_name'], $upload_path)) {
            // Insert product into database
            $insert_query = "INSERT INTO products (business_id, name, description, price, discount, category_id, image, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $insert_stmt = $conn->prepare($insert_query);
            
            if (!$insert_stmt) {
                $errors[] = 'Database error: ' . $conn->error;
            } else {
                $image_path = 'uploads/products/' . $image_name;
                $insert_stmt->bind_param('issdiss', $business_id, $name, $description, $price, $discount, $category, $image_path);
                
                if ($insert_stmt->execute()) {
                    $success_message = 'Product added successfully!';
                    // Reset form
                    $name = $description = $category = $price = '';
                    $discount = 0;
                } else {
                    $errors[] = 'Failed to add product: ' . $conn->error;
                }
            }
        } else {
            $errors[] = 'Failed to upload image';
        }
    }
}

// Set page title
$page_title = "Add New Product";

// Get base URL
$base_url = get_base_url();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | LocalConnect</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
            color: #333;
        }
        .form-control:focus, .form-select:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
        }
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
        }
        .card-header {
            background-color: #28a745;
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        .tips-card {
            background-color: #f0f9ff;
            border-left: 4px solid #0dcaf0;
        }
        .tips-card .bi {
            color: #0dcaf0;
        }
    </style>
</head>
<body>
<?php include_once('../includes/header.php'); ?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mt-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="index.php">Business Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Add Product</li>
        </ol>
    </nav>
    
    <h1 class="mb-4"><?php echo $page_title; ?></h1>
    
    <!-- Display Flash Messages -->
    <?php if (isset($_SESSION['flash_messages']['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['flash_messages']['success']; unset($_SESSION['flash_messages']['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['flash_messages']['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['flash_messages']['error']; unset($_SESSION['flash_messages']['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['flash_messages']['info'])): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['flash_messages']['info']; unset($_SESSION['flash_messages']['info']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($missing_parent_id): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <h5><i class="bi bi-exclamation-triangle-fill"></i> Category Structure Issue</h5>
            <p>The category hierarchy structure is not properly set up in the database. This can affect category selection.</p>
            <div class="mt-2">
                <a href="../fix_db_structure.php" class="btn btn-primary">
                    <i class="bi bi-tools me-2"></i>Fix Database Structure
                </a>
                <a href="../setup_subcategories.php" class="btn btn-outline-primary ms-2">
                    <i class="bi bi-diagram-3 me-2"></i>Setup Categories
                </a>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Form Success/Error Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Please correct the following errors:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header py-3">
                    <h5 class="mb-0">Product Information</h5>
                </div>
                <div class="card-body p-4">
                    <form action="" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="name" class="form-label fw-bold">Product Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-lg" id="name" name="name" value="<?php echo $name; ?>" required>
                            <div class="invalid-feedback">Product name is required</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="category" class="form-label fw-bold">Category <span class="text-danger">*</span></label>
                            <select class="form-select form-select-lg" id="category" name="category" required>
                                <option value="">Select a category</option>
                                <?php foreach ($categories_by_parent as $category_group): ?>
                                    <optgroup label="<?php echo $category_group['parent'][$name_column]; ?>">
                                        <!-- Main category itself as an option -->
                                        <option value="<?php echo $category_group['parent'][$id_column]; ?>" 
                                                <?php echo $category == $category_group['parent'][$id_column] ? 'selected' : ''; ?>>
                                            <?php echo $category_group['parent'][$name_column]; ?> (General)
                                        </option>
                                        
                                        <!-- Subcategories -->
                                        <?php foreach ($category_group['subcategories'] as $subcategory): ?>
                                            <option value="<?php echo $subcategory[$id_column]; ?>" 
                                                    <?php echo $category == $subcategory[$id_column] ? 'selected' : ''; ?>>
                                                <?php echo $subcategory[$name_column]; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a category</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label fw-bold">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="4" required><?php echo $description; ?></textarea>
                            <div class="invalid-feedback">Product description is required</div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="price" class="form-label fw-bold">Price (₹) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-lg" id="price" name="price" value="<?php echo $price; ?>" required>
                                    <div class="invalid-feedback">Please enter a valid price</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="discount" class="form-label fw-bold">Discount (%)</label>
                                <div class="input-group">
                                    <input type="number" step="1" min="0" max="100" class="form-control form-control-lg" id="discount" name="discount" value="<?php echo $discount; ?>">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="image" class="form-label fw-bold">Product Image <span class="text-danger">*</span></label>
                            <input type="file" class="form-control form-control-lg" id="image" name="image" accept="image/jpeg, image/png, image/jpg" required>
                            <div class="form-text">Upload a high-quality image in JPG, JPEG or PNG format (max 5MB)</div>
                            <div class="invalid-feedback">Please select a product image</div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="products.php" class="btn btn-outline-secondary btn-lg px-4">Cancel</a>
                            <button type="submit" class="btn btn-success btn-lg px-5">Add Product</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card tips-card mb-4">
                <div class="card-body p-4">
                    <h5 class="card-title mb-3">
                        <i class="bi bi-lightbulb-fill me-2"></i>
                        Tips for Product Listing
                    </h5>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><i class="bi bi-check-circle-fill me-2 text-success"></i> Use a clear, descriptive product name</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill me-2 text-success"></i> Include detailed product specifications</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill me-2 text-success"></i> Upload high-quality product images</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill me-2 text-success"></i> Set competitive pricing</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill me-2 text-success"></i> Choose the most appropriate category</li>
                        <li><i class="bi bi-check-circle-fill me-2 text-success"></i> Use discounts to attract customers</li>
                    </ul>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-body p-4">
                    <h5 class="card-title mb-3">
                        <i class="bi bi-card-image me-2"></i>
                        Image Guidelines
                    </h5>
                    <p>For the best product presentation:</p>
                    <ul>
                        <li>Use a white or neutral background</li>
                        <li>Ensure good lighting to show product details</li>
                        <li>Upload images with at least 800x800 pixels</li>
                        <li>Show the product from multiple angles if possible</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="bg-dark text-white mt-5 py-4">
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <h5>LocalConnect+</h5>
                <p class="small">Connecting local businesses with customers</p>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="small">&copy; <?php echo date('Y'); ?> LocalConnect+. All rights reserved.</p>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Form Validation Script -->
<script>
(function () {
    'use strict'
    
    // Fetch all forms with needs-validation class
    var forms = document.querySelectorAll('.needs-validation')
    
    // Loop over them and prevent submission
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                
                form.classList.add('was-validated')
            }, false)
        })
})()
</script>
</body>
</html> 
