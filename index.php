<?php
/**
 * Home Page
 * Main landing page for LocalConnect
 */

// Include required files
require_once('config/database.php');
require_once('utils/helpers.php');

// Start session
start_session_if_not_exists();

// Connect to database
$conn = connect_db();

// Check if product_images has is_primary field
$has_is_primary = false;
$image_fields_result = $conn->query("SHOW COLUMNS FROM product_images LIKE 'is_primary'");
if ($image_fields_result && $image_fields_result->num_rows > 0) {
    $has_is_primary = true;
}

// Get featured products (newest 8 products)
$featured_products_query = "
    SELECT p.*, b.business_name, 
           " . ($has_is_primary ? 
           "(SELECT image_path FROM product_images WHERE product_id = p.product_id AND is_primary = 1 LIMIT 1) AS image" : 
           "(SELECT image_path FROM product_images WHERE product_id = p.product_id LIMIT 1) AS image") . "
    FROM products p
    INNER JOIN businesses b ON p.business_id = b.business_id
    ORDER BY p.created_at DESC
    LIMIT 8
";
$featured_products_result = $conn->query($featured_products_query);

// Get deal products (products with discount)
$top_rated_query = "
    SELECT p.*, b.business_name,
           " . ($has_is_primary ? 
           "(SELECT image_path FROM product_images WHERE product_id = p.product_id AND is_primary = 1 LIMIT 1) AS image" : 
           "(SELECT image_path FROM product_images WHERE product_id = p.product_id LIMIT 1) AS image") . ",
           ROUND(p.price * (1 - p.discount_percent/100), 2) as discounted_price
    FROM products p
    INNER JOIN businesses b ON p.business_id = b.business_id
    WHERE p.discount_percent > 0
    ORDER BY p.discount_percent DESC
    LIMIT 4
";
$top_rated_result = $conn->query($top_rated_query);

// Get featured businesses (newest 4 businesses)
$featured_businesses_query = "
    SELECT b.*, COUNT(p.product_id) as product_count
    FROM businesses b
    LEFT JOIN products p ON b.business_id = p.business_id
    GROUP BY b.business_id
    ORDER BY b.created_at DESC
    LIMIT 4
";
$featured_businesses_result = $conn->query($featured_businesses_query);

// Get trending items
$trending_query = "
    SELECT p.*, b.business_name,
           " . ($has_is_primary ? 
           "(SELECT image_path FROM product_images WHERE product_id = p.product_id AND is_primary = 1 LIMIT 1) AS image" : 
           "(SELECT image_path FROM product_images WHERE product_id = p.product_id LIMIT 1) AS image") . "
    FROM products p
    INNER JOIN businesses b ON p.business_id = b.business_id
    ORDER BY RAND()
    LIMIT 8
";
$trending_result = $conn->query($trending_query);

// Close database connection
close_db($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LocalConnect+ - Empower Small Businesses</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('assets/images/hero-bg.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 0;
            margin-bottom: 2rem;
        }
        
        .product-card {
            transition: transform 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
        }
        
        .product-img {
            height: 200px;
            object-fit: cover;
        }
        
        .business-logo {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .feature-icon {
            font-size: 2.5rem;
            color: #0d6efd;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php include_once('includes/header.php'); ?>
    
    <!-- Main Banner Slider -->
    <div class="banner-container container mt-3 position-relative">
        <div id="mainBanner" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-indicators">
                <button type="button" data-bs-target="#mainBanner" data-bs-slide-to="0" class="active"></button>
                <button type="button" data-bs-target="#mainBanner" data-bs-slide-to="1"></button>
                <button type="button" data-bs-target="#mainBanner" data-bs-slide-to="2"></button>
            </div>
            <div class="carousel-inner">
                <div class="carousel-item active">
                    <img src="assets/images/banners/banner1.jpg" class="d-block w-100 banner-img" alt="Special Offer">
                </div>
                <div class="carousel-item">
                    <img src="assets/images/banners/banner2.jpg" class="d-block w-100 banner-img" alt="New Collection">
                </div>
                <div class="carousel-item">
                    <img src="assets/images/banners/banner3.jpg" class="d-block w-100 banner-img" alt="Summer Sale">
                </div>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#mainBanner" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#mainBanner" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    </div>
    
    <!-- Deals of the Day -->
    <div class="container mt-4">
        <div class="section-container">
            <div class="section-title d-flex justify-content-between align-items-center">
                <h3><i class="bi bi-lightning-fill text-warning me-2"></i>Deals of the Day</h3>
                <a href="products/browse.php?deal=1" class="btn btn-sm btn-outline-primary rounded-pill">View All <i class="bi bi-arrow-right"></i></a>
            </div>
            
            <div class="row">
                <?php if ($top_rated_result && $top_rated_result->num_rows > 0): ?>
                    <?php while ($product = $top_rated_result->fetch_assoc()): ?>
                        <div class="col-6 col-md-3">
                            <div class="card h-100 animate-fadeIn">
                                <div class="badge-offer position-absolute top-0 end-0 m-2 bg-danger text-white rounded-pill px-3 py-1">
                                    <?php echo $product['discount_percent']; ?>% OFF
                                </div>
                                <a href="products/details.php?id=<?php echo $product['product_id']; ?>">
                                    <img src="<?php echo $product['image'] ?? 'assets/images/product-placeholder.jpg'; ?>" 
                                         class="card-img-top" alt="<?php echo $product['name']; ?>">
                                </a>
                                <div class="card-body">
                                    <h5 class="card-title text-truncate-2"><?php echo $product['name']; ?></h5>
                                    <p class="card-text text-truncate-2"><?php echo $product['business_name']; ?></p>
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="rating">
                                            <?php for ($i = 0; $i < 5; $i++): ?>
                                                <i class="bi <?php echo $i < rand(3, 5) ? 'bi-star-fill' : 'bi-star'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="rating-count">(<?php echo rand(10, 500); ?>)</span>
                                    </div>
                                    <div>
                                        <span class="price"><?php echo format_price($product['discounted_price']); ?></span>
                                        <span class="original-price"><?php echo format_price($product['price']); ?></span>
                                        <span class="discount"><?php echo $product['discount_percent']; ?>% off</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-4">
                        <p>No deals available at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Category Offers -->
    <div class="container mt-5">
        <div class="section-title d-flex justify-content-between align-items-center">
            <h3><i class="bi bi-grid-fill me-2 text-primary"></i>Shop by Category</h3>
        </div>
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="card bg-light h-100">
                    <div class="card-body p-4 text-center">
                        <i class="bi bi-laptop fs-1 text-primary mb-3"></i>
                        <h4>Electronics</h4>
                        <p class="text-success">Up to 40% Off</p>
                        <img src="assets/images/categories/electronics-offer.jpg" class="img-fluid rounded mb-3" alt="Electronics">
                        <a href="categories.php?category=electronics" class="btn btn-outline-primary">Shop Now</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card bg-light h-100">
                    <div class="card-body p-4 text-center">
                        <i class="bi bi-shirt fs-1 text-primary mb-3"></i>
                        <h4>Fashion</h4>
                        <p class="text-success">30-60% Off</p>
                        <img src="assets/images/categories/fashion-offer.jpg" class="img-fluid rounded mb-3" alt="Fashion">
                        <a href="categories.php?category=fashion" class="btn btn-outline-primary">Shop Now</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card bg-light h-100">
                    <div class="card-body p-4 text-center">
                        <i class="bi bi-house fs-1 text-primary mb-3"></i>
                        <h4>Home Decor</h4>
                        <p class="text-success">From ₹499</p>
                        <img src="assets/images/categories/home-offer.jpg" class="img-fluid rounded mb-3" alt="Home Decor">
                        <a href="categories.php?category=home" class="btn btn-outline-primary">Shop Now</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Featured Products -->
    <div class="container my-5">
        <div class="section-title d-flex justify-content-between align-items-center">
            <h3><i class="bi bi-award me-2 text-primary"></i>Featured Products</h3>
            <a href="products/browse.php?featured=1" class="btn btn-sm btn-outline-primary rounded-pill">View All <i class="bi bi-arrow-right"></i></a>
        </div>
        
        <?php if ($featured_products_result && $featured_products_result->num_rows > 0): ?>
            <div class="row">
                <?php while ($product = $featured_products_result->fetch_assoc()): ?>
                    <div class="col-6 col-md-3 mb-4">
                        <div class="card product-card h-100">
                            <a href="products/details.php?id=<?php echo $product['product_id']; ?>">
                                <img src="<?php echo $product['image'] ?? 'assets/images/product-placeholder.jpg'; ?>" 
                                     class="card-img-top product-img" alt="<?php echo $product['name']; ?>">
                            </a>
                            <div class="card-body">
                                <h5 class="card-title text-truncate-2"><?php echo $product['name']; ?></h5>
                                <p class="card-text text-truncate-2 mb-1 text-secondary"><?php echo $product['business_name']; ?></p>
                                <div class="d-flex align-items-center mb-2">
                                    <div class="rating">
                                        <?php for ($i = 0; $i < 5; $i++): ?>
                                            <i class="bi <?php echo $i < rand(3, 5) ? 'bi-star-fill' : 'bi-star'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="rating-count">(<?php echo rand(10, 500); ?>)</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="price"><?php echo format_price($product['price']); ?></span>
                                    <button class="btn btn-sm btn-primary add-to-cart-btn" data-product-id="<?php echo $product['product_id']; ?>">
                                        <i class="bi bi-cart-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <p>No featured products available at the moment.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Mid Banner -->
    <div class="container mt-4">
        <div class="banner-container">
            <img src="assets/images/banners/mid-banner.jpg" class="banner-img" alt="Special Offer">
        </div>
    </div>
    
    <!-- Featured Businesses -->
    <div class="container mt-4">
        <div class="section-container">
            <div class="section-title">
                <span>Featured Businesses</span>
                <a href="business/index.php" class="view-all">View All</a>
            </div>
            
            <?php if ($featured_businesses_result && $featured_businesses_result->num_rows > 0): ?>
                <div class="row">
                    <?php while ($business = $featured_businesses_result->fetch_assoc()): ?>
                        <div class="col-6 col-lg-3 mb-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <img src="<?php echo $business['logo'] ?? 'assets/images/business-placeholder.jpg'; ?>" 
                                             class="business-logo" alt="<?php echo $business['business_name']; ?>">
                                    </div>
                                    <h5 class="card-title"><?php echo $business['business_name']; ?></h5>
                                    <p class="text-muted mb-2 small">
                                        <?php 
                                        if (isset($business['city']) && isset($business['state'])) {
                                            echo $business['city'] . ', ' . $business['state'];
                                        } elseif (isset($business['location'])) {
                                            echo $business['location'];
                                        } else {
                                            echo "Location not specified";
                                        }
                                        ?>
                                    </p>
                                    
                                    <?php if (!empty($business['business_category'])): ?>
                                    <div class="badge bg-primary mb-2">
                                        <?php 
                                        $category_labels = [
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
                                        $category_code = $business['business_category'];
                                        echo isset($category_labels[$category_code]) ? $category_labels[$category_code] : 'Unknown';
                                        ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="badge bg-light text-dark mb-3"><?php echo $business['product_count']; ?> products</div>
                                    <a href="business/profile.php?id=<?php echo $business['business_id']; ?>" class="btn btn-outline-primary btn-sm">View Shop</a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <p>No featured businesses available at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Trending Now -->
    <div class="container mt-4">
        <div class="section-container">
            <div class="section-title">
                <span>Trending Now <i class="bi bi-fire text-danger"></i></span>
                <a href="products/browse.php?trending=1" class="view-all">View All</a>
            </div>
            
            <?php if ($trending_result && $trending_result->num_rows > 0): ?>
                <div class="row">
                    <?php while ($product = $trending_result->fetch_assoc()): ?>
                        <div class="col-6 col-md-3 mb-4">
                            <div class="card product-card h-100">
                                <div class="badge bg-danger position-absolute top-0 end-0 m-2">Trending</div>
                                <a href="products/details.php?id=<?php echo $product['product_id']; ?>">
                                    <img src="<?php echo $product['image'] ?? 'assets/images/product-placeholder.jpg'; ?>" 
                                         class="card-img-top product-img" alt="<?php echo $product['name']; ?>">
                                </a>
                                <div class="card-body">
                                    <h5 class="card-title text-truncate-2"><?php echo $product['name']; ?></h5>
                                    <p class="card-text text-truncate-2 mb-1 text-secondary"><?php echo $product['business_name']; ?></p>
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="rating">
                                            <?php for ($i = 0; $i < 5; $i++): ?>
                                                <i class="bi <?php echo $i < rand(3, 5) ? 'bi-star-fill' : 'bi-star'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="rating-count">(<?php echo rand(100, 999); ?>)</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="price"><?php echo format_price($product['price']); ?></span>
                                        <button class="btn btn-sm btn-primary add-to-cart-btn" data-product-id="<?php echo $product['product_id']; ?>">
                                            <i class="bi bi-cart-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="col-12 text-center py-4">
                    <p>No trending products available at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Why Shop With Us -->
    <div class="container mt-5 mb-5">
        <h2 class="text-center mb-4">Why Shop With Us?</h2>
        <div class="row text-center">
            <div class="col-md-3 mb-4">
                <div class="feature-icon">
                    <i class="bi bi-truck"></i>
                </div>
                <h5>Free & Fast Delivery</h5>
                <p class="text-muted">Free delivery on all orders above ₹500</p>
            </div>
            <div class="col-md-3 mb-4">
                <div class="feature-icon">
                    <i class="bi bi-shield-check"></i>
                </div>
                <h5>100% Secure Payment</h5>
                <p class="text-muted">Multiple payment options available</p>
            </div>
            <div class="col-md-3 mb-4">
                <div class="feature-icon">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
                <h5>Easy Returns</h5>
                <p class="text-muted">10-day easy return policy</p>
            </div>
            <div class="col-md-3 mb-4">
                <div class="feature-icon">
                    <i class="bi bi-headset"></i>
                </div>
                <h5>24/7 Support</h5>
                <p class="text-muted">Dedicated customer support</p>
            </div>
        </div>
    </div>
    
    <!-- App Download -->
    <div class="container-fluid bg-light py-5 mt-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h3>Download LocalConnect+ App</h3>
                    <p class="text-muted mb-4">Shop on the go with our mobile app. Get exclusive app-only deals and shop faster with saved preferences.</p>
                    <div class="d-flex">
                        <a href="#" class="me-3">
                            <img src="assets/images/app/google-play.png" alt="Google Play" height="50">
                        </a>
                        <a href="#">
                            <img src="assets/images/app/app-store.png" alt="App Store" height="50">
                        </a>
                    </div>
                </div>
                <div class="col-md-6 text-center mt-4 mt-md-0">
                    <img src="assets/images/app-mockup.png" alt="App Mockup" class="img-fluid" style="max-height: 400px;">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Feature Section (New) -->
    <div class="container mt-5 mb-5">
        <div class="row g-4">
            <div class="col-md-3">
                <div class="card h-100 text-center p-4 border-0 animate-fadeIn">
                    <div class="feature-icon mx-auto">
                        <i class="bi bi-truck text-primary"></i>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">Free Shipping</h5>
                        <p class="card-text text-muted">On orders over $50</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 text-center p-4 border-0 animate-fadeIn" style="animation-delay: 0.1s;">
                    <div class="feature-icon mx-auto">
                        <i class="bi bi-shield-check text-primary"></i>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">Secure Payment</h5>
                        <p class="card-text text-muted">100% secure payment</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 text-center p-4 border-0 animate-fadeIn" style="animation-delay: 0.2s;">
                    <div class="feature-icon mx-auto">
                        <i class="bi bi-box-seam text-primary"></i>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">Quality Products</h5>
                        <p class="card-text text-muted">From trusted vendors</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 text-center p-4 border-0 animate-fadeIn" style="animation-delay: 0.3s;">
                    <div class="feature-icon mx-auto">
                        <i class="bi bi-headset text-primary"></i>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">24/7 Support</h5>
                        <p class="card-text text-muted">Dedicated support</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include_once('includes/footer.php'); ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
    
    <?php if (is_customer()): ?>
    <script>
        // Get base URL
        function getBaseUrl() {
            const pathArray = window.location.pathname.split('/');
            const basePathIndex = pathArray.indexOf('LocalConnect');
            
            if (basePathIndex !== -1) {
                const basePath = pathArray.slice(0, basePathIndex + 1).join('/');
                return window.location.protocol + '//' + window.location.host + basePath + '/';
            }
            
            return '/';
        }
        
        // Update cart count
        fetch(getBaseUrl() + 'api/cart-count.php')
            .then(response => response.json())
            .then(data => {
                document.getElementById('cart-count').textContent = data.count;
            })
            .catch(error => console.error('Error fetching cart count:', error));
    </script>
    <?php endif; ?>
</body>
</html> 