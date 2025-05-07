<?php
class Loan {
    private $conn;
    private $table_name = "loan_history";

    // Loan properties
    public $id;
    public $book_id;
    public $member_id;
    public $checkout_date;
    public $due_date;
    public $return_date;
    public $status;
    public $fine;
    public $created_at;

    // Additional properties for joined data
    public $book_title;
    public $member_name;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Check out a book
    public function checkoutBook() {
        // First check if book is available
        $book_query = "SELECT available_copies FROM books WHERE id = ? LIMIT 1";
        $book_stmt = $this->conn->prepare($book_query);
        $book_stmt->bindParam(1, $this->book_id);
        $book_stmt->execute();
        
        $book_row = $book_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$book_row || $book_row['available_copies'] <= 0) {
            return false; // Book not available
        }

        // Check if member exists and is active
        $member_query = "SELECT membership_status FROM members WHERE id = ? LIMIT 1";
        $member_stmt = $this->conn->prepare($member_query);
        $member_stmt->bindParam(1, $this->member_id);
        $member_stmt->execute();
        
        $member_row = $member_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$member_row || $member_row['membership_status'] != 'active') {
            return false; // Member not active
        }

        // Create transaction
        try {
            $this->conn->beginTransaction();

            // Insert loan record
            $loan_query = "INSERT INTO " . $this->table_name . " 
                          (book_id, member_id, checkout_date, due_date, status) 
                          VALUES (?, ?, ?, ?, 'checked_out')";
            
            $loan_stmt = $this->conn->prepare($loan_query);
            $loan_stmt->bindParam(1, $this->book_id);
            $loan_stmt->bindParam(2, $this->member_id);
            $loan_stmt->bindParam(3, $this->checkout_date);
            $loan_stmt->bindParam(4, $this->due_date);
            $loan_stmt->execute();

            // Update book availability
            $update_book_query = "UPDATE books SET available_copies = available_copies - 1 WHERE id = ?";
            $update_book_stmt = $this->conn->prepare($update_book_query);
            $update_book_stmt->bindParam(1, $this->book_id);
            $update_book_stmt->execute();

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    // Return a book
    public function returnBook() {
        // Check if loan exists and is checked out
        $check_query = "SELECT id, status, due_date FROM " . $this->table_name . " 
                        WHERE id = ? AND status = 'checked_out' LIMIT 1";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(1, $this->id);
        $check_stmt->execute();
        
        $row = $check_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false; // Loan not found or already returned
        }

        // Calculate fine if returned after due date
        $fine = 0;
        $due_date = new DateTime($row['due_date']);
        $return_date = new DateTime($this->return_date);
        
        if ($return_date > $due_date) {
            $diff = $return_date->diff($due_date);
            $days_late = $diff->days;
            $fine = $days_late * 0.50; // $0.50 per day late
        }

        // Create transaction
        try {
            $this->conn->beginTransaction();

            // Update loan record
            $update_query = "UPDATE " . $this->table_name . " 
                            SET return_date = ?, status = 'returned', fine = ? 
                            WHERE id = ?";
            
            $update_stmt = $this->conn->prepare($update_query);
            $update_stmt->bindParam(1, $this->return_date);
            $update_stmt->bindParam(2, $fine);
            $update_stmt->bindParam(3, $this->id);
            $update_stmt->execute();

            // Get book_id from loan
            $book_query = "SELECT book_id FROM " . $this->table_name . " WHERE id = ?";
            $book_stmt = $this->conn->prepare($book_query);
            $book_stmt->bindParam(1, $this->id);
            $book_stmt->execute();
            $book_row = $book_stmt->fetch(PDO::FETCH_ASSOC);
            $book_id = $book_row['book_id'];

            // Update book availability
            $update_book_query = "UPDATE books SET available_copies = available_copies + 1 WHERE id = ?";
            $update_book_stmt = $this->conn->prepare($update_book_query);
            $update_book_stmt->bindParam(1, $book_id);
            $update_book_stmt->execute();

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    // Get all loans with book and member details
    public function readAll($page = 1, $records_per_page = 10) {
        $offset = ($page - 1) * $records_per_page;
        
        $query = "SELECT l.*, b.title as book_title, 
                  CONCAT(m.first_name, ' ', m.last_name) as member_name 
                  FROM " . $this->table_name . " l
                  LEFT JOIN books b ON l.book_id = b.id
                  LEFT JOIN members m ON l.member_id = m.id
                  ORDER BY l.checkout_date DESC 
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $records_per_page, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt;
    }

    // Get active loans for a member
    public function getMemberActiveLoans($member_id) {
        $query = "SELECT l.*, b.title as book_title 
                  FROM " . $this->table_name . " l
                  LEFT JOIN books b ON l.book_id = b.id
                  WHERE l.member_id = ? AND l.status = 'checked_out'
                  ORDER BY l.due_date ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $member_id);
        $stmt->execute();
        
        return $stmt;
    }

    // Get loan history for a member
    public function getMemberLoanHistory($member_id) {
        $query = "SELECT l.*, b.title as book_title 
                  FROM " . $this->table_name . " l
                  LEFT JOIN books b ON l.book_id = b.id
                  WHERE l.member_id = ?
                  ORDER BY l.checkout_date DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $member_id);
        $stmt->execute();
        
        return $stmt;
    }

    // Get loan by ID with book and member details
    public function readOne() {
        $query = "SELECT l.*, b.title as book_title, 
                  CONCAT(m.first_name, ' ', m.last_name) as member_name 
                  FROM " . $this->table_name . " l
                  LEFT JOIN books b ON l.book_id = b.id
                  LEFT JOIN members m ON l.member_id = m.id
                  WHERE l.id = ? 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $this->id = $row['id'];
            $this->book_id = $row['book_id'];
            $this->member_id = $row['member_id'];
            $this->checkout_date = $row['checkout_date'];
            $this->due_date = $row['due_date'];
            $this->return_date = $row['return_date'];
            $this->status = $row['status'];
            $this->fine = $row['fine'];
            $this->created_at = $row['created_at'];
            $this->book_title = $row['book_title'];
            $this->member_name = $row['member_name'];
            return true;
        }
        
        return false;
    }

    // Get overdue loans
    public function getOverdueLoans() {
        $today = date('Y-m-d');
        
        $query = "SELECT l.*, b.title as book_title, 
                  CONCAT(m.first_name, ' ', m.last_name) as member_name 
                  FROM " . $this->table_name . " l
                  LEFT JOIN books b ON l.book_id = b.id
                  LEFT JOIN members m ON l.member_id = m.id
                  WHERE l.status = 'checked_out' AND l.due_date < ?
                  ORDER BY l.due_date ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $today);
        $stmt->execute();
        
        return $stmt;
    }

    // Update overdue status
    public function updateOverdueStatus() {
        $today = date('Y-m-d');
        
        $query = "UPDATE " . $this->table_name . " 
                  SET status = 'overdue' 
                  WHERE status = 'checked_out' AND due_date < ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $today);
        
        return $stmt->execute();
    }

    // Count all loans
    public function countAll() {
        $query = "SELECT COUNT(*) as total_loans FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total_loans'];
    }
}