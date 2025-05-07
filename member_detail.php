<?php
// Include header
include_once 'includes/header.php';

// Check if user has librarian privileges
if (!$auth->isLibrarian()) {
    // Redirect to index page if not authorized
    header("Location: index.php");
    exit();
}

// Include database and models
require_once 'config/database.php';
require_once 'models/Member.php';
require_once 'models/Loan.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get member ID from URL
$member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// If no ID provided, redirect to members page
if ($member_id <= 0) {
    header("Location: members.php");
    exit();
}

// Initialize member object and get details
$member = new Member($db);
$member->id = $member_id;
$found = $member->readOne();

// If member not found, redirect
if (!$found) {
    header("Location: members.php");
    exit();
}

// Process form submission to update member
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_member'])) {
    // Update member profile
    $member->first_name = trim($_POST['first_name']);
    $member->last_name = trim($_POST['last_name']);
    $member->email = trim($_POST['email']);
    $member->phone = trim($_POST['phone']);
    $member->address = trim($_POST['address']);
    $member->membership_status = trim($_POST['membership_status']);
    
    // Simple validation
    if (empty($member->first_name) || empty($member->last_name) || empty($member->email)) {
        $error = "First name, last name and email are required.";
    } else {
        if ($member->update()) {
            $success = "Member updated successfully.";
        } else {
            $error = "Unable to update member. Please try again.";
        }
    }
}

// Get loan history for this member
$loan = new Loan($db);
$active_loans_stmt = $loan->getMemberActiveLoans($member_id);
$active_loans = $active_loans_stmt->fetchAll(PDO::FETCH_ASSOC);

$loan_history_stmt = $loan->getMemberLoanHistory($member_id);
$loan_history = $loan_history_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_loans = count($loan_history);
$active_loans_count = count($active_loans);
$returned_loans = array_filter($loan_history, function($loan) {
    return $loan['status'] == 'returned';
});
$returned_count = count($returned_loans);

$overdue_loans = array_filter($loan_history, function($loan) {
    return $loan['status'] == 'overdue';
});
$overdue_count = count($overdue_loans);

$total_fines = 0;
foreach ($loan_history as $loan) {
    $total_fines += $loan['fine'];
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Member Details</h1>
        <div>
            <button class="btn btn-warning" type="button" data-bs-toggle="collapse" data-bs-target="#editMemberForm">
                <i class="fas fa-edit me-2"></i>Edit Member
            </button>
            <a href="members.php" class="btn btn-outline-primary ms-2">
                <i class="fas fa-arrow-left me-2"></i>Back to Members
            </a>
        </div>
    </div>
    
    <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-4">
            <!-- Member Information -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Member Profile</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="avatar bg-light p-3 rounded-circle mx-auto mb-3" style="width: 100px; height: 100px;">
                            <i class="fas fa-user-circle fa-4x text-primary"></i>
                        </div>
                        <h4><?php echo htmlspecialchars($member->first_name . ' ' . $member->last_name); ?></h4>
                        <span class="badge <?php echo $member->membership_status == 'active' ? 'bg-success' : ($member->membership_status == 'expired' ? 'bg-danger' : 'bg-warning text-dark'); ?>">
                            <?php echo ucfirst($member->membership_status); ?>
                        </span>
                    </div>
                    
                    <ul class="list-group list-group-flush">
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
                    </ul>
                    
                    <div class="mt-3">
                        <h6>Address:</h6>
                        <p><?php echo nl2br(htmlspecialchars($member->address)); ?></p>
                    </div>
                    
                    <div class="d-grid gap-2 mt-3">
                        <a href="loans.php?action=add&member_id=<?php echo $member->id; ?>" class="btn btn-success">
                            <i class="fas fa-book-reader me-2"></i>Issue New Loan
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Member Statistics -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Member Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <h2 class="h4"><?php echo $total_loans; ?></h2>
                            <p class="text-muted">Total Loans</p>
                        </div>
                        <div class="col-6 mb-3">
                            <h2 class="h4"><?php echo $active_loans_count; ?></h2>
                            <p class="text-muted">Active Loans</p>
                        </div>
                        <div class="col-6 mb-3">
                            <h2 class="h4"><?php echo $returned_count; ?></h2>
                            <p class="text-muted">Returned</p>
                        </div>
                        <div class="col-6 mb-3">
                            <h2 class="h4"><?php echo $overdue_count; ?></h2>
                            <p class="text-muted">Overdue</p>
                        </div>
                    </div>
                    
                    <div class="text-center mt-2">
                        <h5>Total Fines:</h5>
                        <h3 class="<?php echo $total_fines > 0 ? 'text-danger' : 'text-success'; ?>">
                            $<?php echo number_format($total_fines, 2); ?>
                        </h3>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Edit Member Form (Collapsed by default) -->
            <div class="collapse mb-4" id="editMemberForm">
                <div class="card">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0">Edit Member</h5>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $member->id); ?>" method="post">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name*</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($member->first_name); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name*</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($member->last_name); ?>" required>
                                </div>
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
                            
                            <div class="mb-3">
                                <label for="membership_status" class="form-label">Membership Status*</label>
                                <select class="form-select" id="membership_status" name="membership_status" required>
                                    <option value="active" <?php if($member->membership_status == 'active') echo 'selected'; ?>>Active</option>
                                    <option value="expired" <?php if($member->membership_status == 'expired') echo 'selected'; ?>>Expired</option>
                                    <option value="suspended" <?php if($member->membership_status == 'suspended') echo 'selected'; ?>>Suspended</option>
                                </select>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="update_member" class="btn btn-warning">
                                    <i class="fas fa-save me-2"></i>Update Member
                                </button>
                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#editMemberForm">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Active Loans -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Active Loans (<?php echo $active_loans_count; ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if ($active_loans_count > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Book Title</th>
                                    <th>Checkout Date</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
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
                                    <td>
                                        <a href="loans.php?action=return&id=<?php echo $loan['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Confirm return of this book?')">
                                            <i class="fas fa-undo"></i> Return
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">No active loans for this member.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Loan History -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Loan History</h5>
                </div>
                <div class="card-body">
                    <?php if ($total_loans > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Book Title</th>
                                    <th>Checkout Date</th>
                                    <th>Due Date</th>
                                    <th>Return Date</th>
                                    <th>Status</th>
                                    <th>Fine</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Sort loan history by checkout date descending
                                usort($loan_history, function($a, $b) {
                                    return strtotime($b['checkout_date']) - strtotime($a['checkout_date']);
                                });
                                
                                foreach($loan_history as $loan): 
                                ?>
                                <tr>
                                    <td>
                                        <a href="book_detail.php?id=<?php echo $loan['book_id']; ?>"><?php echo htmlspecialchars($loan['book_title']); ?></a>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($loan['checkout_date'])); ?></td>
                                    <td><?php echo date('d M Y', strtotime($loan['due_date'])); ?></td>
                                    <td>
                                        <?php if (!empty($loan['return_date'])): ?>
                                        <?php echo date('d M Y', strtotime($loan['return_date'])); ?>
                                        <?php else: ?>
                                        -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        switch($loan['status']) {
                                            case 'checked_out':
                                                $status_class = 'bg-warning text-dark';
                                                $status_text = 'Checked Out';
                                                break;
                                            case 'returned':
                                                $status_class = 'bg-success';
                                                $status_text = 'Returned';
                                                break;
                                            case 'overdue':
                                                $status_class = 'bg-danger';
                                                $status_text = 'Overdue';
                                                break;
                                            default:
                                                $status_class = 'bg-secondary';
                                                $status_text = ucfirst($loan['status']);
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($loan['fine'] > 0): ?>
                                        <span class="text-danger">$<?php echo number_format($loan['fine'], 2); ?></span>
                                        <?php else: ?>
                                        -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">No loan history for this member yet.</p>
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