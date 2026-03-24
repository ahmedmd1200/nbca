<?php
session_start();
if(!isset($_SESSION['username'])){
    header("Location: login.php");
    exit();
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=survey_db;charset=utf8","root","");
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

    if(!isset($_GET['id']) || empty($_GET['id'])){
        die("لم يتم تحديد المستخدم.");
    }

    $user_id = (int)$_GET['id'];

    // جلب بيانات المستخدم
    $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$user){
        die("المستخدم غير موجود.");
    }

    // معالجة تحديث المستخدم
    if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])){
        $username = trim($_POST['username']);
        $old_password = trim($_POST['old_password']);
        $new_password = trim($_POST['new_password']);
        $role = trim($_POST['role']);

        if(empty($username) || empty($role) || empty($old_password)){
            $error = "جميع الحقول مطلوبة، ويجب إدخال كلمة المرور القديمة.";
        } elseif($old_password !== $user['password']) {
            $error = "❌ كلمة المرور القديمة غير صحيحة!";
        } else {
            if(!empty($new_password)){
                $update = $pdo->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?");
                $update->execute([$username, $new_password, $role, $user_id]);
            } else {
                $update = $pdo->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                $update->execute([$username, $role, $user_id]);
            }
            $success = "✅ تم تحديث بيانات المستخدم بنجاح!";
            // إعادة جلب البيانات بعد التحديث
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    // معالجة حذف المستخدم
    if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])){
        $delete = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $delete->execute([$user_id]);
        header("Location: users.php");
        exit();
    }

} catch(PDOException $e) {
    die("فشل الاتصال: ".$e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>تعديل المستخدم</title>
<style>
body{font-family:sans-serif; background:#f0f2f5; margin:0; padding:0;}
.container{max-width:500px; margin:50px auto; background:white; padding:30px; border-radius:15px; box-shadow:0 4px 10px rgba(0,0,0,0.1);}
h2{text-align:center; margin-bottom:20px; color:#333;}
form{display:flex; flex-direction:column;}
label{margin:10px 0 5px;}
input, select{padding:10px; border-radius:5px; border:1px solid #ccc;}
button{margin-top:20px; padding:10px; border:none; border-radius:8px; background:#1dd1a1; color:white; cursor:pointer;}
button:hover{opacity:0.8;}
.delete-btn{background:#ee5253; margin-top:10px;}
.message{margin-top:15px; padding:10px; border-radius:5px; text-align:center;}
.error{background:#ee5253; color:white;}
.success{background:#2ecc71; color:white;}
.back-btn{display:block; margin-top:20px; text-align:center; text-decoration:none; color:#764ba2;}
</style>
</head>
<body>

<div class="container">
<h2>تعديل المستخدم</h2>

<?php if(!empty($error)): ?>
<div class="message error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if(!empty($success)): ?>
<div class="message success"><?php echo $success; ?></div>
<?php endif; ?>

<form method="post">
<label>اسم المستخدم</label>
<input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>

<label>كلمة المرور القديمة (مطلوبة للتعديل)</label>
<input type="text" name="old_password" placeholder="أدخل كلمة المرور القديمة" required>

<label>كلمة المرور الجديدة (اتركها فارغة إذا لا تريد التغيير)</label>
<input type="text" name="new_password" placeholder="كلمة المرور الجديدة">

<label>الدور</label>
<select name="role" required>
    <option value="admin" <?php if(strtolower(trim($user['role']))=='admin') echo 'selected'; ?>>Admin</option>
    <option value="user" <?php if(strtolower(trim($user['role']))=='user') echo 'selected'; ?>>User</option>
    <option value="مدير فرعي" <?php if(trim($user['role'])=='مدير فرعي') echo 'selected'; ?>>مدير فرعي</option>
</select>

<button type="submit" name="update">تحديث المستخدم</button>
<button type="submit" name="delete" class="delete-btn" onclick="return confirm('هل أنت متأكد من حذف هذا المستخدم؟');">حذف المستخدم</button>
</form>

<a class="back-btn" href="users.php">العودة إلى قائمة المستخدمين</a>
</div>

</body>
</html>