-- Create the database
CREATE DATABASE IF NOT EXISTS `boardroom booking`;
USE `boardroom booking`;

-- Create the users table
CREATE TABLE IF NOT EXISTS users (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create the rooms table
CREATE TABLE IF NOT EXISTS rooms (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    floor VARCHAR(50) NOT NULL,
    capacity INT(11) NOT NULL,
    equipment TEXT,
    description TEXT,
    image_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create the bookings table
CREATE TABLE IF NOT EXISTS bookings (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    room_id INT(11) NOT NULL,
    event_name VARCHAR(255) NOT NULL,
    event_description TEXT,
    booking_date DATE NOT NULL,
    time_slot VARCHAR(50) NOT NULL,
    equipment VARCHAR(255),
    attendees INT(11),
    special_requests TEXT,
    status ENUM('Pending', 'Approved', 'Rejected', 'Cancelled') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

-- Insert sample rooms
INSERT INTO rooms (name, location, floor, capacity, equipment, description, image_url) VALUES
('Harambee Boardroom', 'Harambee Sacco Plaza', '10th Floor', 20, 'Ideahub,Projector,Video Conference System', 'Modern boardroom with advanced conferencing equipment', 'https://images.unsplash.com/photo-1560448204-603b3fc33ddc?ixlib=rb-4.0.3&w=400'),
('Ukulima Boardroom', 'Ukulima House', '7th Floor', 25, 'Ideahub,Projector,Whiteboard', 'Spacious boardroom ideal for large meetings', 'https://images.unsplash.com/photo-1571624436279-b272aff752b5?ixlib=rb-4.0.3&w=400'),
('Ukulima Small Boardroom', 'Ukulima House', '5th Floor', 15, 'Projector,Whiteboard', 'Intimate boardroom for smaller meetings', 'https://images.unsplash.com/photo-1542744173-8e7e53415bb0?ixlib=rb-4.0.3&w=400');

-- Insert admin user (password: admin123)
INSERT INTO users (username, password, email, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@ngcdf.go.ke', 'admin');