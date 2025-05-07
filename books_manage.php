<?php
// Включаем буферизацию вывода
ob_start();

// Include header
include_once 'includes/header.php';

// Check if user has librarian privileges
if (!$auth->isLibrarian()) {
    // Redirect to books page if not authorized
    header("Location: books.php");
    exit();
}

// Include database and required models/helpers
require_once 'config/database.php';
require_once 'models/Book.php';
require_once 'helpers/S3Helper.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Initialize book object
$book = new Book($db);

// Initialize S3 helper
$s3Helper = new S3Helper();

// Get action and book ID from URL
$action = isset($_GET['action']) ? $_GET['action'] : '';
$book_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Variables for form data
$isbn = '';
$title = '';
$author = '';
$category = '';
$publication_year = '';
$publisher = '';
$total_copies = 1;
$available_copies = 1;
$description = '';
$cover_image = '';
$generate_description = false;

// Process based on action
if ($action == 'edit' && $book_id > 0) {
    // Get book details for editing
    $book->id = $book_id;
    if ($book->readOne()) {
        $isbn = $book->isbn;
        $title = $book->title;
        $author = $book->author;
        $category = $book->category;
        $publication_year = $book->publication_year;
        $publisher = $book->publisher;
        $total_copies = $book->total_copies;
        $available_copies = $book->available_copies;
        $description = $book->description;
        $cover_image = $book->cover_image;
    } else {
        // Book not found, redirect
        header("Location: books.php");
        exit();
    }
} elseif ($action == 'delete' && $book_id > 0) {
    // Delete book
    $book->id = $book_id;
    
    // Get book details first to find the cover image
    if ($book->readOne() && !empty($book->cover_image)) {
        // Delete cover image from S3 if exists
        $s3Helper->deleteImage($book->cover_image);
    }
    
    if ($book->delete()) {
        header("Location: books.php?deleted=1");
    } else {
        header("Location: books.php?deleted=0");
    }
    exit();
}

// Process form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize and set book properties
    $book->isbn = trim($_POST['isbn']);
    $book->title = trim($_POST['title']);
    $book->author = trim($_POST['author']);
    $book->category = trim($_POST['category']);
    $book->publication_year = (int)trim($_POST['publication_year']);
    $book->publisher = trim($_POST['publisher']);
    $book->total_copies = (int)trim($_POST['total_copies']);
    $book->description = trim($_POST['description']);
    $book->cover_image = $cover_image; // Keep existing image URL by default
    
    // Check if description should be generated
    $generate_description = isset($_POST['generate_description']) && $_POST['generate_description'] == 1;
    
    // Process cover image upload if provided
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === 0) {
        // Upload image to S3
        $imageUrl = $s3Helper->uploadImage($_FILES['cover_image'], 'book-covers');
        
        if ($imageUrl) {
            // Delete old image if updating
            if ($action == 'edit' && !empty($cover_image)) {
                $s3Helper->deleteImage($cover_image);
            }
            
            $book->cover_image = $imageUrl;
        } else {
            $error = "Не удалось загрузить изображение обложки. Проверьте формат файла и размер.";
        }
    }
    
    // For new books, available copies = total copies
    if ($action == 'add') {
        $book->available_copies = $book->total_copies;
    } else {
        // For existing books, calculate the change
        $original_total = (int)$total_copies;
        $new_total = $book->total_copies;
        $original_available = (int)$available_copies;
        
        // Adjust available copies proportionally
        if ($original_total != $new_total) {
            $difference = $new_total - $original_total;
            $book->available_copies = max(0, $original_available + $difference);
        } else {
            $book->available_copies = $original_available;
        }
    }
    
    // Generate description using AI if requested
    if ($generate_description) {
        if ($book->generateDescription()) {
            $description = $book->description; // Update variable for display
        } else {
            $error .= "Не удалось сгенерировать описание книги. Используется существующее. ";
        }
    }
    
    // Validate data
    if (empty($book->isbn) || empty($book->title) || empty($book->author)) {
        $error .= "ISBN, Название и Автор - обязательные поля.";
    } else {
        if ($action == 'add') {
            // Create book
            if ($book->create()) {
                $success = "Книга успешно добавлена.";
                // Clear form data
                $isbn = $title = $author = $category = $publication_year = $publisher = $description = $cover_image = '';
                $total_copies = $available_copies = 1;
            } else {
                $error = "Не удалось добавить книгу. Возможно, ISBN уже существует.";
            }
        } elseif ($action == 'edit') {
            // Update book
            $book->id = $book_id;
            if ($book->update()) {
                $success = "Книга успешно обновлена.";
            } else {
                $error = "Не удалось обновить книгу. Возможно, ISBN уже существует.";
            }
        }
    }
    
    // Preserve form data in case of error
    if (!empty($error)) {
        $isbn = $book->isbn;
        $title = $book->title;
        $author = $book->author;
        $category = $book->category;
        $publication_year = $book->publication_year;
        $publisher = $book->publisher;
        $total_copies = $book->total_copies;
        $available_copies = $book->available_copies;
        $description = $book->description;
        $cover_image = $book->cover_image;
    }
}

// Page title based on action
$page_title = ($action == 'edit') ? 'Редактирование книги' : 'Добавление новой книги';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo $page_title; ?></h1>
        <a href="books.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i>Вернуться к книгам
        </a>
    </div>
    
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><?php echo $page_title; ?></h5>
        </div>
        <div class="card-body">
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?action=$action&id=$book_id"); ?>" method="post" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-8">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="isbn" class="form-label">ISBN*</label>
                                <input type="text" class="form-control" id="isbn" name="isbn" value="<?php echo htmlspecialchars($isbn); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="publication_year" class="form-label">Год издания</label>
                                <input type="number" class="form-control" id="publication_year" name="publication_year" min="1000" max="<?php echo date('Y'); ?>" value="<?php echo htmlspecialchars($publication_year); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Название*</label>
                            <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="author" class="form-label">Автор*</label>
                            <input type="text" class="form-control" id="author" name="author" value="<?php echo htmlspecialchars($author); ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="category" class="form-label">Категория</label>
                                <input type="text" class="form-control" id="category" name="category" value="<?php echo htmlspecialchars($category); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="publisher" class="form-label">Издательство</label>
                                <input type="text" class="form-control" id="publisher" name="publisher" value="<?php echo htmlspecialchars($publisher); ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="total_copies" class="form-label">Всего экземпляров*</label>
                                <input type="number" class="form-control" id="total_copies" name="total_copies" min="1" value="<?php echo htmlspecialchars($total_copies); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="available_copies" class="form-label">Доступно экземпляров</label>
                                <input type="number" class="form-control" id="available_copies" value="<?php echo htmlspecialchars($available_copies); ?>" disabled>
                                <small class="text-muted">Будет рассчитано автоматически</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Описание книги</label>
                            <div class="input-group mb-2">
                                <textarea class="form-control" id="description" name="description" rows="5"><?php echo htmlspecialchars($description); ?></textarea>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="generate_description" name="generate_description" value="1">
                                <label class="form-check-label" for="generate_description">
                                    Сгенерировать описание с помощью Gemini AI
                                </label>
                            </div>
                            <small class="text-muted">Опишите книгу или отметьте чекбокс для автоматической генерации описания</small>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="cover_image" class="form-label">Обложка книги</label>
                            <input type="file" class="form-control" id="cover_image" name="cover_image" accept="image/jpeg, image/png, image/jpg">
                            
                            <?php if (!empty($cover_image)): ?>
                            <div class="mt-2 text-center">
                                <p>Текущая обложка:</p>
                                <img src="<?php echo htmlspecialchars($cover_image); ?>" alt="Обложка книги" class="img-thumbnail" style="max-height: 200px;">
                            </div>
                            <?php else: ?>
                            <div class="mt-2 text-center">
                                <div class="bg-light p-4 border rounded" style="height: 200px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-book fa-5x text-secondary"></i>
                                </div>
                                <small class="text-muted">Нет загруженной обложки</small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i><?php echo ($action == 'edit') ? 'Обновить книгу' : 'Добавить книгу'; ?>
                    </button>
                    <a href="books.php" class="btn btn-outline-secondary">Отмена</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';

// Выводим буфер и завершаем скрипт
ob_end_flush();
?>