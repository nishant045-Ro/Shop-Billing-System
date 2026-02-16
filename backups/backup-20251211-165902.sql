-- Backup generated on 2025-12-11 16:59:02
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `bill_items`;
CREATE TABLE `bill_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_id` varchar(20) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `bill_id` (`bill_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `bill_items_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`bill_id`) ON DELETE CASCADE,
  CONSTRAINT `bill_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `bill_items` (`id`, `bill_id`, `product_id`, `quantity`, `price`, `subtotal`) VALUES
('1', 'BILL2025121116311789', '5', '2', '50.00', '100.00');

DROP TABLE IF EXISTS `bills`;
CREATE TABLE `bills` (
  `bill_id` varchar(20) NOT NULL,
  `cashier_id` int(11) NOT NULL,
  `customer_name` varchar(100) DEFAULT 'Walk-in Customer',
  `customer_image` varchar(255) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `final_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','card','upi') DEFAULT 'cash',
  `date_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`bill_id`),
  KEY `cashier_id` (`cashier_id`),
  CONSTRAINT `bills_ibfk_1` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `bills` (`bill_id`, `cashier_id`, `customer_name`, `customer_image`, `total_amount`, `tax_amount`, `discount_amount`, `final_amount`, `payment_method`, `date_time`) VALUES
('BILL2025121116311789', '2', 'Madhu K.C', NULL, '100.00', '18.00', '0.00', '118.00', 'cash', '2025-12-11 21:16:17');

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`) VALUES
('1', 'Electronics', 'Electronic devices and accessories', '2025-12-11 20:55:33'),
('2', 'Clothing', 'Apparel and fashion items', '2025-12-11 20:55:33'),
('3', 'Food & Beverages', 'Edible products and drinks', '2025-12-11 20:55:33'),
('4', 'Home & Garden', 'Home improvement and garden supplies', '2025-12-11 20:55:33'),
('5', 'Books', 'Books and publications', '2025-12-11 20:55:33');

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `products` (`id`, `name`, `category_id`, `price`, `stock`, `created_at`) VALUES
('1', 'Laptop', '1', '45000.00', '10', '2025-12-11 20:55:33'),
('2', 'Smartphone', '1', '25000.00', '15', '2025-12-11 20:55:33'),
('3', 'T-Shirt', '2', '500.00', '50', '2025-12-11 20:55:33'),
('4', 'Jeans', '2', '1200.00', '30', '2025-12-11 20:55:33'),
('5', 'Coffee', '3', '50.00', '98', '2025-12-11 20:55:33'),
('6', 'Bread', '3', '30.00', '80', '2025-12-11 20:55:33'),
('7', 'Garden Tool Set', '4', '800.00', '20', '2025-12-11 20:55:33'),
('8', 'Novel', '5', '200.00', '25', '2025-12-11 20:55:33');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','cashier') NOT NULL DEFAULT 'cashier',
  `store_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`, `username`, `password`, `role`, `store_image`, `created_at`) VALUES
('1', 'admin', '$2y$10$TcooSB/Hz8UeVSK8vIR6Aejn7VPF21ZjNxHHhyUGirgN8QDbExfoC', 'admin', NULL, '2025-12-11 20:55:33'),
('2', 'Sapkota Mini Mart', '$2y$10$wthJPgbDC1oQn16uJZgiXe7zR6XYVEwLJoYpsCQKX6AsO/3O2Og/K', 'cashier', NULL, '2025-12-11 21:06:34');

SET FOREIGN_KEY_CHECKS=1;
