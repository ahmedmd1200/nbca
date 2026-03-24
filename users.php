<?php
session_start();
if(!isset($_SESSION['username'])){
    header("Location: login.php");
    exit();
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=survey_db;charset=utf8","root","");
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

    // جلب جميع المستخدمين
    $stmt_users = $pdo->query("SELECT id, username, password, role, can_edit, can_delete, can_access FROM users ORDER BY id ASC");
    $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

    // صلاحيات المستخدم الحالي
    $stmt_perm = $pdo->prepare("SELECT role, can_edit, can_delete, can_access FROM users WHERE id=?");
    $stmt_perm->execute([$_SESSION['user_id']]);
    $current_user = $stmt_perm->fetch(PDO::FETCH_ASSOC);

    $is_manager_or_admin = ($current_user['role'] === 'admin' || $current_user['role'] === 'manager');
    $can_edit = $current_user['can_edit'] ?? 0;
    $can_delete = $current_user['can_delete'] ?? 0;
    $can_access = $current_user['can_access'] ?? 0;

} catch(PDOException $e) {
    die("فشل الاتصال: ".$e->getMessage());
}

// إخفاء كلمة المرور جزئياً
function maskPassword($password){
    $len = strlen($password);
    if($len <= 3) return str_repeat('*', $len);
    return substr($password, 0, 1) . str_repeat('*', $len-2) . substr($password,-1);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>قائمة المستخدمين</title>
<style>
body{
    font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background:#f4f6f8;
    margin:0; padding:0;
}
.container{
    max-width:1200px;
    margin:50px auto;
    padding:30px;
    background:white;
    border-radius:12px;
    box-shadow:0 8px 20px rgba(0,0,0,0.08);
}
h2{text-align:center; margin-bottom:25px; color:#2c3e50; font-size:26px;}
table{width:100%; border-collapse:collapse; border-radius:10px; overflow:hidden; font-size:15px;}
th, td{padding:12px 15px; border:1px solid #e1e4e8; text-align:center;}
th{background:#3498db; color:white;}
td input{width:100%; padding:7px; border:1px solid #ccc; border-radius:5px; text-align:center; background:#f9f9f9; font-size:14px;}
td button{margin:2px 5px; padding:6px 12px; border:none; border-radius:5px; cursor:pointer; color:white; font-weight:600; transition:0.3s;}
.edit-btn{background:#2ecc71;}
.delete-btn{background:#e74c3c;}
.toggle-btn{background:#f39c12;}
td button:hover{opacity:0.85; transform:scale(1.05);}
.back-btn{display:block; margin:25px auto; padding:12px 25px; background:#34495e; color:white; border:none; border-radius:8px; cursor:pointer; text-align:center; text-decoration:none; font-weight:600; transition:0.3s;}
.back-btn:hover{background:#2c3e50; transform:scale(1.05);}
@media (max-width:768px){table, th, td{font-size:14px;} h2{font-size:22px;}}
</style>
<script>
// دالة لتغيير الصلاحيات عبر AJAX
function togglePermission(uid, type){
    fetch("toggle_permission.php",{
        method:"POST",
        headers:{ "Content-Type":"application/x-www-form-urlencoded" },
        body:"toggle="+uid+"&type="+type
    }).then(res => res.json())
      .then(data=>{
          if(data.status==='success'){
              location.reload();
          } else {
              alert('حدث خطأ، حاول مرة أخرى');
          }
      });
}
</script>
</head>
<body>
<div class="container">
<h2>قائمة المستخدمين</h2>
<table>
<thead>
<tr>
<th>#</th>
<th>اسم المستخدم</th>
<th>كلمة المرور</th>
<th>الدور</th>
<th>صلاحية تعديل</th>
<th>صلاحية حذف</th>
<th>صلاحية دخول صفحات</th>
<th>إجراءات</th>
</tr>
</thead>
<tbody>
<?php foreach($users as $user): ?>
<tr>
<td><?= $user['id']; ?></td>
<td><input type="text" value="<?= htmlspecialchars($user['username']); ?>" readonly></td>
<td><input type="text" value="<?= htmlspecialchars(maskPassword($user['password'])); ?>" readonly></td>
<td><input type="text" value="<?= htmlspecialchars($user['role']); ?>" readonly></td>
<td>
<?= $user['can_edit'] ? '✔' : '✖'; ?>
<?php if($is_manager_or_admin): ?>
<button class="toggle-btn" onclick="togglePermission(<?= $user['id']; ?>,'can_edit')">تغيير</button>
<?php endif; ?>
</td>
<td>
<?= $user['can_delete'] ? '✔' : '✖'; ?>
<?php if($is_manager_or_admin): ?>
<button class="toggle-btn" onclick="togglePermission(<?= $user['id']; ?>,'can_delete')">تغيير</button>
<?php endif; ?>
</td>
<td>
<?= $user['can_access'] ? '✔' : '✖'; ?>
<?php if($is_manager_or_admin): ?>
<button class="toggle-btn" onclick="togglePermission(<?= $user['id']; ?>,'can_access')">تغيير</button>
<?php endif; ?>
</td>
<td>
<?php if($is_manager_or_admin || $can_edit): ?>
<button class="edit-btn" onclick="window.location.href='edit_user.php?id=<?= $user['id']; ?>'">تعديل</button>
<?php else: ?>
<button class="edit-btn" onclick="alert('⚠️ ليس لديك صلاحية تعديل المستخدم')">تعديل</button>
<?php endif; ?>

<?php if($is_manager_or_admin || $can_delete): ?>
<button class="delete-btn" onclick="if(confirm('هل تريد حذف المستخدم؟')) window.location.href='delete_user.php?id=<?= $user['id']; ?>'">حذف</button>
<?php else: ?>
<button class="delete-btn" onclick="alert('⚠️ ليس لديك صلاحية حذف المستخدم')">حذف</button>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<a class="back-btn" href="das.php">العودة إلى لوحة التحكم</a>
</div>
</body>
</html>	