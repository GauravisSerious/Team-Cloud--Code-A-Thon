<?php
/**
 * Database Setup Script
 * 
 * This script checks if the necessary database tables exist and creates them if they don't.
 * Run this script once to setup the database structure for LocalConnect.
 */

// Include required files
require_once('config/database.php');

// Connect to database
$conn = connect_db();

// Set to display all errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>LocalConnect Database Setup</h1>";
echo "<p>Checking and creating database tables...</p>";

// Check if users table exists
$users_check = $conn->query("SHOW TABLES LIKE 'users'");
if ($users_check->num_rows == 0) {
    echo "<p>Creating users table...</p>";
    $create_users = "CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        role ENUM('admin', 'business_owner', 'customer') NOT NULL DEFAULT 'customer',
        is_verified TINYINT(1) DEFAULT 0,
        verification_token VARCHAR(100),
        reset_token VARCHAR(100),
        reset_token_expires DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($create_users)) {
        echo "<p>✅ Users table created successfully.</p>";
    } else {
        echo "<p>❌ Error creating users table: " . $conn->error . "</p>";
    }
} else {
    echo "<p>✅ Users table already exists.</p>";
}

// Check if businesses table exists
$businesses_check = $conn->query("SHOW TABLES LIKE 'businesses'");
if ($businesses_check->num_rows == 0) {
    echo "<p>Creating businesses table...</p>";
    $create_businesses = "CREATE TABLE businesses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        address VARCHAR(255) NOT NULL,
        city VARCHAR(100) NOT NULL,
        state VARCHAR(100) NOT NULL,
        zip VARCHAR(20) NOT NULL,
        description TEXT,
        logo VARCHAR(255),
        website VARCHAR(255),
        business_hours TEXT,
        is_verified TINYINT(1) DEFAULT 0,
        category_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($create_businesses)) {
        echo "<p>✅ Businesses table created successfully.</p>";
    } else {
        echo "<p>❌ Error creating businesses table: " . $conn->error . "</p>";
    }
} else {
    echo "<p>✅ Businesses table already exists.</p>";
}

// Check if categories table exists
$categories_check = $conn->query("SHOW TABLES LIKE 'categories'");
if ($categories_check->num_rows == 0) {
    echo "<p>Creating categories table...</p>";
    $create_categories = "CREATE TABLE categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        parent_id INT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
    )";
    
    if ($conn->query($create_categories)) {
        echo "<p>✅ Categories table created successfully.</p>";
        
        // Insert some default categories
        $default_categories = [
            "Electronics", 
            "Clothing", 
            "Home & Kitchen", 
            "Books", 
            "Toys & Games", 
            "Beauty & Personal Care", 
            "Sports & Outdoors", 
            "Grocery", 
            "Health", 
            "Automotive"
        ];
        
        foreach ($default_categories as $category) {
            $conn->query("INSERT INTO categories (name) VALUES ('" . $conn->real_escape_string($category) . "')");
        }
        
        echo "<p>✅ Default categories added.</p>";
    } else {
        echo "<p>❌ Error creating categories table: " . $conn->error . "</p>";
    }
} else {
    echo "<p>✅ Categories table already exists.</p>";
}

// Check if products table exists
$products_check = $conn->query("SHOW TABLES LIKE 'products'");
if ($products_check->num_rows == 0) {
    echo "<p>Creating products table...</p>";
    $create_products = "CREATE TABLE products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        business_id INT NOT NULL,
        category_id INT,
        name VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        discount DECIMAL(5,2) DEFAULT 0,
        image VARCHAR(255),
        stock_quantity INT DEFAULT 0,
        is_featured TINYINT(1) DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
    )";
    
    if ($conn->query($create_products)) {
        echo "<p>✅ Products table created successfully.</p>";
    } else {
        echo "<p>❌ Error creating products table: " . $conn->error . "</p>";
    }
} else {
    echo "<p>✅ Products table already exists.</p>";
}

// Check for discount_percent column in products table (fix the naming inconsistency)
$column_check = $conn->query("SHOW COLUMNS FROM products LIKE 'discount_percent'");
if ($column_check->num_rows > 0) {
    echo "<p>Converting discount_percent to discount for consistency...</p>";
    
    // Check if discount column exists
    $discount_column_check = $conn->query("SHOW COLUMNS FROM products LIKE 'discount'");
    
    if ($discount_column_check->num_rows == 0) {
        // If discount column doesn't exist, rename discount_percent to discount
        if ($conn->query("ALTER TABLE products CHANGE discount_percent discount DECIMAL(5,2) DEFAULT 0")) {
            echo "<p>✅ Renamed discount_percent to discount.</p>";
        } else {
            echo "<p>❌ Error renaming discount_percent: " . $conn->error . "</p>";
        }
    } else {
        // If both columns exist, migrate data from discount_percent to discount
        if ($conn->query("UPDATE products SET discount = discount_percent WHERE discount = 0 AND discount_percent > 0")) {
            echo "<p>✅ Migrated data from discount_percent to discount.</p>";
            
            if ($conn->query("ALTER TABLE products DROP COLUMN discount_percent")) {
                echo "<p>✅ Dropped discount_percent column.</p>";
            } else {
                echo "<p>❌ Error dropping discount_percent column: " . $conn->error . "</p>";
            }
        } else {
            echo "<p>❌ Error migrating data: " . $conn->error . "</p>";
        }
    }
}

// Creating an example admin user if none exists
$admin_check = $conn->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1");
if ($admin_check->num_rows == 0) {
    echo "<p>Creating example admin user...</p>";
    
    $admin_password = password_hash("admin123", PASSWORD_DEFAULT);
    $create_admin = "INSERT INTO users (first_name, last_name, email, username, password, role, is_verified) 
                    VALUES ('Admin', 'User', 'admin@localconnect.com', 'admin', ?, 'admin', 1)";
    
    $stmt = $conn->prepare($create_admin);
    $stmt->bind_param("s", $admin_password);
    
    if ($stmt->execute()) {
        echo "<p>✅ Example admin user created. Email: admin@localconnect.com, Password: admin123</p>";
    } else {
        echo "<p>❌ Error creating admin user: " . $stmt->error . "</p>";
    }
}

// Creating an example business owner if none exists
$business_owner_check = $conn->query("SELECT * FROM users WHERE role = 'business_owner' LIMIT 1");
if ($business_owner_check->num_rows == 0) {
    echo "<p>Creating example business owner user...</p>";
    
    $business_password = password_hash("business123", PASSWORD_DEFAULT);
    $create_business = "INSERT INTO users (first_name, last_name, email, username, password, role, is_verified) 
                       VALUES ('Business', 'Owner', 'business@localconnect.com', 'business', ?, 'business_owner', 1)";
    
    $stmt = $conn->prepare($create_business);
    $stmt->bind_param("s", $business_password);
    
    if ($stmt->execute()) {
        echo "<p>✅ Example business owner created. Email: business@localconnect.com, Password: business123</p>";
        
        // Get the inserted user ID
        $business_user_id = $conn->insert_id;
        
        // Create a business profile for this user
        $create_business_profile = "INSERT INTO businesses (user_id, name, email, phone, address, city, state, zip, description) 
                                   VALUES (?, 'Local Store', 'business@localconnect.com', '1234567890', '123 Main St', 'Cityville', 'State', '12345', 'An example local business')";
        
        $stmt = $conn->prepare($create_business_profile);
        $stmt->bind_param("i", $business_user_id);
        
        if ($stmt->execute()) {
            echo "<p>✅ Example business profile created.</p>";
        } else {
            echo "<p>❌ Error creating business profile: " . $stmt->error . "</p>";
        }
    } else {
        echo "<p>❌ Error creating business owner: " . $stmt->error . "</p>";
    }
}

echo "<h2>Database setup complete!</h2>";
echo "<p>You can now <a href='index.php'>go to the homepage</a> or <a href='auth/login.php'>login</a> with the example accounts.</p>";

// Close the database connection
$conn->close();
?> 