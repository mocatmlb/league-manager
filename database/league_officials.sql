-- League Administration Officials Table
-- Run this SQL to add the league officials functionality

USE moc835_d8tl_prod;

-- Create the league_officials table
CREATE TABLE IF NOT EXISTS league_officials (
    official_id INT AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(100) NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    display_on_contact_page BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    active_status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_official_active (active_status),
    INDEX idx_official_sort (sort_order),
    INDEX idx_official_display (display_on_contact_page)
);

-- Insert default officials (optional - you can customize these)
INSERT INTO league_officials (role, name, phone, email, display_on_contact_page, sort_order) VALUES
('League Commissioner', 'John Smith', '(555) 123-4567', 'commissioner@district8league.com', TRUE, 1),
('Program Director', 'Jane Doe', '(555) 123-4568', 'director@district8league.com', TRUE, 2),
('Scheduling Coordinator', 'Mike Johnson', '(555) 123-4569', 'scheduling@district8league.com', TRUE, 3),
('Field Coordinator', 'Sarah Wilson', '(555) 123-4570', 'fields@district8league.com', TRUE, 4),
('Umpire Coordinator', 'Tom Brown', '(555) 123-4571', 'umpires@district8league.com', TRUE, 5),
('Registration Coordinator', 'Lisa Davis', '(555) 123-4572', 'registration@district8league.com', TRUE, 6);