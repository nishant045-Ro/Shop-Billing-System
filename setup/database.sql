-- Create database
CREATE DATABASE IF NOT EXISTS shop_billing_system;
USE shop_billing_system;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'cashier') NOT NULL DEFAULT 'cashier',
    store_image VARCHAR(255) DEFAULT NULL,
    -- Optional shop location set by cashier on profile (lat, lng)
    shop_lat DECIMAL(10,7) DEFAULT NULL,
    shop_lng DECIMAL(10,7) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    category_id INT,
    price DECIMAL(10,2) NOT NULL,
    -- Changed from INT to VARCHAR to allow values like "400kg", "2 boxes", etc.
    stock VARCHAR(50) NOT NULL DEFAULT '0',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Bills table
CREATE TABLE bills (
    bill_id VARCHAR(20) PRIMARY KEY,
    cashier_id INT NOT NULL,
    customer_name VARCHAR(100) DEFAULT 'Walk-in Customer',
    customer_phone VARCHAR(20) DEFAULT NULL,
    customer_image VARCHAR(255) DEFAULT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    final_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'card', 'upi') DEFAULT 'cash',
    date_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cashier_id) REFERENCES users(id)
);

-- Bill items table
CREATE TABLE bill_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id VARCHAR(20) NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (bill_id) REFERENCES bills(bill_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Inventory Movements table (for tracking stock adjustments)
CREATE TABLE inventory_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    adjustment INT NOT NULL,
    reason VARCHAR(255),
    old_stock VARCHAR(50),
    new_stock VARCHAR(50),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX (product_id),
    INDEX (created_at)
);

-- Create Suppliers Table
CREATE TABLE Suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    address VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Optional: Insert sample data
INSERT INTO Suppliers (supplier_name, phone, email, address) VALUES
('ABC Suppliers', '9801234567', 'abc@example.com', 'Kathmandu, Nepal'),
('XYZ Traders', '9812345678', 'xyz@example.com', 'Lalitpur, Nepal'),
('MNO Distributors', '9823456789', 'mno@example.com', 'Bhaktapur, Nepal');


-- Insert default admin user (password: admin123)
INSERT INTO users (username, password, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert default categories
INSERT INTO categories (name, description) VALUES 
('Electronics', 'Electronic devices and accessories'),
('Clothing', 'Apparel and fashion items'),
('Food & Beverages', 'Edible products and drinks'),
('Home & Garden', 'Home improvement and garden supplies'),
('Books', 'Books and publications');

-- Insert sample products
INSERT INTO products (name, category_id, price, stock) VALUES 
('Laptop', 1, 45000.00, 10),
('Smartphone', 1, 25000.00, 15),
('T-Shirt', 2, 500.00, 50),
('Jeans', 2, 1200.00, 30),
('Coffee', 3, 50.00, 100),
('Bread', 3, 30.00, 80),
('Garden Tool Set', 4, 800.00, 20),
('Novel', 5, 200.00, 25);
