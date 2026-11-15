<?php
// ===== إعدادات الأخطاء =====
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');

session_start();

// ===== مناداة ملف الاتصال =====
require_once 'config.php';



// معالجة إضافة الكتاب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_book'])) {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $author = trim($_POST['author'] ?? '');
    
    // حقول اختيارية
    $description = trim($_POST['description'] ?? '');
    $text_summary = trim($_POST['text_summary'] ?? '');
    $pages_count = trim($_POST['pages_count'] ?? '');
    $publisher = trim($_POST['publisher'] ?? '');
    $publish_date = trim($_POST['publish_date'] ?? '');
    $isbn = trim($_POST['isbn'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $cover_image = trim($_POST['cover_image'] ?? '');
    $buy_link = trim($_POST['buy_link'] ?? '');
    $download_link = trim($_POST['download_link'] ?? '');
    $read_link = trim($_POST['read_link'] ?? '');
    $audio_link = trim($_POST['audio_link'] ?? '');
    $author_image = trim($_POST['author_image'] ?? '');
    $author_bio = trim($_POST['author_bio'] ?? '');
    $quotes = trim($_POST['quotes'] ?? '');
    
    // قيم افتراضية ثابتة = صفر
    $initial_rating = 0;
    $initial_reviews_count = 0;
    
    // التحقق من الحقول الإجبارية فقط
    $errors = [];
    
    if (empty($title)) {
        $errors[] = 'عنوان الكتاب مطلوب';
    }
    
    if (empty($category)) {
        $errors[] = 'التصنيف مطلوب';
    }
    
    if (!empty($errors)) {
        $message = '❌ ' . implode('<br>', array_map('htmlspecialchars', $errors));
        $messageType = 'error';
    } else {
        $stmt = $conn->prepare("INSERT INTO novels (title, category, author, description, text_summary, pages_count, publisher, publish_date, isbn, price, cover_image, buy_link, download_link, read_link, audio_link, rating, reviews_count, author_image, author_bio, quotes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt) {
            $stmt->bind_param("ssssssssssssssssssss", 
                $title, $category, $author, $description, $text_summary, 
                $pages_count, $publisher, $publish_date, $isbn, $price, 
                $cover_image, $buy_link, $download_link, $read_link, $audio_link,
                $initial_rating, $initial_reviews_count, $author_image, $author_bio, $quotes
            );
            
            if ($stmt->execute()) {
                $new_book_id = $stmt->insert_id;
                $message = '✅ تمت إضافة الكتاب بنجاح! (ID: ' . (int)$new_book_id . ')';
                $messageType = 'success';
            } else {
                error_log('Add book failed: ' . $stmt->error);
                $message = '❌ خطأ في الإضافة، يرجى المحاولة لاحقاً.';
                $messageType = 'error';
            }
            $stmt->close();
        } else {
            error_log('Prepare failed: ' . $conn->error);
            $message = '❌ خطأ في تحضير الاستعلام.';
            $messageType = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة كتاب جديد | Book Universe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #009688;
            --bg: #f5f7fb;
            --card: #ffffff;
            --text: #111827;
            --muted: #6b7280;
            --border: #e5e7eb;
            --danger: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            padding: 20px;
        }
        
        .container { max-width: 900px; margin: 0 auto; }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: var(--card);
            padding: 20px;
            border-radius: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 900;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--primary);
        }
        
        .btn-back {
            background: var(--bg);
            color: var(--text);
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
            border: 1px solid var(--border);
        }
        
        .btn-back:hover { background: var(--border); }
        
        .message {
            padding: 15px 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .message.success {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }
        
        .message.error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
            padding: 15px 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-card {
            background: var(--card);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px dashed var(--border);
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 900;
            margin-bottom: 20px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i { font-size: 20px; }
        
        .optional-badge {
            font-size: 11px;
            background: #fef3c7;
            color: #92400e;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 700;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group { margin-bottom: 15px; }
        
        .form-group.full-width { grid-column: 1 / -1; }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
            font-size: 14px;
            color: var(--text);
        }
        
        .form-group label i {
            color: var(--primary);
            margin-left: 5px;
            width: 20px;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-family: 'Cairo', sans-serif;
            font-size: 14px;
            color: var(--text);
            background: var(--bg);
            transition: 0.3s;
            outline: none;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(0, 150, 136, 0.1);
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: var(--muted);
            font-size: 12px;
        }
        
        .form-group input[required],
        .form-group select[required] {
            border-color: #93c5fd;
            background: #fafeff;
        }
        
        .required {
            color: var(--danger);
            font-weight: 900;
            margin-right: 3px;
        }
        
        .auto-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #dcfce7;
            color: #166534;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 18px;
            font-weight: 900;
            cursor: pointer;
            transition: 0.3s;
            font-family: 'Cairo', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 150, 136, 0.3);
        }
        
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .header { flex-direction: column; gap: 15px; text-align: center; }
            .form-card { padding: 20px; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>
            <i class="fas fa-plus-circle"></i>
            إضافة كتاب جديد
        </h1>
        <a href="index.php" class="btn-back">
            <i class="fas fa-arrow-right"></i>
            العودة للمكتبة
        </a>
    </div>
    
    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <div class="info-box">
        <i class="fas fa-info-circle"></i>
        <span>سيتم إضافة الكتاب تلقائياً بتقييم <strong>0</strong> وعدد تقييمات <strong>0</strong>. يمكن للزوار تقييم الكتاب لاحقاً.</span>
    </div>
    
    <form method="POST" class="form-card">
        <div class="form-section">
            <div class="section-title">
                <i class="fas fa-star" style="color: #f59e0b;"></i>
                معلومات إجبارية
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fas fa-book"></i> عنوان الكتاب <span class="required">*</span></label>
                    <input type="text" name="title" placeholder="أدخل عنوان الكتاب" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-layer-group"></i> التصنيف <span class="required">*</span></label>
                    <select name="category" required>
                        <option value="">اختر التصنيف</option>
                        <optgroup label="📚 الروايات">
                            <option value="رومنسية">روايات رومنسية</option>
                            <option value="فانتازيا">روايات فانتازيا وخيال علمي</option>
                            <option value="حقيقية">روايات حقيقية</option>
                            <option value="عربية">روايات عربية</option>
                            <option value="الأدب">الأدب والشعر</option>
                            <option value="رعب">روايات رعب</option>
                            <option value="بوليسية">روايات بوليسية</option>
                            <option value="مترجمة">روايات مترجمة</option>
                            <option value="تاريخ">روايات تاريخية</option>
                            <option value="كوميدية">روايات كوميدية</option>
                        </optgroup>
                        <optgroup label="📖 قصص">
                            <option value="قصص_قصيرة">قصص قصيرة</option>
                            <option value="قصص_أطفال">قصص أطفال</option>
                            <option value="قصص_واقعية">قصص واقعية</option>
                            <option value="قصص_بوليسية">قصص بوليسية</option>
                            <option value="قصص_رعب">قصص رعب</option>
                        </optgroup>
                        <optgroup label="💡 كتب">
                            <option value="تنمية">كتب تنمية بشرية</option>
                            <option value="علم_نفس">كتب علم النفس</option>
                            <option value="دينية">كتب دينية</option>
                            <option value="سير">سير ذاتية ومذكرات</option>
                            <option value="فلسفة">فلسفة ومنطق</option>
                            <option value="تاريخ_كتب">كتب تاريخ وحضارات</option>
                            <option value="سياسة">سياسة واقتصاد</option>
                            <option value="تربية_أطفال">كتب تربية الأطفال</option>
                            <option value="تطوير_علاقات">كتب تطوير العلاقات</option>
                            <option value="لغة_جسد">كتب تعلم لغة الجسد</option>
                        </optgroup>
                        <optgroup label="🌍 تعلم اللغات">
                            <option value="تعلم_إنجليزية">كتب تعلم اللغة الإنجليزية</option>
                            <option value="تعلم_فرنسية">كتب تعلم اللغة الفرنسية</option>
                            <option value="تعلم_تركية">كتب تعلم اللغة التركية</option>
                            <option value="تعلم_عربية">كتب تعلم اللغة العربية</option>
                        </optgroup>
                        <optgroup label="🎓 دراسة وبرمجة">
                            <option value="لغات_برمجة">كتب لغات البرمجة</option>
                            <option value="جبر">كتب الجبر</option>
                            <option value="رياضيات">كتب رياضيات</option>
                            <option value="اناليز">كتب اناليز</option>
                            <option value="خوارزميات">كتب تعلم الخوارزميات</option>
                            <option value="ذكاء_اصطناعي">ذكاء اصطناعي</option>
                            <option value="شبكات">شبكات وأمن سيبراني</option>
                        </optgroup>
                        <option value="نظم التشغيل">كتب تعلم نظم التشغيل</option>
                        <optgroup label="📰 مجلات">
                            <option value="مجلات_علمية">مجلات علمية</option>
                            <option value="مجلات_تقنية">مجلات تقنية</option>
                            <option value="مجلات_أدبية">مجلات أدبية</option>
                            <option value="مجلات_أطفال">مجلات أطفال</option>
                        </optgroup>
                        <optgroup label="✨ المزيد">
                            <option value="مانجا">مانجا وكوميكس</option>
                            <option value="طبخ">طبخ وتغذية</option>
                            <option value="صحة">صحة ورشاقة</option>
                            <option value="أعمال">إدارة أعمال وتسويق</option>
                            <option value="قانون">قانون وحقوق</option>
                            <option value="رحلات">أدب رحلات</option>
                        </optgroup>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-user-pen"></i> اسم الكاتب</label>
                    <input type="text" name="author" placeholder="أدخل اسم الكاتب (اختياري)">
                </div>
            </div>
        </div>
        
        <div class="form-section">
            <div class="section-title">
                <i class="fas fa-user-tie"></i>
                عن الكاتب
                <span class="optional-badge">اختياري</span>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fas fa-camera"></i> رابط صورة الكاتب</label>
                    <input type="url" name="author_image" placeholder="https://example.com/author-image.jpg">
                    <small>أدخل رابط مباشر لصورة الكاتب</small>
                </div>
                
                <div class="form-group full-width">
                    <label><i class="fas fa-quote-right"></i> نبذة عن الكاتب</label>
                    <textarea name="author_bio" placeholder="اكتب نبذة تعريفية عن الكاتب..."></textarea>
                    <small>سيرة ذاتية مختصرة للكاتب</small>
                </div>
            </div>
        </div>
        
        <div class="form-section">
            <div class="section-title">
                <i class="fas fa-align-left"></i>
                وصف الكتاب
                <span class="optional-badge">اختياري</span>
            </div>
            <div class="form-grid">
                <div class="form-group full-width">
                    <label><i class="fas fa-info-circle"></i> وصف الكتاب</label>
                    <textarea name="description" placeholder="أدخل وصفاً مفصلاً للكتاب..." style="min-height: 150px;"></textarea>
                </div>
                
                <div class="form-group full-width">
                    <label><i class="fas fa-sparkles"></i> ملخص سريع</label>
                    <textarea name="text_summary" placeholder="ملخص قصير ومختصر للكتاب..." style="min-height: 80px;"></textarea>
                </div>
            </div>
        </div>
        
        <div class="form-section">
            <div class="section-title">
                <i class="fas fa-quote-right"></i>
                اقتباسات من الكتاب
                <span class="optional-badge">اختياري</span>
            </div>
            <div class="form-grid">
                <div class="form-group full-width">
                    <label><i class="fas fa-pen-fancy"></i> اقتباسات مميزة</label>
                    <textarea name="quotes" placeholder="أدخل اقتباسات من الكتاب... يمكنك إضافة أكثر من اقتباس مع وضع فاصل بينهم مثل: -- أو سطر فارغ" style="min-height: 130px;"></textarea>
                    <small>أضف أجمل الاقتباسات من الكتاب. استخدم <strong>--</strong> للفصل بين الاقتباسات أو اترك سطر فارغ.</small>
                </div>
            </div>
        </div>
        
        <div class="form-section">
            <div class="section-title">
                <i class="fas fa-circle-info"></i>
                معلومات إضافية
                <span class="optional-badge">اختياري</span>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fas fa-file-lines"></i> عدد الصفحات</label>
                    <input type="number" name="pages_count" placeholder="مثال: 350" min="1">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-building"></i> الناشر</label>
                    <input type="text" name="publisher" placeholder="اسم دار النشر">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> تاريخ النشر</label>
                    <input type="date" name="publish_date">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-barcode"></i> ISBN</label>
                    <input type="text" name="isbn" placeholder="978-...">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-dollar-sign"></i> السعر ($)</label>
                    <input type="number" name="price" placeholder="9.99" step="0.01" min="0">
                </div>
            </div>
        </div>
        
        <div class="form-section">
            <div class="section-title">
                <i class="fas fa-link"></i>
                الروابط والوسائط
                <span class="optional-badge">اختياري</span>
            </div>
            <div class="form-grid">
                <div class="form-group full-width">
                    <label><i class="fas fa-image"></i> رابط صورة الغلاف</label>
                    <input type="url" name="cover_image" placeholder="https://example.com/cover.jpg">
                    <small>يفضل صورة عمودية بنسبة 2:3</small>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-cart-shopping"></i> رابط الشراء</label>
                    <input type="url" name="buy_link" placeholder="https://...">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-download"></i> رابط التحميل</label>
                    <input type="url" name="download_link" placeholder="https://...">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-book-open"></i> رابط القراءة</label>
                    <input type="url" name="read_link" placeholder="https://...">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-headphones"></i> رابط الاستماع</label>
                    <input type="url" name="audio_link" placeholder="https://...">
                </div>
            </div>
        </div>
        
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 15px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-check-circle" style="color: #16a34a; font-size: 20px;"></i>
            <span style="font-size: 14px; color: #166534;">
                <strong>تلقائياً:</strong> سيبدأ الكتاب بـ <span class="auto-badge"><i class="fas fa-star"></i> 0</span> تقييم و <span class="auto-badge"><i class="fas fa-users"></i> 0</span> مراجعة
            </span>
        </div>
        
        <button type="submit" name="add_book" class="btn-submit">
            <i class="fas fa-plus-circle"></i>
            إضافة الكتاب إلى المكتبة
        </button>
    </form>
</div>

</body>
</html>
