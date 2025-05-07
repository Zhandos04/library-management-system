<?php
// Include header
include_once 'includes/header.php';

// Include database and models
require_once 'config/database.php';
require_once 'models/Book.php';
require_once 'models/Loan.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get book ID from URL
$book_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// If no ID provided, redirect to books page
if ($book_id <= 0) {
    header("Location: books.php");
    exit();
}

// Initialize book object and get details
$book = new Book($db);
$book->id = $book_id;
$found = $book->readOne();

// If book not found, redirect
if (!$found) {
    header("Location: books.php");
    exit();
}

// Get loan history for this book
$loan = new Loan($db);
$loan_history_query = "SELECT l.*, CONCAT(m.first_name, ' ', m.last_name) as member_name 
                      FROM loan_history l
                      JOIN members m ON l.member_id = m.id
                      WHERE l.book_id = ?
                      ORDER BY l.checkout_date DESC
                      LIMIT 5";
$stmt = $db->prepare($loan_history_query);
$stmt->bindParam(1, $book_id);
$stmt->execute();
$loan_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Информация о книге</h1>
        <a href="books.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i>Вернуться к книгам
        </a>
    </div>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?php echo htmlspecialchars($book->title); ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center mb-3">
                                <?php if (!empty($book->cover_image)): ?>
                                <!-- Display book cover image -->
                                <img src="<?php echo htmlspecialchars($book->cover_image); ?>" alt="Обложка книги" class="img-thumbnail" style="max-height: 250px;">
                                <?php else: ?>
                                <!-- Placeholder book cover -->
                                <div class="bg-light p-4 border rounded" style="height: 250px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-book fa-5x text-secondary"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="d-grid gap-2">
                                <?php if($auth->isLoggedIn() && $book->available_copies > 0): ?>
                                <a href="loans.php?action=checkout&book_id=<?php echo $book->id; ?>" class="btn btn-success">
                                    <i class="fas fa-book-reader me-2"></i>Взять книгу
                                </a>
                                <?php elseif($book->available_copies <= 0): ?>
                                <button class="btn btn-secondary" disabled>
                                    <i class="fas fa-times-circle me-2"></i>Нет в наличии
                                </button>
                                <?php endif; ?>
                                
                                <?php if($auth->isLibrarian()): ?>
                                <a href="books_manage.php?action=edit&id=<?php echo $book->id; ?>" class="btn btn-warning">
                                    <i class="fas fa-edit me-2"></i>Редактировать
                                </a>
                                <a href="books_manage.php?action=delete&id=<?php echo $book->id; ?>" class="btn btn-danger" onclick="return confirm('Вы уверены, что хотите удалить эту книгу?')">
                                    <i class="fas fa-trash me-2"></i>Удалить
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <table class="table">
                                <tbody>
                                    <tr>
                                        <th width="150">ISBN</th>
                                        <td><?php echo htmlspecialchars($book->isbn); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Автор</th>
                                        <td><?php echo htmlspecialchars($book->author); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Категория</th>
                                        <td><?php echo htmlspecialchars($book->category); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Издательство</th>
                                        <td><?php echo htmlspecialchars($book->publisher); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Год издания</th>
                                        <td><?php echo htmlspecialchars($book->publication_year); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Всего экземпляров</th>
                                        <td><?php echo htmlspecialchars($book->total_copies); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Доступно</th>
                                        <td>
                                            <?php if($book->available_copies > 0): ?>
                                            <span class="badge bg-success"><?php echo $book->available_copies; ?> доступно</span>
                                            <?php else: ?>
                                            <span class="badge bg-danger">Нет в наличии</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Добавлена</th>
                                        <td><?php echo date('d.m.Y', strtotime($book->created_at)); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                            
                            <?php if (!empty($book->description)): ?>
                            <div class="mt-3">
                                <h5>Описание</h5>
                                <p><?php echo nl2br(htmlspecialchars($book->description)); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <?php if($auth->isLibrarian() && count($loan_history) > 0): ?>
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">История выдачи</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <?php foreach($loan_history as $loan): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong><?php echo htmlspecialchars($loan['member_name']); ?></strong><br>
                                    <small>Выдана: <?php echo date('d.m.Y', strtotime($loan['checkout_date'])); ?></small><br>
                                    <small>Срок: <?php echo date('d.m.Y', strtotime($loan['due_date'])); ?></small>
                                </div>
                                <div>
                                    <?php if($loan['status'] == 'returned'): ?>
                                    <span class="badge bg-success">Возвращена</span><br>
                                    <small>Дата: <?php echo date('d.m.Y', strtotime($loan['return_date'])); ?></small>
                                    <?php elseif($loan['status'] == 'overdue'): ?>
                                    <span class="badge bg-danger">Просрочена</span>
                                    <?php else: ?>
                                    <span class="badge bg-warning text-dark">На руках</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card mt-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Похожие книги</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get similar books by same author or category
                    $similar_query = "SELECT id, title, author, cover_image
                                     FROM books 
                                     WHERE (author = ? OR category = ?) 
                                     AND id != ? 
                                     LIMIT 5";
                    $similar_stmt = $db->prepare($similar_query);
                    $similar_stmt->bindParam(1, $book->author);
                    $similar_stmt->bindParam(2, $book->category);
                    $similar_stmt->bindParam(3, $book->id);
                    $similar_stmt->execute();
                    $similar_books = $similar_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if(count($similar_books) > 0):
                    ?>
                    <div class="row">
                        <?php foreach($similar_books as $similar): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="text-center pt-2">
                                    <?php if (!empty($similar['cover_image'])): ?>
                                    <!-- Display book cover image -->
                                    <img src="<?php echo htmlspecialchars($similar['cover_image']); ?>" alt="Обложка книги" class="img-thumbnail" style="height: 100px;">
                                    <?php else: ?>
                                    <!-- Placeholder book cover -->
                                    <div class="bg-light border rounded mx-auto" style="height: 100px; width: 70px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-book fa-2x text-secondary"></i>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body text-center">
                                    <h6 class="card-title">
                                        <a href="book_detail.php?id=<?php echo $similar['id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($similar['title']); ?>
                                        </a>
                                    </h6>
                                    <p class="card-text small">Автор: <?php echo htmlspecialchars($similar['author']); ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">Похожих книг не найдено.</p>
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