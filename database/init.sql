-- Create tables for library management system

-- Drop existing tables if they exist
DROP TABLE IF EXISTS loan_history;
DROP TABLE IF EXISTS books;
DROP TABLE IF EXISTS members;
DROP TABLE IF EXISTS users;

-- Create users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'librarian', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create members table
CREATE TABLE members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    address TEXT,
    membership_date DATE NOT NULL,
    membership_status ENUM('active', 'expired', 'suspended') DEFAULT 'active',
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Create books table
CREATE TABLE books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(20) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(100) NOT NULL,
    category VARCHAR(50),
    publication_year INT,
    publisher VARCHAR(100),
    total_copies INT NOT NULL DEFAULT 1,
    available_copies INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create loan_history table
CREATE TABLE loan_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    member_id INT NOT NULL,
    checkout_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE,
    status ENUM('checked_out', 'returned', 'overdue') DEFAULT 'checked_out',
    fine DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
);

-- Insert sample data

-- Sample users (password is 'password' hashed)
INSERT INTO users (username, password, role) VALUES 
('admin', '$2y$10$LKCw9FTipKaDRBFin7ui2O9o/Sf0xOuC280AD1X2d9bWLnhrZfTRG', 'admin'),
('librarian', '$2y$10$LKCw9FTipKaDRBFin7ui2O9o/Sf0xOuC280AD1X2d9bWLnhrZfTRG', 'librarian'),
('user', '$2y$10$LKCw9FTipKaDRBFin7ui2O9o/Sf0xOuC280AD1X2d9bWLnhrZfTRG', 'user');

-- Sample members
INSERT INTO members (first_name, last_name, email, phone, address, membership_date, user_id) VALUES 
('John', 'Doe', 'john@example.com', '123-456-7890', '123 Main St, City', '2023-01-01', 3),
('Jane', 'Smith', 'jane@example.com', '987-654-3210', '456 Oak St, Town', '2023-02-15', NULL),
('Michael', 'Johnson', 'michael@example.com', '555-123-4567', '789 Pine St, Village', '2023-03-20', NULL);

-- Sample books
INSERT INTO books (isbn, title, author, category, publication_year, publisher, total_copies, available_copies) VALUES 
('9780451524935', '1984', 'George Orwell', 'Fiction', 1949, 'Signet Classics', 5, 5),
('9780061120084', 'To Kill a Mockingbird', 'Harper Lee', 'Fiction', 1960, 'HarperPerennial', 3, 3),
('9780142437179', 'Pride and Prejudice', 'Jane Austen', 'Romance', 1813, 'Penguin Classics', 4, 4),
('9780307277671', 'The Road', 'Cormac McCarthy', 'Post-Apocalyptic', 2006, 'Vintage', 2, 2),
('9780316769174', 'The Catcher in the Rye', 'J.D. Salinger', 'Fiction', 1951, 'Little, Brown and Company', 3, 3);

-- Sample loan history
INSERT INTO loan_history (book_id, member_id, checkout_date, due_date, return_date, status) VALUES 
(1, 1, '2023-05-01', '2023-05-15', '2023-05-10', 'returned'),
(2, 2, '2023-05-05', '2023-05-19', NULL, 'checked_out'),
(3, 1, '2023-05-10', '2023-05-24', NULL, 'checked_out');