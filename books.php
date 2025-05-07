<?php
// Include header
include_once 'includes/header.php';

// Include database and book model
require_once 'config/database.php';
require_once 'models/Book.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Initialize book object
$book = new Book($db);

// Page parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$view = isset($_GET['view']) ? trim($_GET['view']) : 'list'; // list or grid view

// Get books based on search or all
if (!empty($search)) {
    $stmt = $book->search($search);
    $total_rows = $stmt->rowCount();
} else {
    $stmt = $book->readAll($page, $records_per_page);
    $total_rows = $book->countAll();
}

// Calculate total pages
$total_pages = ceil($total_rows / $records_per_page);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Каталог книг</h1>
        <div>
            <div class="btn-group me-2" role="group">
                <a href="?view=list<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-outline-secondary <?php echo $view == 'list' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i>
                </a>
                <a href="?view=grid<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-outline-secondary <?php echo $view == 'grid' ? 'active' : ''; ?>">
                    <i class="fas fa-th-large"></i>
                </a>
            </div>
            
            <?php if($auth->isLibrarian()): ?>
            <a href="books_manage.php?action=add" class="btn btn-success">
                <i class="fas fa-plus-circle me-2"></i>Добавить новую книгу
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Search Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="books.php" method="GET" class="search-form">
                <input type="hidden" name="view" value="<?php echo $view; ?>">
                <div class="input-group">
                    <input type="text" class="form-control" name="search" placeholder="Поиск по названию или автору" value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search me-2"></i>Поиск
                    </button>
                    <?php if(!empty($search)): ?>
                    <a href="books.php?view=<?php echo $view; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-2"></i>Очистить
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <?php if($stmt->rowCount() > 0): ?>
    
    <?php if($view == 'grid'): ?>
    <!-- Grid View for Books -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <?php if(!empty($search)): ?>
            <h5 class="mb-0">Результаты поиска: "<?php echo htmlspecialchars($search); ?>" (найдено <?php echo $total_rows; ?> результатов)</h5>
            <?php else: ?>
            <h5 class="mb-0">Все книги (всего <?php echo $total_rows; ?>)</h5>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="row">
                <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                <div class="col-md-3 mb-4">
                    <div class="card h-100">
                        <div class="text-center pt-3">
                            <?php if (!empty($row['cover_image'])): ?>
                            <img src="<?php echo htmlspecialchars($row['cover_image']); ?>" alt="Обложка книги" class="img-thumbnail" style="height: 150px;">
                            <?php else: ?>
                            <div class="bg-light border rounded mx-auto" style="height: 150px; width: 100px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-book fa-3x text-secondary"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">
                                <a href="book_detail.php?id=<?php echo $row['id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($row['title']); ?>
                                </a>
                            </h5>
                            <p class="card-text">Автор: <?php echo htmlspecialchars($row['author']); ?></p>
                            <p class="card-text">
                                <small class="text-muted"><?php echo htmlspecialchars($row['category']); ?> (<?php echo htmlspecialchars($row['publication_year']); ?>)</small>
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <?php if($row['available_copies'] > 0): ?>
                                <span class="badge bg-success"><?php echo $row['available_copies']; ?> доступно</span>
                                <?php else: ?>
                                <span class="badge bg-danger">Нет в наличии</span>
                                <?php endif; ?>
                                
                                <div>
                                    <?php if($auth->isLoggedIn() && $row['available_copies'] > 0): ?>
                                    <a href="loans.php?action=checkout&book_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-book-reader"></i>
                                    </a>
                                    <?php endif; ?>
                                    <a href="book_detail.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-info-circle"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            
            <!-- Pagination -->
            <?php if($total_pages > 1 && empty($search)): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="books.php?view=<?php echo $view; ?>&page=1">Первая</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="books.php?view=<?php echo $view; ?>&page=<?php echo $page-1; ?>">Предыдущая</a>
                    </li>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                    <li class="page-item <?php if($i == $page) echo 'active'; ?>">
                        <a class="page-link" href="books.php?view=<?php echo $view; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="books.php?view=<?php echo $view; ?>&page=<?php echo $page+1; ?>">Следующая</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="books.php?view=<?php echo $view; ?>&page=<?php echo $total_pages; ?>">Последняя</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <!-- List View for Books (Original Table) -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <?php if(!empty($search)): ?>
            <h5 class="mb-0">Результаты поиска: "<?php echo htmlspecialchars($search); ?>" (найдено <?php echo $total_rows; ?> результатов)</h5>
            <?php else: ?>
            <h5 class="mb-0">Все книги (всего <?php echo $total_rows; ?>)</h5>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Обложка</th>
                            <th>Название</th>
                            <th>Автор</th>
                            <th>Категория</th>
                            <th>Год издания</th>
                            <th>Доступно</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <td>
                                <?php if (!empty($row['cover_image'])): ?>
                                <img src="<?php echo htmlspecialchars($row['cover_image']); ?>" alt="Обложка книги" class="img-thumbnail" style="height: 50px;">
                                <?php else: ?>
                                <div class="bg-light border rounded" style="height: 50px; width: 35px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-book text-secondary"></i>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="book_detail.php?id=<?php echo $row['id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($row['title']); ?>
                                </a>
                                <?php if (!empty($row['description'])): ?>
                                <br><small class="text-muted"><?php echo substr(htmlspecialchars($row['description']), 0, 50) . '...'; ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['author']); ?></td>
                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                            <td><?php echo htmlspecialchars($row['publication_year']); ?></td>
                            <td>
                                <?php if($row['available_copies'] > 0): ?>
                                <span class="badge bg-success"><?php echo $row['available_copies']; ?> из <?php echo $row['total_copies']; ?></span>
                                <?php else: ?>
                                <span class="badge bg-danger">Нет в наличии</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="book_detail.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-info-circle"></i>
                                </a>
                                <?php if($auth->isLibrarian()): ?>
                                <a href="books_manage.php?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="books_manage.php?action=delete&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Вы уверены, что хотите удалить эту книгу?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                                <?php if($auth->isLoggedIn() && $row['available_copies'] > 0): ?>
                                <a href="loans.php?action=checkout&book_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success">
                                    <i class="fas fa-book-reader"></i>
                                </a>
                                <?php endif; ?>
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
                        <a class="page-link" href="books.php?view=<?php echo $view; ?>&page=1">Первая</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="books.php?view=<?php echo $view; ?>&page=<?php echo $page-1; ?>">Предыдущая</a>
                    </li>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                    <li class="page-item <?php if($i == $page) echo 'active'; ?>">
                        <a class="page-link" href="books.php?view=<?php echo $view; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="books.php?view=<?php echo $view; ?>&page=<?php echo $page+1; ?>">Следующая</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="books.php?view=<?php echo $view; ?>&page=<?php echo $total_pages; ?>">Последняя</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
            
        </div>
    </div>
    <?php endif; ?>
    
    <?php else: ?>
    <div class="alert alert-info">
        <?php if(!empty($search)): ?>
        <h4 class="alert-heading">Результатов не найдено!</h4>
        <p>Ваш поиск "<?php echo htmlspecialchars($search); ?>" не дал результатов в нашем каталоге.</p>
        <hr>
        <p class="mb-0">Попробуйте другие ключевые слова или <a href="books.php" class="alert-link">просмотрите все книги</a>.</p>
        <?php else: ?>
        <h4 class="alert-heading">Книги отсутствуют!</h4>
        <p>В каталоге пока нет книг.</p>
        <?php if($auth->isLibrarian()): ?>
        <hr>
        <p class="mb-0">Начните с <a href="books_manage.php?action=add" class="alert-link">добавления новой книги</a>.</p>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>