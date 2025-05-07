<?php
// Include header
include_once 'includes/header.php';

// Check if user has admin privileges
if (!$auth->isAdmin()) {
    // Redirect to index page if not authorized
    header("Location: index.php");
    exit();
}

// Include database
require_once 'config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Process user actions
$error = '';
$success = '';

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);
    
    if (empty($username) || empty($password) || empty($role)) {
        $error = "All fields are required.";
    } else {
        if ($auth->register($username, $password, $role)) {
            $success = "User created successfully.";
        } else {
            $error = "Failed to create user. Username may already exist.";
        }
    }
}

// Handle user update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    $user_id = (int)$_POST['user_id'];
    $role = trim($_POST['role']);
    
    if (empty($user_id) || empty($role)) {
        $error = "User ID and role are required.";
    } else {
        $query = "UPDATE users SET role = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $role);
        $stmt->bindParam(2, $user_id);
        
        if ($stmt->execute()) {
            $success = "User role updated successfully.";
        } else {
            $error = "Failed to update user role.";
        }
    }
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
    $user_id = (int)$_POST['user_id'];
    
    if (empty($user_id)) {
        $error = "User ID is required.";
    } elseif ($user_id == $auth->getCurrentUserId()) {
        $error = "You cannot delete your own account.";
    } else {
        // Check if user has member profile
        $check_query = "SELECT id FROM members WHERE user_id = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(1, $user_id);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $error = "Cannot delete user with associated member profile. Delete the member record first.";
        } else {
            $query = "DELETE FROM users WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $user_id);
            
            if ($stmt->execute()) {
                $success = "User deleted successfully.";
            } else {
                $error = "Failed to delete user.";
            }
        }
    }
}

// Reset overdue fines for all books
if (isset($_GET['action']) && $_GET['action'] == 'reset_fines') {
    $query = "UPDATE loan_history SET fine = 0 WHERE status = 'returned'";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute()) {
        $success = "All fines have been reset successfully.";
    } else {
        $error = "Failed to reset fines.";
    }
}

// Update overdue book statuses
if (isset($_GET['action']) && $_GET['action'] == 'update_overdue') {
    require_once 'models/Loan.php';
    $loan = new Loan($db);
    
    if ($loan->updateOverdueStatus()) {
        $success = "Overdue book statuses updated successfully.";
    } else {
        $error = "Failed to update overdue book statuses.";
    }
}

// Get all users
$users_query = "SELECT u.id, u.username, u.role, u.created_at, 
               (SELECT COUNT(*) FROM members WHERE user_id = u.id) as has_member_profile
               FROM users u
               ORDER BY u.username";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get system statistics
$stats = [
    'total_books' => 0,
    'total_members' => 0,
    'total_loans' => 0,
    'active_loans' => 0,
    'overdue_books' => 0,
    'total_fines' => 0
];

// Get book stats
$book_query = "SELECT COUNT(*) as total, SUM(total_copies) as total_copies, SUM(available_copies) as available_copies FROM books";
$book_stmt = $db->prepare($book_query);
$book_stmt->execute();
$book_stats = $book_stmt->fetch(PDO::FETCH_ASSOC);
$stats['total_books'] = $book_stats['total'];
$stats['total_copies'] = $book_stats['total_copies'];
$stats['available_copies'] = $book_stats['available_copies'];

// Get member stats
$member_query = "SELECT COUNT(*) as total, 
                SUM(CASE WHEN membership_status = 'active' THEN 1 ELSE 0 END) as active_members
                FROM members";
$member_stmt = $db->prepare($member_query);
$member_stmt->execute();
$member_stats = $member_stmt->fetch(PDO::FETCH_ASSOC);
$stats['total_members'] = $member_stats['total'];
$stats['active_members'] = $member_stats['active_members'];

// Get loan stats
$loan_query = "SELECT COUNT(*) as total, 
              SUM(CASE WHEN status = 'checked_out' THEN 1 ELSE 0 END) as active_loans,
              SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_books,
              SUM(fine) as total_fines
              FROM loan_history";
$loan_stmt = $db->prepare($loan_query);
$loan_stmt->execute();
$loan_stats = $loan_stmt->fetch(PDO::FETCH_ASSOC);
$stats['total_loans'] = $loan_stats['total'];
$stats['active_loans'] = $loan_stats['active_loans'];
$stats['overdue_books'] = $loan_stats['overdue_books'];
$stats['total_fines'] = $loan_stats['total_fines'];
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Admin Panel</h1>
    </div>
    
    <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-8">
            <!-- System Overview -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">System Overview</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h1 class="display-4"><?php echo $stats['total_books']; ?></h1>
                                    <p class="mb-0">Books (<?php echo $stats['total_copies']; ?> copies)</p>
                                    <p class="text-muted"><?php echo $stats['available_copies']; ?> available</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h1 class="display-4"><?php echo $stats['total_members']; ?></h1>
                                    <p class="mb-0">Members</p>
                                    <p class="text-muted"><?php echo $stats['active_members']; ?> active</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h1 class="display-4"><?php echo $stats['total_loans']; ?></h1>
                                    <p class="mb-0">Total Loans</p>
                                    <p class="text-muted"><?php echo $stats['active_loans']; ?> active, <?php echo $stats['overdue_books']; ?> overdue</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title">System Actions</h5>
                                    <div class="d-grid gap-2">
                                        <a href="admin.php?action=update_overdue" class="btn btn-warning">
                                            <i class="fas fa-sync me-2"></i>Update Overdue Statuses
                                        </a>
                                        <a href="admin.php?action=reset_fines" class="btn btn-danger" onclick="return confirm('Are you sure you want to reset all fines?')">
                                            <i class="fas fa-dollar-sign me-2"></i>Reset All Fines
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title">Financial Overview</h5>
                                    <div class="text-center">
                                        <h2 class="text-success">$<?php echo number_format($stats['total_fines'], 2); ?></h2>
                                        <p class="mb-0">Total Fines Collected</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- User Management -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">User Management</h5>
                </div>
                <div class="card-body">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Created</th>
                                <th>Member Profile</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td>
                                    <span class="badge <?php echo $user['role'] == 'admin' ? 'bg-danger' : ($user['role'] == 'librarian' ? 'bg-warning text-dark' : 'bg-info'); ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if($user['has_member_profile'] > 0): ?>
                                    <span class="badge bg-success">Yes</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $user['id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if($user['id'] != $auth->getCurrentUserId() && $user['has_member_profile'] == 0): ?>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal<?php echo $user['id']; ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <!-- Edit User Modal -->
                            <div class="modal fade" id="editUserModal<?php echo $user['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header bg-primary text-white">
                                            <h5 class="modal-title">Edit User: <?php echo htmlspecialchars($user['username']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                            <div class="modal-body">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                
                                                <div class="mb-3">
                                                    <label for="role<?php echo $user['id']; ?>" class="form-label">Role</label>
                                                    <select class="form-select" id="role<?php echo $user['id']; ?>" name="role">
                                                        <option value="user" <?php if($user['role'] == 'user') echo 'selected'; ?>>User</option>
                                                        <option value="librarian" <?php if($user['role'] == 'librarian') echo 'selected'; ?>>Librarian</option>
                                                        <option value="admin" <?php if($user['role'] == 'admin') echo 'selected'; ?>>Admin</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="submit" name="update_user" class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Delete User Modal -->
                            <div class="modal fade" id="deleteUserModal<?php echo $user['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header bg-danger text-white">
                                            <h5 class="modal-title">Delete User</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Are you sure you want to delete the user <strong><?php echo htmlspecialchars($user['username']); ?></strong>?</p>
                                            <p class="text-danger">This action cannot be undone.</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="delete_user" class="btn btn-danger">Delete User</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Create New User -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Create New User</h5>
                </div>
                <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username*</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password*</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role*</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">-- Select Role --</option>
                                <option value="user">User</option>
                                <option value="librarian">Librarian</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="create_user" class="btn btn-success">
                                <i class="fas fa-plus-circle me-2"></i>Create User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- System Information -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">System Information</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>PHP Version</span>
                            <span class="badge bg-primary rounded-pill"><?php echo phpversion(); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Database</span>
                            <span class="badge bg-primary rounded-pill">MySQL</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Server Software</span>
                            <span class="badge bg-primary rounded-pill"><?php echo $_SERVER['SERVER_SOFTWARE']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Current Date</span>
                            <span class="badge bg-primary rounded-pill"><?php echo date('d M Y'); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Logged in User</span>
                            <span class="badge bg-primary rounded-pill"><?php echo $_SESSION['username']; ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>