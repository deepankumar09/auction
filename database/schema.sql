CREATE DATABASE IF NOT EXISTS auction_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE auction_db;

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS admin (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS vehicles (
    vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
    category ENUM('Bike', 'Car') NOT NULL,
    brand VARCHAR(100) NOT NULL,
    model VARCHAR(100) NOT NULL,
    registration_no VARCHAR(50) NOT NULL UNIQUE,
    year YEAR NOT NULL,
    vehicle_condition VARCHAR(100) NOT NULL,
    base_price DECIMAL(12,2) NOT NULL,
    market_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    image VARCHAR(255) DEFAULT NULL,
    auction_status ENUM('open', 'closed', 'sold') NOT NULL DEFAULT 'open',
    winner_user_id INT DEFAULT NULL,
    final_price DECIMAL(12,2) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (winner_user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS bids (
    bid_id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    user_id INT NOT NULL,
    bid_amount DECIMAL(12,2) NOT NULL,
    bid_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    razorpay_order_id VARCHAR(120) NOT NULL,
    razorpay_payment_id VARCHAR(120) DEFAULT NULL,
    razorpay_signature VARCHAR(255) DEFAULT NULL,
    payment_status ENUM('created', 'paid', 'failed') NOT NULL DEFAULT 'created',
    payment_date DATETIME DEFAULT NULL,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS advertisements (
    ad_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    image VARCHAR(255) DEFAULT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS defaulters (
    defaulter_id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL UNIQUE,
    defaulter_name VARCHAR(150) NOT NULL,
    loan_account_number VARCHAR(100) NOT NULL,
    bank_name VARCHAR(150) NOT NULL,
    loan_amount DECIMAL(12,2) NOT NULL,
    pending_amount DECIMAL(12,2) NOT NULL,
    seizure_date DATE NOT NULL,
    reason_for_seizure TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE CASCADE
);

INSERT INTO admin (username, password)
SELECT 'admin', '$2y$10$wzgnZZFqhBS4RQEXB12wLuRhAkzs7C7dBCxl.vEFbaj49tXNMWuxe'
WHERE NOT EXISTS (SELECT 1 FROM admin WHERE username = 'admin');
