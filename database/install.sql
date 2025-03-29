-- LocalConnect Database Installation Script
-- This script sets up all required tables for the LocalConnect platform

-- Set foreign key checks off during installation
SET FOREIGN_KEY_CHECKS=0;

-- Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('customer','business_owner','admin') NOT NULL DEFAULT 'customer',
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- User Addresses Table
CREATE TABLE IF NOT EXISTS `user_addresses` (
  `address_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `address_line1` varchar(255) NOT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `country` varchar(100) NOT NULL DEFAULT 'USA',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `address_type` enum('billing','shipping','both') NOT NULL DEFAULT 'both',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`address_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Categories Table
CREATE TABLE IF NOT EXISTS `categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `category_slug` varchar(100) NOT NULL,
  `category_description` text DEFAULT NULL,
  `category_icon` varchar(50) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `category_slug` (`category_slug`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Businesses Table
CREATE TABLE IF NOT EXISTS `businesses` (
  `business_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `business_name` varchar(100) NOT NULL,
  `business_slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `banner_path` varchar(255) DEFAULT NULL,
  `address_line1` varchar(255) NOT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `country` varchar(100) NOT NULL DEFAULT 'USA',
  `business_phone` varchar(20) NOT NULL,
  `business_email` varchar(100) NOT NULL,
  `website` varchar(255) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('pending','active','suspended') NOT NULL DEFAULT 'pending',
  `operating_hours` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`business_id`),
  UNIQUE KEY `business_slug` (`business_slug`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `businesses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Products Table
CREATE TABLE IF NOT EXISTS `products` (
  `product_id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `is_featured` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `total_sales` int(11) DEFAULT 0,
  `sku` varchar(50) DEFAULT NULL,
  `weight` decimal(8,2) DEFAULT NULL,
  `dimensions` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`product_id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `business_id` (`business_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE,
  CONSTRAINT `products_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Product Images Table
CREATE TABLE IF NOT EXISTS `product_images` (
  `image_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`image_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Product Reviews Table
CREATE TABLE IF NOT EXISTS `product_reviews` (
  `review_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(1) NOT NULL,
  `review_text` text DEFAULT NULL,
  `is_verified_purchase` tinyint(1) DEFAULT 0,
  `is_hidden` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`review_id`),
  KEY `product_id` (`product_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `product_reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  CONSTRAINT `product_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Product Attributes Table
CREATE TABLE IF NOT EXISTS `product_attributes` (
  `attribute_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `attribute_name` varchar(100) NOT NULL,
  `attribute_value` varchar(255) NOT NULL,
  `display_order` int(11) DEFAULT 0,
  PRIMARY KEY (`attribute_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `product_attributes_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Product Tags Table
CREATE TABLE IF NOT EXISTS `product_tags` (
  `tag_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `tag_name` varchar(50) NOT NULL,
  PRIMARY KEY (`tag_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `product_tags_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Cart Items Table
CREATE TABLE IF NOT EXISTS `cart_items` (
  `cart_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cart_item_id`),
  UNIQUE KEY `user_product` (`user_id`,`product_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Orders Table
CREATE TABLE IF NOT EXISTS `orders` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `order_status` enum('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `shipping_address_id` int(11) NOT NULL,
  `billing_address_id` int(11) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `subtotal` decimal(10,2) NOT NULL,
  `shipping_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`order_id`),
  KEY `user_id` (`user_id`),
  KEY `shipping_address_id` (`shipping_address_id`),
  KEY `billing_address_id` (`billing_address_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`shipping_address_id`) REFERENCES `user_addresses` (`address_id`),
  CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`billing_address_id`) REFERENCES `user_addresses` (`address_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Order Items Table
CREATE TABLE IF NOT EXISTS `order_items` (
  `order_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','processing','shipped','delivered','cancelled','returned') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`order_item_id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  KEY `business_id` (`business_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE RESTRICT,
  CONSTRAINT `order_items_ibfk_3` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Wishlist Items Table
CREATE TABLE IF NOT EXISTS `wishlist_items` (
  `wishlist_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`wishlist_id`),
  UNIQUE KEY `user_product` (`user_id`,`product_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `wishlist_items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `wishlist_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Forum Topics Table
CREATE TABLE IF NOT EXISTS `forum_topics` (
  `topic_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `is_sticky` tinyint(1) NOT NULL DEFAULT 0,
  `views` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`topic_id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `forum_topics_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Forum Posts Table
CREATE TABLE IF NOT EXISTS `forum_posts` (
  `post_id` int(11) NOT NULL AUTO_INCREMENT,
  `topic_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `is_solution` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`post_id`),
  KEY `topic_id` (`topic_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `forum_posts_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `forum_topics` (`topic_id`) ON DELETE CASCADE,
  CONSTRAINT `forum_posts_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Messages Table
CREATE TABLE IF NOT EXISTS `messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`message_id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default categories
INSERT INTO `categories` (`category_name`, `category_slug`, `category_description`, `category_icon`, `display_order`)
VALUES
('All Categories', 'all', 'Browse all products across categories', 'bi-grid-fill', 1),
('Grocery', 'grocery', 'Fresh foods, pantry items, and household essentials', 'bi-basket-fill', 2),
('Electronics', 'electronics', 'Computers, mobile phones, TVs, and other gadgets', 'bi-laptop-fill', 3),
('Fashion', 'fashion', 'Clothing, shoes, and accessories for all ages', 'bi-bag-fill', 4),
('Home', 'home', 'Furniture, decor, and home improvement products', 'bi-house-fill', 5),
('Appliances', 'appliances', 'Kitchen and home appliances for modern living', 'bi-tv-fill', 6),
('Beauty', 'beauty', 'Makeup, skincare, and personal care products', 'bi-stars', 7),
('Toys', 'toys', 'Toys, games, and entertainment for children', 'bi-controller', 8),
('Community', 'community', 'Products and services from your local community', 'bi-people-fill', 9);

-- Add subcategories for Electronics
INSERT INTO `categories` (`category_name`, `category_slug`, `category_description`, `category_icon`, `parent_id`, `display_order`)
SELECT 'Smartphones', 'smartphones', 'Mobile phones and accessories', 'bi-phone-fill', category_id, 1
FROM `categories` WHERE category_slug = 'electronics';

INSERT INTO `categories` (`category_name`, `category_slug`, `category_description`, `category_icon`, `parent_id`, `display_order`)
SELECT 'Laptops', 'laptops', 'Laptops and accessories', 'bi-laptop-fill', category_id, 2
FROM `categories` WHERE category_slug = 'electronics';

INSERT INTO `categories` (`category_name`, `category_slug`, `category_description`, `category_icon`, `parent_id`, `display_order`)
SELECT 'Audio', 'audio', 'Headphones, speakers and audio equipment', 'bi-headphones', category_id, 3
FROM `categories` WHERE category_slug = 'electronics';

-- Add subcategories for Fashion
INSERT INTO `categories` (`category_name`, `category_slug`, `category_description`, `category_icon`, `parent_id`, `display_order`)
SELECT 'Men', 'mens-fashion', 'Clothing, footwear and accessories for men', 'bi-person-fill', category_id, 1
FROM `categories` WHERE category_slug = 'fashion';

INSERT INTO `categories` (`category_name`, `category_slug`, `category_description`, `category_icon`, `parent_id`, `display_order`)
SELECT 'Women', 'womens-fashion', 'Clothing, footwear and accessories for women', 'bi-person-fill', category_id, 2
FROM `categories` WHERE category_slug = 'fashion';

INSERT INTO `categories` (`category_name`, `category_slug`, `category_description`, `category_icon`, `parent_id`, `display_order`)
SELECT 'Kids', 'kids-fashion', 'Clothing, footwear and accessories for children', 'bi-person-fill', category_id, 3
FROM `categories` WHERE category_slug = 'fashion';

-- Add subcategories for Grocery
INSERT INTO `categories` (`category_name`, `category_slug`, `category_description`, `category_icon`, `parent_id`, `display_order`)
SELECT 'Fresh Produce', 'fresh-produce', 'Fresh fruits and vegetables', 'bi-basket2-fill', category_id, 1
FROM `categories` WHERE category_slug = 'grocery';

INSERT INTO `categories` (`category_name`, `category_slug`, `category_description`, `category_icon`, `parent_id`, `display_order`)
SELECT 'Bakery', 'bakery', 'Fresh breads, pastries and baked goods', 'bi-basket2-fill', category_id, 2
FROM `categories` WHERE category_slug = 'grocery';

INSERT INTO `categories` (`category_name`, `category_slug`, `category_description`, `category_icon`, `parent_id`, `display_order`)
SELECT 'Dairy', 'dairy', 'Milk, cheese and other dairy products', 'bi-basket2-fill', category_id, 3
FROM `categories` WHERE category_slug = 'grocery';

-- Create admin user (password: admin123)
INSERT INTO `users` (`username`, `first_name`, `last_name`, `email`, `phone`, `password_hash`, `role`, `is_verified`) 
VALUES ('admin', 'Admin', 'User', 'admin@localconnect.com', '1234567890', '$2y$10$8FPnHg3aLxO5pZliTEyBte4P5U0jxH9MXU9kJHbpqHG7jOIFfJnl2', 'admin', 1);

-- Set foreign key checks back on
SET FOREIGN_KEY_CHECKS=1; 