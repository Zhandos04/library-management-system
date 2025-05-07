<?php
session_start();

class Auth {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login($username, $password) {
        $query = "SELECT id, username, password, role FROM users WHERE username = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $username);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $id = $row['id'];
            $username = $row['username'];
            $hashed_password = $row['password'];
            $role = $row['role'];

            if (password_verify($password, $hashed_password)) {
                $_SESSION['user_id'] = $id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;
                return true;
            }
        }

        return false;
    }

    public function register($username, $password, $role = 'user') {
        // Check if username already exists
        $check_query = "SELECT id FROM users WHERE username = ?";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(1, $username);
        $check_stmt->execute();

        if ($check_stmt->rowCount() > 0) {
            return false; // Username already exists
        }

        // Hash password and create user
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $query = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $username);
        $stmt->bindParam(2, $hashed_password);
        $stmt->bindParam(3, $role);

        return $stmt->execute();
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
    }

    public function isLibrarian() {
        return isset($_SESSION['role']) && ($_SESSION['role'] == 'librarian' || $_SESSION['role'] == 'admin');
    }

    public function logout() {
        session_unset();
        session_destroy();
        return true;
    }

    public function getCurrentUserId() {
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }

    public function getCurrentUserRole() {
        return isset($_SESSION['role']) ? $_SESSION['role'] : null;
    }
}