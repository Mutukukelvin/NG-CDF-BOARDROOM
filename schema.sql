-- Create database
CREATE DATABASE IF NOT EXISTS ngcdf_boardroom;
USE ngcdf_boardroom;

-- Users table with admin types
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    admin_type ENUM('super_admin', 'ict_admin', 'other_admin') NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Rooms table
CREATE TABLE rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    floor VARCHAR(50) NOT NULL,
    capacity INT NOT NULL,
    equipment TEXT,
    description TEXT,
    image_url VARCHAR(500) DEFAULT 'cdfpicture.jpg',
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Bookings table
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    room_id INT NOT NULL,
    event_name VARCHAR(255) NOT NULL,
    event_description TEXT,
    booking_date DATE NOT NULL,
    time_slot VARCHAR(50) NOT NULL,
    equipment VARCHAR(255),
    attendees INT,
    special_requests TEXT,
    refreshments TEXT,
    status ENUM('Pending', 'Approved', 'Rejected', 'Cancelled') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

-- Meals & Drinks table
CREATE TABLE meals_drinks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category ENUM('meal', 'drink') NOT NULL,
    description TEXT,
    price DECIMAL(10,2) DEFAULT 0.00,
    available BOOLEAN DEFAULT TRUE,
    image_url VARCHAR(500),
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- System logs table
CREATE TABLE system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert default admin users (all are admins with different types)
-- Password for all: password123
INSERT INTO users (username, email, password, role, admin_type, is_active) VALUES 
('superadmin', 'superadmin@ngcdf.go.ke', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'super_admin', TRUE),
('ictadmin', 'ictadmin@ngcdf.go.ke', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'ict_admin', TRUE),
('otheradmin', 'otheradmin@ngcdf.go.ke', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'other_admin', TRUE);

-- Insert regular user (not admin)
INSERT INTO users (username, email, password, role, is_active) VALUES 
('regularuser', 'user@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', TRUE);

-- Insert sample rooms
INSERT INTO rooms (name, location, floor, capacity, equipment, description, created_by) VALUES
('Harambee Boardroom', 'Harambee Sacco Plaza', '10th Floor', 20, 'Projector,Whiteboard,Video Conference System', 'Modern boardroom with advanced conferencing equipment', 1),
('Ukulima Boardroom', 'Ukulima House', '7th Floor', 25, 'Projector,Whiteboard,Ideahub', 'Spacious boardroom ideal for large meetings', 1),
('Ukulima Small Boardroom', 'Ukulima House', '5th Floor', 15, 'Projector,Whiteboard', 'Intimate boardroom for smaller meetings', 1),
('NG-CDF Main Boardroom', 'NG-CDF Headquarters', '3rd Floor', 30, 'Projector,Video Conference,Audio System', 'Main boardroom for executive meetings', 1);

-- Insert sample meals and drinks
INSERT INTO meals_drinks (name, category, description, available, created_by) VALUES
('Tea & Coffee', 'drink', 'Hot beverages with sugar and milk options', TRUE, 3),
('Bottled Water', 'drink', '500ml bottled drinking water', TRUE, 3),
('Fruit Juice', 'drink', 'Assorted fruit juices', TRUE, 3),
('Sandwich Platter', 'meal', 'Assorted sandwiches with vegetarian options', TRUE, 3),
('Fruit Basket', 'meal', 'Seasonal fresh fruits', TRUE, 3),
('Cookies & Pastries', 'meal', 'Assorted cookies and pastries', TRUE, 3);

-- Insert sample bookings for testing
INSERT INTO bookings (user_id, room_id, event_name, event_description, booking_date, time_slot, equipment, attendees, status) VALUES
(4, 1, 'Weekly Team Meeting', 'Regular team sync and planning session', CURDATE() + INTERVAL 1 DAY, '10am-11am', 'Projector', 15, 'Approved'),
(4, 2, 'Client Presentation', 'Product demo for new clients', CURDATE() + INTERVAL 2 DAY, '2pm-3pm', 'Projector,Video Conference System', 20, 'Approved');

-- Verify the data insertion
SELECT 'Database setup completed successfully!' as status;

-- Show created tables
SHOW TABLES;

-- Show sample data from each table
SELECT 'Users:' as '';
SELECT id, username, email, role, admin_type, is_active FROM users;

SELECT 'Rooms:' as '';
SELECT id, name, location, floor, capacity FROM rooms;

SELECT 'Meals & Drinks:' as '';
SELECT id, name, category, available FROM meals_drinks;

SELECT 'Bookings:' as '';
SELECT id, event_name, booking_date, time_slot, status FROM bookings;
