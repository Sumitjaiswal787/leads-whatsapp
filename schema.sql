-- WhatsApp Lead Grabber CRM Database Schema

CREATE DATABASE IF NOT EXISTS whatsapp_crm;
USE whatsapp_crm;

-- Users Table (Admins and Staff)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff', 'superadmin') DEFAULT 'admin',
    tenant_id INT NULL, -- For staff, this points to their admin's ID
    full_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Plans Table
CREATE TABLE IF NOT EXISTS plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    max_sessions INT NOT NULL,
    max_staff INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    duration_days INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Subscriptions Table
CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    plan_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'expired', 'pending', 'suspended') DEFAULT 'active',
    is_trial BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES plans(id)
);

-- WhatsApp Sessions Table
CREATE TABLE IF NOT EXISTS whatsapp_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    session_id VARCHAR(100) UNIQUE NOT NULL, -- Logical ID for Baileys
    status ENUM('connected', 'disconnected', 'pending_qr', 'initializing') DEFAULT 'disconnected',
    qr_code TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Leads Table
CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    session_id INT NOT NULL,
    staff_id INT NULL, -- Assigned staff
    name VARCHAR(100),
    number VARCHAR(20) NOT NULL,
    last_message TEXT,
    status ENUM('new', 'contacted', 'closed') DEFAULT 'new',
    tag ENUM('hot', 'warm', 'cold', 'none') DEFAULT 'none',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES whatsapp_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Messages History Table
CREATE TABLE IF NOT EXISTS whatsapp_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    message TEXT,
    direction ENUM('incoming', 'outgoing') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
);

-- Assignment Queue (Round Robin)
CREATE TABLE IF NOT EXISTS assign_queue (
    tenant_id INT PRIMARY KEY,
    last_staff_index INT DEFAULT 0,
    FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Auto Reply Settings
CREATE TABLE IF NOT EXISTS auto_reply_settings (
    session_id INT PRIMARY KEY,
    welcome_message TEXT,
    is_enabled BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (session_id) REFERENCES whatsapp_sessions(id) ON DELETE CASCADE
);

-- Payment Transactions
CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    razorpay_payment_id VARCHAR(100),
    amount DECIMAL(10, 2),
    status VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES users(id)
);

-- Insert Default Plans
INSERT IGNORE INTO plans (name, max_sessions, max_staff, price, duration_days) VALUES
('Basic', 1, 3, 499.00, 30),
('Pro', 5, 10, 999.00, 30),
('Premium', 15, 50, 2499.00, 30);
