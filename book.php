<?php
// ===== إعدادات الأخطاء =====
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');

session_start();

// ===== الاتصال بقاعدة البيانات عبر ملف الإعدادات =====
if (!file_exists(__DIR__ . '/config.php')) {
    die("ملف الإعدادات غير موجود.");
}
$config = require __DIR__ . '/config.php';

$conn = new mysqli($config['DB_HOST'], $config['DB_USER'], $config['DB_PASS'], $config['DB_NAME']);
if ($conn->connect_error) {
    error_log('DB connection failed: ' . $conn->connect_error);
    die("فشل الاتصال بقاعدة البيانات.");
}
$conn->set_charset("utf8mb4");

// ✅ إنشاء جدول حالة القراءة
$conn->query("CREATE TABLE IF NOT EXISTS reading_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    status ENUM('read', 'reading', 'want_to_read', 'dnf') NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_book (user_id, book_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ✅ إنشاء جدول الاقتباسات
$conn->query("CREATE TABLE IF NOT EXISTS book_quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    quote_text TEXT NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ✅ معالج تحديث حالة القراءة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_reading_status'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'يجب تسجيل الدخول']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $book_id = intval($_POST['book_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    
    if ($book_id <= 0 || !in_array($status, ['read', 'reading', 'want_to_read', 'dnf'])) {
        echo json_encode(['status' => 'error', 'message' => 'بيانات غير صحيحة']);
        exit;
    }
    
    $stmt = $conn->prepare("INSERT INTO reading_status (user_id, book_id, status) VALUES (?, ?, ?) 
                            ON DUPLICATE KEY UPDATE status = ?, updated_at = CURRENT_TIMESTAMP");
    $stmt->bind_param("iiss", $user_id, $book_id, $status, $status);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'تم تحديث الحالة']);
    } else {
        error_log('Reading status error: ' . $stmt->error);
        echo json_encode(['status' => 'error', 'message' => 'حدث خطأ، حاول لاحقاً']);
    }
    $stmt->close();
    exit;
}

// ✅ معالج إزالة حالة القراءة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_reading_status'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'يجب تسجيل الدخول']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $book_id = intval($_POST['book_id'] ?? 0);
    
    $stmt = $conn->prepare("DELETE FROM reading_status WHERE user_id = ? AND book_id = ?");
    $stmt->bind_param("ii", $user_id, $book_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'تم إزالة الحالة']);
    } else {
        error_log('Remove reading status error: ' . $stmt->error);
        echo json_encode(['status' => 'error', 'message' => 'حدث خطأ، حاول لاحقاً']);
    }
    $stmt->close();
    exit;
}

// معالجة الاقتباسات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_quote'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'يجب تسجيل الدخول']);
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $quote_text = trim($_POST['quote_text'] ?? '');
    $quote_comment = trim($_POST['quote_comment'] ?? '');
    $book_id_post = intval($_POST['book_id'] ?? 0);
    if (empty($quote_text) || $book_id_post <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'جميع الحقول مطلوبة']);
        exit;
    }
    $stmt = $conn->prepare("INSERT INTO book_quotes (user_id, book_id, quote_text, comment) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $user_id, $book_id_post, $quote_text, $quote_comment);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'تمت إضافة الاقتباس']);
    } else {
        error_log('Add quote error: ' . $stmt->error);
        echo json_encode(['status' => 'error', 'message' => 'حدث خطأ، حاول لاحقاً']);
    }
    $stmt->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_quote'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['user_id'])) { die(json_encode(['status' => 'error', 'message' => 'يجب تسجيل الدخول'])); }
    $user_id = $_SESSION['user_id'];
    $quote_id = intval($_POST['quote_id'] ?? 0);
    $quote_text = trim($_POST['quote_text'] ?? '');
    $quote_comment = trim($_POST['quote_comment'] ?? '');
    if (empty($quote_text) || $quote_id <= 0) { die(json_encode(['status' => 'error', 'message' => 'جميع الحقول مطلوبة'])); }
    $check = $conn->prepare("SELECT id FROM book_quotes WHERE id = ? AND user_id = ?");
    $check->bind_param("ii", $quote_id, $user_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) { die(json_encode(['status' => 'error', 'message' => 'لا تملك صلاحية'])); }
    $check->close();
    $stmt = $conn->prepare("UPDATE book_quotes SET quote_text = ?, comment = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ssii", $quote_text, $quote_comment, $quote_id, $user_id);
    $ok = $stmt->execute();
    if (!$ok) error_log('Edit quote error: ' . $stmt->error);
    echo json_encode($ok ? ['status' => 'success', 'message' => 'تم التعديل'] : ['status' => 'error', 'message' => 'حدث خطأ']);
    $stmt->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_quote'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['user_id'])) { die(json_encode(['status' => 'error', 'message' => 'يجب تسجيل الدخول'])); }
    $user_id = $_SESSION['user_id'];
    $quote_id = intval($_POST['quote_id'] ?? 0);
    $check = $conn->prepare("SELECT id FROM book_quotes WHERE id = ? AND user_id = ?");
    $check->bind_param("ii", $quote_id, $user_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) { die(json_encode(['status' => 'error', 'message' => 'لا تملك صلاحية'])); }
    $check->close();
    $stmt = $conn->prepare("DELETE FROM book_quotes WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $quote_id, $user_id);
    $ok = $stmt->execute();
    if (!$ok) error_log('Delete quote error: ' . $stmt->error);
    echo json_encode($ok ? ['status' => 'success', 'message' => 'تم الحذف'] : ['status' => 'error', 'message' => 'حدث خطأ']);
    $stmt->close();
    exit;
}

// ===== دوال مساعدة =====
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function present($v): bool { return isset($v) && trim((string)$v) !== ''; }
function col_exists(mysqli $conn, string $table, string $column): bool {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && $res->num_rows > 0;
}

// ===== جلب بيانات الكتاب =====
$book_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($book_id <= 0) { die("معرّف الكتاب غير صحيح."); }

$stmt_book = $conn->prepare("SELECT * FROM novels WHERE ID = ? LIMIT 1");
$stmt_book->bind_param("i", $book_id);
$stmt_book->execute();
$book_q = $stmt_book->get_result();
$book = $book_q ? $book_q->fetch_assoc() : null;
$stmt_book->close();
if (!$book) { die("الكتاب غير موجود."); }

// ===== حالة القراءة للجميع =====
$reading_status = null;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    $status_check = $conn->prepare("SELECT status FROM reading_status WHERE user_id = ? AND book_id = ?");
    $status_check->bind_param("ii", $user_id, $book_id);
    $status_check->execute();
    $status_result = $status_check->get_result();
    if ($status_result->num_rows > 0) {
        $reading_status = $status_result->fetch_assoc()['status'];
    }
    $status_check->close();
}

// ===== المفضلة والمحفوظات =====
$is_favorited = false;
$is_saved = false;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $check_fav = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND book_id = ?");
    $check_fav->bind_param("ii", $user_id, $book_id);
    $check_fav->execute();
    $is_favorited = $check_fav->get_result()->num_rows > 0;
    $check_fav->close();
    
    $check_saved = $conn->prepare("SELECT id FROM saved_novels WHERE user_id = ? AND book_id = ?");
    $check_saved->bind_param("ii", $user_id, $book_id);
    $check_saved->execute();
    $is_saved = $check_saved->get_result()->num_rows > 0;
    $check_saved->close();
}

// ===== التقييمات =====
$reviews_count = 0; $reviews_avg = 0;
$stmt_rev = $conn->prepare("SELECT AVG(rating) AS avg_r, COUNT(*) AS total FROM reviews WHERE book_id = ?");
$stmt_rev->bind_param("i", $book_id);
$stmt_rev->execute();
$reviews_q = $stmt_rev->get_result();
if ($reviews_q) {
    $reviews_stats = $reviews_q->fetch_assoc();
    $reviews_avg = (float)($reviews_stats['avg_r'] ?? 0);
    $reviews_count = (int)($reviews_stats['total'] ?? 0);
}
$stmt_rev->close();

$initial_rating = present($book['initial_rating'] ?? null) ? (float)$book['initial_rating'] : 0;
$initial_reviews_count = present($book['initial_reviews_count'] ?? null) ? (int)$book['initial_reviews_count'] : 0;
$total_reviews = $reviews_count + $initial_reviews_count;
$avg_rating = $total_reviews > 0 
    ? round((($reviews_avg * $reviews_count) + ($initial_rating * $initial_reviews_count)) / $total_reviews, 1)
    : round($initial_rating, 1);

// ===== متغيرات الكتاب =====
$cover = present($book['cover_image'] ?? null) ? $book['cover_image'] : 'https://via.placeholder.com/900x1200?text=Book+Cover';

$link = $book['link'] ?? '';
$read_link = $book['read_link'] ?? '';
$audio_link = $book['audio_link'] ?? '';
$download_link = $book['download_link'] ?? '';
$buy_link = $book['buy_link'] ?? '';

$text_summary = $book['text_summary'] ?? ($book['summary'] ?? '');
$pages_count = present($book['pages_count'] ?? null) ? $book['pages_count'] : ($book['pages'] ?? '');
$publisher = $book['publisher'] ?? '';
$publish_date = $book['publish_date'] ?? '';
$isbn = $book['isbn'] ?? '';
$description = $book['description'] ?? '';
$author = $book['author'] ?? '';
$title = $book['title'] ?? '';
$category = $book['category'] ?? '';

// ===== تفاصيل الكتاب =====
$details = [];
if (present($author)) $details[] = ['اسم الكاتب', $author];
if (present($pages_count)) $details[] = ['عدد الصفحات', $pages_count . ' صفحة'];
if (present($category)) $details[] = ['التصنيف', $category];
if (present($publisher)) $details[] = ['الناشر', $publisher];
if (present($publish_date)) $details[] = ['تاريخ النشر', $publish_date];
if (present($isbn)) $details[] = ['ISBN', $isbn];
if (present($book['price'] ?? null)) $details[] = ['السعر', $book['price'] . ' $'];

// ===== صورة ونبذة الكاتب =====
$author_image = (col_exists($conn, 'novels', 'author_image') && present($book['author_image'] ?? null)) ? $book['author_image'] : '';
$author_bio = (col_exists($conn, 'novels', 'author_bio') && present($book['author_bio'] ?? null)) ? $book['author_bio'] : '';

// ===== كتب لنفس المؤلف =====
$author_books = [];
if (present($author)) {
    $stmt_author = $conn->prepare("SELECT ID, title, author, cover_image FROM novels WHERE author = ? AND ID != ? LIMIT 4");
    if ($stmt_author) {
        $stmt_author->bind_param("si", $author, $book_id);
        $stmt_author->execute();
        $result = $stmt_author->get_result();
        while ($row = $result->fetch_assoc()) $author_books[] = $row;
        $stmt_author->close();
    }
}

// ===== كتب مشابهة =====
$similar_books = [];
if (present($category)) {
    $stmt_similar = $conn->prepare("SELECT ID, title, author, cover_image FROM novels WHERE category = ? AND ID != ? LIMIT 4");
    if ($stmt_similar) {
        $stmt_similar->bind_param("si", $category, $book_id);
        $stmt_similar->execute();
        $result = $stmt_similar->get_result();
        while ($row = $result->fetch_assoc()) $similar_books[] = $row;
        $stmt_similar->close();
    }
}

// ===== كتب مقترحة عشوائية =====
$related_books = [];
$stmt_random = $conn->prepare("SELECT ID, title, author, cover_image FROM novels WHERE ID != ? ORDER BY RAND() LIMIT 6");
if ($stmt_random) {
    $stmt_random->bind_param("i", $book_id);
    $stmt_random->execute();
    $result = $stmt_random->get_result();
    while ($row = $result->fetch_assoc()) $related_books[] = $row;
    $stmt_random->close();
}

// ===== اقتباسات الأدمن =====
$book_quotes = [];
if (col_exists($conn, 'novels', 'quotes') && present($book['quotes'] ?? null)) {
    $raw = preg_split('/\n\s*\n|--/', $book['quotes']);
    $book_quotes = array_filter($raw, function($q) { return !empty(trim($q)); });
}

// ===== اقتباسات المستخدمين =====
$user_quotes = [];
$table_check = $conn->query("SHOW TABLES LIKE 'book_quotes'");
if ($table_check && $table_check->num_rows > 0) {
    $q_stmt = $conn->prepare("SELECT bq.*, u.username FROM book_quotes bq LEFT JOIN users u ON bq.user_id = u.id WHERE bq.book_id = ? ORDER BY bq.created_at DESC");
    if ($q_stmt) {
        $q_stmt->bind_param("i", $book_id);
        $q_stmt->execute();
        $result = $q_stmt->get_result();
        while ($row = $result->fetch_assoc()) $user_quotes[] = $row;
        $q_stmt->close();
    }
}

// ===== أسماء حالات القراءة =====
$status_labels = [
    'reading' => 'يقرأه الآن',
    'want_to_read' => 'سيقرأه',
    'read' => 'قرأه',
    'dnf' => 'لم يكمله'
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title><?php echo h($title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root{--bg:#f5f7fb;--card:#ffffff;--text:#111827;--muted:#6b7280;--border:#e5e7eb;--primary:#009688;--gold:#f59e0b;--shadow:0 18px 50px rgba(15,23,42,.08);--radius:24px}
        *{box-sizing:border-box}html,body{margin:0;padding:0}body{font-family:"Cairo",sans-serif;background:var(--bg);color:var(--text);line-height:1.75;-webkit-font-smoothing:antialiased;padding-bottom:20px}a{color:inherit;text-decoration:none}
        .page{max-width:980px;margin:0 auto;padding:14px 14px 28px}
        .topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;gap:10px}
        .brand{display:flex;align-items:center;gap:10px;font-weight:900;font-size:18px}
        .brand .dot{width:12px;height:12px;border-radius:50%;background:var(--primary);box-shadow:0 0 0 6px rgba(0,150,136,.08)}
        .icon-btn{border:none;background:#fff;width:42px;height:42px;border-radius:14px;box-shadow:0 8px 24px rgba(15,23,42,.06);display:grid;place-items:center;cursor:pointer;color:var(--primary);text-decoration:none}
        .topbar-actions{display:flex;gap:8px}
        .back-category-bar{background:#fff;border:1px solid var(--border);border-radius:16px;padding:10px 16px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;box-shadow:0 2px 10px rgba(15,23,42,.03)}
        .back-category-info{display:flex;align-items:center;gap:8px;color:var(--primary);font-weight:700;font-size:0.9rem}
        .back-category-info i{font-size:1rem}
        .back-category-btn{background:var(--primary);color:#fff;padding:8px 18px;border-radius:20px;text-decoration:none;font-weight:700;font-size:0.85rem;display:flex;align-items:center;gap:6px;transition:0.3s}
        .back-category-btn:hover{opacity:0.9}
        .hero{position:relative;overflow:hidden;border-radius:32px;background:linear-gradient(180deg, rgba(15,23,42,.08), rgba(15,23,42,.68)), url('<?php echo h($cover); ?>') center/cover no-repeat;min-height:640px;box-shadow:var(--shadow);display:flex;flex-direction:column;justify-content:flex-end;padding:18px}.hero::before{content:"";position:absolute;inset:0;background:linear-gradient(180deg, rgba(255,255,255,0) 0%, rgba(255,255,255,.88) 78%, rgba(255,255,255,.97) 100%)}.hero-inner{position:relative;z-index:1;display:grid;grid-template-columns:1fr;gap:16px}.hero-cover-wrap{width:100%;display:flex;justify-content:center}.cover{width:min(280px, 78vw);aspect-ratio:2/3;object-fit:cover;border-radius:22px;box-shadow:0 18px 40px rgba(15,23,42,.25);border:4px solid rgba(255,255,255,.65);background:#fff}.hero-card{background:rgba(255,255,255,.92);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.75);border-radius:28px;padding:18px;box-shadow:0 12px 30px rgba(15,23,42,.08)}.chip-row{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px}.chip{display:inline-flex;align-items:center;gap:7px;border:1px solid var(--border);background:#fff;color:#374151;padding:7px 12px;border-radius:999px;font-size:13px;font-weight:800}.chip.purple{background:#eef2ff;color:#4338ca;border-color:#c7d2fe}.chip.green{background:#ecfdf5;color:#047857;border-color:#a7f3d0}h1{margin:0 0 8px;font-size:clamp(26px, 5vw, 42px);line-height:1.2;font-weight:900}.author{color:var(--muted);font-size:16px;margin:0 0 12px;font-weight:700}.author a{color:var(--primary);text-decoration:underline;font-weight:900}
        .unified-actions{display:flex;gap:8px;margin:16px 0;flex-wrap:wrap;justify-content:center}
        .qa{border:1px solid var(--border);background:#fff;border-radius:16px;padding:10px 14px;text-align:center;font-weight:800;color:#374151;cursor:pointer;transition:.18s ease;display:flex;flex-direction:column;align-items:center;gap:4px;flex:1;min-width:65px;max-width:80px;font-size:0.75rem}
        .qa i{font-size:18px;color:var(--primary)}.qa:hover{transform:translateY(-2px);box-shadow:0 10px 20px rgba(15,23,42,.06)}.qa.active{background:#eef2ff;color:#1d4ed8;border-color:#c7d2fe}.qa.active i{color:#ff4757}
        .reading-status-bar{display:flex;align-items:center;justify-content:center;gap:0;margin:14px 0;background:#fff;border:1px solid var(--border);border-radius:30px;padding:4px;box-shadow:0 2px 10px rgba(15,23,42,0.03);max-width:420px;margin-left:auto;margin-right:auto;direction:ltr}
        .rs-option{width:44px;height:44px;border-radius:50%;border:none;background:transparent;cursor:pointer;position:relative;transition:all 0.25s cubic-bezier(0.4, 0, 0.2, 1);display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:1.2rem}
        .rs-option:hover{background:#f8fafc;color:#64748b}
        .rs-option.active-reading{background:#fef3c7;color:#f59e0b;box-shadow:0 2px 8px rgba(245, 158, 11, 0.2)}
        .rs-option.active-want{background:#dbeafe;color:#3b82f6;box-shadow:0 2px 8px rgba(59, 130, 246, 0.2)}
        .rs-option.active-read{background:#dcfce7;color:#10b981;box-shadow:0 2px 8px rgba(16, 185, 129, 0.2)}
        .rs-option.active-dnf{background:#fee2e2;color:#ef4444;box-shadow:0 2px 8px rgba(239, 68, 68, 0.2)}
        .rs-option::after{content:attr(data-tooltip);position:absolute;bottom:-32px;left:50%;transform:translateX(-50%) translateY(5px);background:#1e293b;color:#fff;padding:4px 10px;border-radius:6px;font-size:0.7rem;font-weight:600;font-family:'Cairo', sans-serif;white-space:nowrap;opacity:0;pointer-events:none;transition:all 0.2s ease;z-index:10}
        .rs-option:hover::after{opacity:1;transform:translateX(-50%) translateY(0)}
        .current-reading-status{text-align:center;margin-top:4px;font-size:0.75rem;font-weight:700;color:#64748b;display:flex;align-items:center;justify-content:center;gap:6px}
        .current-reading-status .dot{width:7px;height:7px;border-radius:50%;display:inline-block}
        .dot.dot-reading{background:#f59e0b}.dot.dot-want{background:#3b82f6}.dot.dot-read{background:#10b981}.dot.dot-dnf{background:#ef4444}
        .rs-divider{width:1px;height:24px;background:#e5e7eb;margin:0 6px}
        .rs-clear{width:36px;height:36px;border-radius:50%;border:none;background:transparent;cursor:pointer;color:#cbd5e1;font-size:0.8rem;transition:all 0.2s;display:flex;align-items:center;justify-content:center;margin-right:4px}
        .rs-clear:hover{color:#ef4444;background:#fef2f2}
        @media (max-width: 480px){.reading-status-bar{max-width:100%;border-radius:24px;padding:3px;gap:0}.rs-option{width:38px;height:38px;font-size:1rem}.rs-divider{margin:0 2px}}
        .cta-grid{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px;justify-content:center}
        .cta{border:none;border-radius:18px;padding:12px 16px;font-weight:900;font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:.18s ease;flex:1 0 calc(33.33% - 10px);min-width:80px;max-width:180px;text-decoration:none;color:#fff}
        .cta.buy{background:var(--primary);color:#fff}.cta.read{background:#111827;color:#fff}.cta.download{background:#2563eb;color:#fff}.cta.audio{background:#7c3aed;color:#fff}
        .cta.summary{background:#ff0000;color:#fff}.cta.summary:hover{background:#cc0000}
        .cta:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,0.15)}
        @media(max-width:480px){.cta-grid{flex-direction:row;flex-wrap:wrap;justify-content:center;gap:8px}.cta{flex:1 0 calc(33.33% - 8px);min-width:60px;padding:10px 8px;font-size:12px}}
        .summary{white-space:pre-wrap;color:#374151;background:linear-gradient(180deg, #fffdf5, #fff);border:1px solid #fde68a;padding:16px;border-radius:18px;font-size:15px}.desc{color:#374151;white-space:pre-wrap}.details{display:grid;grid-template-columns:1fr 1fr;gap:10px}.detail-item{border:1px solid var(--border);background:#fafafa;border-radius:18px;padding:12px 14px}.detail-item .k{color:var(--muted);font-size:12px;margin-bottom:5px;display:block;font-weight:700}.detail-item .v{font-size:15px;font-weight:900;color:var(--text)}
        .rating-strip{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-top:16px;padding:14px 16px;border-radius:20px;background:linear-gradient(135deg, #ffffff, #f8fafc);border:1px solid var(--border)}.rating-main{display:flex;flex-direction:column;gap:3px;min-width:120px}.rating-main .score{font-size:36px;line-height:1;font-weight:900}.rating-main .sub{color:var(--muted);font-size:13px;font-weight:700}.rating-stars{color:var(--gold);font-size:18px;letter-spacing:1px;margin-top:2px}.progress{flex:1}.progress .bar{width:100%;height:10px;background:#e5e7eb;border-radius:999px;overflow:hidden}.progress .fill{height:100%;background:linear-gradient(90deg, var(--primary), #334155);border-radius:999px}.progress small{display:block;margin-top:8px;color:var(--muted);font-weight:700;font-size:12px}
        .section{margin-top:16px;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);padding:18px}.section h2{margin:0 0 12px;font-size:20px;font-weight:900;display:flex;align-items:center;gap:10px}.section h2::before{content:"";width:10px;height:22px;border-radius:999px;background:var(--primary);display:inline-block}
        .author-card{display:flex;gap:20px;align-items:flex-start;width:100%}.author-card>div{flex:1;min-width:0}.author-img{width:96px;height:96px;border-radius:18px;object-fit:cover;background:#e5e7eb;border:1px solid var(--border)}.author-name{margin:0;font-size:18px;font-weight:900}.author-bio{margin:6px 0 0;color:#374151;white-space:pre-wrap;width:100%}.review-form{display:grid;gap:12px}.stars{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.stars input{display:none}.stars label{font-size:28px;color:#d1d5db;cursor:pointer;transition:.15s ease;line-height:1}.stars input:checked~label,.stars label:hover,.stars label:hover~label{color:var(--gold)}.review-form textarea{width:100%;min-height:110px;resize:vertical;border:1px solid var(--border);border-radius:18px;padding:14px;font-family:inherit;font-size:15px;outline:none;background:#fff}.review-form button{border:none;background:var(--primary);color:#fff;border-radius:18px;padding:14px 16px;font-weight:900;font-size:15px;cursor:pointer}.reviews-list{display:grid;gap:12px;margin-top:12px}.review-card{border:1px solid var(--border);background:#fff;border-radius:18px;padding:14px}.review-top{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:8px}.review-name{font-weight:900;font-size:15px}.review-date{color:var(--muted);font-size:12px;font-weight:700}.review-text{margin:8px 0 0;color:#374151;white-space:pre-wrap}.related-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:15px;margin-top:10px}.book-card{background:#fff;border:1px solid var(--border);border-radius:20px;overflow:hidden;box-shadow:0 8px 18px rgba(15,23,42,.04);transition:.3s;cursor:pointer}.book-card:hover{transform:translateY(-5px);box-shadow:0 12px 25px rgba(15,23,42,.1)}.book-card img{width:100%;aspect-ratio:3/4;object-fit:cover;background:#e5e7eb}.book-card .info{padding:10px 11px 12px}.book-card .info h3{margin:0 0 4px;font-size:14px;line-height:1.35;font-weight:900}.book-card .info p{margin:0;color:var(--muted);font-size:12px;font-weight:700}
        .quotes-section{background:linear-gradient(135deg, #faf5ff, #fdf2f8);border:1px solid #e9d5ff;border-radius:24px;padding:20px;margin-top:16px}.quote-card{background:white;border-radius:18px;padding:20px 24px;margin-bottom:14px;position:relative;border-right:4px solid #a855f7;box-shadow:0 4px 12px rgba(168,85,247,.06)}.quote-card .quote-text{font-size:1.1rem;line-height:2;color:#374151;font-style:italic;padding:8px 0;white-space:pre-wrap}.quote-card .quote-author{color:#6b7280;font-size:.8rem;margin-top:8px;font-weight:700;display:flex;align-items:center;justify-content:space-between}.add-quote-btn{background:linear-gradient(135deg, #a855f7, #7c3aed);color:white;border:none;padding:12px 22px;border-radius:14px;font-weight:800;cursor:pointer;font-family:'Cairo',sans-serif;margin-top:10px}.quote-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.6);z-index:9999;justify-content:center;align-items:center}.quote-modal.active{display:flex}.quote-modal-content{background:white;border-radius:24px;padding:24px;width:90%;max-width:500px}.quote-modal textarea{width:100%;min-height:130px;border:2px solid #e5e7eb;border-radius:16px;padding:14px;font-family:'Cairo',sans-serif;resize:vertical;outline:none}.quote-modal-actions{display:flex;gap:10px;margin-top:16px}.btn-save-quote{flex:1;background:#7c3aed;color:white;border:none;padding:12px;border-radius:14px;font-weight:800;cursor:pointer;font-family:'Cairo',sans-serif}.btn-cancel-quote{flex:1;background:#f3f4f6;border:1px solid #e5e7eb;padding:12px;border-radius:14px;font-weight:800;cursor:pointer;font-family:'Cairo',sans-serif}
        @media(min-width:760px){.hero-inner{grid-template-columns:300px 1fr;align-items:end}.hero{min-height:auto;padding:22px}.hero-cover-wrap{justify-content:flex-start}.cover{width:280px}.related-grid{grid-template-columns:repeat(4, minmax(0,1fr))}}@media(max-width:640px){.details{grid-template-columns:1fr}.author-card{flex-direction:column;align-items:center;text-align:center}.related-grid{grid-template-columns:repeat(2, minmax(0,1fr))}.rating-strip{flex-direction:column;align-items:stretch}.unified-actions{flex-wrap:wrap}.qa{min-width:55px;max-width:70px;padding:8px 10px;font-size:0.65rem}}
    </style>
</head>
<body>

<div class="page">
    <div class="topbar">
        <div class="brand"><span class="dot"></span><span>صفحة الكتاب</span></div>
        <div class="topbar-actions">
            <a href="index.php" class="icon-btn"><i class="fa-solid fa-house"></i></a>
        </div>
    </div>

    <?php if (isset($_SESSION['last_category']) && isset($_SESSION['last_category_name'])): ?>
    <div class="back-category-bar">
        <div class="back-category-info">
            <i class="fa-solid fa-folder-open"></i>
            <span><?php echo h($_SESSION['last_category_name']); ?></span>
        </div>
        <a href="index.php?cat=<?php echo urlencode($_SESSION['last_category']); ?>" class="back-category-btn">
            <i class="fa-solid fa-arrow-right"></i> تصفح التصنيف
        </a>
    </div>
    <?php endif; ?>

    <section class="hero"><div class="hero-inner"><div class="hero-cover-wrap"><img src="<?php echo h($cover); ?>" alt="<?php echo h($title); ?>" class="cover"></div><div class="hero-card"><div class="chip-row"><span class="chip purple"><i class="fa-solid fa-bolt"></i> إصدار جديد</span><?php if(present($category)): ?><span class="chip"><i class="fa-solid fa-layer-group"></i> <?php echo h($category); ?></span><?php endif; ?><?php if(present($pages_count)): ?><span class="chip green"><i class="fa-solid fa-book"></i> <?php echo h($pages_count); ?> صفحة</span><?php endif; ?></div><h1><?php echo h($title); ?></h1><?php if(present($author)): ?><p class="author">بقلم: <a href="author_books.php?author=<?php echo urlencode($author); ?>"><?php echo h($author); ?></a></p><?php endif; ?><div class="rating-strip"><div class="rating-main"><div class="score"><?php echo h($avg_rating); ?></div><div class="sub"><?php echo h($total_reviews); ?> تقييم</div></div><div class="progress"><div class="rating-stars"><?php $filled=(int)round($avg_rating);for($i=1;$i<=5;$i++)echo $i<=$filled?'<i class="fa-solid fa-star"></i>':'<i class="fa-regular fa-star"></i>'; ?></div><div class="bar"><div class="fill" style="width:<?php echo min(100,max(0,$avg_rating*20)); ?>%"></div></div><small>متوسط تقييم القراء</small></div></div></div></div></section>

    <div class="unified-actions">
        <button class="qa <?php echo $is_favorited?'active':''; ?>" onclick="toggleAction(this,'fav')"><i class="<?php echo $is_favorited?'fa-solid':'fa-regular'; ?> fa-heart"></i><span>مفضلة</span></button>
        <button class="qa <?php echo $is_saved?'active':''; ?>" onclick="toggleAction(this,'save')"><i class="<?php echo $is_saved?'fa-solid':'fa-regular'; ?> fa-bookmark"></i><span>حفظ</span></button>
        <button class="qa" onclick="shareBook()"><i class="fa-solid fa-share-nodes"></i><span>مشاركة</span></button>
    </div>

<div class="reading-status-bar" id="readingStatusBar">
    <button class="rs-option <?php echo ($reading_status === 'reading') ? 'active-reading' : ''; ?>" data-tooltip="يقرأه الآن" onclick="setReadingStatus('reading')" title="يقرأه الآن"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg></button>
    <button class="rs-option <?php echo ($reading_status === 'want_to_read') ? 'active-want' : ''; ?>" data-tooltip="سأقرأه" onclick="setReadingStatus('want_to_read')" title="سأقرأه"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></button>
    <button class="rs-option <?php echo ($reading_status === 'read') ? 'active-read' : ''; ?>" data-tooltip="قرأته" onclick="setReadingStatus('read')" title="قرأته"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></button>
    <button class="rs-option <?php echo ($reading_status === 'dnf') ? 'active-dnf' : ''; ?>" data-tooltip="لم أكمله" onclick="setReadingStatus('dnf')" title="لم أكمله"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></button>
    <?php if($reading_status): ?>
    <span class="rs-divider"></span>
    <button class="rs-clear" data-tooltip="إزالة" onclick="removeReadingStatus()" title="إزالة من المكتبة"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
    <?php endif; ?>
</div>

<?php if($reading_status): ?>
<div class="current-reading-status">
    <span class="dot dot-<?php echo $reading_status === 'want_to_read' ? 'want' : h($reading_status); ?>"></span>
    <?php echo h($status_labels[$reading_status]); ?>
</div>
<?php else: ?>
<div class="current-reading-status" style="opacity:0.5;">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    أضف للمكتبة
</div>
<?php endif; ?>

<?php if(present($buy_link)||present($download_link)||present($audio_link)||present($read_link)||present($link)): ?>
<div class="cta-grid">
    <?php if(present($buy_link)): ?><a class="cta buy" href="<?php echo h($buy_link); ?>" target="_blank" rel="noopener"><i class="fa-solid fa-cart-shopping"></i> شراء</a><?php endif; ?>
    <?php if(present($link)): ?><a class="cta summary" href="<?php echo h($link); ?>" target="_blank" rel="noopener"><i class="fa-brands fa-youtube"></i> ملخص</a><?php endif; ?>
    <?php if(present($read_link)): ?><a class="cta read" href="<?php echo h($read_link); ?>" target="_blank" rel="noopener"><i class="fa-solid fa-book-open"></i> اقرأ أونلاين</a><?php endif; ?>
    <?php if(present($download_link)): ?><a class="cta download" href="<?php echo h($download_link); ?>" target="_blank" rel="noopener"><i class="fa-solid fa-download"></i> تحميل</a><?php endif; ?>
    <?php if(present($audio_link)): ?><a class="cta audio" href="<?php echo h($audio_link); ?>" target="_blank" rel="noopener"><i class="fa-solid fa-headphones"></i> استماع</a><?php endif; ?>
</div>
<?php endif; ?>

    <?php if(present($text_summary)): ?><section class="section"><h2>ملخص سريع</h2><div class="summary"><?php echo h($text_summary); ?></div></section><?php endif; ?>
    <?php if(present($description)): ?><section class="section"><h2>عن الكتاب</h2><div class="desc"><?php echo nl2br(h($description)); ?></div></section><?php endif; ?>
    <?php if(!empty($details)): ?><section class="section"><h2>تفاصيل الكتاب</h2><div class="details"><?php foreach($details as $item): ?><div class="detail-item"><span class="k"><?php echo h($item[0]); ?></span><span class="v"><?php echo h($item[1]); ?></span></div><?php endforeach; ?></div></section><?php endif; ?>
    <?php if(present($author)||present($author_bio)||present($author_image)): ?><section class="section"><h2>عن الكاتب</h2><div class="author-card"><?php if(present($author_image)): ?><img class="author-img" src="<?php echo h($author_image); ?>" alt="<?php echo h($author); ?>"><?php endif; ?><div><?php if(present($author)): ?><p class="author-name"><?php echo h($author); ?></p><?php endif; ?><?php if(present($author_bio)): ?><p class="author-bio"><?php echo nl2br(h($author_bio)); ?></p><?php endif; ?></div></div></section><?php endif; ?>
    <?php if(!empty($author_books)): ?><section class="section"><h2>كتب لنفس المؤلف</h2><div class="related-grid"><?php foreach($author_books as $ab): ?><a class="book-card" href="book.php?id=<?php echo (int)$ab['ID']; ?>"><img src="<?php echo h($ab['cover_image']?:'https://via.placeholder.com/300x400?text=Book'); ?>" alt="<?php echo h($ab['title']); ?>"><div class="info"><h3><?php echo h($ab['title']); ?></h3><p><?php echo h($ab['author']); ?></p></div></a><?php endforeach; ?></div></section><?php endif; ?>
    <?php if(!empty($similar_books)): ?><section class="section"><h2>كتب مشابهة</h2><div class="related-grid"><?php foreach($similar_books as $sb): ?><a class="book-card" href="book.php?id=<?php echo (int)$sb['ID']; ?>"><img src="<?php echo h($sb['cover_image']?:'https://via.placeholder.com/300x400?text=Book'); ?>" alt="<?php echo h($sb['title']); ?>"><div class="info"><h3><?php echo h($sb['title']); ?></h3><p><?php echo h($sb['author']); ?></p></div></a><?php endforeach; ?></div></section><?php endif; ?>
    <?php if(!empty($related_books)): ?><section class="section"><h2>كتب مقترحة لك</h2><div class="related-grid"><?php foreach($related_books as $rb): ?><a class="book-card" href="book.php?id=<?php echo (int)$rb['ID']; ?>"><img src="<?php echo h($rb['cover_image']?:'https://via.placeholder.com/300x400?text=Book'); ?>" alt="<?php echo h($rb['title']); ?>"><div class="info"><h3><?php echo h($rb['title']); ?></h3><p><?php echo h($rb['author']); ?></p></div></a><?php endforeach; ?></div></section><?php endif; ?>
    <?php if(!empty($book_quotes)||!empty($user_quotes)): ?><section class="quotes-section" id="quotesSection"><h2 style="margin:0 0 16px;color:#7c3aed;"><i class="fas fa-quote-right"></i> اقتباسات من الكتاب</h2><?php foreach($book_quotes as $quote): ?><div class="quote-card"><div class="quote-text"><?php echo nl2br(h(trim($quote))); ?></div></div><?php endforeach; ?><?php foreach($user_quotes as $uq): $is_quote_owner=isset($_SESSION['user_id'])&&$_SESSION['user_id']==($uq['user_id']??0); ?><div class="quote-card" data-quote-id="<?php echo (int)$uq['id']; ?>"><div class="quote-text" id="quoteText-<?php echo (int)$uq['id']; ?>"><?php echo nl2br(h($uq['quote_text'])); ?></div><div class="quote-author"><span><i class="fas fa-user-circle"></i> <?php echo h($uq['username']??'مستخدم'); ?> &middot; <?php echo h($uq['created_at']??''); ?></span><?php if($is_quote_owner): ?><span class="quote-actions"><button class="btn-edit-quote" onclick="editQuote(<?php echo (int)$uq['id']; ?>)" title="تعديل"><i class="fa-solid fa-pen-to-square"></i></button><button class="btn-delete-quote" onclick="deleteQuote(<?php echo (int)$uq['id']; ?>)" title="حذف"><i class="fa-solid fa-trash-can"></i></button></span><?php endif; ?></div></div><?php endforeach; ?></section><?php endif; ?>
    <div style="text-align:center;margin-top:10px;"><button class="add-quote-btn" onclick="openQuoteModal()"><i class="fas fa-plus-circle"></i> أضف اقتباساً</button></div>
    <div class="quote-modal" id="quoteModal"><div class="quote-modal-content"><h3 style="color:#7c3aed;"><i class="fas fa-quote-right"></i> <span id="quoteModalTitle">أضف اقتباساً</span></h3><input type="hidden" id="editQuoteId" value=""><textarea id="quoteTextInput" placeholder="اكتب الاقتباس هنا..."></textarea><textarea id="quoteCommentInput" placeholder="تعليقك (اختياري)" style="min-height:60px;margin-top:8px;"></textarea><div class="quote-modal-actions"><button class="btn-cancel-quote" onclick="closeQuoteModal()">إلغاء</button><button class="btn-save-quote" id="saveQuoteBtn" onclick="submitQuote()">إضافة</button></div></div></div>

    <section class="section"><h2>إضافة تقييم</h2><form class="review-form" method="POST" action="submit_review.php"><input type="hidden" name="book_id" value="<?php echo (int)$book_id; ?>"><div class="stars"><?php for($i=5;$i>=1;$i--): ?><input type="radio" name="rating" id="star<?php echo $i; ?>" value="<?php echo $i; ?>" required><label for="star<?php echo $i; ?>">★</label><?php endfor; ?></div><textarea name="review_text" placeholder="اكتب تعليقك..." maxlength="1000"></textarea><button type="submit">إرسال التقييم</button></form></section>

    <section class="section"><h2>آخر التقييمات</h2><div class="reviews-list" id="reviewsContainer"><?php $review_cols=[];$cols_res=$conn->query("SHOW COLUMNS FROM reviews");if($cols_res){while($c=$cols_res->fetch_assoc())$review_cols[]=$c['Field'];} $review_fields=['id','user_id','book_id','rating','review_text','comment','created_at','name','user_name'];$select_fields=[];foreach($review_fields as $f){if(in_array($f,$review_cols,true))$select_fields[]="`$f`";} $select_sql="SELECT ".implode(", ",$select_fields)." FROM reviews WHERE book_id = ? ORDER BY ".(in_array('created_at',$review_cols,true)?"created_at DESC":"id DESC");$stmt=$conn->prepare($select_sql);if($stmt){$stmt->bind_param("i",$book_id);$stmt->execute();$result=$stmt->get_result();if($result&&$result->num_rows>0){while($rv=$result->fetch_assoc()){$review_id=$rv['id']??0;$review_user_id=$rv['user_id']??0;$r_text=$rv['review_text']??($rv['comment']??'');$r_name=$rv['name']??($rv['user_name']??'زائر');$r_date=$rv['created_at']??'';$r_rating=isset($rv['rating'])?(int)$rv['rating']:0;$is_owner=isset($_SESSION['user_id'])&&$_SESSION['user_id']==$review_user_id; ?><div class="review-card" data-review-id="<?php echo (int)$review_id; ?>"><div class="review-top"><div><div class="review-name"><?php echo h($r_name); ?></div><div class="review-date"><?php echo $r_date?h($r_date):''; ?></div></div><?php if($is_owner): ?><div class="review-actions"><button class="btn-edit-review" onclick="editReview(<?php echo (int)$review_id; ?>,this)"><i class="fa-solid fa-pen-to-square"></i></button><button class="btn-delete-review" onclick="deleteReview(<?php echo (int)$review_id; ?>)"><i class="fa-solid fa-trash-can"></i></button></div><?php endif; ?></div><div class="rating-stars" style="font-size:14px"><?php for($i=1;$i<=5;$i++)echo $i<=$r_rating?'<i class="fa-solid fa-star"></i>':'<i class="fa-regular fa-star"></i>'; ?></div><?php if(present($r_text)): ?><div class="review-text" id="reviewText-<?php echo (int)$review_id; ?>"><?php echo nl2br(h($r_text)); ?></div><?php endif; ?></div><?php }}else{echo '<div class="review-card">لا توجد تقييمات بعد.</div>';}$stmt->close();}?></div></section>
</div>

<script>
var bookId = <?php echo (int)$book_id; ?>;

function toggleAction(btn, type) {
    <?php if(!isset($_SESSION['user_id'])): ?>
        alert('يجب تسجيل الدخول أولاً'); window.location.href='login.php'; return;
    <?php endif; ?>
    var icon = btn.querySelector('i');
    fetch('toggle_action.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'book_id='+bookId+'&type='+type})
    .then(function(res){return res.json();})
    .then(function(data){
        if(data.status==='added'){icon.className='fa-solid '+(type==='fav'?'fa-heart':'fa-bookmark');btn.classList.add('active');}
        else if(data.status==='removed'){icon.className='fa-regular '+(type==='fav'?'fa-heart':'fa-bookmark');btn.classList.remove('active');}
        alert(data.message);
    });
}

function setReadingStatus(status) {
    <?php if(!isset($_SESSION['user_id'])): ?>
        alert('يجب تسجيل الدخول أولاً'); window.location.href='login.php'; return;
    <?php endif; ?>
    var formData = new FormData();
    formData.append('update_reading_status', '1');
    formData.append('book_id', bookId);
    formData.append('status', status);
    fetch('book.php?id='+bookId, {method:'POST', body:formData})
    .then(function(res){return res.json();})
    .then(function(data){
        if(data.status === 'success') { location.reload(); }
        else { alert(data.message); }
    });
}

function removeReadingStatus() {
    if(!confirm('هل تريد إزالة حالة القراءة؟')) return;
    var formData = new FormData();
    formData.append('remove_reading_status', '1');
    formData.append('book_id', bookId);
    fetch('book.php?id='+bookId, {method:'POST', body:formData})
    .then(function(res){return res.json();})
    .then(function(data){
        if(data.status === 'success') { location.reload(); }
        else { alert(data.message); }
    });
}

function shareBook(){
    var url=window.location.href;
    if(navigator.share){navigator.share({title:document.title,url:url});}
    else{navigator.clipboard.writeText(url);alert('تم نسخ الرابط');}
}

function openQuoteModal(quoteId){
    <?php if(!isset($_SESSION['user_id'])): ?>
        alert('يجب تسجيل الدخول لإضافة اقتباس'); window.location.href='login.php'; return;
    <?php endif; ?>
    var modal=document.getElementById('quoteModal');
    var title=document.getElementById('quoteModalTitle');
    var textInput=document.getElementById('quoteTextInput');
    var editId=document.getElementById('editQuoteId');
    var saveBtn=document.getElementById('saveQuoteBtn');
    if(quoteId){
        title.textContent='تعديل الاقتباس';
        saveBtn.textContent='حفظ التعديل';
        editId.value=quoteId;
        textInput.value=document.getElementById('quoteText-'+quoteId).textContent.trim();
        saveBtn.setAttribute('onclick','submitEditQuote()');
    }else{
        title.textContent='أضف اقتباساً';
        saveBtn.textContent='إضافة';
        editId.value='';
        textInput.value='';
        saveBtn.setAttribute('onclick','submitQuote()');
    }
    modal.classList.add('active');
    textInput.focus();
}

function closeQuoteModal(){ document.getElementById('quoteModal').classList.remove('active'); }

function submitQuote(){
    var text=document.getElementById('quoteTextInput').value.trim();
    if(!text){alert('اكتب الاقتباس');return;}
    var comment=document.getElementById('quoteCommentInput').value.trim();
    var formData=new FormData();
    formData.append('add_quote','1');formData.append('book_id',bookId);
    formData.append('quote_text',text);formData.append('quote_comment',comment);
    fetch('book.php?id='+bookId,{method:'POST',body:formData})
    .then(function(res){return res.json();})
    .then(function(data){
        if(data.status==='success'){alert('تمت الإضافة');closeQuoteModal();location.reload();}
        else{alert(data.message||'خطأ');}
    });
}

function editQuote(quoteId){openQuoteModal(quoteId);}
function submitEditQuote(){
    var quoteId=document.getElementById('editQuoteId').value;
    var text=document.getElementById('quoteTextInput').value.trim();
    if(!text){alert('اكتب الاقتباس');return;}
    var comment=document.getElementById('quoteCommentInput').value.trim();
    var formData=new FormData();
    formData.append('edit_quote','1');formData.append('quote_id',quoteId);
    formData.append('quote_text',text);formData.append('quote_comment',comment);
    fetch('book.php?id='+bookId,{method:'POST',body:formData})
    .then(function(res){return res.json();})
    .then(function(data){
        if(data.status==='success'){alert('تم التعديل');closeQuoteModal();location.reload();}
        else{alert(data.message||'خطأ');}
    });
}

function deleteQuote(quoteId){
    if(!confirm('حذف الاقتباس؟'))return;
    var formData=new FormData();
    formData.append('delete_quote','1');formData.append('quote_id',quoteId);
    fetch('book.php?id='+bookId,{method:'POST',body:formData})
    .then(function(res){return res.json();})
    .then(function(data){
        if(data.status==='success'){alert('تم الحذف');location.reload();}
        else{alert(data.message||'خطأ');}
    });
}

document.addEventListener('click',function(e){
    if(e.target===document.getElementById('quoteModal'))closeQuoteModal();
});
</script>
</body>
</html>
