<?php
// Include header
include_once 'includes/header.php';

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Include database and models
require_once 'config/database.php';
require_once 'models/Member.php';
require_once 'models/Loan.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get member information
$member = new Member($db);
$has_member_profile = $member->getByUserId($auth->getCurrentUserId());

// Get active loans if member profile exists
$active_loans = [];
$loan_history = [];
if ($has_member_profile) {
    $loan = new Loan($db);
    $active_loans_stmt = $loan->getMemberActiveLoans($member->id);
    $active_loans = $active_loans_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $loan_history_stmt = $loan->getMemberLoanHistory($member->id);
    $loan_history = $loan_history_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Process form submission to update profile
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    // Update member profile
    $member->first_name = trim($_POST['first_name']);
    $member->last_name = trim($_POST['last_name']);
    $member->email = trim($_POST['email']);
    $member->phone = trim($_POST['phone']);
    $member->address = trim($_POST['address']);
    
    // Simple validation
    if (empty($member->first_name) || empty($member->last_name) || empty($member->email)) {
        $error = "First name, last name and email are required.";
    } else {
        if ($member->update()) {
            $success = "Profile updated successfully.";
        } else {
            $error = "Unable to update profile. Please try again.";
        }
    }
}

// Process password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validate password change
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All password fields are required.";
    } elseif ($new_password != $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters long.";
    } else {
        // Verify current password
        $username = $_SESSION['username'];
        
        $query = "SELECT password FROM users WHERE username = ?";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $username);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row && password_verify($current_password, $row['password'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $user_id = $auth->getCurrentUserId();
            
            $update_query = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(1, $hashed_password);
            $update_stmt->bindParam(2, $user_id);
            
            if ($update_stmt->execute()) {
                $success = "Password changed successfully.";
            } else {
                $error = "Unable to change password. Please try again.";
            }
        } else {
            $error = "Current password is incorrect.";
        }
    }
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>My Profile</h1>
    </div>
    
    <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-4 mb-4">
            <!-- Profile Information -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Account Information</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="avatar bg-light p-3 rounded-circle mx-auto mb-3" style="width: 100px; height: 100px;">
                            <i class="fas fa-user-circle fa-4x text-primary"></i>
                        </div>
                        <h4><?php echo $_SESSION['username']; ?></h4>
                        <span class="badge bg-info"><?php echo ucfirst($_SESSION['role']); ?></span>
                    </div>
                    
                    <?php if ($has_member_profile): ?>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-user me-2"></i> Name</span>
                            <span><?php echo htmlspecialchars($member->first_name . ' ' . $member->last_name); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-envelope me-2"></i> Email</span>
                            <span><?php echo htmlspecialchars($member->email); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-phone me-2"></i> Phone</span>
                            <span><?php echo htmlspecialchars($member->phone); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-calendar-alt me-2"></i> Member Since</span>
                            <span><?php echo date('d M Y', strtotime($member->membership_date)); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-flag me-2"></i> Status</span>
                            <span class="badge bg-success"><?php echo ucfirst($member->membership_status); ?></span>
                        </li>
                    </ul>
                    <?php else: ?>
                    <div class="alert alert-warning">
                        <p>You don't have a member profile associated with your account. Please contact a librarian to set up your membership.</p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2 mt-3">
                        <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#editProfileForm">
                            <i class="fas fa-edit me-2"></i>Edit Profile
                        </button>
                        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#changePasswordForm">
                            <i class="fas fa-key me-2"></i>Change Password
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Edit Profile Form (Collapsed by default) -->
            <div class="collapse mt-4" id="editProfileForm">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Edit Profile</h5>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name*</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($member->first_name); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name*</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($member->last_name); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email*</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($member->email); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($member->phone); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($member->address); ?></textarea>
                            </div>
                            <div class="d-grid">
                                <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Change Password Form (Collapsed by default) -->
            <div class="collapse mt-4" id="changePasswordForm">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password*</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password*</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password*</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <?php if ($has_member_profile): ?>
            <!-- Active Loans -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">My Active Loans (<?php echo count($active_loans); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (count($active_loans) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Book Title</th>
                                    <th>Checkout Date</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($active_loans as $loan): ?>
                                <tr>
                                    <td>
                                        <a href="book_detail.php?id=<?php echo $loan['book_id']; ?>"><?php echo htmlspecialchars($loan['book_title']); ?></a>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($loan['checkout_date'])); ?></td>
                                    <td><?php echo date('d M Y', strtotime($loan['due_date'])); ?></td>
                                    <td>
                                        <?php
                                        $today = new DateTime();
                                        $due_date = new DateTime($loan['due_date']);
                                        $days_left = $today->diff($due_date)->format("%r%a");
                                        
                                        if ($days_left < 0) {
                                            echo '<span class="badge bg-danger">Overdue by ' . abs($days_left) . ' days</span>';
                                        } elseif ($days_left <= 3) {
                                            echo '<span class="badge bg-warning text-dark">' . $days_left . ' days left</span>';
                                        } else {
                                            echo '<span class="badge bg-success">' . $days_left . ' days left</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">You don't have any active loans.</p>
                    <a href="books.php" class="btn btn-outline-primary">Browse Books</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Loan History -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">My Loan History</h5>
                </div>
                <div class="card-body">
                    <?php if (count($loan_history) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Book Title</th>
                                    <th>Checkout Date</th>
                                    <th>Return Date</th>
                                    <th>Status</th>
                                    <th>Fine</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($loan_history as $loan): ?>
                                <?php if ($loan['status'] == 'returned'): ?>
                                <tr>
                                    <td>
                                        <a href="book_detail.php?id=<?php echo $loan['book_id']; ?>"><?php echo htmlspecialchars($loan['book_title']); ?></a>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($loan['checkout_date'])); ?></td>
                                    <td><?php echo date('d M Y', strtotime($loan['return_date'])); ?></td>
                                    <td><span class="badge bg-success">Returned</span></td>
                                    <td>
                                        <?php if ($loan['fine'] > 0): ?>
                                        <span class="text-danger">$<?php echo number_format($loan['fine'], 2); ?></span>
                                        <?php else: ?>
                                        -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">You don't have any loan history yet.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-info">
                <h4 class="alert-heading">Welcome to the Library Management System!</h4>
                <p>To borrow books and access member features, you need to have a member profile.</p>
                <hr>
                <p>Please visit the library in person or contact a librarian to create your membership profile.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>