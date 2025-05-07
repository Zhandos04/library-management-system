<?php
// Include header
include_once 'includes/header.php';

// Include database and models
require_once 'config/database.php';
require_once 'models/Book.php';
require_once 'models/Loan.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get book ID from URL
$book_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// If no ID provided, redirect to books page
if ($book_id <= 0) {
    header("Location: books.php");
    exit();
}

// Initialize book object and get details
$book = new Book($db);
$book->id = $book_id;
$found = $book->readOne();

// If book not found, redirect
if (!$found) {
    header("Location: books.php");
    exit();
}

// Get loan history for this book
$loan = new Loan($db);
$loan_history_query = "SELECT l.*, CONCAT(m.first_name, ' ', m.last_name) as member_name 
                      FROM loan_history l
                      JOIN members m ON l.member_id = m.id
                      WHERE l.book_id = ?
                      ORDER BY l.checkout_date DESC
                      LIMIT 5";
$stmt = $db->prepare($loan_history_query);
$stmt->bindParam(1, $book_id);
$stmt->execute();
$loan_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Book Details</h1>
        <a href="books.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i>Back to Books
        </a>
    </div>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?php echo htmlspecialchars($book->title); ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center mb-3">
                                <!-- Placeholder book cover -->
                                <div class="bg-light p-4 border rounded" style="height: 200px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-book fa-5x text-secondary"></i>
                                </div>
                            </div>
                            <div class="d-grid gap-2">
                                <?php if($auth->isLoggedIn() && $book->available_copies > 0): ?>
                                <a href="loans.php?action=checkout&book_id=<?php echo $book->id; ?>" class="btn btn-success">
                                    <i class="fas fa-book-reader me-2"></i>Borrow Book
                                </a>
                                <?php elseif($book->available_copies <= 0): ?>
                                <button class="btn btn-secondary" disabled>
                                    <i class="fas fa-times-circle me-2"></i>Not Available
                                </button>
                                <?php endif; ?>
                                
                                <?php if($auth->isLibrarian()): ?>
                                <a href="books_manage.php?action=edit&id=<?php echo $book->id; ?>" class="btn btn-warning">
                                    <i class="fas fa-edit me-2"></i>Edit Book
                                </a>
                                <a href="books_manage.php?action=delete&id=<?php echo $book->id; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this book?')">
                                    <i class="fas fa-trash me-2"></i>Delete Book
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <table class="table">
                                <tbody>
                                    <tr>
                                        <th width="150">ISBN</th>
                                        <td><?php echo htmlspecialchars($book->isbn); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Author</th>
                                        <td><?php echo htmlspecialchars($book->author); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Category</th>
                                        <td><?php echo htmlspecialchars($book->category); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Publisher</th>
                                        <td><?php echo htmlspecialchars($book->publisher); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Publication Year</th>
                                        <td><?php echo htmlspecialchars($book->publication_year); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Total Copies</th>
                                        <td><?php echo htmlspecialchars($book->total_copies); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Available Copies</th>
                                        <td>
                                            <?php if($book->available_copies > 0): ?>
                                            <span class="badge bg-success"><?php echo $book->available_copies; ?> Available</span>
                                            <?php else: ?>
                                            <span class="badge bg-danger">Not Available</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Added On</th>
                                        <td><?php echo date('d M Y', strtotime($book->created_at)); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <?php if($auth->isLibrarian() && count($loan_history) > 0): ?>
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Loan History</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <?php foreach($loan_history as $loan): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong><?php echo htmlspecialchars($loan['member_name']); ?></strong><br>
                                    <small>Checkout: <?php echo date('d M Y', strtotime($loan['checkout_date'])); ?></small><br>
                                    <small>Due: <?php echo date('d M Y', strtotime($loan['due_date'])); ?></small>
                                </div>
                                <div>
                                    <?php if($loan['status'] == 'returned'): ?>
                                    <span class="badge bg-success">Returned</span><br>
                                    <small>on <?php echo date('d M Y', strtotime($loan['return_date'])); ?></small>
                                    <?php elseif($loan['status'] == 'overdue'): ?>
                                    <span class="badge bg-danger">Overdue</span>
                                    <?php else: ?>
                                    <span class="badge bg-warning text-dark">Checked Out</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card mt-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Similar Books</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get similar books by same author or category
                    $similar_query = "SELECT id, title, author 
                                     FROM books 
                                     WHERE (author = ? OR category = ?) 
                                     AND id != ? 
                                     LIMIT 5";
                    $similar_stmt = $db->prepare($similar_query);
                    $similar_stmt->bindParam(1, $book->author);
                    $similar_stmt->bindParam(2, $book->category);
                    $similar_stmt->bindParam(3, $book->id);
                    $similar_stmt->execute();
                    $similar_books = $similar_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if(count($similar_books) > 0):
                    ?>
                    <ul class="list-group">
                        <?php foreach($similar_books as $similar): ?>
                        <li class="list-group-item">
                            <a href="book_detail.php?id=<?php echo $similar['id']; ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($similar['title']); ?>
                            </a>
                            <br>
                            <small>by <?php echo htmlspecialchars($similar['author']); ?></small>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p class="text-muted">No similar books found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>