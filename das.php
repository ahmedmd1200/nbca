<?php
// ✅ بدء الجلسة بطريقة آمنة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    session_regenerate_id(true); // حماية الجلسة من الاختطاف
}

// 🔒 تحقق من تسجيل الدخول
if (!isset($_SESSION['username'])) {
    echo "<h2 style='color:red; text-align:center; margin-top:50px;'>⚠ الرجاء تسجيل الدخول للوصول إلى هذه الصفحة</h2>";
    exit();
}

// جلب بيانات الجلسة
$username = $_SESSION['username'];
$role = $_SESSION['role'] ?? 'user'; // افتراضي: مستخدم عادي
$user_id = $_SESSION['user_id'] ?? 0;

$is_admin_or_manager = in_array($role, ['admin','manager']);
$is_admin = ($role === 'admin');

try {
    $pdo = new PDO("mysql:host=localhost;dbname=survey_db;charset=utf8","root","");
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

    // 📊 الإحصائيات العامة
    $stmt = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM staff_list) AS total_staff,
            (SELECT COUNT(*) FROM surveys) AS total_surveys,
            (SELECT COUNT(*) FROM clients) AS total_clients,
            (SELECT COUNT(*) FROM messages) AS total_messages,
            (SELECT COUNT(*) FROM notifications) AS total_notifications
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 👨‍💼 إحصائيات الأخصائيين
    $stmt_specialists = $pdo->query("
        SELECT 
            s.id,
            s.staff_name AS username,
            s.role AS role,
            COUNT(sv.id) AS total_surveys,
            COUNT(CASE WHEN DATE(sv.created_at)=CURDATE() THEN 1 END) AS last_day_clients
        FROM staff_list s
        LEFT JOIN surveys sv ON s.id = sv.user_id
        GROUP BY s.id, s.staff_name, s.role
        ORDER BY total_surveys DESC
    ");
    $specialists = $stmt_specialists->fetchAll(PDO::FETCH_ASSOC);

    // حساب المجاميع
    $total_surveys_sum = array_sum(array_column($specialists,'total_surveys'));
    $last_day_clients_sum = array_sum(array_column($specialists,'last_day_clients'));

    // معالجة إضافة مستخدم جديد (خاص بالمدير الأعلى فقط)
    $message = "";
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_user'])) {
        if(!$is_admin){
            $message = "⚠️ فقط المدير الأعلى يمكنه إضافة مستخدمين";
        } else {
            $staff_name = $_POST['staff_name'] ?? '';
            $new_role = $_POST['new_role'] ?? 'user';
            if($staff_name){
                $stmt_insert = $pdo->prepare("INSERT INTO staff_list (staff_name, role) VALUES (:name,:role)");
                $stmt_insert->execute([':name'=>$staff_name,':role'=>$new_role]);
                $message = "تم إضافة المستخدم بنجاح!";
                // تحديث الأخصائيين
                $stmt_specialists = $pdo->query("
                    SELECT 
                        s.id,
                        s.staff_name AS username,
                        s.role AS role,
                        COUNT(sv.id) AS total_surveys,
                        COUNT(CASE WHEN DATE(sv.created_at)=CURDATE() THEN 1 END) AS last_day_clients
                    FROM staff_list s
                    LEFT JOIN surveys sv ON s.id = sv.user_id
                    GROUP BY s.id, s.staff_name, s.role
                    ORDER BY total_surveys DESC
                ");
                $specialists = $stmt_specialists->fetchAll(PDO::FETCH_ASSOC);
                $total_surveys_sum = array_sum(array_column($specialists,'total_surveys'));
                $last_day_clients_sum = array_sum(array_column($specialists,'last_day_clients'));
            } else {
                $message = "⚠️ الرجاء إدخال اسم المستخدم";
            }
        }
    }

} catch(PDOException $e){
    die("فشل الاتصال بقاعدة البيانات: ".$e->getMessage());
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
tfoot td{font-weight:bold;}
.add-user-box{max-width:500px;margin:20px auto;background:white;padding:20px;border-radius:15px;}
input,select,button{width:100%;padding:10px;margin:5px 0;}
button{background:#3498db;color:white;border:none;border-radius:5px;cursor:pointer;}
button:hover{background:#2980b9;}
.message{color:green;font-weight:bold;margin:10px 0;}
</style>
</head>
<body>

<button class="logout" onclick="window.location.href='login.php'">تسجيل الخروج</button>

<h2 style="text-align:center;color:white;">مرحباً يا <?php echo htmlspecialchars($username); ?></h2>
<p style="text-align:center;color:white;">دورك في الموقع: <?php echo ($role==='admin'?'مدير':($role==='manager'?'مدير فرعي':'مستخدم')); ?></p>

<div class="container">
<a class="card users" href="<?php echo $is_admin_or_manager ? 'users.php' : '#'; ?>" 
onclick="<?php if(!$is_admin_or_manager) echo 'requestPermission()'; ?>">
<i class="fas fa-users"></i><h4>الأخصائيين</h4><p><?php echo $stats['total_staff']; ?></p></a>

<a class="card surveys" href="go.php"><i class="fas fa-file-alt"></i><h4>الاستبيانات</h4><p><?php echo $stats['total_surveys']; ?></p></a>

<a class="card clients" href="pepol.php"><i class="fas fa-user-check"></i><h4>بحث بالعملاء</h4><p><?php echo $stats['total_clients']; ?></p></a>

<a class="card messages" href="<?php echo $is_admin_or_manager ? 'formal.php' : '#'; ?>" 
onclick="<?php if(!$is_admin_or_manager) echo 'requestPermission()'; ?>">
<i class="fas fa-envelope"></i><h4>ادخال بيانات يوميه</h4><p><?php echo $stats['total_messages']; ?></p></a>

<a class="card notifications" href="view_surveys.php"><i class="fas fa-bell"></i><h4>قائمة العملاء</h4><p><?php echo $stats['total_notifications']; ?></p></a>

<a class="card notifications" href="requests.php"><i class="fas fa-bell"></i><h4>تعديلات المستخدمين</h4><p><?php echo $stats['total_notifications']; ?></p></a>

<a class="card settings" href="<?php echo $is_admin_or_manager ? 'setting.php' : '#'; ?>" 
onclick="<?php if(!$is_admin_or_manager) echo 'requestPermission()'; ?>">
<i class="fas fa-cog"></i><h4>الإعدادات</h4><p>-</p></a>
</div>

<?php if($is_admin): ?>
<div class="add-user-box">
<h3>إضافة مستخدم أو مدير جديد</h3>
<?php if($message): ?><p class="message"><?php echo $message; ?></p><?php endif; ?>
<form method="post">
<label>اسم المستخدم:</label>
<input type="text" name="staff_name" required>
<label>الدور:</label>
<select name="new_role">
<option value="admin">مدير</option>
<option value="manager">مدير فرعي</option>
<option value="user" selected>مستخدم عادي</option>
</select>
<button type="submit" name="add_user">إضافة</button>
</form>
</div>
<?php endif; ?>

<div class="table-box">
<h2>إحصائية الأخصائيين</h2>
<table>
<thead>
<tr>
<th>اسم الأخصائي</th>
<th>الدور</th>
<th>عدد الاستبيانات</th>
<th>عدد العملاء في آخر يوم</th>
</tr>
</thead>
<tbody>
<?php foreach($specialists as $sp): ?>
<tr>
<td><?php echo htmlspecialchars($sp['username']); ?></td>
<td><?php echo htmlspecialchars($sp['role']); ?></td>
<td class="total_surveys"><?php echo $sp['total_surveys']; ?></td>
<td class="last_day_clients"><?php echo $sp['last_day_clients']; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
<tfoot>
<tr>
<td>المجموع</td>
<td>-</td>
<td><?php echo $total_surveys_sum; ?></td>
<td><?php echo $last_day_clients_sum; ?></td>
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

function showNotification(message){
    // إنشاء عنصر مؤقت للإشعار
    let toast = document.createElement('div');
    toast.innerText = message;
    toast.style.position = 'fixed';
    toast.style.bottom = '20px';
    toast.style.right = '20px';
    toast.style.background = '#27ae60';
    toast.style.color = 'white';
    toast.style.padding = '15px 20px';
    toast.style.borderRadius = '8px';
    toast.style.boxShadow = '0 3px 8px rgba(0,0,0,0.2)';
    toast.style.zIndex = '9999';
    toast.style.opacity = '0';
    toast.style.transition = '0.5s';

    document.body.appendChild(toast);

    // ظهور تدريجي
    setTimeout(()=>{toast.style.opacity='1';}, 100);

    // اختفاء بعد 3 ثواني
    setTimeout(()=>{
        toast.style.opacity='0';
        setTimeout(()=> toast.remove(),500);
    }, 3000);
}
</script>

</body>
</html>