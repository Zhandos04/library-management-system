<?php
// Include header
include_once 'includes/header.php';

// Check if user has librarian privileges
if (!$auth->isLibrarian()) {
    // Redirect to books page if not authorized
    header("Location: index.php");
    exit();
}

// Include database and member model
require_once 'config/database.php';
require_once 'models/Member.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Initialize member object
$member = new Member($db);

// Page parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get members based on search or all
if (!empty($search)) {
    $stmt = $member->search($search);
    $total_rows = $stmt->rowCount();
} else {
    $stmt = $member->readAll($page, $records_per_page);
    $total_rows = $member->countAll();
}

// Calculate total pages
$total_pages = ceil($total_rows / $records_per_page);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Читатели библиотеки</h1>
        <a href="members.php?action=add" class="btn btn-success">
            <i class="fas fa-plus-circle me-2"></i>Добавить читателя
        </a>
    </div>
    
    <!-- Search Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="members.php" method="GET" class="search-form">
                <div class="input-group">
                    <input type="text" class="form-control" name="search" placeholder="Поиск по имени или email" value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search me-2"></i>Поиск
                    </button>
                    <?php if(!empty($search)): ?>
                    <a href="members.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-2"></i>Очистить
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <?php if($stmt->rowCount() > 0): ?>
    
    <!-- Members Table -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <?php if(!empty($search)): ?>
            <h5 class="mb-0">Результаты поиска: "<?php echo htmlspecialchars($search); ?>" (найдено <?php echo $total_rows; ?> результатов)</h5>
            <?php else: ?>
            <h5 class="mb-0">Все читатели (всего <?php echo $total_rows; ?>)</h5>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ФИО</th>
                            <th>Email</th>
                            <th>Телефон</th>
                            <th>Дата регистрации</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['first_name']) . ' ' . htmlspecialchars($row['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['phone']); ?></td>
                            <td><?php echo date('d.m.Y', strtotime($row['membership_date'])); ?></td>
                            <td>
                                <?php
                                $status_class = '';
                                switch($row['membership_status']) {
                                    case 'active':
                                        $status_class = 'bg-success';
                                        $status_text = 'Активен';
                                        break;
                                    case 'expired':
                                        $status_class = 'bg-danger';
                                        $status_text = 'Истёк';
                                        break;
                                    case 'suspended':
                                        $status_class = 'bg-warning text-dark';
                                        $status_text = 'Приостановлен';
                                        break;
                                    default:
                                        $status_class = 'bg-secondary';
                                        $status_text = ucfirst($row['membership_status']);
                                }
                                ?>
                                <span class="badge <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td>
                                <a href="member_detail.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-info-circle"></i>
                                </a>
                                <a href="members.php?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="loans.php?action=add&member_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success">
                                    <i class="fas fa-book-reader"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if($total_pages > 1 && empty($search)): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php if($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="members.php?page=1">Первая</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="members.php?page=<?php echo $page-1; ?>">Предыдущая</a>
                    </li>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                    <li class="page-item <?php if($i == $page) echo 'active'; ?>">
                        <a class="page-link" href="members.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="members.php?page=<?php echo $page+1; ?>">Следующая</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="members.php?page=<?php echo $total_pages; ?>">Последняя</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
            
        </div>
    </div>
    
    <?php else: ?>
    <div class="alert alert-info">
        <?php if(!empty($search)): ?>
        <h4 class="alert-heading">Результатов не найдено!</h4>
        <p>Ваш поиск "<?php echo htmlspecialchars($search); ?>" не дал результатов в нашей базе данных.</p>
        <hr>
        <p class="mb-0">Попробуйте другие ключевые слова или <a href="members.php" class="alert-link">просмотрите всех читателей</a>.</p>
        <?php else: ?>
        <h4 class="alert-heading">Нет читателей!</h4>
        <p>В базе данных пока нет ни одного читателя.</p>
        <hr>
        <p class="mb-0">Начните с <a href="members.php?action=add" class="alert-link">добавления нового читателя</a>.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>