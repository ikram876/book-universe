<?php
// ===== إعدادات الأخطاء (عطّل display_errors في الإنتاج) =====
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');
session_start();

// ===== بيانات الاتصال من متغيرات البيئة (لا تكتبها هنا) =====
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: '';
$db_pass = getenv('DB_PASS') ?: '';
$db_name = getenv('DB_NAME') ?: '';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    error_log('DB connection failed: ' . $conn->connect_error);
    die('حدث خطأ في الاتصال بقاعدة البيانات.');
}
$conn->set_charset("utf8mb4");

// ===== دالة ترجمة التصنيفات =====
function getCategoryName($cat) {
    $categories = [
        'رومنسية' => 'روايات رومنسية',
        'فانتازيا' => 'رواية فانتازيا وخيال علمي',
        'حقيقية' => 'روايات حقيقية',
        'عربية' => 'روايات عربية',
        'الأدب' => 'الأدب والشعر',
        'رعب' => 'روايات رعب',
        'بوليسية' => 'روايات بوليسية',
        'مترجمة' => 'روايات مترجمة',
        'تاريخ' => 'روايات تاريخية',
        'كوميدية' => 'روايات كوميدية',
        'قصص_قصيرة' => 'قصص قصيرة',
        'قصص_أطفال' => 'قصص أطفال',
        'قصص_واقعية' => 'قصص واقعية',
        'قصص_بوليسية' => 'قصص بوليسية',
        'قصص_رعب' => 'قصص رعب',
        'تنمية' => 'كتب تنمية بشرية',
        'علم_نفس' => 'كتب علم النفس',
        'دينية' => 'كتب دينية',
        'سير' => 'سير ذاتية ومذكرات',
        'فلسفة' => 'فلسفة ومنطق',
        'تاريخ_كتب' => 'كتب تاريخ وحضارات',
        'سياسة' => 'سياسة واقتصاد',
        'تربية_أطفال' => 'كتب تربية الأطفال',
        'تطوير_علاقات' => 'كتب تطوير العلاقات',
        'لغة_جسد' => 'كتب تعلم لغة الجسد',
        'تعلم_إنجليزية' => 'كتب تعلم اللغة الإنجليزية',
        'تعلم_فرنسية' => 'كتب تعلم اللغة الفرنسية',
        'تعلم_تركية' => 'كتب تعلم اللغة التركية',
        'تعلم_عربية' => 'كتب تعلم اللغة العربية',
        'لغات_برمجة' => 'كتب لغات البرمجة',
        'جبر' => 'كتب الجبر',
        'رياضيات' => 'كتب رياضيات',
        'اناليز' => 'كتب اناليز',
        'خوارزميات' => 'كتب تعلم الخوارزميات',
        'ذكاء_اصطناعي' => 'ذكاء اصطناعي',
        'شبكات' => 'شبكات وأمن سيبراني',
        'نظم_التشغيل' => 'كتب تعلم نظم التشغيل',
        'مجلات_علمية' => 'مجلات علمية',
        'مجلات_تقنية' => 'مجلات تقنية',
        'مجلات_أدبية' => 'مجلات أدبية',
        'مجلات_أطفال' => 'مجلات أطفال',
        'مانجا' => 'مانجا وكوميكس',
        'طبخ' => 'طبخ وتغذية',
        'صحة' => 'صحة ورشاقة',
        'أعمال' => 'إدارة أعمال وتسويق',
        'قانون' => 'قانون وحقوق',
        'رحلات' => 'أدب رحلات',
    ];
    return isset($categories[$cat]) ? $categories[$cat] : $cat;
}

// ===== حفظ آخر تصنيف تلقائياً =====
$category = isset($_GET['cat']) ? $_GET['cat'] : '';
if ($category != '') {
    $_SESSION['last_category'] = $category;
    $_SESSION['last_category_name'] = getCategoryName($category);
}

// ===== نظام التقييم =====
$rating_stats = $conn->query("SELECT * FROM rating_stats LIMIT 1")->fetch_assoc();
$avg_rating = $rating_stats['average_rating'] ?? 0;
$total_ratings = $rating_stats['total_ratings'] ?? 0;
$five_star = $rating_stats['five_star'] ?? 0;
$four_star = $rating_stats['four_star'] ?? 0;
$three_star = $rating_stats['three_star'] ?? 0;
$two_star = $rating_stats['two_star'] ?? 0;
$one_star = $rating_stats['one_star'] ?? 0;

// ===== جلب تعليق المستخدم الحالي =====
$user_comment = null;
$user_rating = null;
$user_comment_id = null;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $check_user_comment = $conn->prepare("SELECT id, rating, comment FROM ratings WHERE user_id = ?");
    $check_user_comment->bind_param("i", $user_id);
    $check_user_comment->execute();
    $comment_result = $check_user_comment->get_result();
    if ($comment_result->num_rows > 0) {
        $row = $comment_result->fetch_assoc();
        $user_comment = $row['comment'];
        $user_rating = $row['rating'];
        $user_comment_id = $row['id'];
    }
}

// ===== معالجة إضافة التقييم فقط =====
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_rating'])) {
    if (!isset($_SESSION['user_id'])) {
        echo "<script>alert('يجب تسجيل الدخول للتقييم'); window.location.href='login.php';</script>";
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    if ($rating < 1 || $rating > 5) {
        echo "<script>alert('يرجى اختيار تقييم من 1 إلى 5 نجوم'); window.location.href='index.php';</script>";
        exit;
    }
    $comment = trim($_POST['comment']);

    $check = $conn->prepare("SELECT id FROM ratings WHERE user_id = ?");
    $check->bind_param("i", $user_id);
    $check->execute();
    if ($check->get_result()->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO ratings (user_id, rating, comment) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $user_id, $rating, $comment);
        $stmt->execute();

        $conn->query("
            UPDATE rating_stats SET 
            total_ratings = total_ratings + 1,
            average_rating = (SELECT AVG(rating) FROM ratings),
            five_star = (SELECT COUNT(*) FROM ratings WHERE rating = 5),
            four_star = (SELECT COUNT(*) FROM ratings WHERE rating = 4),
            three_star = (SELECT COUNT(*) FROM ratings WHERE rating = 3),
            two_star = (SELECT COUNT(*) FROM ratings WHERE rating = 2),
            one_star = (SELECT COUNT(*) FROM ratings WHERE rating = 1)
            WHERE id = 1
        ");

        echo "<script>alert('تم إرسال تقييمك بنجاح! 🌟 شكراً لك'); window.location.href='index.php';</script>";
    } else {
        echo "<script>alert('لقد قمت بالتقييم مسبقاً'); window.location.href='index.php';</script>";
    }
    exit;
}

// ===== نظام الترقيم =====
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$books_per_page = 30;
$offset = ($page - 1) * $books_per_page;

if ($category != '') {
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM novels WHERE category = ?");
    $count_stmt->bind_param("s", $category);
    $count_stmt->execute();
    $total_books = $count_stmt->get_result()->fetch_row()[0];

    $stmt = $conn->prepare("SELECT * FROM novels WHERE category = ? ORDER BY ID DESC LIMIT ?, ?");
    $stmt->bind_param("sii", $category, $offset, $books_per_page);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $total_books = $conn->query("SELECT COUNT(*) FROM novels")->fetch_row()[0];

    $stmt = $conn->prepare("SELECT * FROM novels ORDER BY ID DESC LIMIT ?, ?");
    $stmt->bind_param("ii", $offset, $books_per_page);
    $stmt->execute();
    $result = $stmt->get_result();
}

$total_pages = ceil($total_books / $books_per_page);
$current_page = $page;
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Book Universe | عالم الكتب</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="theme-color" content="#ffffff">
    <style>
        :root { --primary: #009688; --bg: #f8f9fa; --card-bg: #ffffff; --text: #333; --sidebar-width: 260px; }
        .dark-mode { --bg: #121212; --card-bg: #1e1e1e; --text: #e0e0e0; }
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; background-color: var(--bg); color: var(--text); }
        .page-wrapper { display: flex; flex-direction: column; min-height: 100vh; }
        .main-content { flex: 1; }
        header { background: var(--card-bg); padding: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .header-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .site-title { font-weight: bold; font-size: 1.3rem; color: var(--primary); display: flex; align-items: center; gap: 10px; flex-wrap: nowrap; white-space: nowrap; }
        .site-title span { flex-shrink: 0; }
        .current-category-bar { background: var(--bg); padding: 10px 15px; margin-bottom: 15px; border-radius: 10px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
        .current-category-label { font-weight: bold; color: var(--primary); display: flex; align-items: center; gap: 5px; font-size: 0.9rem; }
        .current-category-label i { font-size: 0.8rem; }
        .back-to-all { background: var(--primary); color: white; padding: 6px 15px; border-radius: 20px; text-decoration: none; font-size: 0.85rem; font-weight: bold; display: flex; align-items: center; gap: 5px; transition: 0.3s; }
        .back-to-all:hover { opacity: 0.9; transform: translateX(5px); }
        .category-page-info { font-size: 0.8rem; color: var(--text); opacity: 0.7; }
        .nav-tools { display: flex; gap: 25px; justify-content: center; align-items: center; background: var(--bg); padding: 10px; border-radius: 10px; margin-top: 10px; flex-wrap: wrap; }
        .nav-tools a { text-decoration: none; color: var(--text); font-weight: bold; display: flex; align-items: center; gap: 5px; font-size: 0.95rem; cursor: pointer; }
        .nav-tools a:hover { color: var(--primary); }
        .sidebar { width: var(--sidebar-width); background: var(--card-bg); height: 100vh; position: fixed; right: -260px; top: 0; box-shadow: -2px 0 10px rgba(0,0,0,0.1); padding: 20px; transition: 0.3s ease; z-index: 2000; overflow-y: auto; box-sizing: border-box; }
        .sidebar.active { right: 0; }
        .sidebar h3 { margin-top: 0; color: var(--primary); border-bottom: 2px solid var(--bg); padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .sidebar a { display: block; padding: 12px 10px; text-decoration: none; color: var(--text); border-bottom: 1px solid #eee; transition: 0.3s; border-radius: 5px; }
        .sidebar a:hover { background: var(--bg); color: var(--primary); padding-right: 15px; }
        .close-sidebar-btn { background: none; border: none; color: #ff4757; font-size: 1.5rem; cursor: pointer; float: left; }
        .container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 20px; box-sizing: border-box; }
        .books-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .book-card { background: var(--card-bg); border-radius: 15px; padding: 15px; text-align: center; box-shadow: 0 4px 10px rgba(0,0,0,0.05); display: flex; flex-direction: column; transition: transform 0.2s; height: 100%; overflow: hidden; }
        .book-card:hover { transform: translateY(-5px); }
        .book-card img { width: 100%; height: 180px; object-fit: cover; border-radius: 10px; flex-shrink: 0; }
        .book-card h3 { font-size: 1rem; margin: 12px 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex-shrink: 0; }
        .card-actions { margin-top: auto; display: flex; gap: 8px; flex-direction: column; width: 100%; flex-shrink: 0; }
        .btn-action { background: var(--primary); color: white; padding: 12px 15px; border-radius: 8px; text-decoration: none; font-weight: bold; text-align: center; display: block; width: 100%; box-sizing: border-box; font-size: 0.9rem; transition: background 0.3s; border: none; cursor: pointer; }
        .btn-action:hover { opacity: 0.9; }
        .pagination { display: flex; justify-content: center; align-items: center; gap: 8px; margin: 40px 0 20px; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 8px 16px; background: var(--card-bg); color: var(--text); text-decoration: none; border-radius: 8px; border: 1px solid rgba(0,0,0,0.08); transition: all 0.3s ease; font-weight: bold; font-size: 0.9rem; min-width: 40px; text-align: center; }
        .pagination a:hover { background: var(--primary); color: white; border-color: var(--primary); transform: translateY(-2px); }
        .pagination a.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 4px 15px rgba(0,150,136,0.3); }
        .pagination .disabled { opacity: 0.5; cursor: not-allowed; }
        .pagination .page-info { background: none; border: none; font-weight: normal; color: #888; }
        .rating-section { background: var(--card-bg); padding: 40px 30px; margin: 60px auto 0; max-width: 800px; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
        .rating-header { text-align: center; margin-bottom: 30px; }
        .rating-header h2 { font-size: 1.3rem; font-weight: 800; color: var(--text); margin: 0 0 5px; }
        .rating-header p { color: var(--text); font-size: 0.85rem; margin: 0; opacity: 0.7; }
        .rating-overview { display: flex; align-items: center; gap: 30px; padding: 25px; background: var(--bg); border-radius: 15px; margin-bottom: 30px; }
        .rating-big-score { text-align: center; min-width: 100px; }
        .rating-big-score .big-number { font-size: 3rem; font-weight: 900; color: var(--text); line-height: 1; }
        .rating-big-score .stars { color: #ffa502; font-size: 1.2rem; margin: 5px 0; }
        .rating-big-score .count { font-size: 0.8rem; color: var(--text); opacity: 0.6; }
        .rating-bars { flex: 1; }
        .rating-bar-row { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
        .rating-bar-row .stars-label { font-size: 0.8rem; color: var(--text); opacity: 0.7; min-width: 30px; text-align: left; direction: ltr; }
        .rating-bar-row .bar-bg { flex: 1; height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden; }
        .rating-bar-row .bar-fill { height: 100%; background: #ffa502; border-radius: 4px; transition: width 0.5s ease; }
        .rating-bar-row .bar-count { font-size: 0.75rem; color: var(--text); opacity: 0.5; min-width: 25px; }
        .rating-form-container { border-top: 1px solid rgba(0,0,0,0.08); padding-top: 25px; margin-top: 25px; }
        .rating-form-container h3 { font-size: 1rem; font-weight: 700; margin: 0 0 20px; text-align: center; }
        .star-rating-input { display: flex; flex-direction: row-reverse; justify-content: center; gap: 15px; margin: 20px 0 25px; direction: ltr; flex-wrap: wrap; }
        .star-rating-input input { display: none; }
        .star-rating-input label { font-size: 2.5rem; color: #ddd; cursor: pointer; transition: 0.3s; padding: 0 5px; position: relative; display: flex; flex-direction: column; align-items: center; gap: 8px; }
        .star-rating-input label i { transition: 0.3s; }
        .star-rating-input label:hover i,
        .star-rating-input label:hover ~ label i,
        .star-rating-input input:checked ~ label i { color: #ffa502; transform: scale(1.1); }
        .star-rating-input .star-number { font-size: 0.75rem; color: #888; font-weight: 700; background: rgba(0,0,0,0.06); padding: 2px 8px; border-radius: 12px; transition: 0.3s; direction: ltr; }
        .star-rating-input label:hover .star-number,
        .star-rating-input label:hover ~ label .star-number,
        .star-rating-input input:checked ~ label .star-number { background: #ffa502; color: white; }
        .thank-you-message { text-align: center; padding: 30px 20px; background: var(--bg); border-radius: 12px; animation: fadeInUp 0.5s ease; }
        .thank-you-message i { font-size: 3rem; color: #2ed573; }
        .thank-you-message h3 { margin: 15px 0 5px; color: var(--text); }
        .thank-you-message p { color: var(--text); opacity: 0.6; font-size: 0.9rem; margin: 0; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .rating-comment { width: 100%; padding: 15px; border: 2px solid rgba(0,0,0,0.08); border-radius: 12px; font-family: inherit; resize: vertical; background: var(--bg); color: var(--text); font-size: 0.9rem; margin-bottom: 15px; transition: 0.3s; box-sizing: border-box; }
        .rating-comment:focus { outline: none; border-color: var(--primary); }
        .submit-rating-btn { width: 100%; background: var(--primary); color: white; padding: 14px; border: none; border-radius: 12px; font-weight: bold; cursor: pointer; font-size: 1rem; transition: 0.3s; font-family: inherit; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .submit-rating-btn:hover { opacity: 0.9; transform: translateY(-2px); box-shadow: 0 5px 20px rgba(0,150,136,0.3); }
        .site-footer { background: var(--card-bg); border-top: 1px solid rgba(0,0,0,0.05); padding: 30px 20px; margin-top: 60px; width: 100%; box-sizing: border-box; text-align: center; }
        .footer-content { max-width: 800px; margin: 0 auto; direction: rtl; }
        .footer-content h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 8px; color: var(--text); }
        .footer-content h3 span { color: var(--primary); }
        .footer-content p { color: var(--text); font-size: 0.85rem; opacity: 0.7; margin: 5px 0; line-height: 1.6; }
        .social-icons { display: flex; gap: 12px; justify-content: center; margin: 20px 0; flex-wrap: wrap; }
        .social-icon { width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.1rem; text-decoration: none; transition: all 0.3s ease; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .social-icon:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.2); }
        .social-linkedin { background: #0a66c2; }
        .footer-bottom { margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(0,0,0,0.05); }
        .footer-bottom p { font-size: 0.85rem; color: var(--text); margin: 3px 0; }
        .footer-bottom .developer { font-weight: 700; color: var(--primary); }
        .mobile-nav { position: fixed; bottom: 0; left: 0; width: 100%; background: var(--card-bg); display: none; justify-content: space-around; padding: 10px 0; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); z-index: 1500; }
        .mobile-nav a { text-decoration: none; color: var(--text); font-size: 0.85rem; display: flex; flex-direction: column; align-items: center; gap: 5px; cursor: pointer; }
        .mobile-nav a i { font-size: 1.2rem; }
        .mobile-nav a:hover { color: var(--primary); }
        .search-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: var(--bg); z-index: 9999; display: none; flex-direction: column; color: var(--text); }
        .search-overlay-header { display: flex; align-items: center; padding: 12px 15px; background: var(--card-bg); box-shadow: 0 2px 5px rgba(0,0,0,0.05); gap: 10px; }
        .search-back-btn { background: none; border: none; color: var(--text); font-size: 1.4rem; cursor: pointer; padding: 5px; transform: scaleX(-1); }
        .search-overlay-form { flex: 1; display: flex; align-items: center; background: var(--bg); border: 1px solid rgba(0,0,0,0.1); border-radius: 25px; padding: 6px 15px; }
        .search-overlay-form input { width: 100%; background: none; border: none; color: var(--text); font-size: 1rem; outline: none; direction: rtl; }
        .search-overlay-submit { background: none; border: none; color: var(--text); font-size: 1.1rem; cursor: pointer; padding-left: 5px; }
        .search-overlay-history { padding: 20px; direction: rtl; }
        .search-overlay-history h3 { font-size: 0.95rem; color: #888; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
        .search-overlay-history ul { list-style: none; padding: 0; margin: 0; }
        .search-overlay-history ul li { border-bottom: 1px solid rgba(0, 0, 0, 0.05); display: flex; align-items: center; justify-content: space-between; padding: 4px 0; }
        .search-overlay-history ul li a { display: block; padding: 8px 5px; color: var(--text); text-decoration: none; font-size: 1rem; flex: 1; }
        .delete-search-item { background: none; border: none; color: #ff4757; cursor: pointer; font-size: 1rem; padding: 10px; }
        .theme-selector-wrapper { margin-bottom: 10px; }
        .theme-toggle-btn { background: var(--bg); border: 1px solid rgba(0,0,0,0.05); color: var(--text); padding: 10px; border-radius: 8px; width: 100%; text-align: right; display: flex; justify-content: space-between; align-items: center; cursor: pointer; font-weight: bold; font-size: 0.95rem; }
        .theme-colors-container { display: none; flex-wrap: wrap; gap: 12px; padding: 15px 5px 5px; justify-content: flex-start; }
        .theme-colors-container.open { display: flex; }
        .color-dot { width: 32px; height: 32px; border-radius: 50%; cursor: pointer; transition: transform 0.2s; border: 2px solid transparent; }
        .color-dot:hover { transform: scale(1.15); }
        .left-header-actions { display: flex; align-items: center; gap: 12px; }
        .vip-upgrade-btn { background: linear-gradient(135deg, #ffa502, #ff7b00); color: white !important; border: none; padding: 5px 12px; border-radius: 15px; font-size: 0.85rem; font-weight: bold; text-decoration: none; display: flex; align-items: center; gap: 5px; box-shadow: 0 2px 5px rgba(255,165,2,0.4); transition: 0.3s; }
        .vip-upgrade-btn:hover { transform: scale(1.05); }
        @media (max-width: 900px) { .books-grid { grid-template-columns: repeat(2, 1fr); gap: 15px; } .container { padding: 15px; } .rating-overview { flex-direction: column; text-align: center; } }
        @media (max-width: 600px) {
            .books-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .book-card { padding: 10px; }
            .book-card img { height: 140px; }
            .book-card h3 { font-size: 0.85rem; }
            .container { padding: 10px; }
            .btn-action { padding: 10px 12px; font-size: 0.85rem; }
            .pagination a, .pagination span { padding: 5px 10px; font-size: 0.8rem; min-width: 30px; }
            .rating-section { padding: 25px 15px; margin: 40px 10px 0; }
            .site-title { font-size: 1rem; gap: 6px; }
            .current-category-bar { padding: 8px 12px; font-size: 0.8rem; }
            .current-category-label { font-size: 0.8rem; }
            .back-to-all { padding: 5px 12px; font-size: 0.75rem; }
            .rating-overview { flex-direction: column; align-items: stretch; padding: 15px; }
            .rating-big-score { display: flex; align-items: center; justify-content: center; gap: 15px; flex-wrap: wrap; margin-bottom: 10px; }
            .rating-big-score .big-number { font-size: 2.5rem; }
            .rating-big-score .stars { font-size: 1rem; }
            .rating-bars { width: 100%; }
            .rating-bar-row { gap: 6px; font-size: 0.8rem; }
            .rating-bar-row .stars-label { min-width: 25px; font-size: 0.7rem; }
            .rating-bar-row .bar-bg { height: 6px; }
            .rating-bar-row .bar-count { font-size: 0.65rem; min-width: 20px; }
            .star-rating-input { gap: 8px; }
            .star-rating-input label { font-size: 2rem; }
            .star-rating-input .star-number { font-size: 0.6rem; padding: 1px 6px; }
        }
        @media (max-width: 768px) { .nav-tools { display: none; } .mobile-nav { display: flex; padding: 8px 5px; gap: 2px; } .mobile-nav a { font-size: 0.65rem; gap: 2px; padding: 5px 2px; } .mobile-nav a i { font-size: 1rem; } .page-wrapper { padding-bottom: 100px; } .site-footer { margin-bottom: 60px; } }
        @media (min-width: 1200px) { .books-grid { grid-template-columns: repeat(4, 1fr); } }
    </style>
</head>
<body>
<div class="page-wrapper">

<div id="youtubeSearchOverlay" class="search-overlay">
    <div class="search-overlay-header">
        <button id="closeSearchBtn" class="search-back-btn"><i class="fas fa-arrow-left"></i></button>
        <form id="searchForm" action="search.php" method="GET" class="search-overlay-form">
            <input type="text" name="query" id="searchInputField" placeholder="ابحث عن كتاب، رواية، أو تصنيف..." autocomplete="off" required>
            <button type="submit" class="search-overlay-submit"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <div class="search-overlay-history">
        <h3><span><i class="fas fa-clock" style="margin-left: 5px;"></i> عمليات البحث الأخيرة</span><span id="clearAllHistory" style="font-size: 0.8rem; color: #ff4757; cursor: pointer; font-weight: normal;">مسح الكل</span></h3>
        <ul id="historyList"></ul>
    </div>
</div>

<aside class="sidebar">
    <button class="close-sidebar-btn" onclick="document.querySelector('.sidebar').classList.remove('active')"><i class="fas fa-times"></i></button>
    <div class="theme-selector-wrapper">
        <div class="theme-toggle-btn" onclick="toggleColorPalette()">
            <span><i class="fas fa-palette" style="margin-left: 5px; color: var(--primary);"></i></span>
            <i class="fas fa-chevron-down" id="paletteArrow"></i>
        </div>
        <div class="theme-colors-container" id="colorPalette">
            <span class="color-dot" style="background-color: #000000;" onclick="changeTheme('#000000')"></span>
            <span class="color-dot" style="background-color: #ffd700;" onclick="changeTheme('#ffd700')"></span>
            <span class="color-dot" style="background-color: #009688;" onclick="changeTheme('#009688')"></span>
            <span class="color-dot" style="background-color: #ff4757;" onclick="changeTheme('#ff4757')"></span>
            <span class="color-dot" style="background-color: #ff9248;" onclick="changeTheme('#ff9248')"></span>
            <span class="color-dot" style="background-color: #ffa502;" onclick="changeTheme('#ffa502')"></span>
            <span class="color-dot" style="background-color: #2ed573;" onclick="changeTheme('#2ed573')"></span>
            <span class="color-dot" style="background-color: #1e90ff;" onclick="changeTheme('#1e90ff')"></span>
            <span class="color-dot" style="background-color: #747d8c;" onclick="changeTheme('#747d8c')"></span>
            <span class="color-dot" style="background-color: #8e44ad;" onclick="changeTheme('#8e44ad')"></span>
        </div>
    </div>
    <hr style="border:0; border-top:1px solid rgba(0,0,0,0.05); margin:15px 0;">
    <a href="authors.php" style="background: linear-gradient(135deg, var(--primary), #20b2aa); color: white !important; font-weight: bold; border-radius: 12px; margin-bottom: 10px; text-align: center; display:block; padding:12px;">
        <i class="fas fa-users"></i> جميع المؤلفين
    </a>
    <h3><i class="fas fa-layer-group"></i> التصنيفات</h3>
    <a href="index.php" style="background: var(--bg); font-weight:bold;"><i class="fas fa-border-all"></i> الكل</a>
    <h4 style="margin:15px 10px 5px; color:#666; font-size:0.9rem; border-bottom:1px dashed #ccc;">📚 الروايات</h4>
    <a href="?cat=رومنسية">روايات رومنسية</a>
    <a href="?cat=فانتازيا">رواية فانتازيا وخيال علمي</a>
    <a href="?cat=حقيقية">روايات حقيقية</a>
    <a href="?cat=عربية">روايات عربية</a>
    <a href="?cat=الأدب">الأدب والشعر</a>
    <a href="?cat=رعب">روايات رعب</a>
    <a href="?cat=بوليسية">روايات بوليسية</a>
    <a href="?cat=مترجمة">روايات مترجمة</a>
    <a href="?cat=تاريخ">روايات تاريخية</a>
    <a href="?cat=كوميدية">روايات كوميدية</a>
    <h4 style="margin:15px 10px 5px; color:#666; font-size:0.9rem; border-bottom:1px dashed #ccc;">📖 قصص</h4>
    <a href="?cat=قصص_قصيرة">قصص قصيرة</a>
    <a href="?cat=قصص_أطفال">قصص أطفال</a>
    <a href="?cat=قصص_واقعية">قصص واقعية</a>
    <a href="?cat=قصص_بوليسية">قصص بوليسية</a>
    <a href="?cat=قصص_رعب">قصص رعب</a>
    <h4 style="margin:15px 10px 5px; color:#666; font-size:0.9rem; border-bottom:1px dashed #ccc;">💡 كتب</h4>
    <a href="?cat=تنمية">كتب تنمية بشرية</a>
    <a href="?cat=علم_نفس">كتب علم النفس</a>
    <a href="?cat=دينية">كتب دينية</a>
    <a href="?cat=سير">سير ذاتية ومذكرات</a>
    <a href="?cat=فلسفة">فلسفة ومنطق</a>
    <a href="?cat=تاريخ_كتب">كتب تاريخ وحضارات</a>
    <a href="?cat=سياسة">سياسة واقتصاد</a>
    <a href="?cat=تربية_أطفال">كتب تربية الأطفال</a>
    <a href="?cat=تطوير_علاقات">كتب تطوير العلاقات</a>
    <a href="?cat=لغة_جسد">كتب تعلم لغة الجسد</a>
    <h4 style="margin:15px 10px 5px; color:#666; font-size:0.9rem; border-bottom:1px dashed #ccc;">🌍 تعلم اللغات</h4>
    <a href="?cat=تعلم_إنجليزية">كتب تعلم اللغة الإنجليزية</a>
    <a href="?cat=تعلم_فرنسية">كتب تعلم اللغة الفرنسية</a>
    <a href="?cat=تعلم_تركية">كتب تعلم اللغة التركية</a>
    <a href="?cat=تعلم_عربية">كتب تعلم اللغة العربية</a>
    <h4 style="margin:15px 10px 5px; color:#666; font-size:0.9rem; border-bottom:1px dashed #ccc;">🎓 دراسة وبرمجة</h4>
    <a href="?cat=لغات_برمجة">كتب لغات البرمجة</a>
    <a href="?cat=جبر">كتب الجبر</a>
    <a href="?cat=رياضيات">كتب رياضيات</a>
    <a href="?cat=اناليز">كتب اناليز</a>
    <a href="?cat=خوارزميات">كتب تعلم الخوارزميات</a>
    <a href="?cat=ذكاء_اصطناعي">ذكاء اصطناعي</a>
    <a href="?cat=شبكات">شبكات وأمن سيبراني</a>
    <a href="?cat=نظم_التشغيل">كتب تعلم نظم التشغيل</a>
    <h4 style="margin:15px 10px 5px; color:#666; font-size:0.9rem; border-bottom:1px dashed #ccc;">📰 مجلات</h4>
    <a href="?cat=مجلات_علمية">مجلات علمية</a>
    <a href="?cat=مجلات_تقنية">مجلات تقنية</a>
    <a href="?cat=مجلات_أدبية">مجلات أدبية</a>
    <a href="?cat=مجلات_أطفال">مجلات أطفال</a>
    <h4 style="margin:15px 10px 5px; color:#666; font-size:0.9rem; border-bottom:1px dashed #ccc;">✨ المزيد</h4>
    <a href="?cat=مانجا">مانجا وكوميكس</a>
    <a href="?cat=طبخ">طبخ وتغذية</a>
    <a href="?cat=صحة">صحة ورشاقة</a>
    <a href="?cat=أعمال">إدارة أعمال وتسويق</a>
    <a href="?cat=قانون">قانون وحقوق</a>
    <a href="?cat=رحلات">أدب رحلات</a>
</aside>

<div class="main-content">
    <header>
        <div class="header-top">
            <button style="border:none; background:none; font-size:1.3rem; cursor:pointer; color:var(--text);" onclick="document.querySelector('.sidebar').classList.add('active')">
                <i class="fas fa-bars"></i>
            </button>
            <div class="site-title"><span>Book Universe</span></div>
            <div class="left-header-actions">
                <a href="my-cart.php" style="color: var(--text); font-size: 1.2rem; position: relative;">
                    <i class="fas fa-shopping-cart"></i>
                    <span id="cartCount" style="position: absolute; top: -8px; right: -8px; background: #ff4757; color: white; font-size: 0.65rem; width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">0</span>
                </a>
                <a href="subscribe.php" class="vip-upgrade-btn"><i class="fas fa-crown"></i> ترقية</a>
                <a href="explore.php" class="btn"><i class="fas fa-users"></i></a>
                <button onclick="toggleDarkMode()" style="border:none; background:none; font-size:1.2rem; cursor:pointer; color:var(--text);">
                    <i class="fas fa-moon"></i>
                </button>
            </div>
        </div>
        <?php if ($category != ''): ?>
        <div class="current-category-bar">
            <div class="current-category-label">
                <i class="fas fa-folder-open"></i>
                <?php echo htmlspecialchars(getCategoryName($category)); ?>
                <span class="category-page-info">(<?php echo (int)$total_books; ?> كتاب)</span>
            </div>
            <a href="index.php" class="back-to-all"><i class="fas fa-home"></i> كل الكتب</a>
        </div>
        <?php endif; ?>
        <div class="nav-tools">
            <a class="openSearchTrigger"><i class="fas fa-search"></i> بحث</a>
            <a href="favorites.php"><i class="fas fa-heart"></i> المفضلة</a>
            <a href="saved.php"><i class="fas fa-bookmark"></i> المحفوظات</a>
            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="my-cart.php"><i class="fas fa-shopping-cart"></i> مشترياتي</a>
                <a href="profile.php"><i class="fas fa-user-circle"></i> حسابي</a>
                <a href="logout.php" style="color: #ff4757;"><i class="fas fa-sign-out-alt"></i> خروج</a>
            <?php else: ?>
                <a href="login.php"><i class="fas fa-sign-in-alt"></i> دخول</a>
            <?php endif; ?>
            <?php if(isset($_SESSION['role']) && trim($_SESSION['role']) == 'admin'): ?>
                <a href="add_book.php" style="color: var(--primary);"><i class="fas fa-plus-circle"></i> إضافة كتاب</a>
            <?php endif; ?>
        </div>
    </header>

    <div class="container">
        <div class="books-grid">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <div class="book-card">
                        <?php
                        $cover = !empty($row['cover_image']) ? $row['cover_image'] : 'https://via.placeholder.com/200x300?text=No+Cover';
                        ?>
                        <img src="<?php echo htmlspecialchars($cover, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($row['title']); ?>" onerror="this.src='https://via.placeholder.com/200x300?text=No+Cover'">
                        <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                        <div class="card-actions">
                            <a class="btn-action" href="book.php?id=<?php echo (int)$row['ID']; ?>">
                                <i class="fas fa-book-open"></i> التفاصيل
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align: center; width: 100%;">لا توجد كتب في هذا التصنيف حالياً.</p>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($current_page > 1): ?>
                <a href="?page=<?php echo $current_page - 1; ?><?php echo $category ? '&cat='.urlencode($category) : ''; ?>"><i class="fas fa-chevron-right"></i> السابق</a>
            <?php else: ?>
                <span class="disabled"><i class="fas fa-chevron-right"></i> السابق</span>
            <?php endif; ?>
            <?php
            $start = max(1, $current_page - 2);
            $end = min($total_pages, $current_page + 2);
            if ($start > 1) {
                echo '<a href="?page=1'.($category ? '&cat='.urlencode($category) : '').'">1</a>';
                if ($start > 2) echo '<span>...</span>';
            }
            for ($i = $start; $i <= $end; $i++):
            ?>
                <a href="?page=<?php echo $i; ?><?php echo $category ? '&cat='.urlencode($category) : ''; ?>" class="<?php echo $i == $current_page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor;
            if ($end < $total_pages) {
                if ($end < $total_pages - 1) echo '<span>...</span>';
                echo '<a href="?page='.$total_pages.($category ? '&cat='.urlencode($category) : '').'">'.$total_pages.'</a>';
            }
            ?>
            <?php if ($current_page < $total_pages): ?>
                <a href="?page=<?php echo $current_page + 1; ?><?php echo $category ? '&cat='.urlencode($category) : ''; ?>">التالي <i class="fas fa-chevron-left"></i></a>
            <?php else: ?>
                <span class="disabled">التالي <i class="fas fa-chevron-left"></i></span>
            <?php endif; ?>
            <span class="page-info">صفحة <?php echo $current_page; ?> من <?php echo $total_pages; ?></span>
        </div>
        <?php endif; ?>

        <!-- ===== RATING SECTION ===== -->
        <div class="rating-section">
            <div class="rating-header">
                <h2>🌟 تقييم المكتبة</h2>
            </div>
            <div class="rating-overview">
                <div class="rating-big-score">
                    <div class="big-number"><?php echo number_format((float)$avg_rating, 1); ?></div>
                    <div class="stars">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star" style="color: <?php echo $i <= round($avg_rating) ? '#ffa502' : '#ddd'; ?>;"></i>
                        <?php endfor; ?>
                    </div>
                    <div class="count"><?php echo (int)$total_ratings; ?> تقييم</div>
                </div>
                <div class="rating-bars">
                    <?php
                    $stars_data = [
                        5 => $five_star,
                        4 => $four_star,
                        3 => $three_star,
                        2 => $two_star,
                        1 => $one_star,
                    ];
                    foreach ($stars_data as $star => $count):
                        $percent = $total_ratings > 0 ? ($count / $total_ratings) * 100 : 0;
                    ?>
                    <div class="rating-bar-row">
                        <span class="stars-label"><?php echo $star; ?> ★</span>
                        <div class="bar-bg"><div class="bar-fill" style="width: <?php echo $percent; ?>%"></div></div>
                        <span class="bar-count"><?php echo (int)$count; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="rating-form-container">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($user_comment !== null && $user_rating !== null): ?>
                        <div id="thankYouMessage" class="thank-you-message">
                            <i class="fas fa-check-circle"></i>
                            <h3>شكراً لتقييمك! 🌟</h3>
                            <p>تم تسجيل تقييمك بنجاح</p>
                        </div>
                    <?php else: ?>
                        <h3>أضف تقييمك</h3>
                        <form method="POST" id="ratingForm">
                            <div class="star-rating-input">
                                <input type="radio" id="star5" name="rating" value="5" required><label for="star5"><i class="fas fa-star"></i><span class="star-number">5</span></label>
                                <input type="radio" id="star4" name="rating" value="4"><label for="star4"><i class="fas fa-star"></i><span class="star-number">4</span></label>
                                <input type="radio" id="star3" name="rating" value="3"><label for="star3"><i class="fas fa-star"></i><span class="star-number">3</span></label>
                                <input type="radio" id="star2" name="rating" value="2"><label for="star2"><i class="fas fa-star"></i><span class="star-number">2</span></label>
                                <input type="radio" id="star1" name="rating" value="1"><label for="star1"><i class="fas fa-star"></i><span class="star-number">1</span></label>
                            </div>
                            <textarea name="comment" class="rating-comment" rows="3" placeholder="اكتب تعليقك هنا..."></textarea>
                            <button type="submit" name="submit_rating" class="submit-rating-btn">
                                <i class="fas fa-paper-plane"></i> إرسال التقييم
                            </button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <div id="loginMessage" style="text-align:center; padding:20px;">
                        <p style="font-size:1rem; opacity:0.7;">🔒 يرجى <a href="login.php" style="color:var(--primary); font-weight:bold;">تسجيل الدخول</a> لإضافة تقييمك</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ===== FOOTER ===== -->
<footer class="site-footer">
    <div class="footer-content">
        <h3>📚 Book <span>Universe</span></h3>
        <p>مكتبتك الرقمية الشاملة - مئات الكتب والروايات في مكان واحد</p>
        <div class="social-icons">
            <a href="#" target="_blank" rel="noopener noreferrer" class="social-icon social-linkedin" title="لينكد إن">
                <i class="fab fa-linkedin-in"></i>
            </a>
        </div>
        <div class="footer-bottom">
            <p style="font-size: 0.9rem; margin-bottom: 6px;">
                <span style="font-weight: 700; color: var(--primary);">Developed by BEN MIR IKRAM</span>
                <span style="color: var(--text); opacity: 0.6;">© 2026</span>
            </p>
            <p style="font-size: 0.8rem; color: var(--text); opacity: 0.6; margin: 4px 0;">
                <i class="fas fa-inbox" style="margin-left: 5px;"></i>
                للتواصل أو الاستفسار عبر البريد
            </p>
            <p style="font-size: 0.8rem; color: var(--text); opacity: 0.4; margin: 6px 0 0;">
                <i class="fas fa-copyright" style="font-size: 0.7rem;"></i>
                جميع الحقوق محفوظة 2026
            </p>
        </div>
    </div>
</footer>
</div>

<nav class="mobile-nav">
    <a href="index.php"><i class="fas fa-home"></i> الرئيسية</a>
    <a class="openSearchTrigger" style="cursor:pointer;"><i class="fas fa-search"></i> بحث</a>
    <a href="favorites.php"><i class="fas fa-heart"></i> المفضلة</a>
    <a href="saved.php"><i class="fas fa-bookmark"></i> المحفوظات</a>
    <?php if(isset($_SESSION['user_id'])): ?>
        <a href="profile.php"><i class="fas fa-user-check"></i> حسابي</a>
        <a href="logout.php" style="color: #ff4757;"><i class="fas fa-sign-out-alt"></i> خروج</a>
    <?php else: ?>
        <a href="login.php"><i class="fas fa-user"></i> دخول</a>
    <?php endif; ?>
    <?php if(isset($_SESSION['role']) && trim($_SESSION['role']) == 'admin'): ?>
        <a href="add_book.php"><i class="fas fa-plus-circle"></i> إضافة</a>
    <?php endif; ?>
</nav>

<script>
function toggleColorPalette() {
    const palette = document.getElementById('colorPalette');
    const arrow = document.getElementById('paletteArrow');
    palette.classList.toggle('open');
    if(palette.classList.contains('open')) {
        arrow.classList.replace('fa-chevron-down', 'fa-chevron-up');
    } else {
        arrow.classList.replace('fa-chevron-up', 'fa-chevron-down');
    }
}
function changeTheme(color) {
    document.documentElement.style.setProperty('--primary', color);
    localStorage.setItem('user-theme-color', color);
}
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    let themeMeta = document.querySelector('meta[name="theme-color"]');
    if (themeMeta) {
        themeMeta.setAttribute('content', document.body.classList.contains('dark-mode') ? '#121212' : '#ffffff');
    }
    localStorage.setItem('dark-mode', document.body.classList.contains('dark-mode'));
}
function updateCartCount() {
    const cart = JSON.parse(localStorage.getItem('myCart') || '[]');
    const countEl = document.getElementById('cartCount');
    if (countEl) {
        countEl.textContent = cart.length;
        countEl.style.display = cart.length > 0 ? 'flex' : 'none';
    }
}
updateCartCount();
function renderHistory() {
    let history = JSON.parse(localStorage.getItem('search-history')) || [];
    const historyList = document.getElementById('historyList');
    historyList.innerHTML = '';
    if (history.length === 0) {
        historyList.innerHTML = '<p style="text-align:center; color:#888; font-size:0.9rem;">لا توجد عمليات بحث مؤخراً.</p>';
        return;
    }
    history.forEach((term, index) => {
        const li = document.createElement('li');
        li.innerHTML = `
            <i class="fas fa-history"></i>
            <a href="search.php?query=${encodeURIComponent(term)}">${term.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</a>
            <button class="delete-search-item" onclick="deleteHistoryItem(${index})"><i class="fas fa-times"></i></button>
        `;
        historyList.appendChild(li);
    });
}
window.deleteHistoryItem = function(index) {
    let history = JSON.parse(localStorage.getItem('search-history')) || [];
    history.splice(index, 1);
    localStorage.setItem('search-history', JSON.stringify(history));
    renderHistory();
};
document.addEventListener("DOMContentLoaded", function() {
    const savedColor = localStorage.getItem('user-theme-color');
    if (savedColor) document.documentElement.style.setProperty('--primary', savedColor);
    if (localStorage.getItem('dark-mode') === 'true') {
        document.body.classList.add('dark-mode');
        document.querySelector('meta[name="theme-color"]')?.setAttribute('content', '#121212');
    }
    renderHistory();
    const triggers = document.querySelectorAll(".openSearchTrigger");
    const overlay = document.getElementById("youtubeSearchOverlay");
    const searchInputField = document.getElementById("searchInputField");
    triggers.forEach(trigger => {
        trigger.addEventListener("click", function(e) {
            e.preventDefault();
            overlay.style.display = "flex";
            renderHistory();
            if (searchInputField) searchInputField.focus();
        });
    });
    document.getElementById("closeSearchBtn")?.addEventListener("click", function() {
        overlay.style.display = "none";
    });
    document.getElementById('searchForm')?.addEventListener('submit', function(e) {
        let term = document.getElementById('searchInputField').value.trim();
        if (term) {
            let history = JSON.parse(localStorage.getItem('search-history')) || [];
            history = history.filter(item => item !== term);
            history.unshift(term);
            if(history.length > 8) history.pop();
            localStorage.setItem('search-history', JSON.stringify(history));
        }
    });
    document.getElementById('clearAllHistory')?.addEventListener('click', function() {
        localStorage.removeItem('search-history');
        renderHistory();
    });
});
</script>
</body>
</html>
