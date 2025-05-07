<?php
// Include header
include_once 'includes/header.php';

// Check if user has librarian privileges
if (!$auth->isLibrarian()) {
    // Redirect to books page if not authorized
    header("Location: books.php");
    exit();
}

// Include database and book model
require_once 'config/database.php';
require_once 'models/Book.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Initialize book object
$book = new Book($db);

// Get action and book ID from URL
$action = isset($_GET['action']) ? $_GET['action'] : '';
$book_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Variables for form data
$isbn = '';
$title = '';
$author = '';
$category = '';
$publication_year = '';
$publisher = '';
$total_copies = 1;
$available_copies = 1;

// Process based on action
if ($action == 'edit' && $book_id > 0) {
    // Get book details for editing
    $book->id = $book_id;
    if ($book->readOne()) {
        $isbn = $book->isbn;
        $title = $book->title;
        $author = $book->author;
        $category = $book->category;
        $publication_year = $book->publication_year;
        $publisher = $book->publisher;
        $total_copies = $book->total_copies;
        $available_copies = $book->available_copies;
    } else {
        // Book not found, redirect
        header("Location: books.php");
        exit();
    }
} elseif ($action == 'delete' && $book_id > 0) {
    // Delete book
    $book->id = $book_id;
    if ($book->delete()) {
        header("Location: books.php?deleted=1");
    } else {
        header("Location: books.php?deleted=0");
    }
    exit();
}

// Process form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize and set book properties
    $book->isbn = trim($_POST['isbn']);
    $book->title = trim($_POST['title']);
    $book->author = trim($_POST['author']);
    $book->category = trim($_POST['category']);
    $book->publication_year = (int)trim($_POST['publication_year']);
    $book->publisher = trim($_POST['publisher']);
    $book->total_copies = (int)trim($_POST['total_copies']);
    
    // For new books, available copies = total copies
    if ($action == 'add') {
        $book->available_copies = $book->total_copies;
    } else {
        // For existing books, calculate the change
        $original_total = (int)$total_copies;
        $new_total = $book->total_copies;
        $original_available = (int)$available_copies;
        
        // Adjust available copies proportionally
        if ($original_total != $new_total) {
            $difference = $new_total - $original_total;
            $book->available_copies = max(0, $original_available + $difference);
        } else {
            $book->available_copies = $original_available;
        }
    }
    
    // Validate data
    if (empty($book->isbn) || empty($book->title) || empty($book->author)) {
        $error = "ISBN, Title and Author are required fields.";
    } else {
        if ($action == 'add') {
            // Create book
            if ($book->create()) {
                $success = "Book was created successfully.";
                // Clear form data
                $isbn = $title = $author = $category = $publication_year = $publisher = '';
                $total_copies = $available_copies = 1;
            } else {
                $error = "Unable to create book. ISBN may already exist.";
            }
        } elseif ($action == 'edit') {
            // Update book
            $book->id = $book_id;
            if ($book->update()) {
                $success = "Book was updated successfully.";
            } else {
                $error = "Unable to update book. ISBN may already exist.";
            }
        }
    }
    
    // Preserve form data in case of error
    if (!empty($error)) {
        $isbn = $book->isbn;
        $title = $book->title;
        $author = $book->author;
        $category = $book->category;
        $publication_year = $book->publication_year;
        $publisher = $book->publisher;
        $total_copies = $book->total_copies;
        $available_copies = $book->available_copies;
    }
}

// Page title based on action
$page_title = ($action == 'edit') ? 'Edit Book' : 'Add New Book';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo $page_title; ?></h1>
        <a href="books.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i>Back to Books
        </a>
    </div>
    
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><?php echo $page_title; ?></h5>
        </div>
        <div class="card-body">
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?action=$action&id=$book_id"); ?>" method="post">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="isbn" class="form-label">ISBN*</label>
                        <input type="text" class="form-control" id="isbn" name="isbn" value="<?php echo htmlspecialchars($isbn); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="publication_year" class="form-label">Publication Year</label>
                        <input type="number" class="form-control" id="publication_year" name="publication_year" min="1000" max="<?php echo date('Y'); ?>" value="<?php echo htmlspecialchars($publication_year); ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="title" class="form-label">Title*</label>
                    <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="author" class="form-label">Author*</label>
                    <input type="text" class="form-control" id="author" name="author" value="<?php echo htmlspecialchars($author); ?>" required>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="category" class="form-label">Category</label>
                        <input type="text" class="form-control" id="category" name="category" value="<?php echo htmlspecialchars($category); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="publisher" class="form-label">Publisher</label>
                        <input type="text" class="form-control" id="publisher" name="publisher" value="<?php echo htmlspecialchars($publisher); ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="total_copies" class="form-label">Total Copies*</label>
                        <input type="number" class="form-control" id="total_copies" name="total_copies" min="1" value="<?php echo htmlspecialchars($total_copies); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="available_copies" class="form-label">Available Copies</label>
                        <input type="number" class="form-control" id="available_copies" value="<?php echo htmlspecialchars($available_copies); ?>" disabled>
                        <small class="text-muted">This will be calculated automatically</small>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i><?php echo ($action == 'edit') ? 'Update Book' : 'Add Book'; ?>
                    </button>
                    <a href="books.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>