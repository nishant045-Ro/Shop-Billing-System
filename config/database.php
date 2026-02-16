<?php
// Database configuration
$host = 'localhost';
$dbname = 'shop_billing_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Lightweight schema patching to keep installs up to date
    $checkColumn = $pdo->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'bills' 
          AND COLUMN_NAME = 'customer_image'
    ");
    $checkColumn->execute();
    $hasCustomerImage = (int)$checkColumn->fetchColumn() === 1;

    if (!$hasCustomerImage) {
        $pdo->exec("ALTER TABLE bills ADD COLUMN customer_image VARCHAR(255) NULL AFTER customer_name");
    }

    $checkStoreImage = $pdo->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'users' 
          AND COLUMN_NAME = 'store_image'
    ");
    $checkStoreImage->execute();
    $hasStoreImage = (int)$checkStoreImage->fetchColumn() === 1;

    if (!$hasStoreImage) {
        $pdo->exec("ALTER TABLE users ADD COLUMN store_image VARCHAR(255) NULL AFTER role");
    }

    // Check if Suppliers table exists, create if not
    $checkSuppliersTable = $pdo->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.TABLES 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'Suppliers'
    ");
    $checkSuppliersTable->execute();
    $hasSuppliersTable = (int)$checkSuppliersTable->fetchColumn() === 1;

    if (!$hasSuppliersTable) {
        $pdo->exec("
            CREATE TABLE Suppliers (
                supplier_id INT AUTO_INCREMENT PRIMARY KEY,
                supplier_name VARCHAR(100) NOT NULL,
                phone VARCHAR(20),
                email VARCHAR(100),
                address VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    // Ensure users.username column can hold long usernames (up to 255 chars)
    $checkUsernameLength = $pdo->prepare("
        SELECT CHARACTER_MAXIMUM_LENGTH
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'username'
    ");
    $checkUsernameLength->execute();
    $maxLen = (int)$checkUsernameLength->fetchColumn() ?: 0;

    if ($maxLen < 255) {
        // Modify column to support longer usernames (preserve UNIQUE constraint)
        $pdo->exec("ALTER TABLE users MODIFY username VARCHAR(255) NOT NULL UNIQUE");
    }

    // Ensure users table has email and verification fields
    $checkEmailColumn = $pdo->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'email'
    ");
    $checkEmailColumn->execute();
    $hasEmail = (int)$checkEmailColumn->fetchColumn() === 1;

    if (!$hasEmail) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL AFTER username");
        // Add unique index on email (safe for fresh installs)
        try {
            $pdo->exec("ALTER TABLE users ADD UNIQUE (email)");
        } catch (PDOException $e) {
            // ignore if cannot add unique (existing duplicates)
        }
    }

    $checkEmailVerified = $pdo->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'email_verified'
    ");
    $checkEmailVerified->execute();
    $hasEmailVerified = (int)$checkEmailVerified->fetchColumn() === 1;

    if (!$hasEmailVerified) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email_verified TINYINT(1) DEFAULT 0 AFTER email");
    }

    $checkVerifyToken = $pdo->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'verify_token'
    ");
    $checkVerifyToken->execute();
    $hasVerifyToken = (int)$checkVerifyToken->fetchColumn() === 1;

    if (!$hasVerifyToken) {
        $pdo->exec("ALTER TABLE users ADD COLUMN verify_token VARCHAR(64) NULL AFTER email_verified");
    }

    $checkVerifyExpires = $pdo->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'verify_expires'
    ");
    $checkVerifyExpires->execute();
    $hasVerifyExpires = (int)$checkVerifyExpires->fetchColumn() === 1;

    if (!$hasVerifyExpires) {
        $pdo->exec("ALTER TABLE users ADD COLUMN verify_expires DATETIME NULL AFTER verify_token");
    }
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
