<?php
// Include header
include_once 'includes/header.php';

// Include database and models
require_once 'config/database.php';
require_once 'models/Book.php';
require_once 'models/Member.php';
require_once 'models/Loan.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$book = new Book($db);
$member = new Member($db);
$loan = new Loan($db);

// Get counts
$total_books = $book->countAll();
$total_members = $member->countAll();
$total_loans = $loan->countAll();

// Get recently added books
$recent_books_stmt = $book->readAll(1, 5);
$recent_books = $recent_books_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get overdue loans if librarian
$overdue_loans = [];
if ($auth->isLibrarian()) {
    $overdue_loans_stmt = $loan->getOverdueLoans();
    $overdue_loans = $overdue_loans_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get active loans for current user if logged in and not librarian
$user_loans = [];
if ($auth->isLoggedIn() && !$auth->isLibrarian()) {
    $member->getByUserId($auth->getCurrentUserId());
    if ($member->id) {
        $user_loans_stmt = $loan->getMemberActiveLoans($member->id);
        $user_loans = $user_loans_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<div class="container mt-4">
    <h1 class="mb-4">Welcome to Library Management System</h1>
    
    <?php if (!$auth->isLoggedIn()): ?>
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title">About Our Library</h2>
                    <p class="card-text">Welcome to our online library management system. Here you can browse our collection, check book availability, and manage your loans. Please login or register to access all features.</p>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                        <a href="login.php" class="btn btn-primary me-md-2">Login</a>
                        <a href="register.php" class="btn btn-outline-primary">Register</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Library Stats</h5>
                    <p class="card-text">Books: <?php echo $total_books; ?></p>
                    <p class="card-text">Members: <?php echo $total_members; ?></p>
                    <p class="card-text">Total Loans: <?php echo $total_loans; ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="row">
        <?php if ($auth->isLibrarian()): ?>
        <!-- Admin/Librarian Dashboard -->
        <div class="col-md-3 mb-4">
            <div class="card text-white bg-primary h-100">
                <div class="card-body">
                    <h5 class="card-title">Books</h5>
                    <h1><?php echo $total_books; ?></h1>
                    <p class="card-text">Total books in library</p>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="books.php" class="text-white">View Details</a>
                    <i class="fas fa-book fa-2x"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card text-white bg-success h-100">
                <div class="card-body">
                    <h5 class="card-title">Members</h5>
                    <h1><?php echo $total_members; ?></h1>
                    <p class="card-text">Registered library members</p>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="members.php" class="text-white">View Details</a>
                    <i class="fas fa-users fa-2x"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card text-white bg-info h-100">
                <div class="card-body">
                    <h5 class="card-title">Loans</h5>
                    <h1><?php echo $total_loans; ?></h1>
                    <p class="card-text">Total book loans</p>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="loans.php" class="text-white">View Details</a>
                    <i class="fas fa-exchange-alt fa-2x"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card text-white bg-danger h-100">
                <div class="card-body">
                    <h5 class="card-title">Overdue</h5>
                    <h1><?php echo count($overdue_loans); ?></h1>
                    <p class="card-text">Books past due date</p>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="loans.php?filter=overdue" class="text-white">View Details</a>
                    <i class="fas fa-exclamation-circle fa-2x"></i>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Regular User Dashboard -->
        <div class="col-md-4 mb-4">
            <div class="card text-white bg-primary h-100">
                <div class="card-body">
                    <h5 class="card-title">My Loans</h5>
                    <h1><?php echo count($user_loans); ?></h1>
                    <p class="card-text">Active book loans</p>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="profile.php" class="text-white">View Details</a>
                    <i class="fas fa-book-reader fa-2x"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card text-white bg-success h-100">
                <div class="card-body">
                    <h5 class="card-title">Available Books</h5>
                    <h1><?php echo $total_books; ?></h1>
                    <p class="card-text">Browse our collection</p>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="books.php" class="text-white">Browse Books</a>
                    <i class="fas fa-book fa-2x"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card text-white bg-info h-100">
                <div class="card-body">
                    <h5 class="card-title">Search</h5>
                    <p class="card-text">Find books by title or author</p>
                    <form action="books.php" method="GET">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Book title or author">
                            <button class="btn btn-dark" type="submit">Search</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recently Added Books</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th>Category</th>
                                    <th>Availability</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_books as $book): ?>
                                <tr>
                                    <td><a href="book_detail.php?id=<?php echo $book['id']; ?>"><?php echo $book['title']; ?></a></td>
                                    <td><?php echo $book['author']; ?></td>
                                    <td><?php echo $book['category']; ?></td>
                                    <td>
                                        <?php if($book['available_copies'] > 0): ?>
                                            <span class="badge bg-success">Available (<?php echo $book['available_copies']; ?>)</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Not Available</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end">
                        <a href="books.php" class="btn btn-outline-primary">View All Books</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <?php if ($auth->isLibrarian() && count($overdue_loans) > 0): ?>
            <div class="card mb-4">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">Overdue Books</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <?php foreach($overdue_loans as $index => $loan): ?>
                            <?php if ($index < 5): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo $loan['book_title']; ?></strong><br>
                                    <small>Borrowed by: <?php echo $loan['member_name']; ?></small><br>
                                    <small>Due: <?php echo date('d M Y', strtotime($loan['due_date'])); ?></small>
                                </div>
                                <span class="badge bg-danger rounded-pill">
                                    <?php 
                                        $due_date = new DateTime($loan['due_date']);
                                        $today = new DateTime();
                                        $days = $today->diff($due_date)->days;
                                        echo $days . " days";
                                    ?>
                                </span>
                            </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (count($overdue_loans) > 5): ?>
                    <div class="text-end mt-3">
                        <a href="loans.php?filter=overdue" class="btn btn-sm btn-outline-danger">View All Overdue</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif (!$auth->isLibrarian() && count($user_loans) > 0): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">My Active Loans</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <?php foreach($user_loans as $loan): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo $loan['book_title']; ?></strong><br>
                                <small>Due: <?php echo date('d M Y', strtotime($loan['due_date'])); ?></small>
                            </div>
                            <?php 
                                $due_date = new DateTime($loan['due_date']);
                                $today = new DateTime();
                                $days_left = $today->diff($due_date)->format("%r%a");
                                
                                if ($days_left < 0) {
                                    echo '<span class="badge bg-danger rounded-pill">Overdue</span>';
                                } elseif ($days_left <= 3) {
                                    echo '<span class="badge bg-warning text-dark rounded-pill">' . $days_left . ' days left</span>';
                                } else {
                                    echo '<span class="badge bg-success rounded-pill">' . $days_left . ' days left</span>';
                                }
                            ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="books.php" class="btn btn-outline-primary">Browse Books</a>
                        <?php if ($auth->isLibrarian()): ?>
                        <a href="books_manage.php?action=add" class="btn btn-outline-success">Add New Book</a>
                        <a href="members.php?action=add" class="btn btn-outline-success">Add New Member</a>
                        <a href="loans.php?action=add" class="btn btn-outline-success">Issue New Loan</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>