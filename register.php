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
        $error = "Пожалуйста, заполните все обязательные поля.";
    } elseif ($password != $confirm_password) {
        $error = "Пароли не совпадают.";
    } elseif (strlen($password) < 6) {
        $error = "Пароль должен содержать не менее 6 символов.";
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
                $success = "Регистрация успешно завершена! Теперь вы можете войти в систему.";
                // Clear form fields
                $username = $first_name = $last_name = $email = $phone = $address = '';
            } else {
                $error = "Ошибка при создании профиля читателя.";
                
                // Roll back user creation
                $query = "DELETE FROM users WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $user_id);
                $stmt->execute();
            }
        } else {
            $error = "Имя пользователя уже существует или произошла ошибка при регистрации.";
        }
    }
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Регистрация</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                        <a href="login.php" class="alert-link">Нажмите здесь, чтобы войти</a>
                    </div>
                    <?php else: ?>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Имя пользователя*</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo $username; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email*</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo $email; ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Пароль*</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <small class="text-muted">Минимум 6 символов</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Подтверждение пароля*</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">Имя*</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo $first_name; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Фамилия*</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo $last_name; ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Номер телефона</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?php echo $phone; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Адрес</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo $address; ?></textarea>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Зарегистрироваться</button>
                        </div>
                    </form>
                    
                    <?php endif; ?>
                </div>
                <div class="card-footer text-center">
                    <p class="mb-0">Уже есть аккаунт? <a href="login.php">Войти</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>