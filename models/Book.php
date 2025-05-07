<?php
class Book {
    private $conn;
    private $table_name = "books";

    // Book properties
    public $id;
    public $isbn;
    public $title;
    public $author;
    public $category;
    public $publication_year;
    public $publisher;
    public $total_copies;
    public $available_copies;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create book
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (isbn, title, author, category, publication_year, publisher, total_copies, available_copies) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);

        // Sanitize input
        $this->isbn = htmlspecialchars(strip_tags($this->isbn));
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->author = htmlspecialchars(strip_tags($this->author));
        $this->category = htmlspecialchars(strip_tags($this->category));
        $this->publication_year = htmlspecialchars(strip_tags($this->publication_year));
        $this->publisher = htmlspecialchars(strip_tags($this->publisher));
        $this->total_copies = htmlspecialchars(strip_tags($this->total_copies));
        $this->available_copies = htmlspecialchars(strip_tags($this->available_copies));

        // Bind values
        $stmt->bindParam(1, $this->isbn);
        $stmt->bindParam(2, $this->title);
        $stmt->bindParam(3, $this->author);
        $stmt->bindParam(4, $this->category);
        $stmt->bindParam(5, $this->publication_year);
        $stmt->bindParam(6, $this->publisher);
        $stmt->bindParam(7, $this->total_copies);
        $stmt->bindParam(8, $this->available_copies);

        // Execute query
        if ($stmt->execute()) {
            return true;
        }

        return false;
    }

    // Read all books
    public function readAll($page = 1, $records_per_page = 10) {
        $offset = ($page - 1) * $records_per_page;
        
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY title LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $records_per_page, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt;
    }

    // Read one book
    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $this->isbn = $row['isbn'];
            $this->title = $row['title'];
            $this->author = $row['author'];
            $this->category = $row['category'];
            $this->publication_year = $row['publication_year'];
            $this->publisher = $row['publisher'];
            $this->total_copies = $row['total_copies'];
            $this->available_copies = $row['available_copies'];
            $this->created_at = $row['created_at'];
            return true;
        }
        
        return false;
    }

    // Update book
    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET isbn = ?, title = ?, author = ?, category = ?, 
                      publication_year = ?, publisher = ?, total_copies = ?, available_copies = ? 
                  WHERE id = ?";

        $stmt = $this->conn->prepare($query);

        // Sanitize input
        $this->isbn = htmlspecialchars(strip_tags($this->isbn));
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->author = htmlspecialchars(strip_tags($this->author));
        $this->category = htmlspecialchars(strip_tags($this->category));
        $this->publication_year = htmlspecialchars(strip_tags($this->publication_year));
        $this->publisher = htmlspecialchars(strip_tags($this->publisher));
        $this->total_copies = htmlspecialchars(strip_tags($this->total_copies));
        $this->available_copies = htmlspecialchars(strip_tags($this->available_copies));
        $this->id = htmlspecialchars(strip_tags($this->id));

        // Bind values
        $stmt->bindParam(1, $this->isbn);
        $stmt->bindParam(2, $this->title);
        $stmt->bindParam(3, $this->author);
        $stmt->bindParam(4, $this->category);
        $stmt->bindParam(5, $this->publication_year);
        $stmt->bindParam(6, $this->publisher);
        $stmt->bindParam(7, $this->total_copies);
        $stmt->bindParam(8, $this->available_copies);
        $stmt->bindParam(9, $this->id);

        // Execute query
        if ($stmt->execute()) {
            return true;
        }

        return false;
    }

    // Delete book
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

    // Search books
    public function search($keywords) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE title LIKE ? OR author LIKE ? 
                  ORDER BY title";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitize
        $keywords = htmlspecialchars(strip_tags($keywords));
        $keywords = "%{$keywords}%";
        
        // Bind
        $stmt->bindParam(1, $keywords);
        $stmt->bindParam(2, $keywords);
        
        $stmt->execute();
        return $stmt;
    }

    // Count all books
    public function countAll() {
        $query = "SELECT COUNT(*) as total_books FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total_books'];
    }

    // Update book availability
    public function updateAvailability($operation = 'checkout') {
        if ($operation == 'checkout') {
            $query = "UPDATE " . $this->table_name . " 
                      SET available_copies = available_copies - 1 
                      WHERE id = ? AND available_copies > 0";
        } else {
            $query = "UPDATE " . $this->table_name . " 
                      SET available_copies = available_copies + 1 
                      WHERE id = ? AND available_copies < total_copies";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        
        return $stmt->execute();
    }
}