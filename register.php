<?php
// Include header
include_once 'includes/header.php';

// Check if user is already logged in
if ($auth->isLoggedIn()) {
    header("Location: index.php");
    exit();
}

// Process registration form
$error = '';
$success = '';
$username = '';
$first_name = '';
$last_name = '';
$email = '';
$phone = '';
$address = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // Validate inputs
    if (empty($username) || empty($password) || empty($confirm_password) || 
        empty($first_name) || empty($last_name) || empty($email)) {
        $error = "Please fill all required fields.";
    } elseif ($password != $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Register the user
        if ($auth->register($username, $password, 'user')) {
            // Get the new user ID
            $query = "SELECT id FROM users WHERE username = ?";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $username);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $user_id = $row['id'];
            
            // Create member record
            require_once 'models/Member.php';
            $member = new Member($db);
            $member->first_name = $first_name;
            $member->last_name = $last_name;
            $member->email = $email;
            $member->phone = $phone;
            $member->address = $address;
            $member->membership_date = date('Y-m-d');
            $member->membership_status = 'active';
            $member->user_id = $user_id;
            
            if ($member->create()) {
                $success = "Registration successful! You can now login.";
                // Clear form fields
                $username = $first_name = $last_name = $email = $phone = $address = '';
            } else {
                $error = "Error creating member profile.";
                
                // Roll back user creation
                $query = "DELETE FROM users WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $user_id);
                $stmt->execute();
            }
        } else {
            $error = "Username already exists or error registering user.";
        }
    }
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Register</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                        <a href="login.php" class="alert-link">Click here to login</a>
                    </div>
                    <?php else: ?>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username*</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo $username; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email*</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo $email; ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password*</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password*</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name*</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo $first_name; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name*</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo $last_name; ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?php echo $phone; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo $address; ?></textarea>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Register</button>
                        </div>
                    </form>
                    
                    <?php endif; ?>
                </div>
                <div class="card-footer text-center">
                    <p class="mb-0">Already have an account? <a href="login.php">Login</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>