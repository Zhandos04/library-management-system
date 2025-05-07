<?php
class Member {
    private $conn;
    private $table_name = "members";

    // Member properties
    public $id;
    public $first_name;
    public $last_name;
    public $email;
    public $phone;
    public $address;
    public $membership_date;
    public $membership_status;
    public $user_id;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create member
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (first_name, last_name, email, phone, address, membership_date, membership_status, user_id) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);

        // Sanitize input
        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->address = htmlspecialchars(strip_tags($this->address));
        $this->membership_date = htmlspecialchars(strip_tags($this->membership_date));
        $this->membership_status = htmlspecialchars(strip_tags($this->membership_status));

        // Bind values
        $stmt->bindParam(1, $this->first_name);
        $stmt->bindParam(2, $this->last_name);
        $stmt->bindParam(3, $this->email);
        $stmt->bindParam(4, $this->phone);
        $stmt->bindParam(5, $this->address);
        $stmt->bindParam(6, $this->membership_date);
        $stmt->bindParam(7, $this->membership_status);
        $stmt->bindParam(8, $this->user_id);

        // Execute query
        if ($stmt->execute()) {
            return true;
        }

        return false;
    }

    // Read all members
    public function readAll($page = 1, $records_per_page = 10) {
        $offset = ($page - 1) * $records_per_page;
        
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY last_name LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $records_per_page, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt;
    }

    // Read one member
    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $this->first_name = $row['first_name'];
            $this->last_name = $row['last_name'];
            $this->email = $row['email'];
            $this->phone = $row['phone'];
            $this->address = $row['address'];
            $this->membership_date = $row['membership_date'];
            $this->membership_status = $row['membership_status'];
            $this->user_id = $row['user_id'];
            $this->created_at = $row['created_at'];
            return true;
        }
        
        return false;
    }

    // Update member
    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET first_name = ?, last_name = ?, email = ?, phone = ?, 
                      address = ?, membership_status = ?, user_id = ? 
                  WHERE id = ?";

        $stmt = $this->conn->prepare($query);

        // Sanitize input
        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->address = htmlspecialchars(strip_tags($this->address));
        $this->membership_status = htmlspecialchars(strip_tags($this->membership_status));
        $this->id = htmlspecialchars(strip_tags($this->id));

        // Bind values
        $stmt->bindParam(1, $this->first_name);
        $stmt->bindParam(2, $this->last_name);
        $stmt->bindParam(3, $this->email);
        $stmt->bindParam(4, $this->phone);
        $stmt->bindParam(5, $this->address);
        $stmt->bindParam(6, $this->membership_status);
        $stmt->bindParam(7, $this->user_id);
        $stmt->bindParam(8, $this->id);

        // Execute query
        if ($stmt->execute()) {
            return true;
        }

        return false;
    }

    // Delete member
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(1, $this->id);
        
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }

    // Search members
    public function search($keywords) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? 
                  ORDER BY last_name";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitize
        $keywords = htmlspecialchars(strip_tags($keywords));
        $keywords = "%{$keywords}%";
        
        // Bind
        $stmt->bindParam(1, $keywords);
        $stmt->bindParam(2, $keywords);
        $stmt->bindParam(3, $keywords);
        
        $stmt->execute();
        return $stmt;
    }

    // Count all members
    public function countAll() {
        $query = "SELECT COUNT(*) as total_members FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total_members'];
    }
    
    // Get member by user_id
    public function getByUserId($user_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = ? LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $this->id = $row['id'];
            $this->first_name = $row['first_name'];
            $this->last_name = $row['last_name'];
            $this->email = $row['email'];
            $this->phone = $row['phone'];
            $this->address = $row['address'];
            $this->membership_date = $row['membership_date'];
            $this->membership_status = $row['membership_status'];
            $this->user_id = $row['user_id'];
            $this->created_at = $row['created_at'];
            return true;
        }
        
        return false;
    }
}