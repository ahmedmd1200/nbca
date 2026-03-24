<?php
// ✅ بدء الجلسة بطريقة آمنة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    session_regenerate_id(true);
}

// 🔒 تحقق من تسجيل الدخول
if (!isset($_SESSION['username'])) {
    echo "<h2 style='color:red; text-align:center; margin-top:50px;'>⚠ الرجاء تسجيل الدخول للوصول إلى هذه الصفحة</h2>";
    exit();
}

// جلب بيانات الجلسة
$username = $_SESSION['username'];
$role = $_SESSION['role'] ?? 'user';
$user_id = $_SESSION['user_id'] ?? 0;

$is_admin_or_manager = in_array($role, ['admin','manager']);
$is_admin = ($role === 'admin');

try {
    // اتصال بقاعدة البيانات
    $pdo = new PDO(
        "mysql:host=fdb1031.runhosting.com;dbname=4728212_elhmamy;charset=utf8",
        "4728212_elhmamy",
        "0172301281m"
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 📊 الإحصائيات العامة
    $stmt = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM staff_list) AS total_staff,
            (SELECT COUNT(*) FROM surveys) AS total_surveys,
            (SELECT COUNT(*) FROM clients) AS total_clients,
            (SELECT COUNT(*) FROM messages) AS total_messages,
            (SELECT COUNT(*) FROM staff_search_results) AS total_searches
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 👨‍💼 إحصائيات الأخصائيين
    $stmt_specialists = $pdo->query("
        SELECT 
            s.id,
            s.staff_name AS username,
            s.role AS role,
            COUNT(sv.id) AS total_surveys,
            COUNT(CASE WHEN DATE(sv.created_at)=CURDATE() THEN 1 END) AS last_day_clients,
            COUNT(ssr.id) AS total_searches
        FROM staff_list s
        LEFT JOIN surveys sv ON s.id = sv.user_id
        LEFT JOIN staff_search_results ssr ON s.id = ssr.staff_id
        GROUP BY s.id, s.staff_name, s.role
        ORDER BY total_surveys DESC
    ");
    $specialists = $stmt_specialists->fetchAll(PDO::FETCH_ASSOC);

    // حساب المجاميع
    $total_surveys_sum = array_sum(array_column($specialists,'total_surveys'));
    $last_day_clients_sum = array_sum(array_column($specialists,'last_day_clients'));
    $total_searches_sum = array_sum(array_column($specialists,'total_searches'));

    // معالجة إضافة مستخدم جديد (خاص بالمدير الأعلى فقط)
    $message = "";
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_user'])) {
        if(!$is_admin){
            $message = "⚠️ فقط المدير الأعلى يمكنه إضافة مستخدمين";
        } else {
            $staff_name = trim($_POST['staff_name'] ?? '');
            $new_role = $_POST['new_role'] ?? 'user';
            if($staff_name){
                $stmt_insert = $pdo->prepare("INSERT INTO staff_list (staff_name, role) VALUES (:name,:role)");
                $stmt_insert->execute([':name'=>$staff_name,':role'=>$new_role]);
                $message = "✅ تم إضافة المستخدم بنجاح!";

                // تحديث الأخصائيين بعد الإضافة
                $stmt_specialists = $pdo->query("
                    SELECT 
                        s.id,
                        s.staff_name AS username,
                        s.role AS role,
                        COUNT(sv.id) AS total_surveys,
                        COUNT(CASE WHEN DATE(sv.created_at)=CURDATE() THEN 1 END) AS last_day_clients,
                        COUNT(ssr.id) AS total_searches
                    FROM staff_list s
                    LEFT JOIN surveys sv ON s.id = sv.user_id
                    LEFT JOIN staff_search_results ssr ON s.id = ssr.staff_id
                    GROUP BY s.id, s.staff_name, s.role
                    ORDER BY total_surveys DESC
                ");
                $specialists = $stmt_specialists->fetchAll(PDO::FETCH_ASSOC);
                $total_surveys_sum = array_sum(array_column($specialists,'total_surveys'));
                $last_day_clients_sum = array_sum(array_column($specialists,'last_day_clients'));
                $total_searches_sum = array_sum(array_column($specialists,'total_searches'));
            } else {
                $message = "⚠️ الرجاء إدخال اسم المستخدم";
            }
        }
    }

} catch(PDOException $e){
    // تسجيل الخطأ داخليًا وعدم كشفه للمستخدم
    error_log("Database connection failed: ".$e->getMessage());
    die("⚠ فشل الاتصال بالخادم، الرجاء المحاولة لاحقاً");
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>لوحة التحكم</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body{font-family:sans-serif;background:linear-gradient(135deg,#667eea,#764ba2);margin:0;padding:0;}
.container{max-width:1200px;margin:50px auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;}
.card{border-radius:15px;padding:20px;color:white;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;text-align:center;min-height:120px;transition:0.3s;text-decoration:none;}
.card:hover{transform:translateY(-3px);opacity:0.9;}
.card h4{margin-bottom:8px;font-size:16px;}
.card p{font-size:24px;font-weight:bold;margin:0;}
.card i{font-size:28px;margin-bottom:5px;}
.users{background:#f5576c;}
.surveys{background:#ff9f43;}
.clients{background:#1dd1a1;}
.messages{background:#54a0ff;}
.notifications{background:#5f27cd;}
.settings{background:#ee5253;}
.logout{
  position: fixed;
  top: 100px;
  right: 40px;
  background: #f093fb;
  color: white;
  border: none;
  padding: 8px 26px;
  border-radius: 18px;
  cursor: pointer;
  font-size: 20px;
}.logout:hover{background:#f5576c;}
.table-box{max-width:1200px;margin:30px auto;background:white;padding:20px;border-radius:15px;}
table{width:100%;border-collapse:collapse;}
th,td{padding:10px;border:1px solid #ddd;text-align:center;}
th{background:#f5576c;color:white;}
td.total_surveys{color:#3498db;font-weight:bold;}
td.last_day_clients{color:#27ae60;font-weight:bold;}
td.total_searches{color:#f39c12;font-weight:bold;}
tfoot td{font-weight:bold;}
.add-user-box{max-width:500px;margin:20px auto;background:white;padding:20px;border-radius:15px;}
input,select,button{width:100%;padding:10px;margin:5px 0;}
button{background:#3498db;color:white;border:none;border-radius:5px;cursor:pointer;}
button:hover{background:#2980b9;}
.message{color:green;font-weight:bold;margin:10px 0;}
        /* لون خاص لزر الازارار */
.buttons-special {
    background: #8e44ad; /* اللون الرئيسي للزر */
}
.buttons-special:hover {
    background: #71368a; /* اللون عند المرور بالماوس */
}
    .buttons-specil {
    background: navy; /* اللون الرئيسي للزر */
}
.buttons-specil:hover {
    background: #71368a; /* اللون عند المرور بالماوس */
}
        
</style>
</head>
<body>

<button class="logout" onclick="window.location.href='login.php'">تسجيل الخروج</button>

<h2 style="text-align:center;color:white;">مرحباً يا <?php echo htmlspecialchars($username); ?></h2>
<p style="text-align:center;color:white;">دورك في الموقع: <?php echo ($role==='admin'?'مدير':($role==='manager'?'مدير فرعي':'مستخدم')); ?></p>

<div class="container">
<a class="card users" href="<?php echo $is_admin_or_manager ? 'users.php' : '#'; ?>" 
onclick="<?php if(!$is_admin_or_manager) echo 'requestPermission()'; ?>">
<i class="fas fa-users"></i><h4>قائمه المستخدمين </h4><p><?php echo $stats['total_staff']; ?></p></a>

<a class="card surveys" href="go.php"><i class="fas fa-file-alt"></i><h4>الاستبيانات</h4><p><?php echo $stats['total_surveys']; ?></p></a>

<a class="card clients" href="pepol.php"><i class="fas fa-user-check"></i><h4>بحث العملاء الكل </h4><p><?php echo $stats['total_clients']; ?></p></a>

<a class="card clients buttons-specil " href="staff.php"><i class="fas fa-user-check"></i><h4>بحث بالاخصائي</h4><p><?php echo $stats['total_searches']; ?></p></a>
<a class="card clients buttons-special" href="zero.php">
  <i class="fas fa-user-check"></i>
  <h4>الازارار</h4>
  <p><?php echo $stats['total_searches']; ?></p>
</a><a class="card clients" href="view_surveys.php"><i class="fas fa-user-check"></i><h4>قائمه العملاء</h4><p><?php echo $stats['total_searches']; ?></p></a>

<a class="card messages" href="<?php echo $is_admin_or_manager ? 'formal.php' : '#'; ?>" 
onclick="<?php if(!$is_admin_or_manager) echo 'requestPermission()'; ?>">
<i class="fas fa-envelope"></i><h4>ادخال بيانات يوميه</h4><p><?php echo $stats['total_messages']; ?></p></a>
</div>

<div class="table-box">
<h2>إحصائية الأخصائيين</h2>
<table>
<thead>
<tr>
<th>اسم الأخصائي</th>
<th>الدور</th>
<th>عدد الاستبيانات</th>
<th>عدد العملاء في آخر يوم</th>
<th>عدد عمليات البحث</th>
</tr>
</thead>
<tbody>
<?php foreach($specialists as $sp): ?>
<tr>
<td><?php echo htmlspecialchars($sp['username']); ?></td>
<td><?php echo htmlspecialchars($sp['role']); ?></td>
<td class="total_surveys"><?php echo $sp['total_surveys']; ?></td>
<td class="last_day_clients"><?php echo $sp['last_day_clients']; ?></td>
<td class="total_searches"><?php echo $sp['total_searches']; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
<tfoot>
<tr>
<td>المجموع</td>
<td>-</td>
<td><?php echo $total_surveys_sum; ?></td>
<td><?php echo $last_day_clients_sum; ?></td>
<td><?php echo $total_searches_sum; ?></td>
</tr>
</tfoot>
</table>
</div>


<script>
function requestPermission(){
    alert("⚠️ ليس لديك صلاحية التعديل\nتم إرسال طلب للمدير");
    fetch("send_request.php")
    .then(response => response.json())
    .then(data => console.log(data))
    .catch(err => console.error("خطأ في إرسال الطلب:", err));
}
</script>

</body>
</html>
