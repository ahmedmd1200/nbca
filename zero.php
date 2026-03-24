<?php
// تعريف $stats افتراضي لتجنب الأخطاء
$stats = [
    'total_staff' => 0,
    'total_surveys' => 0,
    'total_clients' => 0,
    'total_searches' => 0,
    'total_messages' => 0
];

// صلاحيات المستخدم
$is_admin_or_manager = true; // ضع هنا منطق التحقق الفعلي
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>لوحة التحكم</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body {
    font-family: sans-serif;
    background: linear-gradient(135deg, #667eea, #764ba2);
    margin: 0;
    padding: 0;
}
.container {
    max-width: 1200px;
    margin: 50px auto;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
}
.card {
    border-radius: 15px;
    padding: 20px;
    color: white;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    text-align: center;
    min-height: 140px;
    transition: 0.3s;
    text-decoration: none;
}
.card:hover {
    transform: translateY(-5px);
    opacity: 0.95;
    box-shadow: 0 6px 20px rgba(0,0,0,0.25);
}
.card h4 {
    margin-bottom: 8px;
    font-size: 16px;
}
.card p {
    font-size: 24px;
    font-weight: bold;
    margin: 0;
}
.card i {
    font-size: 32px;
    margin-bottom: 10px;
}
.users { background: #f5576c; }
.surveys { background: #ff9f43; }
.clients { background: #1dd1a1; }
.messages { background: #54a0ff; }
.notifications { background: #5f27cd; }
.settings { background: #ee5253; }
.logout {
    position: fixed;
    top: 100px;
    right: 40px;
    background: #f093fb;
    color: white;
    border: none;
    padding: 10px 26px;
    border-radius: 18px;
    cursor: pointer;
    font-size: 20px;
}
.logout:hover { background: #f5576c; }
</style>
<script>
function requestPermission() {
    alert("ليس لديك صلاحية الدخول لهذه الصفحة!");
}
</script>
</head>
<body>

<div class="container">
    <!-- المستخدمين -->
    <a class="card users" href="users.php" 
       onclick="<?php if(!$is_admin_or_manager) echo 'requestPermission(); return false;'; ?>">
        <i class="fas fa-users"></i>
        <h4>قائمة المستخدمين</h4>
        <p><?php echo $stats['total_staff']; ?></p>
    </a>

    <!-- الاستبيانات -->
    <a class="card surveys" href="view_surveys.php">
        <i class="fas fa-file-alt"></i>
        <h4>الاستبيانات</h4>
        <p><?php echo $stats['total_surveys']; ?></p>
    </a>

    <!-- العملاء -->
    <a class="card clients" href="view_data.php">
        <i class="fas fa-user-check"></i>
        <h4>بحث العملاء الكل</h4>
        <p><?php echo $stats['total_clients']; ?></p>
    </a>

    <!-- بحث بالأخصائي -->
    <a class="card clients" href="staff.php">
        <i class="fas fa-user-check"></i>
        <h4>بحث بالأخصائي</h4>
        <p><?php echo $stats['total_searches']; ?></p>
    </a>

    <!-- أزرار إضافية -->
    <a class="card clients" href="user_action.php">
        <i class="fas fa-user-cog"></i>
        <h4>أزرار إضافية</h4>
        <p>0</p>
    </a>

    <a class="card messages" href="update_client_request.php">
        <i class="fas fa-envelope"></i>
        <h4>تحديث طلب العملاء</h4>
        <p>0</p>
    </a>

    <a class="card notifications" href="update_client.php">
        <i class="fas fa-bell"></i>
        <h4>تحديث بيانات العملاء</h4>
        <p>0</p>
    </a>

    <a class="card settings" href="surveys_list.php">
        <i class="fas fa-list"></i>
        <h4>قائمة الاستبيانات</h4>
        <p>0</p>
    </a>

    <a class="card messages" href="send_request.php">
        <i class="fas fa-paper-plane"></i>
        <h4>إرسال طلب</h4>
        <p>0</p>
    </a>
</div>

<button class="logout">تسجيل الخروج</button>

</body>
</html>