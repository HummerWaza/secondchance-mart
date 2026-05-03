-- ============================================================
-- SecondChance Mart — Final Production Database Schema
-- CBS University Project | Singapore Supermarket Clearance Platform
-- Updated: role 'warehouse' renamed to 'delivery'
--          UNIQUE(email, role) allows same email across roles
--          Payment methods: card, paynow, bank_transfer (COD removed)
-- ============================================================

CREATE DATABASE IF NOT EXISTS secondchance_mart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE secondchance_mart;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS delivery_status;
DROP TABLE IF EXISTS email_notifications;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS warehouse_staff;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ── users ────────────────────────────────────────────────────
-- One email can have multiple roles (e.g. same person testing all roles)
-- UNIQUE(email, role) replaces old UNIQUE(email)
CREATE TABLE users (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    email      VARCHAR(255) NOT NULL,
    password   VARCHAR(255) NOT NULL,           -- bcrypt via password_hash()
    role       ENUM('customer','admin','supplier','delivery') DEFAULT 'customer',
    is_active  TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_email_role (email, role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── customers ────────────────────────────────────────────────
CREATE TABLE customers (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    user_id     INT NOT NULL,
    first_name  VARCHAR(100) NOT NULL,
    last_name   VARCHAR(100) NOT NULL,
    phone       VARCHAR(20),
    address     TEXT,
    city        VARCHAR(100),
    postal_code VARCHAR(20),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── admins ───────────────────────────────────────────────────
CREATE TABLE admins (
    id      INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name    VARCHAR(255) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── suppliers ────────────────────────────────────────────────
CREATE TABLE suppliers (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    user_id        INT NOT NULL,
    company_name   VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255),
    phone          VARCHAR(20),
    address        TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── warehouse_staff ──────────────────────────────────────────
-- Stores delivery staff profiles (role='delivery' in users table)
CREATE TABLE warehouse_staff (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    user_id        INT NOT NULL,
    name           VARCHAR(255) NOT NULL,
    vehicle_number VARCHAR(50),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── categories ───────────────────────────────────────────────
CREATE TABLE categories (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) UNIQUE NOT NULL,
    icon        VARCHAR(100),
    description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── products ─────────────────────────────────────────────────
CREATE TABLE products (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    supplier_id         INT,
    category_id         INT NOT NULL,
    name                VARCHAR(255) NOT NULL,
    description         TEXT,
    original_price      DECIMAL(10,2) NOT NULL,
    discount_price      DECIMAL(10,2) NOT NULL,
    discount_percentage INT DEFAULT 0,
    stock_quantity      INT DEFAULT 0,
    expiry_date         DATE NULL,
    image_url           VARCHAR(500),
    deal_type           ENUM('near_expiry','overstock','damaged_pkg','seasonal','general') DEFAULT 'general',
    status              ENUM('active','inactive') DEFAULT 'active',
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── cart ─────────────────────────────────────────────────────
CREATE TABLE cart (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    user_id    INT NOT NULL,
    product_id INT NOT NULL,
    quantity   INT DEFAULT 1,
    added_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cart_item (user_id, product_id),
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── orders ───────────────────────────────────────────────────
-- COD removed. Payment methods: card, paynow, bank_transfer
CREATE TABLE orders (
    id               INT PRIMARY KEY AUTO_INCREMENT,
    user_id          INT NOT NULL,
    order_number     VARCHAR(20) UNIQUE NOT NULL,
    total_amount     DECIMAL(10,2) NOT NULL,
    status           ENUM('pending','confirmed','packed','out_for_delivery','delivered','cancelled') DEFAULT 'pending',
    payment_method   ENUM('card','paynow','bank_transfer') NOT NULL,
    payment_status   ENUM('pending','paid','failed') DEFAULT 'pending',
    shipping_name    VARCHAR(255),
    shipping_phone   VARCHAR(20),
    shipping_address TEXT,
    shipping_city    VARCHAR(100),
    shipping_postal  VARCHAR(20),
    notes            TEXT,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── order_items ──────────────────────────────────────────────
CREATE TABLE order_items (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    order_id     INT NOT NULL,
    product_id   INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity     INT NOT NULL,
    unit_price   DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── payments ─────────────────────────────────────────────────
CREATE TABLE payments (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    order_id       INT NOT NULL UNIQUE,
    amount         DECIMAL(10,2) NOT NULL,
    method         ENUM('card','paynow','bank_transfer') NOT NULL,
    status         ENUM('pending','completed','failed') DEFAULT 'pending',
    transaction_id VARCHAR(255),
    paid_at        TIMESTAMP NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── email_notifications ──────────────────────────────────────
CREATE TABLE email_notifications (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    order_id        INT,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_type  ENUM('customer','admin','supplier','warehouse'),
    subject         VARCHAR(255),
    body            TEXT,
    trigger_event   VARCHAR(100),
    status          ENUM('sent','failed','pending') DEFAULT 'pending',
    sent_at         TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── delivery_status ──────────────────────────────────────────
CREATE TABLE delivery_status (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    order_id   INT NOT NULL,
    status     VARCHAR(100) NOT NULL,
    notes      TEXT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id)   REFERENCES orders(id)  ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Performance indexes ──────────────────────────────────────
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_status   ON products(status);
CREATE INDEX idx_orders_user       ON orders(user_id);
CREATE INDEX idx_orders_status     ON orders(status);
CREATE INDEX idx_cart_user         ON cart(user_id);
CREATE INDEX idx_order_items_order ON order_items(order_id);

-- ── Categories seed data ─────────────────────────────────────
INSERT INTO categories (name, slug, icon, description) VALUES
('Fruits & Vegetables', 'fruits-vegetables', 'fa-apple-alt',    'Fresh and near-expiry produce at discounted prices'),
('Bakery',              'bakery',             'fa-bread-slice',  'Day-old bread, pastries, and baked goods'),
('Dairy & Eggs',        'dairy',              'fa-cheese',       'Near-expiry milk, eggs, cheese, yogurt, and dairy items'),
('Frozen Food',         'frozen-food',        'fa-snowflake',    'Clearance frozen meals, meats, and ice cream'),
('Snacks & Cereals',    'snacks',             'fa-cookie-bite',  'Overstock biscuits, chips, noodles, and snack items'),
('Drinks & Beverages',  'drinks',             'fa-glass-cheers', 'Near-expiry juices, sodas, milk teas, and beverages'),
('Household & Personal','household',          'fa-soap',         'Overstock cleaning supplies and personal care products'),
('Near Expiry Deals',   'near-expiry',        'fa-clock',        'Special deals on products expiring within 7 days'),
('Overstock Clearance', 'overstock',          'fa-tags',         'Bulk clearance on overstock pantry and canned goods');

-- ── Demo users (NOTE: run install.php for correct bcrypt hashes) ──
-- All 4 accounts share the email heinminthant325@gmail.com
-- UNIQUE KEY is on (email, role) so all 4 rows are valid
--
-- Customer: heinminthant325@gmail.com / Customer123  → /login.php
-- Admin:    heinminthant325@gmail.com / Admin123     → /admin/login.php
-- Supplier: heinminthant325@gmail.com / Supplier123  → /supplier/login.php
-- Delivery: heinminthant325@gmail.com / Delivery123  → /warehouse/login.php
--
-- Run http://localhost:8000/install.php to insert users with correct hashes.
