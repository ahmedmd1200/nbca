<?php
session_start();

try {
    $pdo = new PDO("mysql:host=localhost;dbname=survey_db;charset=utf8","root","");
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

    // تأكد من وجود أعمدة الصلاحيات
    $pdo->exec("ALTER TABLE users 
        ADD COLUMN IF NOT EXISTS can_edit TINYINT(1) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS can_delete TINYINT(1) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS can_access TINYINT(1) DEFAULT 0;
    ");

} catch(PDOException $e){
    die("فشل الاتصال: ".$e->getMessage());
}

// ======= تسجيل الخروج =======
if(isset($_GET['logout'])){
    session_destroy();
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// ======= تسجيل الدخول =======
$login_error="";
if(isset($_POST['login'])){
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username=? AND password=?");
    $stmt->execute([$username,$password]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if($user){
        $_SESSION['user_id']=$user['id'];
        $_SESSION['username']=$user['username'];
        $_SESSION['role']=$user['role'];
        $_SESSION['can_edit']=$user['can_edit'] ?? 0;
        $_SESSION['can_delete']=$user['can_delete'] ?? 0;
        $_SESSION['can_access']=$user['can_access'] ?? 0;
        header("Location: ".$_SERVER['PHP_SELF']); exit();
    } else {
        $login_error="❌ اسم المستخدم أو كلمة المرور خطأ";
    }
}

// ======= طلب صلاحيات للمستخدم العادي =======
$msg="";
if(isset($_POST['request_perm']) && isset($_SESSION['user_id'])){
    $action = $_POST['action'] ?? "تعديل بيانات";
    $title = "طلب صلاحية: ".$action;
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, username, title) VALUES (?,?,?)");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['username'], $title]);
    $msg="✅ تم إرسال طلب الصلاحية للمدير";
}

// ======= تحديث الحقول مباشرة (AJAX) =======
if(isset($_POST['update_field'])){
    if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'],['manager','admin'])){
        echo json_encode(['status'=>'error']); exit;
    }
    $uid = (int)$_POST['uid'];
    $field = $_POST['field'];
    $value = $_POST['value'];
    if(!in_array($field,['username','password','role'])) exit;
    $stmt = $pdo->prepare("UPDATE users SET $field=? WHERE id=?");
    $stmt->execute([$value,$uid]);
    echo json_encode(['status'=>'success']); exit;
}

// ======= تغيير الصلاحيات مباشرة (AJAX) =======
if(isset($_POST['toggle'])){
    if(!isset($_SESSION['role']) || !in_array($_SESSION['role'],['manager','admin'])){
        echo json_encode(['status'=>'error']); exit;
    }
    $uid = (int)$_POST['toggle'];
    $type = $_POST['type'];
    if(!in_array($type,['can_edit','can_delete','can_access'])) exit;
    $stmt = $pdo->prepare("SELECT $type FROM users WHERE id=?");
    $stmt->execute([$uid]);
    $val = $stmt->fetchColumn();
    $new_val = $val ? 0 : 1;
    $stmt2 = $pdo->prepare("UPDATE users SET $type=? WHERE id=?");
    $stmt2->execute([$new_val,$uid]);
    echo json_encode(['status'=>'success','new_val'=>$new_val]); exit;
}

// ======= جلب جميع المستخدمين للمدير =======
$users=[];
if(isset($_SESSION['role']) && $_SESSION['role']==='manager'){
    $users = $pdo->query("SELECT id, username, password, role, can_edit, can_delete, can_access FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
}

// دالة لإخفاء كلمة المرور جزئياً
function maskPassword($password){
    $len = strlen($password);
    if($len <= 3) return str_repeat('*', $len);
    return substr($password,0,1) . str_repeat('*', $len-2) . substr($password,-1);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>نظام الصلاحيات المتكامل</title>
<style>
body{font-family:sans-serif;background:#f5f5f5;padding:20px;}
.container{background:white;padding:20px;border-radius:10px;max-width:1000px;margin:auto;}
h2,h3{color:#333;}
form{margin-bottom:20px;}
input,select,button{padding:10px;margin:5px 0;width:100%;border-radius:5px;border:1px solid #ccc;}
button{background:#4CAF50;color:white;border:none;cursor:pointer;}
button:hover{opacity:0.9;}
.user-btn{padding:5px 10px;border-radius:5px;border:none;color:white;cursor:pointer;margin:2px;}
.active{background:green;}
.inactive{background:gray;}
.msg{color:green;font-weight:bold;}
.error{color:red;font-weight:bold;}
table{width:100%;border-collapse:collapse;margin-top:20px;}
th,td{border:1px solid #ccc;padding:10px;text-align:center;}
th{background:#f5576c;color:white;}
td[contenteditable="true"]{background:#f9f9f9;}
</style>
</head>
<body>
<div class="container">
<h2>نظام الصلاحيات المتكامل</h2>

<?php if(!isset($_SESSION['user_id'])): ?>
<form method="POST">
<input type="text" name="username" placeholder="اسم المستخدم" required>
<input type="password" name="password" placeholder="كلمة المرور" required>
<button name="login">تسجيل الدخول</button>
<p class="error"><?php echo $login_error; ?></p>
</form>

<?php else: ?>
<p>مرحباً <?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo htmlspecialchars($_SESSION['role']); ?>)</p>
<a href="?logout=1">تسجيل الخروج</a>

<?php if($_SESSION['role']!=='manager'): ?>
<form method="POST">
<select name="action">
<option value="تعديل بيانات">تعديل بيانات</option>
<option value="حذف بيانات">حذف بيانات</option>
<option value="دخول مكان محظور">دخول مكان محظور</option>
</select>
<button name="request_perm">طلب صلاحية</button>
<p class="msg"><?php echo $msg; ?></p>
</form>
<?php endif; ?>

<?php if($_SESSION['role']==='manager'): ?>
<h3>المستخدمون والصلاحيات</h3>
<table>
<tr><th>المستخدم</th><th>كلمة المرور</th><th>الدور</th><th>تعديل</th><th>حذف</th><th>دخول صفحات</th></tr>
<?php foreach($users as $u): ?>
<tr>
<td contenteditable="true" onblur="updateField(<?= $u['id']; ?>,'username',this.innerText)">
<?= htmlspecialchars($u['username']); ?></td>
<td contenteditable="true" onblur="updateField(<?= $u['id']; ?>,'password',this.innerText)">
<?= htmlspecialchars(maskPassword($u['password'])); ?></td>
<td contenteditable="true" onblur="updateField(<?= $u['id']; ?>,'role',this.innerText)">
<?= htmlspecialchars($u['role']); ?></td>

<td>
<button class="user-btn <?= $u['can_edit']?'active':'inactive' ?>" data-uid="<?= $u['id']; ?>" data-type="can_edit">
<?= $u['can_edit']?'مفعل':'غير مفعل'; ?></button>
</td>
<td>
<button class="user-btn <?= $u['can_delete']?'active':'inactive' ?>" data-uid="<?= $u['id']; ?>" data-type="can_delete">
<?= $u['can_delete']?'مفعل':'غير مفعل'; ?></button>
</td>
<td>
<button class="user-btn <?= $u['can_access']?'active':'inactive' ?>" data-uid="<?= $u['id']; ?>" data-type="can_access">
<?= $u['can_access']?'مصرح بالدخول':'ممنوع الدخول'; ?></button>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<?php endif; ?>
</div>

<script>
function updateField(uid, field, value){
    fetch('',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'update_field=1&uid='+uid+'&field='+field+'&value='+encodeURIComponent(value)
    }).then(res=>res.json())
    .then(data=>{ if(data.status!=='success') alert('خطأ في تحديث البيانات'); });
}

document.querySelectorAll('.user-btn').forEach(btn=>{
    btn.addEventListener('click',function(){
        let uid = this.dataset.uid;
        let type = this.dataset.type;
        fetch('',{
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'toggle='+uid+'&type='+type
        }).then(res=>res.json())
        .then(data=>{
            if(data.status==='success'){
                if(data.new_val==1){
                    btn.classList.remove('inactive'); btn.classList.add('active');
                    btn.textContent = type==='can_access' ? 'مصرح بالدخول' : 'مفعل';
                } else {
                    btn.classList.remove('active'); btn.classList.add('inactive');
                    btn.textContent = type==='can_access' ? 'ممنوع الدخول' : 'غير مفعل';
                }
            }
        }).catch(err=>alert('حدث خطأ'));
    });
});
</script>
</body>
</html>