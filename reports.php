<?php
// Включаем буферизацию вывода
ob_start();

// Include header
include_once 'includes/header.php';

// Check if user has librarian privileges
if (!$auth->isLibrarian()) {
    // Redirect to index page if not authorized
    header("Location: index.php");
    exit();
}

// Include database
require_once 'config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get report type from URL
$report_type = isset($_GET['type']) ? $_GET['type'] : 'popular_books';
$period = isset($_GET['period']) ? $_GET['period'] : '30'; // Default to 30 days

// Set date range based on period
$start_date = date('Y-m-d', strtotime("-{$period} days"));
$end_date = date('Y-m-d');

// Generate report based on type
$report_data = [];
$report_title = '';

switch ($report_type) {
    case 'popular_books':
        $report_title = 'Популярные книги';
        $query = "SELECT b.id, b.title, b.author, COUNT(l.id) as loan_count 
                 FROM books b
                 JOIN loan_history l ON b.id = l.book_id
                 WHERE l.checkout_date BETWEEN ? AND ?
                 GROUP BY b.id
                 ORDER BY loan_count DESC
                 LIMIT 10";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $start_date);
        $stmt->bindParam(2, $end_date);
        $stmt->execute();
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
    
    case 'active_members':
        $report_title = 'Активные читатели';
        $query = "SELECT m.id, m.first_name, m.last_name, m.email, COUNT(l.id) as loan_count 
                 FROM members m
                 JOIN loan_history l ON m.id = l.member_id
                 WHERE l.checkout_date BETWEEN ? AND ?
                 GROUP BY m.id
                 ORDER BY loan_count DESC
                 LIMIT 10";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $start_date);
        $stmt->bindParam(2, $end_date);
        $stmt->execute();
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
    
    case 'overdue_books':
        $report_title = 'Просроченные книги';
        $query = "SELECT l.id, b.title, b.author, m.first_name, m.last_name, m.email, 
                 l.checkout_date, l.due_date, 
                 DATEDIFF(CURRENT_DATE, l.due_date) as days_overdue
                 FROM loan_history l
                 JOIN books b ON l.book_id = b.id
                 JOIN members m ON l.member_id = m.id
                 WHERE l.status IN ('checked_out', 'overdue') 
                 AND l.due_date < CURRENT_DATE
                 ORDER BY days_overdue DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
    
    case 'fines_collected':
        $report_title = 'Собранные штрафы';
        $query = "SELECT m.id, m.first_name, m.last_name, m.email, 
                 SUM(l.fine) as total_fine, COUNT(l.id) as overdue_count
                 FROM loan_history l
                 JOIN members m ON l.member_id = m.id
                 WHERE l.fine > 0 AND l.return_date BETWEEN ? AND ?
                 GROUP BY m.id
                 ORDER BY total_fine DESC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $start_date);
        $stmt->bindParam(2, $end_date);
        $stmt->execute();
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
    
    case 'inventory':
        $report_title = 'Инвентаризация книг';
        $query = "SELECT b.id, b.isbn, b.title, b.author, b.category, 
                 b.total_copies, b.available_copies, 
                 (b.total_copies - b.available_copies) as checked_out
                 FROM books b
                 ORDER BY b.category, b.title";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
    
    case 'loan_stats':
    default:
        $report_title = 'Статистика выдач';
        // Get daily loan counts for the past period
        $query = "SELECT DATE(checkout_date) as loan_date, COUNT(*) as loan_count
                 FROM loan_history
                 WHERE checkout_date BETWEEN ? AND ?
                 GROUP BY DATE(checkout_date)
                 ORDER BY loan_date";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $start_date);
        $stmt->bindParam(2, $end_date);
        $stmt->execute();
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Отчеты библиотеки</h1>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-file-export me-2"></i>Экспорт
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="javascript:window.print();">Печать</a></li>
                <!-- Add export options if needed -->
            </ul>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-3">
            <!-- Report Selection Menu -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Типы отчетов</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="reports.php?type=popular_books" class="list-group-item list-group-item-action <?php if($report_type == 'popular_books') echo 'active'; ?>">
                            <i class="fas fa-book me-2"></i>Популярные книги
                        </a>
                        <a href="reports.php?type=active_members" class="list-group-item list-group-item-action <?php if($report_type == 'active_members') echo 'active'; ?>">
                            <i class="fas fa-users me-2"></i>Активные читатели
                        </a>
                        <a href="reports.php?type=overdue_books" class="list-group-item list-group-item-action <?php if($report_type == 'overdue_books') echo 'active'; ?>">
                            <i class="fas fa-exclamation-circle me-2"></i>Просроченные книги
                        </a>
                        <a href="reports.php?type=fines_collected" class="list-group-item list-group-item-action <?php if($report_type == 'fines_collected') echo 'active'; ?>">
                            <i class="fas fa-dollar-sign me-2"></i>Собранные штрафы
                        </a>
                        <a href="reports.php?type=inventory" class="list-group-item list-group-item-action <?php if($report_type == 'inventory') echo 'active'; ?>">
                            <i class="fas fa-warehouse me-2"></i>Инвентаризация книг
                        </a>
                        <a href="reports.php?type=loan_stats" class="list-group-item list-group-item-action <?php if($report_type == 'loan_stats') echo 'active'; ?>">
                            <i class="fas fa-chart-line me-2"></i>Статистика выдач
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Time Period Selection -->
            <?php if($report_type != 'overdue_books' && $report_type != 'inventory'): ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Период времени</h5>
                </div>
                <div class="card-body">
                    <form action="reports.php" method="GET">
                        <input type="hidden" name="type" value="<?php echo $report_type; ?>">
                        <div class="mb-3">
                            <label for="period" class="form-label">Выберите период</label>
                            <select class="form-select" id="period" name="period" onchange="this.form.submit()">
                                <option value="7" <?php if($period == '7') echo 'selected'; ?>>Последние 7 дней</option>
                                <option value="30" <?php if($period == '30') echo 'selected'; ?>>Последние 30 дней</option>
                                <option value="90" <?php if($period == '90') echo 'selected'; ?>>Последние 3 месяца</option>
                                <option value="180" <?php if($period == '180') echo 'selected'; ?>>Последние 6 месяцев</option>
                                <option value="365" <?php if($period == '365') echo 'selected'; ?>>Последний год</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Диапазон дат</label>
                            <div class="input-group">
                                <input type="text" class="form-control" value="<?php echo date('d.m.Y', strtotime($start_date)); ?>" disabled>
                                <span class="input-group-text">до</span>
                                <input type="text" class="form-control" value="<?php echo date('d.m.Y', strtotime($end_date)); ?>" disabled>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-9">
            <!-- Report Content -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?php echo $report_title; ?></h5>
                </div>
                <div class="card-body">
                    <?php if (count($report_data) > 0): ?>
                        <?php if($report_type == 'popular_books'): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Ранг</th>
                                            <th>Название</th>
                                            <th>Автор</th>
                                            <th>Выдач</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($report_data as $index => $book): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><a href="book_detail.php?id=<?php echo $book['id']; ?>"><?php echo htmlspecialchars($book['title']); ?></a></td>
                                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                                            <td><span class="badge bg-primary"><?php echo $book['loan_count']; ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php elseif($report_type == 'active_members'): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Ранг</th>
                                            <th>Читатель</th>
                                            <th>Email</th>
                                            <th>Книг взято</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($report_data as $index => $member): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><a href="member_detail.php?id=<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></a></td>
                                            <td><?php echo htmlspecialchars($member['email']); ?></td>
                                            <td><span class="badge bg-primary"><?php echo $member['loan_count']; ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php elseif($report_type == 'overdue_books'): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Название книги</th>
                                            <th>Читатель</th>
                                            <th>Срок возврата</th>
                                            <th>Дней просрочено</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($report_data as $overdue): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($overdue['title']); ?></td>
                                            <td><?php echo htmlspecialchars($overdue['first_name'] . ' ' . $overdue['last_name']); ?></td>
                                            <td><?php echo date('d.m.Y', strtotime($overdue['due_date'])); ?></td>
                                            <td><span class="badge bg-danger"><?php echo $overdue['days_overdue']; ?> дн.</span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php elseif($report_type == 'fines_collected'): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Читатель</th>
                                            <th>Email</th>
                                            <th>Просроченные книги</th>
                                            <th>Сумма штрафов</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($report_data as $fine): ?>
                                        <tr>
                                            <td><a href="member_detail.php?id=<?php echo $fine['id']; ?>"><?php echo htmlspecialchars($fine['first_name'] . ' ' . $fine['last_name']); ?></a></td>
                                            <td><?php echo htmlspecialchars($fine['email']); ?></td>
                                            <td><?php echo $fine['overdue_count']; ?></td>
                                            <td class="text-danger"><?php echo number_format($fine['total_fine'], 2); ?> р.</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php
                            // Calculate total fines
                            $total_fines = 0;
                            foreach($report_data as $fine) {
                                $total_fines += $fine['total_fine'];
                            }
                            ?>
                            <div class="card mt-3 bg-light">
                                <div class="card-body">
                                    <h5 class="card-title">Итоги</h5>
                                    <p class="card-text">Общая сумма штрафов за этот период: <strong class="text-success"><?php echo number_format($total_fines, 2); ?> р.</strong></p>
                                </div>
                            </div>
                        <?php elseif($report_type == 'inventory'): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ISBN</th>
                                            <th>Название</th>
                                            <th>Автор</th>
                                            <th>Категория</th>
                                            <th>Всего экз.</th>
                                            <th>Доступно</th>
                                            <th>На руках</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $current_category = '';
                                        foreach($report_data as $book): 
                                            // Add category header row
                                            if ($book['category'] != $current_category) {
                                                $current_category = $book['category'];
                                                echo '<tr class="table-secondary"><th colspan="7">' . ($current_category ? htmlspecialchars($current_category) : 'Без категории') . '</th></tr>';
                                            }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                                            <td><a href="book_detail.php?id=<?php echo $book['id']; ?>"><?php echo htmlspecialchars($book['title']); ?></a></td>
                                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                                            <td><?php echo htmlspecialchars($book['category']); ?></td>
                                            <td><?php echo $book['total_copies']; ?></td>
                                            <td>
                                                <?php if($book['available_copies'] > 0): ?>
                                                <span class="badge bg-success"><?php echo $book['available_copies']; ?></span>
                                                <?php else: ?>
                                                <span class="badge bg-danger">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($book['checked_out'] > 0): ?>
                                                <span class="badge bg-warning text-dark"><?php echo $book['checked_out']; ?></span>
                                                <?php else: ?>
                                                <span class="badge bg-secondary">0</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php
                            // Calculate inventory summary
                            $total_books = count($report_data);
                            $total_copies = array_sum(array_column($report_data, 'total_copies'));
                            $available_copies = array_sum(array_column($report_data, 'available_copies'));
                            $checked_out_copies = array_sum(array_column($report_data, 'checked_out'));
                            $availability_rate = ($total_copies > 0) ? ($available_copies / $total_copies * 100) : 0;
                            ?>
                            <div class="card mt-3 bg-light">
                                <div class="card-body">
                                    <h5 class="card-title">Сводка по инвентаризации</h5>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <p class="card-text">Всего книг: <strong><?php echo $total_books; ?></strong></p>
                                        </div>
                                        <div class="col-md-3">
                                            <p class="card-text">Всего экземпляров: <strong><?php echo $total_copies; ?></strong></p>
                                        </div>
                                        <div class="col-md-3">
                                            <p class="card-text">Доступно: <strong><?php echo $available_copies; ?></strong></p>
                                        </div>
                                        <div class="col-md-3">
                                            <p class="card-text">На руках: <strong><?php echo $checked_out_copies; ?></strong></p>
                                        </div>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $availability_rate; ?>%" aria-valuenow="<?php echo $availability_rate; ?>" aria-valuemin="0" aria-valuemax="100">
                                            <?php echo round($availability_rate); ?>% доступно
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Loan Statistics -->
                            <div id="loan-chart" style="height: 300px;"></div>
                            
                            <div class="table-responsive mt-4">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Дата</th>
                                            <th>Выдано книг</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_loans = 0;
                                        foreach($report_data as $stat): 
                                            $total_loans += $stat['loan_count'];
                                        ?>
                                        <tr>
                                            <td><?php echo date('d.m.Y', strtotime($stat['loan_date'])); ?></td>
                                            <td><span class="badge bg-primary"><?php echo $stat['loan_count']; ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="card mt-3 bg-light">
                                <div class="card-body">
                                    <h5 class="card-title">Итоги</h5>
                                    <p class="card-text">Всего выдач за период: <strong><?php echo $total_loans; ?></strong></p>
                                    <p class="card-text">Среднее количество выдач в день: <strong><?php echo round($total_loans / count($report_data), 2); ?></strong></p>
                                </div>
                            </div>
                            
                            <!-- JavaScript for Chart -->
                            <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    var options = {
                                        series: [{
                                            name: 'Выдано книг',
                                            data: [
                                                <?php 
                                                foreach($report_data as $stat) {
                                                    echo $stat['loan_count'] . ', ';
                                                }
                                                ?>
                                            ]
                                        }],
                                        chart: {
                                            height: 300,
                                            type: 'line',
                                            zoom: {
                                                enabled: true
                                            }
                                        },
                                        dataLabels: {
                                            enabled: false
                                        },
                                        stroke: {
                                            curve: 'straight'
                                        },
                                        grid: {
                                            row: {
                                                colors: ['#f3f3f3', 'transparent'],
                                                opacity: 0.5
                                            },
                                        },
                                        xaxis: {
                                            categories: [
                                                <?php 
                                                foreach($report_data as $stat) {
                                                    echo "'" . date('d.m', strtotime($stat['loan_date'])) . "', ";
                                                }
                                                ?>
                                            ],
                                        },
                                        colors: ['#0d6efd']
                                    };

                                    var chart = new ApexCharts(document.querySelector("#loan-chart"), options);
                                    chart.render();
                                });
                            </script>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <h4 class="alert-heading">Нет доступных данных!</h4>
                            <p>Для данного отчета в выбранный период времени нет данных.</p>
                            <?php if($report_type != 'overdue_books' && $report_type != 'inventory'): ?>
                            <hr>
                            <p class="mb-0">Попробуйте выбрать другой период времени.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';

// Выводим буфер и завершаем скрипт
ob_end_flush();
?>