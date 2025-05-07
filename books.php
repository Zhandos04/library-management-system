<?php
// Include header
include_once 'includes/header.php';

// Include database and book model
require_once 'config/database.php';
require_once 'models/Book.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Initialize book object
$book = new Book($db);

// Page parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get books based on search or all
if (!empty($search)) {
    $stmt = $book->search($search);
    $total_rows = $stmt->rowCount();
} else {
    $stmt = $book->readAll($page, $records_per_page);
    $total_rows = $book->countAll();
}

// Calculate total pages
$total_pages = ceil($total_rows / $records_per_page);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Books Catalog</h1>
        <?php if($auth->isLibrarian()): ?>
        <a href="books_manage.php?action=add" class="btn btn-success">
            <i class="fas fa-plus-circle me-2"></i>Add New Book
        </a>
        <?php endif; ?>
    </div>
    
    <!-- Search Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="books.php" method="GET" class="search-form">
                <div class="input-group">
                    <input type="text" class="form-control" name="search" placeholder="Search by title or author" value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search me-2"></i>Search
                    </button>
                    <?php if(!empty($search)): ?>
                    <a href="books.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-2"></i>Clear
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <?php if($stmt->rowCount() > 0): ?>
    
    <!-- Books Table -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <?php if(!empty($search)): ?>
            <h5 class="mb-0">Search Results for: "<?php echo htmlspecialchars($search); ?>" (<?php echo $total_rows; ?> results found)</h5>
            <?php else: ?>
            <h5 class="mb-0">All Books (<?php echo $total_rows; ?> total)</h5>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Category</th>
                            <th>Publication Year</th>
                            <th>Available Copies</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo htmlspecialchars($row['author']); ?></td>
                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                            <td><?php echo htmlspecialchars($row['publication_year']); ?></td>
                            <td>
                                <?php if($row['available_copies'] > 0): ?>
                                <span class="badge bg-success"><?php echo $row['available_copies']; ?> of <?php echo $row['total_copies']; ?></span>
                                <?php else: ?>
                                <span class="badge bg-danger">Not Available</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="book_detail.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-info-circle"></i>
                                </a>
                                <?php if($auth->isLibrarian()): ?>
                                <a href="books_manage.php?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="books_manage.php?action=delete&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this book?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                                <?php if($auth->isLoggedIn() && $row['available_copies'] > 0): ?>
                                <a href="loans.php?action=checkout&book_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success">
                                    <i class="fas fa-book-reader"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if($total_pages > 1 && empty($search)): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php if($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="books.php?page=1">First</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="books.php?page=<?php echo $page-1; ?>">Previous</a>
                    </li>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                    <li class="page-item <?php if($i == $page) echo 'active'; ?>">
                        <a class="page-link" href="books.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="books.php?page=<?php echo $page+1; ?>">Next</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="books.php?page=<?php echo $total_pages; ?>">Last</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
            
        </div>
    </div>
    
    <?php else: ?>
    <div class="alert alert-info">
        <?php if(!empty($search)): ?>
        <h4 class="alert-heading">No results found!</h4>
        <p>Your search for "<?php echo htmlspecialchars($search); ?>" did not match any books in our catalog.</p>
        <hr>
        <p class="mb-0">Try different keywords or <a href="books.php" class="alert-link">browse all books</a>.</p>
        <?php else: ?>
        <h4 class="alert-heading">No books available!</h4>
        <p>There are currently no books in the catalog.</p>
        <?php if($auth->isLibrarian()): ?>
        <hr>
        <p class="mb-0">Start by <a href="books_manage.php?action=add" class="alert-link">adding a new book</a>.</p>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>