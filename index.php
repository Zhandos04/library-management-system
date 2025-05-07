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
    <h1 class="mb-4">Добро пожаловать в систему управления библиотекой</h1>
    
    <?php if (!$auth->isLoggedIn()): ?>
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title">О нашей библиотеке</h2>
                    <p class="card-text">Добро пожаловать в нашу онлайн-систему управления библиотекой. Здесь вы можете просматривать нашу коллекцию, проверять доступность книг и управлять своими выдачами. Пожалуйста, войдите или зарегистрируйтесь для доступа ко всем функциям.</p>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                        <a href="login.php" class="btn btn-primary me-md-2">Войти</a>
                        <a href="register.php" class="btn btn-outline-primary">Регистрация</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Статистика библиотеки</h5>
                    <p class="card-text">Книги: <?php echo $total_books; ?></p>
                    <p class="card-text">Читатели: <?php echo $total_members; ?></p>
                    <p class="card-text">Всего выдач: <?php echo $total_loans; ?></p>
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
                    <h5 class="card-title">Книги</h5>
                    <h1><?php echo $total_books; ?></h1>
                    <p class="card-text">Всего книг в библиотеке</p>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="books.php" class="text-white">Подробнее</a>
                    <i class="fas fa-book fa-2x"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card text-white bg-success h-100">
                <div class="card-body">
                    <h5 class="card-title">Читатели</h5>
                    <h1><?php echo $total_members; ?></h1>
                    <p class="card-text">Зарегистрированные читатели</p>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="members.php" class="text-white">Подробнее</a>
                    <i class="fas fa-users fa-2x"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card text-white bg-info h-100">
                <div class="card-body">
                    <h5 class="card-title">Выдачи</h5>
                    <h1><?php echo $total_loans; ?></h1>
                    <p class="card-text">Всего выдано книг</p>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="loans.php" class="text-white">Подробнее</a>
                    <i class="fas fa-exchange-alt fa-2x"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card text-white bg-danger h-100">
                <div class="card-body">
                    <h5 class="card-title">Просрочено</h5>
                    <h1><?php echo count($overdue_loans); ?></h1>
                    <p class="card-text">Книги с истекшим сроком возврата</p>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="loans.php?filter=overdue" class="text-white">Подробнее</a>
                    <i class="fas fa-exclamation-circle fa-2x"></i>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Regular User Dashboard -->
        <div class="col-md-4 mb-4">
            <div class="card text-white bg-primary h-100">
                <div class="card-body">
                    <h5 class="card-title">Мои книги</h5>
                    <h1><?php echo count($user_loans); ?></h1>
                    <p class="card-text">Активные выдачи книг</p>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="profile.php" class="text-white">Подробнее</a>
                    <i class="fas fa-book-reader fa-2x"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card text-white bg-success h-100">
                <div class="card-body">
                    <h5 class="card-title">Доступные книги</h5>
                    <h1><?php echo $total_books; ?></h1>
                    <p class="card-text">Просмотреть нашу коллекцию</p>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="books.php" class="text-white">Просмотр книг</a>
                    <i class="fas fa-book fa-2x"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card text-white bg-info h-100">
                <div class="card-body">
                    <h5 class="card-title">Поиск</h5>
                    <p class="card-text">Найти книги по названию или автору</p>
                    <form action="books.php" method="GET">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Название или автор">
                            <button class="btn btn-dark" type="submit">Поиск</button>
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
                    <h5 class="mb-0">Недавно добавленные книги</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Название</th>
                                    <th>Автор</th>
                                    <th>Категория</th>
                                    <th>Доступность</th>
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
                                            <span class="badge bg-success">Доступно (<?php echo $book['available_copies']; ?>)</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Нет в наличии</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end">
                        <a href="books.php" class="btn btn-outline-primary">Просмотреть все книги</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <?php if ($auth->isLibrarian() && count($overdue_loans) > 0): ?>
            <div class="card mb-4">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">Просроченные книги</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <?php foreach($overdue_loans as $index => $loan): ?>
                            <?php if ($index < 5): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo $loan['book_title']; ?></strong><br>
                                    <small>Читатель: <?php echo $loan['member_name']; ?></small><br>
                                    <small>Срок: <?php echo date('d.m.Y', strtotime($loan['due_date'])); ?></small>
                                </div>
                                <span class="badge bg-danger rounded-pill">
                                    <?php 
                                        $due_date = new DateTime($loan['due_date']);
                                        $today = new DateTime();
                                        $days = $today->diff($due_date)->days;
                                        echo $days . " дн.";
                                    ?>
                                </span>
                            </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (count($overdue_loans) > 5): ?>
                    <div class="text-end mt-3">
                        <a href="loans.php?filter=overdue" class="btn btn-sm btn-outline-danger">Просмотреть все просроченные</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif (!$auth->isLibrarian() && count($user_loans) > 0): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Мои активные выдачи</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <?php foreach($user_loans as $loan): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo $loan['book_title']; ?></strong><br>
                                <small>Срок: <?php echo date('d.m.Y', strtotime($loan['due_date'])); ?></small>
                            </div>
                            <?php 
                                $due_date = new DateTime($loan['due_date']);
                                $today = new DateTime();
                                $days_left = $today->diff($due_date)->format("%r%a");
                                
                                if ($days_left < 0) {
                                    echo '<span class="badge bg-danger rounded-pill">Просрочено</span>';
                                } elseif ($days_left <= 3) {
                                    echo '<span class="badge bg-warning text-dark rounded-pill">Осталось ' . $days_left . ' дн.</span>';
                                } else {
                                    echo '<span class="badge bg-success rounded-pill">Осталось ' . $days_left . ' дн.</span>';
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
                    <h5 class="mb-0">Быстрые действия</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="books.php" class="btn btn-outline-primary">Просмотр книг</a>
                        <?php if ($auth->isLibrarian()): ?>
                        <a href="books_manage.php?action=add" class="btn btn-outline-success">Добавить новую книгу</a>
                        <a href="members.php?action=add" class="btn btn-outline-success">Добавить нового читателя</a>
                        <a href="loans.php?action=add" class="btn btn-outline-success">Оформить новую выдачу</a>
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