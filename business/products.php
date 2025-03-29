<?php
/**
 * Product Management Page
 * Allows business owners to manage their products
 */

// Set error reporting to show all errors except notices and warnings 
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Include required files
require_once('../config/database.php');
require_once('../utils/helpers.php');

// Connect to database
$conn = connect_db();

// Start session if not already started
start_session_if_not_exists();

// Check if user is logged in
if (!is_logged_in()) {
    set_flash_message('error', 'You must be logged in to view your products');
    redirect('../auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Check if user is a business owner
if (!is_business_owner()) {
    set_flash_message('error', 'You must have a business account to access this page');
    redirect('../index.php');
    exit;
}

// Get user's business ID
$user_id = $_SESSION['user_id'];

// Check the businesses table structure first
$business_columns = [];
$business_structure = $conn->query("DESCRIBE businesses");
if ($business_structure) {
    while ($col = $business_structure->fetch_assoc()) {
        $business_columns[] = $col['Field'];
    }
}

// Determine the correct ID column name
$id_column = 'id';
if (!in_array('id', $business_columns) && in_array('business_id', $business_columns)) {
    $id_column = 'business_id';
}

// First try with the determined ID column
$business_query = "SELECT $id_column, name FROM businesses WHERE user_id = ?";
$business_stmt = $conn->prepare($business_query);

// Check if prepare statement failed
if (!$business_stmt) {
    echo "<div class='alert alert-danger'>";
    echo "<h3>Database Structure Error</h3>";
    echo "<p>Cannot find the right column name in businesses table. Please run the db_setup.php script to create proper tables.</p>";
    echo "<p>Error: " . $conn->error . "</p>";
    echo "<p><a href='../fix_businesses_table.php' class='btn btn-primary'>Fix Businesses Table</a> ";
    echo "<a href='../db_setup.php' class='btn btn-secondary'>Run Database Setup</a></p>";
    die("</div>");
}

$business_stmt->bind_param('i', $user_id);
$business_stmt->execute();
$business_result = $business_stmt->get_result();

if ($business_result->num_rows === 0) {
    set_flash_message('info', 'You need to complete your business profile before managing products');
    redirect('register.php?redirect=products');
    exit;
}

$business = $business_result->fetch_assoc();

// Check which column name was used (id or business_id)
if (isset($business['id'])) {
    $business_id = $business['id'];
} else if (isset($business['business_id'])) {
    $business_id = $business['business_id'];
} else {
    die("Could not determine business ID column. Please run db_setup.php to fix database structure.");
}

$business_name = $business['name'];

// Handle product deletion
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $product_id = intval($_GET['delete']);
    
    // Verify product belongs to this business - first try with id column
    $check_query = "SELECT id FROM products WHERE id = ? AND business_id = ?";
    $check_stmt = $conn->prepare($check_query);

    // If it fails, try product_id
    if (!$check_stmt) {
        $check_query = "SELECT product_id FROM products WHERE product_id = ? AND business_id = ?";
        $check_stmt = $conn->prepare($check_query);
        
        if (!$check_stmt) {
            set_flash_message('error', "Database error: " . $conn->error . ". Column structure issue in products table.");
            redirect('products.php');
            exit;
        }
    }

    $check_stmt->bind_param('ii', $product_id, $business_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Determine if we're using id or product_id
        $product_row = $check_result->fetch_assoc();
        $product_id_column = isset($product_row['id']) ? 'id' : 'product_id';
        
        // Get image path to delete file - try with appropriate ID column
        $image_query = "SELECT image FROM products WHERE $product_id_column = ?";
        $image_stmt = $conn->prepare($image_query);

        if (!$image_stmt) {
            set_flash_message('error', "Database error with image query: " . $conn->error);
            redirect('products.php');
            exit;
        }

        $image_stmt->bind_param('i', $product_id);
        $image_stmt->execute();
        $image_result = $image_stmt->get_result();
        $image_row = $image_result->fetch_assoc();
        
        // Delete product using appropriate ID column
        $delete_query = "DELETE FROM products WHERE $product_id_column = ?";
        $delete_stmt = $conn->prepare($delete_query);

        if (!$delete_stmt) {
            set_flash_message('error', "Database error with delete query: " . $conn->error);
            redirect('products.php');
            exit;
        }

        $delete_stmt->bind_param('i', $product_id);
        
        if ($delete_stmt->execute()) {
            // Delete image file if it exists
            if (!empty($image_row['image']) && file_exists('../' . $image_row['image']) && $image_row['image'] != 'assets/img/products/default.jpg') {
                @unlink('../' . $image_row['image']);
            }
            
            set_flash_message('success', 'Product deleted successfully');
        } else {
            set_flash_message('error', 'Failed to delete product: ' . $conn->error);
        }
    } else {
        set_flash_message('error', 'You do not have permission to delete this product');
    }
    
    // Redirect to remove the delete parameter from URL
    redirect('products.php');
}

// Get products with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$search_condition = '';
$search_params = [];

if (!empty($search)) {
    $search_condition .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $search_params[] = "%$search%";
    $search_params[] = "%$search%";
}

// Check if categories have parent_id to determine structure
$parent_check = $conn->query("SHOW COLUMNS FROM categories LIKE 'parent_id'");
$has_parent_id = ($parent_check && $parent_check->num_rows > 0);

// If parent_id column is missing, we need to show a warning
$missing_parent_id = !$has_parent_id;

if ($category_filter > 0) {
    if ($has_parent_id) {
        // Get subcategories of the selected main category
        $subcategories_query = "SELECT $category_id_column FROM categories WHERE parent_id = ? OR $category_id_column = ?";
        $subcategories_stmt = $conn->prepare($subcategories_query);
        
        if (!$subcategories_stmt) {
            // Just filter by the selected category if query fails
            $search_condition .= " AND p.category_id = ?";
            $search_params[] = $category_filter;
        } else {
            $subcategories_stmt->bind_param('ii', $category_filter, $category_filter);
            $subcategories_stmt->execute();
            $subcategories_result = $subcategories_stmt->get_result();
            
            if ($subcategories_result->num_rows > 0) {
                $category_ids = [];
                while ($cat = $subcategories_result->fetch_assoc()) {
                    $category_ids[] = $cat[$category_id_column];
                }
                
                if (count($category_ids) > 0) {
                    $placeholders = implode(',', array_fill(0, count($category_ids), '?'));
                    $search_condition .= " AND p.category_id IN ($placeholders)";
                    $search_params = array_merge($search_params, $category_ids);
                }
            } else {
                // Just filter by the selected category
                $search_condition .= " AND p.category_id = ?";
                $search_params[] = $category_filter;
            }
        }
    } else {
        // Simple category filter for flat structure
        $search_condition .= " AND p.category_id = ?";
        $search_params[] = $category_filter;
    }
}

// Try to determine the correct column names by checking if products table uses id or product_id
$column_check = $conn->query("SHOW COLUMNS FROM products LIKE 'id'");
$product_id_column = ($column_check->num_rows > 0) ? 'id' : 'product_id';

// Get total products count for pagination
$count_query = "SELECT COUNT(*) as total FROM products p WHERE p.business_id = ?" . $search_condition;
$count_stmt = $conn->prepare($count_query);

// Check if prepare statement failed
if (!$count_stmt) {
    set_flash_message('error', "Database error with count query: " . $conn->error);
    redirect('index.php');
    exit;
}

if (!empty($search_params)) {
    $types = str_repeat('s', count($search_params)); // Create a string of 's' characters for each param
    $bind_params = array_merge(['i' . $types], [$business_id], $search_params);
    $count_stmt->bind_param(...$bind_params);
} else {
    $count_stmt->bind_param('i', $business_id);
}

$count_stmt->execute();
$count_result = $count_stmt->get_result();
$count_row = $count_result->fetch_assoc();
$total_products = $count_row['total'];
$total_pages = ceil($total_products / $limit);

// Get products, using the determined ID column
$products_query = "SELECT p.*, c.{$category_name_column} as category_name 
                   FROM products p 
                   LEFT JOIN categories c ON p.category_id = c.{$category_id_column} 
                   WHERE p.business_id = ?" . $search_condition . " 
                   ORDER BY p.created_at DESC 
                   LIMIT ? OFFSET ?";
$products_stmt = $conn->prepare($products_query);

// Check if prepare statement failed
if (!$products_stmt) {
    set_flash_message('error', "Database error with products query: " . $conn->error . ". Please check your database structure.");
    redirect('index.php');
    exit;
}

if (!empty($search_params)) {
    $types = str_repeat('s', count($search_params)); // Create a string of 's' characters for each param
    $bind_params = array_merge(['i' . $types . 'ii'], [$business_id], $search_params, [$limit, $offset]);
    $products_stmt->bind_param(...$bind_params);
} else {
    $products_stmt->bind_param('iii', $business_id, $limit, $offset);
}

$products_stmt->execute();
$products_result = $products_stmt->get_result();

// Set page title
$page_title = "My Products";

// Get base URL
$base_url = get_base_url();

// Get categories for the filter dropdown
$category_id_column = 'id';
$category_name_column = 'name';

// Check if categories use id or category_id
$cat_column_check = $conn->query("SHOW COLUMNS FROM categories LIKE 'id'");
if ($cat_column_check->num_rows === 0) {
    $category_id_column = 'category_id';
    $category_name_column = 'category_name';
}

// Check if categories have parent_id to determine structure
$parent_check = $conn->query("SHOW COLUMNS FROM categories LIKE 'parent_id'");
$categories_by_parent = [];

if ($parent_check->num_rows > 0) {
    // Get main categories (parent_id IS NULL or 0)
    $main_categories_query = "SELECT * FROM categories WHERE parent_id IS NULL OR parent_id = 0";
    $main_categories_result = $conn->query($main_categories_query);
    
    if ($main_categories_result && $main_categories_result->num_rows > 0) {
        while ($main_category = $main_categories_result->fetch_assoc()) {
            $main_id = $main_category[$category_id_column];
            $categories_by_parent[$main_id] = [
                'parent' => $main_category,
                'subcategories' => []
            ];
            
            // Get subcategories
            $sub_query = "SELECT * FROM categories WHERE parent_id = ?";
            $sub_stmt = $conn->prepare($sub_query);
            $sub_stmt->bind_param('i', $main_id);
            $sub_stmt->execute();
            $sub_result = $sub_stmt->get_result();
            
            while ($sub = $sub_result->fetch_assoc()) {
                $categories_by_parent[$main_id]['subcategories'][] = $sub;
            }
        }
    }
} else {
    // Flat category structure, get all categories
    $all_categories_query = "SELECT * FROM categories";
    $all_categories_result = $conn->query($all_categories_query);
    
    if ($all_categories_result && $all_categories_result->num_rows > 0) {
        while ($category = $all_categories_result->fetch_assoc()) {
            $cat_id = $category[$category_id_column];
            $categories_by_parent[$cat_id] = [
                'parent' => $category,
                'subcategories' => []
            ];
        }
    }
}
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
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #28a745;
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        .product-image {
            height: 180px;
            object-fit: cover;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        .stats-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        .stats-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        .page-link {
            color: #28a745;
        }
        .page-item.active .page-link {
            background-color: #28a745;
            border-color: #28a745;
        }
        .table th {
            background-color: #f8f9fa;
        }
        .search-form .form-control:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
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
            <li class="breadcrumb-item active" aria-current="page">My Products</li>
        </ol>
    </nav>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0"><?php echo $page_title; ?></h1>
        <a href="add_product.php" class="btn btn-success">
            <i class="bi bi-plus-lg me-2"></i>Add New Product
        </a>
    </div>
    
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
            <p>The category hierarchy structure is not properly set up in the database. This can affect category filtering.</p>
            <div class="mt-2">
                <a href="../setup_subcategories.php" class="btn btn-primary">
                    <i class="bi bi-diagram-3 me-2"></i>Run Category Setup
                </a>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card stats-card mb-4 mb-md-0">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1">Total Products</h5>
                            <h2 class="mb-0 fw-bold"><?php echo $total_products; ?></h2>
                        </div>
                        <div class="stats-icon">
                            <i class="bi bi-box-seam"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card mb-0">
                <div class="card-body p-4">
                    <form class="search-form" action="" method="GET">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <input type="text" class="form-control form-control-lg" placeholder="Search products..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-success px-4" type="submit">
                                        <i class="bi bi-search me-2"></i>Search
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select form-select-lg" name="category" id="category-filter">
                                    <option value="0">All Categories</option>
                                    <?php foreach ($categories_by_parent as $cat_id => $category_group): ?>
                                        <optgroup label="<?php echo htmlspecialchars($category_group['parent'][$category_name_column]); ?>">
                                            <!-- Main category itself as an option -->
                                            <option value="<?php echo $cat_id; ?>" 
                                                    <?php echo $category_filter == $cat_id ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category_group['parent'][$category_name_column]); ?> (General)
                                            </option>
                                            
                                            <!-- Subcategories -->
                                            <?php foreach ($category_group['subcategories'] as $subcategory): ?>
                                                <?php $sub_id = $subcategory[$category_id_column]; ?>
                                                <option value="<?php echo $sub_id; ?>" 
                                                        <?php echo $category_filter == $sub_id ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($subcategory[$category_name_column]); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <?php if (!empty($search) || $category_filter > 0): ?>
                                    <a href="products.php" class="btn btn-outline-secondary btn-lg w-100">Clear Filters</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($products_result->num_rows > 0): ?>
        <!-- Products Table -->
        <div class="card mb-4">
            <div class="card-header py-3">
                <h5 class="mb-0">Products List</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th width="80">Image</th>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Discount</th>
                                <th>Created</th>
                                <th width="150">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($product = $products_result->fetch_assoc()): ?>
                                <?php 
                                    // Determine the correct id column for this product
                                    $product_id = isset($product['id']) ? $product['id'] : $product['product_id'];
                                    
                                    $price = $product['price'];
                                    // Get discount from either discount or discount_percent
                                    $discount = isset($product['discount']) ? $product['discount'] : 
                                               (isset($product['discount_percent']) ? $product['discount_percent'] : 0);
                                    $discounted_price = $price - ($price * $discount / 100);
                                ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo '../' . $product['image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" width="60" height="60" class="rounded">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                    <td>
                                        <?php if ($discount > 0): ?>
                                            <span class="text-decoration-line-through text-muted">₹<?php echo number_format($price, 2); ?></span><br>
                                            <span class="fw-bold text-success">₹<?php echo number_format($discounted_price, 2); ?></span>
                                        <?php else: ?>
                                            <span class="fw-bold">₹<?php echo number_format($price, 2); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($discount > 0): ?>
                                            <span class="badge bg-success"><?php echo $discount; ?>% OFF</span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($product['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="edit_product.php?id=<?php echo $product_id; ?>" class="btn btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="products.php?delete=<?php echo $product_id; ?>" class="btn btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this product?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo ($page <= 1) ? '#' : "products.php?page=" . ($page - 1) . 
                            (!empty($search) ? "&search=" . urlencode($search) : "") . 
                            ($category_filter > 0 ? "&category=" . $category_filter : ""); ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="products.php?page=<?php echo $i . 
                                (!empty($search) ? "&search=" . urlencode($search) : "") . 
                                ($category_filter > 0 ? "&category=" . $category_filter : ""); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo ($page >= $total_pages) ? '#' : "products.php?page=" . ($page + 1) . 
                            (!empty($search) ? "&search=" . urlencode($search) : "") . 
                            ($category_filter > 0 ? "&category=" . $category_filter : ""); ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
        
    <?php else: ?>
        <!-- No Products Message -->
        <div class="card">
            <div class="card-body py-5 text-center">
                <?php if (!empty($search)): ?>
                    <div class="mb-4">
                        <i class="bi bi-search" style="font-size: 3rem; color: #6c757d;"></i>
                    </div>
                    <h3 class="mb-3">No products match your search</h3>
                    <p class="text-muted mb-4">We couldn't find any products matching "<?php echo htmlspecialchars($search); ?>"</p>
                    <a href="products.php" class="btn btn-outline-secondary px-4">Clear Search</a>
                <?php else: ?>
                    <div class="mb-4">
                        <i class="bi bi-box-seam" style="font-size: 3rem; color: #6c757d;"></i>
                    </div>
                    <h3 class="mb-3">No products found</h3>
                    <p class="text-muted mb-4">You haven't added any products to your store yet.</p>
                    <a href="add_product.php" class="btn btn-success px-4">
                        <i class="bi bi-plus-lg me-2"></i>Add Your First Product
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
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
</body>
</html> 