USE vavuniya_ads;

-- ALTER TABLE ads ADD COLUMN is_verified BOOLEAN DEFAULT FALSE;

-- INSERT INTO categories (name) VALUES ('Electronics'), ('Vehicles');
-- INSERT INTO ads (title, description, price, images, location, status, user_id, category_id, item_condition)
-- VALUES ('Phone', 'New iPhone', 1000, '["img1.jpg"]', 'Colombo', 'active', 1, 1, 'new');

-- ALTER TABLE otp_attempts ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL;
-- ALTER TABLE favorites ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL;

-- -- Users Table
-- CREATE TABLE IF NOT EXISTS users (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     name VARCHAR(255) NOT NULL,
--     phone VARCHAR(20) NOT NULL,
--     email VARCHAR(255) UNIQUE NOT NULL,
--     password VARCHAR(255) NOT NULL,
--     role ENUM('user', 'admin') DEFAULT 'user',
--     is_active BOOLEAN DEFAULT TRUE,
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
--     INDEX idx_phone (phone),
--     INDEX idx_email (email)
-- );

-- -- OTP Table
-- CREATE TABLE IF NOT EXISTS otp (
--     id INT PRIMARY KEY AUTO_INCREMENT,
--     code VARCHAR(6) NOT NULL,
--     phone VARCHAR(20) NOT NULL,
--     expires_at DATETIME NOT NULL,
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     used TINYINT DEFAULT 0,
--     INDEX idx_phone (phone)
-- );

-- -- OTP Attempts Table
-- CREATE TABLE IF NOT EXISTS otp_attempts (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     phone VARCHAR(15) NOT NULL,
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     attempt_type ENUM('phone', 'email') DEFAULT 'phone',
--     status ENUM('success', 'failed') DEFAULT 'failed',
--     INDEX idx_phone (phone)
-- );

-- -- Email Verifications Table
-- CREATE TABLE IF NOT EXISTS email_verifications (
--     id INT PRIMARY KEY AUTO_INCREMENT,
--     email VARCHAR(255) NOT NULL,
--     otp VARCHAR(10) NOT NULL,
--     expires_at DATETIME NOT NULL,
--     verified BOOLEAN DEFAULT FALSE,
--     INDEX idx_email (email)
-- );

-- -- Categories Table
-- CREATE TABLE IF NOT EXISTS categories (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     name VARCHAR(255) NOT NULL,
--     parent_id INT,
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
--     FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE,
--     INDEX idx_parent_id (parent_id)
-- );

-- -- Ads Table
-- CREATE TABLE IF NOT EXISTS ads (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     title VARCHAR(255) NOT NULL,
--     description TEXT NOT NULL,
--     price DECIMAL(10,2) NOT NULL,
--     images JSON,
--     location VARCHAR(255),
--     status ENUM('active', 'pending', 'sold', 'deleted') DEFAULT 'pending',
--     user_id INT,
--     category_id INT,
--     item_condition ENUM('new', 'used') NOT NULL,
--     is_featured BOOLEAN DEFAULT FALSE,
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
--     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
--     FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
--     INDEX idx_user_id (user_id),
--     INDEX idx_category_id (category_id)
-- );

-- -- Favorites Table
CREATE TABLE IF NOT EXISTS favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ad_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE,
    UNIQUE KEY unique_favorite (user_id, ad_id)
);

-- -- Messages Table
-- CREATE TABLE IF NOT EXISTS messages (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     sender_id INT,
--     receiver_id INT,
--     ad_id INT,
--     message TEXT NOT NULL,
--     is_read BOOLEAN DEFAULT FALSE,
--     sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
--     FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
--     FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE
-- );

-- -- Ad Reports Table
-- CREATE TABLE IF NOT EXISTS ad_reports (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     user_id INT,
--     ad_id INT,
--     reason TEXT NOT NULL,
--     status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
--     FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE
-- );

-- -- Notifications Table
-- CREATE TABLE IF NOT EXISTS notifications (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     user_id INT,
--     message TEXT NOT NULL,
--     is_read BOOLEAN DEFAULT FALSE,
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
-- );

-- -- Ad Views Table
-- CREATE TABLE IF NOT EXISTS ad_views (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     ad_id INT,
--     user_id INT NULL,
--     ip_address VARCHAR(45),
--     viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE,
--     INDEX idx_ad_id (ad_id)
-- );

-- -- Transactions Table
-- CREATE TABLE IF NOT EXISTS transactions (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     buyer_id INT,
--     seller_id INT,
--     ad_id INT,
--     amount DECIMAL(10,2) NOT NULL,
--     transaction_id VARCHAR(255),
--     payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
--     FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
--     FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE
-- );