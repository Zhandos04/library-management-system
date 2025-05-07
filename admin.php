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
        $error = "Все поля обязательны для заполнения.";
    } else {
        if ($auth->register($username, $password, $role)) {
            $success = "Пользователь успешно создан.";
        } else {
            $error = "Не удалось создать пользователя. Возможно, имя пользователя уже существует.";
        }
    }
}

// Handle user update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    $user_id = (int)$_POST['user_id'];
    $role = trim($_POST['role']);
    
    if (empty($user_id) || empty($role)) {
        $error = "ID пользователя и роль обязательны.";
    } else {
        $query = "UPDATE users SET role = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $role);
        $stmt->bindParam(2, $user_id);
        
        if ($stmt->execute()) {
            $success = "Роль пользователя успешно обновлена.";
        } else {
            $error = "Не удалось обновить роль пользователя.";
        }
    }
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
    $user_id = (int)$_POST['user_id'];
    
    if (empty($user_id)) {
        $error = "ID пользователя обязателен.";
    } elseif ($user_id == $auth->getCurrentUserId()) {
        $error = "Вы не можете удалить свою собственную учетную запись.";
    } else {
        // Check if user has member profile
        $check_query = "SELECT id FROM members WHERE user_id = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(1, $user_id);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $error = "Невозможно удалить пользователя с привязанным профилем читателя. Сначала удалите запись читателя.";
        } else {
            $query = "DELETE FROM users WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $user_id);
            
            if ($stmt->execute()) {
                $success = "Пользователь успешно удален.";
            } else {
                $error = "Не удалось удалить пользователя.";
            }
        }
    }
}

// Reset overdue fines for all books
if (isset($_GET['action']) && $_GET['action'] == 'reset_fines') {
    $query = "UPDATE loan_history SET fine = 0 WHERE status = 'returned'";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute()) {
        $success = "Все штрафы успешно сброшены.";
    } else {
        $error = "Не удалось сбросить штрафы.";
    }
}

// Update overdue book statuses
if (isset($_GET['action']) && $_GET['action'] == 'update_overdue') {
    require_once 'models/Loan.php';
    $loan = new Loan($db);
    
    if ($loan->updateOverdueStatus()) {
        $success = "Статусы просроченных книг успешно обновлены.";
    } else {
        $error = "Не удалось обновить статусы просроченных книг.";
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
        <h1>Панель администратора</h1>
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
                    <h5 class="mb-0">Обзор системы</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h1 class="display-4"><?php echo $stats['total_books']; ?></h1>
                                    <p class="mb-0">Книги (<?php echo $stats['total_copies']; ?> экземпляров)</p>
                                    <p class="text-muted"><?php echo $stats['available_copies']; ?> доступно</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h1 class="display-4"><?php echo $stats['total_members']; ?></h1>
                                    <p class="mb-0">Читатели</p>
                                    <p class="text-muted"><?php echo $stats['active_members']; ?> активных</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h1 class="display-4"><?php echo $stats['total_loans']; ?></h1>
                                    <p class="mb-0">Всего выдач</p>
                                    <p class="text-muted"><?php echo $stats['active_loans']; ?> активных, <?php echo $stats['overdue_books']; ?> просроченных</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title">Системные действия</h5>
                                    <div class="d-grid gap-2">
                                        <a href="admin.php?action=update_overdue" class="btn btn-warning">
                                            <i class="fas fa-sync me-2"></i>Обновить статусы просроченных
                                        </a>
                                        <a href="admin.php?action=reset_fines" class="btn btn-danger" onclick="return confirm('Вы уверены, что хотите сбросить все штрафы?')">
                                            <i class="fas fa-dollar-sign me-2"></i>Сбросить все штрафы
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title">Финансовый обзор</h5>
                                    <div class="text-center">
                                        <h2 class="text-success"><?php echo number_format($stats['total_fines'], 2); ?> р.</h2>
                                        <p class="mb-0">Всего собрано штрафов</p>
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
                    <h5 class="mb-0">Управление пользователями</h5>
                </div>
                <div class="card-body">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Имя пользователя</th>
                                <th>Роль</th>
                                <th>Создан</th>
                                <th>Профиль читателя</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td>
                                    <span class="badge <?php echo $user['role'] == 'admin' ? 'bg-danger' : ($user['role'] == 'librarian' ? 'bg-warning text-dark' : 'bg-info'); ?>">
                                        <?php
                                        $role_text = '';
                                        switch($user['role']) {
                                            case 'admin':
                                                $role_text = 'Администратор';
                                                break;
                                            case 'librarian':
                                                $role_text = 'Библиотекарь';
                                                break;
                                            case 'user':
                                                $role_text = 'Пользователь';
                                                break;
                                            default:
                                                $role_text = ucfirst($user['role']);
                                        }
                                        echo $role_text;
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo date('d.m.Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if($user['has_member_profile'] > 0): ?>
                                    <span class="badge bg-success">Да</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Нет</span>
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
                                            <h5 class="modal-title">Редактировать пользователя: <?php echo htmlspecialchars($user['username']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                            <div class="modal-body">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                
                                                <div class="mb-3">
                                                    <label for="role<?php echo $user['id']; ?>" class="form-label">Роль</label>
                                                    <select class="form-select" id="role<?php echo $user['id']; ?>" name="role">
                                                        <option value="user" <?php if($user['role'] == 'user') echo 'selected'; ?>>Пользователь</option>
                                                        <option value="librarian" <?php if($user['role'] == 'librarian') echo 'selected'; ?>>Библиотекарь</option>
                                                        <option value="admin" <?php if($user['role'] == 'admin') echo 'selected'; ?>>Администратор</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                                                <button type="submit" name="update_user" class="btn btn-primary">Сохранить изменения</button>
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
                                            <h5 class="modal-title">Удалить пользователя</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Вы уверены, что хотите удалить пользователя <strong><?php echo htmlspecialchars($user['username']); ?></strong>?</p>
                                            <p class="text-danger">Это действие нельзя отменить.</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="delete_user" class="btn btn-danger">Удалить пользователя</button>
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
                    <h5 class="mb-0">Создать нового пользователя</h5>
                </div>
                <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="mb-3">
                            <label for="username" class="form-label">Имя пользователя*</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Пароль*</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <small class="text-muted">Минимум 6 символов</small>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Роль*</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">-- Выберите роль --</option>
                                <option value="user">Пользователь</option>
                                <option value="librarian">Библиотекарь</option>
                                <option value="admin">Администратор</option>
                            </select>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="create_user" class="btn btn-success">
                                <i class="fas fa-plus-circle me-2"></i>Создать пользователя
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- System Information -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Информация о системе</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Версия PHP</span>
                            <span class="badge bg-primary rounded-pill"><?php echo phpversion(); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>База данных</span>
                            <span class="badge bg-primary rounded-pill">MySQL</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Серверное ПО</span>
                            <span class="badge bg-primary rounded-pill"><?php echo $_SERVER['SERVER_SOFTWARE']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Текущая дата</span>
                            <span class="badge bg-primary rounded-pill"><?php echo date('d.m.Y'); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Вход выполнен</span>
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