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
        $error = "Имя, фамилия и email обязательны для заполнения.";
    } else {
        if ($member->update()) {
            $success = "Информация о читателе успешно обновлена.";
        } else {
            $error = "Не удалось обновить информацию о читателе. Пожалуйста, попробуйте еще раз.";
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
        <h1>Карточка читателя</h1>
        <div>
            <button class="btn btn-warning" type="button" data-bs-toggle="collapse" data-bs-target="#editMemberForm">
                <i class="fas fa-edit me-2"></i>Редактировать
            </button>
            <a href="members.php" class="btn btn-outline-primary ms-2">
                <i class="fas fa-arrow-left me-2"></i>К списку читателей
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
                    <h5 class="mb-0">Профиль читателя</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="avatar bg-light p-3 rounded-circle mx-auto mb-3" style="width: 100px; height: 100px;">
                            <i class="fas fa-user-circle fa-4x text-primary"></i>
                        </div>
                        <h4><?php echo htmlspecialchars($member->first_name . ' ' . $member->last_name); ?></h4>
                        <span class="badge <?php echo $member->membership_status == 'active' ? 'bg-success' : ($member->membership_status == 'expired' ? 'bg-danger' : 'bg-warning text-dark'); ?>">
                            <?php 
                            $status_text = '';
                            switch($member->membership_status) {
                                case 'active':
                                    $status_text = 'Активен';
                                    break;
                                case 'expired':
                                    $status_text = 'Истёк';
                                    break;
                                case 'suspended':
                                    $status_text = 'Приостановлен';
                                    break;
                                default:
                                    $status_text = ucfirst($member->membership_status);
                            }
                            echo $status_text;
                            ?>
                        </span>
                    </div>
                    
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-envelope me-2"></i> Email</span>
                            <span><?php echo htmlspecialchars($member->email); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-phone me-2"></i> Телефон</span>
                            <span><?php echo htmlspecialchars($member->phone); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-calendar-alt me-2"></i> Дата регистрации</span>
                            <span><?php echo date('d.m.Y', strtotime($member->membership_date)); ?></span>
                        </li>
                    </ul>
                    
                    <div class="mt-3">
                        <h6>Адрес:</h6>
                        <p><?php echo nl2br(htmlspecialchars($member->address)); ?></p>
                    </div>
                    
                    <div class="d-grid gap-2 mt-3">
                        <a href="loans.php?action=add&member_id=<?php echo $member->id; ?>" class="btn btn-success">
                            <i class="fas fa-book-reader me-2"></i>Оформить выдачу
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Member Statistics -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Статистика читателя</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <h2 class="h4"><?php echo $total_loans; ?></h2>
                            <p class="text-muted">Всего книг</p>
                        </div>
                        <div class="col-6 mb-3">
                            <h2 class="h4"><?php echo $active_loans_count; ?></h2>
                            <p class="text-muted">На руках</p>
                        </div>
                        <div class="col-6 mb-3">
                            <h2 class="h4"><?php echo $returned_count; ?></h2>
                            <p class="text-muted">Возвращено</p>
                        </div>
                        <div class="col-6 mb-3">
                            <h2 class="h4"><?php echo $overdue_count; ?></h2>
                            <p class="text-muted">Просрочено</p>
                        </div>
                    </div>
                    
                    <div class="text-center mt-2">
                        <h5>Сумма штрафов:</h5>
                        <h3 class="<?php echo $total_fines > 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php echo number_format($total_fines, 2); ?> р.
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
                        <h5 class="mb-0">Редактирование читателя</h5>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $member->id); ?>" method="post">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">Имя*</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($member->first_name); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Фамилия*</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($member->last_name); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email*</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($member->email); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Телефон</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($member->phone); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Адрес</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($member->address); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="membership_status" class="form-label">Статус*</label>
                                <select class="form-select" id="membership_status" name="membership_status" required>
                                    <option value="active" <?php if($member->membership_status == 'active') echo 'selected'; ?>>Активен</option>
                                    <option value="expired" <?php if($member->membership_status == 'expired') echo 'selected'; ?>>Истёк</option>
                                    <option value="suspended" <?php if($member->membership_status == 'suspended') echo 'selected'; ?>>Приостановлен</option>
                                </select>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="update_member" class="btn btn-warning">
                                    <i class="fas fa-save me-2"></i>Обновить данные
                                </button>
                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#editMemberForm">
                                    Отмена
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Active Loans -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Активные выдачи (<?php echo $active_loans_count; ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if ($active_loans_count > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Название книги</th>
                                    <th>Дата выдачи</th>
                                    <th>Срок возврата</th>
                                    <th>Статус</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($active_loans as $loan): ?>
                                <tr>
                                    <td>
                                        <a href="book_detail.php?id=<?php echo $loan['book_id']; ?>"><?php echo htmlspecialchars($loan['book_title']); ?></a>
                                    </td>
                                    <td><?php echo date('d.m.Y', strtotime($loan['checkout_date'])); ?></td>
                                    <td><?php echo date('d.m.Y', strtotime($loan['due_date'])); ?></td>
                                    <td>
                                        <?php
                                        $today = new DateTime();
                                        $due_date = new DateTime($loan['due_date']);
                                        $days_left = $today->diff($due_date)->format("%r%a");
                                        
                                        if ($days_left < 0) {
                                            echo '<span class="badge bg-danger">Просрочено на ' . abs($days_left) . ' дн.</span>';
                                        } elseif ($days_left <= 3) {
                                            echo '<span class="badge bg-warning text-dark">Осталось ' . $days_left . ' дн.</span>';
                                        } else {
                                            echo '<span class="badge bg-success">Осталось ' . $days_left . ' дн.</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="loans.php?action=return&id=<?php echo $loan['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Подтвердить возврат этой книги?')">
                                            <i class="fas fa-undo"></i> Вернуть
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">У этого читателя нет активных выдач.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Loan History -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">История выдач</h5>
                </div>
                <div class="card-body">
                    <?php if ($total_loans > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Название книги</th>
                                    <th>Дата выдачи</th>
                                    <th>Срок возврата</th>
                                    <th>Дата возврата</th>
                                    <th>Статус</th>
                                    <th>Штраф</th>
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
                                    <td><?php echo date('d.m.Y', strtotime($loan['checkout_date'])); ?></td>
                                    <td><?php echo date('d.m.Y', strtotime($loan['due_date'])); ?></td>
                                    <td>
                                        <?php if (!empty($loan['return_date'])): ?>
                                        <?php echo date('d.m.Y', strtotime($loan['return_date'])); ?>
                                        <?php else: ?>
                                        -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        $status_text = '';
                                        switch($loan['status']) {
                                            case 'checked_out':
                                                $status_class = 'bg-warning text-dark';
                                                $status_text = 'На руках';
                                                break;
                                            case 'returned':
                                                $status_class = 'bg-success';
                                                $status_text = 'Возвращена';
                                                break;
                                            case 'overdue':
                                                $status_class = 'bg-danger';
                                                $status_text = 'Просрочена';
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
                                        <span class="text-danger"><?php echo number_format($loan['fine'], 2); ?> р.</span>
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
                    <p class="text-muted">У этого читателя пока нет истории выдач.</p>
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