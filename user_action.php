<?php
session_start();
$pdo = new PDO("mysql:host=localhost;dbname=survey_db;charset=utf8","root","");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$user_id = $_SESSION['user_id'] ?? 0;
$username = $_SESSION['username'] ?? '';
$can_edit = $_SESSION['can_edit'] ?? 0;

// المحاولة لأي عملية
if(isset($_GET['action']) && $user_id){
    $action = $_GET['action'];
    if(!$can_edit){
        // إرسال طلب للمدير
        $title = "المستخدم $username طلب صلاحية: $action";
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, username, title) VALUES (?,?,?)");
        $stmt->execute([$user_id,$username,$title]);
        $msg = "⚠️ لا يمكنك تنفيذ $action بدون صلاحية. تم إرسال طلب للمدير.";
    } else {
        // تنفيذ العملية مباشرة (تعديل، حذف، دخول مكان)
        $msg = "✅ تم تنفيذ $action بنجاح!";
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>صفحة المستخدم</title>
<style>
body{font-family:sans-serif;padding:20px;background:#f5f5f5;}
a{display:block;margin:10px 0;color:#fff;background:#4CAF50;padding:10px;width:200px;text-decoration:none;text-align:center;border-radius:5px;}
.msg{margin:10px 0;color:blue;font-weight:bold;}
</style>
</head>
<body>

<h2>مرحبا <?php echo htmlspecialchars($username); ?></h2>
<p>صلاحياتك الحالية: <?php echo $can_edit ? "يمكنك التعديل والدخول" : "مستخدم عادي"; ?></p>

<?php if(!empty($msg)) echo "<p class='msg'>$msg</p>"; ?>

<h3>اختيار العملية:</h3>
<a href="?action=تعديل بيانات">تعديل بيانات</a>
<a href="?action=حذف بيانات">حذف بيانات</a>
<a href="?action=دخول مكان محظور">دخول مكان محظور</a>

<a href="logout.php">تسجيل الخروج</a>

</body>
</html>