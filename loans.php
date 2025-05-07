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

// Get action from URL
$action = isset($_GET['action']) ? $_GET['action'] : '';
$loan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$book_id = isset($_GET['book_id']) ? (int)$_GET['book_id'] : 0;
$member_id = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';

// Check if regular user has member record
$user_member_id = 0;
if ($auth->isLoggedIn() && !$auth->isLibrarian()) {
    $member->getByUserId($auth->getCurrentUserId());
    $user_member_id = $member->id;
    
    // Regular users can only checkout for themselves
    if ($action == 'checkout' && $member->id) {
        $member_id = $member->id;
    } else {
        // Redirect if trying to access other loan functions
        header("Location: index.php");
        exit();
    }
}

// Process loan actions
$error = '';
$success = '';

// Process checkout action
if ($action == 'checkout' && $book_id > 0) {
    // Get book details
    $book->id = $book_id;
    if (!$book->readOne() || $book->available_copies <= 0) {
        $error = "Книга недоступна для выдачи.";
    } else {
        // If member_id not provided, show form to select member
        if ($member_id <= 0 && $auth->isLibrarian()) {
            // Show form, processing continues below
        } else {
            // Process checkout
            $loan->book_id = $book_id;
            $loan->member_id = $member_id;
            $loan->checkout_date = date('Y-m-d');
            $loan->due_date = date('Y-m-d', strtotime('+14 days')); // 2 weeks loan period
            
            if ($loan->checkoutBook()) {
                $success = "Книга успешно выдана.";
                // Clear variables
                $book_id = $member_id = 0;
            } else {
                $error = "Не удалось выдать книгу. Пожалуйста, попробуйте еще раз.";
            }
        }
    }
}

// Process return action
if ($action == 'return' && $loan_id > 0 && $auth->isLibrarian()) {
    $loan->id = $loan_id;
    $loan->return_date = date('Y-m-d');
    
    if ($loan->returnBook()) {
        $success = "Книга успешно возвращена.";
        $loan_id = 0;
    } else {
        $error = "Не удалось вернуть книгу. Пожалуйста, попробуйте еще раз.";
    }
}

// Page parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;

// Get loans based on filter
if ($auth->isLibrarian()) {
    if ($filter == 'overdue') {
        $stmt = $loan->getOverdueLoans();
    } else {
        $stmt = $loan->readAll($page, $records_per_page);
    }
} else {
    // For regular users, only show their loans
    if ($user_member_id > 0) {
        $stmt = $loan->getMemberLoanHistory($user_member_id);
    } else {
        // No member record
        $stmt = null;
    }
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            <?php if ($filter == 'overdue'): ?>
            Просроченные книги
            <?php elseif (!$auth->isLibrarian()): ?>
            Мои книги
            <?php else: ?>
            Управление выдачей
            <?php endif; ?>
        </h1>
        
        <?php if ($auth->isLibrarian()): ?>
        <div>
            <?php if ($filter == 'overdue'): ?>
            <a href="loans.php" class="btn btn-outline-primary me-2">
                <i class="fas fa-list me-2"></i>Все выдачи
            </a>
            <?php else: ?>
            <a href="loans.php?filter=overdue" class="btn btn-outline-danger me-2">
                <i class="fas fa-exclamation-circle me-2"></i>Просроченные
            </a>
            <?php endif; ?>
            
            <a href="loans.php?action=add" class="btn btn-success">
                <i class="fas fa-plus-circle me-2"></i>Новая выдача
            </a>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if ($action == 'checkout' && $book_id > 0 && $member_id <= 0 && $auth->isLibrarian()): ?>
    <!-- Checkout Form (for librarians to select member) -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Выдача книги: <?php echo htmlspecialchars($book->title); ?></h5>
        </div>
        <div class="card-body">
            <form action="loans.php" method="GET">
                <input type="hidden" name="action" value="checkout">
                <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                
                <div class="mb-3">
                    <label for="member_id" class="form-label">Выберите читателя</label>
                    <select class="form-select" id="member_id" name="member_id" required>
                        <option value="">-- Выберите читателя --</option>
                        <?php
                        $members_query = "SELECT id, first_name, last_name, email FROM members WHERE membership_status = 'active' ORDER BY last_name, first_name";
                        $members_stmt = $db->prepare($members_query);
                        $members_stmt->execute();
                        
                        while ($member_row = $members_stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo '<option value="' . $member_row['id'] . '">' . 
                                htmlspecialchars($member_row['first_name'] . ' ' . $member_row['last_name']) . 
                                ' (' . htmlspecialchars($member_row['email']) . ')' . 
                                '</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Информация о книге</label>
                    <div class="card">
                        <div class="card-body">
                            <p><strong>Название:</strong> <?php echo htmlspecialchars($book->title); ?></p>
                            <p><strong>Автор:</strong> <?php echo htmlspecialchars($book->author); ?></p>
                            <p><strong>ISBN:</strong> <?php echo htmlspecialchars($book->isbn); ?></p>
                            <p><strong>Доступно экземпляров:</strong> <?php echo $book->available_copies; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Период выдачи</label>
                    <div class="card">
                        <div class="card-body">
                            <p><strong>Дата выдачи:</strong> <?php echo date('d.m.Y'); ?></p>
                            <p><strong>Срок возврата:</strong> <?php echo date('d.m.Y', strtotime('+14 days')); ?></p>
                            <p class="text-muted"><small>Стандартный срок выдачи - 14 дней</small></p>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check-circle me-2"></i>Оформить выдачу
                    </button>
                    <a href="books.php" class="btn btn-outline-secondary">Отмена</a>
                </div>
            </form>
        </div>
    </div>
    <?php elseif ($action == 'add' && $auth->isLibrarian()): ?>
    <!-- New Loan Form -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Оформление новой выдачи</h5>
        </div>
        <div class="card-body">
            <form action="loans.php" method="GET">
                <input type="hidden" name="action" value="checkout">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="book_id" class="form-label">Выберите книгу</label>
                        <select class="form-select" id="book_id" name="book_id" required>
                            <option value="">-- Выберите книгу --</option>
                            <?php
                            $books_query = "SELECT id, title, author, available_copies FROM books WHERE available_copies > 0 ORDER BY title";
                            $books_stmt = $db->prepare($books_query);
                            $books_stmt->execute();
                            
                            while ($book_row = $books_stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo '<option value="' . $book_row['id'] . '">' . 
                                    htmlspecialchars($book_row['title']) . ' - ' . htmlspecialchars($book_row['author']) . 
                                    ' (' . $book_row['available_copies'] . ' экз.)' . 
                                    '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="member_id" class="form-label">Выберите читателя</label>
                        <select class="form-select" id="member_id" name="member_id" required>
                            <option value="">-- Выберите читателя --</option>
                            <?php
                            $members_query = "SELECT id, first_name, last_name, email FROM members WHERE membership_status = 'active' ORDER BY last_name, first_name";
                            $members_stmt = $db->prepare($members_query);
                            $members_stmt->execute();
                            
                            while ($member_row = $members_stmt->fetch(PDO::FETCH_ASSOC)) {
                                $selected = ($member_row['id'] == $member_id) ? 'selected' : '';
                                echo '<option value="' . $member_row['id'] . '" ' . $selected . '>' . 
                                    htmlspecialchars($member_row['first_name'] . ' ' . $member_row['last_name']) . 
                                    ' (' . htmlspecialchars($member_row['email']) . ')' . 
                                    '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-arrow-right me-2"></i>Перейти к оформлению
                    </button>
                    <a href="loans.php" class="btn btn-outline-secondary">Отмена</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Loans Table -->
    <?php if (isset($stmt) && $stmt && $stmt->rowCount() > 0): ?>
    <div class="card">
        <div class="card-header bg-primary text-white">
            <?php if ($filter == 'overdue'): ?>
            <h5 class="mb-0">Просроченные книги (всего: <?php echo $stmt->rowCount(); ?>)</h5>
            <?php elseif (!$auth->isLibrarian()): ?>
            <h5 class="mb-0">История моих книг</h5>
            <?php else: ?>
            <h5 class="mb-0">Все выдачи</h5>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Книга</th>
                            <?php if ($auth->isLibrarian()): ?>
                            <th>Читатель</th>
                            <?php endif; ?>
                            <th>Дата выдачи</th>
                            <th>Срок возврата</th>
                            <th>Дата возврата</th>
                            <th>Статус</th>
                            <th>Штраф</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td>
                                <?php if (isset($row['book_title'])): ?>
                                <a href="book_detail.php?id=<?php echo $row['book_id']; ?>"><?php echo htmlspecialchars($row['book_title']); ?></a>
                                <?php else: ?>
                                <a href="book_detail.php?id=<?php echo $row['book_id']; ?>">Книга #<?php echo $row['book_id']; ?></a>
                                <?php endif; ?>
                            </td>
                            <?php if ($auth->isLibrarian()): ?>
                            <td>
                                <?php if (isset($row['member_name'])): ?>
                                <a href="member_detail.php?id=<?php echo $row['member_id']; ?>"><?php echo htmlspecialchars($row['member_name']); ?></a>
                                <?php else: ?>
                                <a href="member_detail.php?id=<?php echo $row['member_id']; ?>">Читатель #<?php echo $row['member_id']; ?></a>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td><?php echo date('d.m.Y', strtotime($row['checkout_date'])); ?></td>
                            <td><?php echo date('d.m.Y', strtotime($row['due_date'])); ?></td>
                            <td>
                                <?php if (!empty($row['return_date'])): ?>
                                <?php echo date('d.m.Y', strtotime($row['return_date'])); ?>
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $status_class = '';
                                switch($row['status']) {
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
                                        $status_text = ucfirst($row['status']);
                                }
                                ?>
                                <span class="badge <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($row['fine'] > 0): ?>
                                <span class="text-danger"><?php echo number_format($row['fine'], 2); ?> р.</span>
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($auth->isLibrarian() && $row['status'] == 'checked_out'): ?>
                                <a href="loans.php?action=return&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Подтвердить возврат этой книги?')">
                                    <i class="fas fa-undo"></i> Вернуть
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php elseif (isset($stmt)): ?>
    <div class="alert alert-info">
        <?php if ($filter == 'overdue'): ?>
        <h4 class="alert-heading">Нет просроченных книг!</h4>
        <p>В настоящее время нет просроченных книг.</p>
        <?php elseif (!$auth->isLibrarian()): ?>
        <h4 class="alert-heading">Нет истории выдач!</h4>
        <p>Вы еще не брали книги.</p>
        <hr>
        <p class="mb-0"><a href="books.php" class="alert-link">Просмотрите каталог</a>, чтобы взять книгу.</p>
        <?php else: ?>
        <h4 class="alert-heading">Нет записей о выдачах!</h4>
        <p>В системе пока нет записей о выдачах книг.</p>
        <hr>
        <p class="mb-0">Начните с <a href="loans.php?action=add" class="alert-link">оформления новой выдачи</a>.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>