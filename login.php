<?php
session_start();

// الاتصال بقاعدة البيانات
$conn = new mysqli('fdb1031.runhosting.com','4728212_elhmamy','0172301281m','4728212_elhmamy');
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$msg = "";
$error = "";

/* تسجيل مستخدم جديد */
if(isset($_POST['register'])){
    $username = trim($_POST['username']);
    $password = trim($_POST['password']); // نص صريح بدون هاش
    $role = $_POST['role'] ?? 'user';
    $manager_code = $_POST['manager_code'] ?? '';

    if($role === "manager" && $manager_code !== "1"){
        $error = "❌ انت مش مدير!";
    } else {
        // تحقق من تكرار اسم المستخدم
        $check = $conn->prepare("SELECT id FROM users WHERE username=?");
        $check->bind_param("s",$username);
        $check->execute();
        $check->store_result();

        if($check->num_rows > 0){
            $error = "❌ اسم المستخدم مستخدم بالفعل";
        } else {
            $stmt = $conn->prepare("INSERT INTO users (username,password,role) VALUES (?,?,?)");
            $stmt->bind_param("sss",$username,$password,$role);

            if($stmt->execute()){
                $msg = "🎉 تم إنشاء الحساب بنجاح!";
                $error = "";
            } else {
                $error = "❌ حدث خطأ أثناء إنشاء الحساب";
            }
            $stmt->close();
        }
        $check->close();
    }
}

/* تسجيل الدخول */
if(isset($_POST['login'])){
    $username = trim($_POST['l_username']);
    $password = trim($_POST['l_password']); // نص صريح بدون هاش

    $stmt = $conn->prepare("SELECT id,password,role FROM users WHERE username=?");
    $stmt->bind_param("s",$username);
    $stmt->execute();
    $stmt->store_result();

    if($stmt->num_rows > 0){
        $stmt->bind_result($id,$db_password,$role);
        $stmt->fetch();

        // مقارنة نصية مباشرة بدون هاش
        if($password === $db_password){
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            $_SESSION['user_id'] = $id;

            header("Location: das.php");
            exit();
        } else {
            $error = "❌ اسم المستخدم أو كلمة المرور خطأ";
        }
    } else {
        $error = "❌ اسم المستخدم أو كلمة المرور خطأ";
    }

    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>نظام الدخول الاحتفالي</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Baloo+2:wght@500&display=swap');
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Baloo 2', cursive;background: linear-gradient(135deg,#f093fb,#f5576c);height:100vh;display:flex;justify-content:center;align-items:center;}
.container{background:white;padding:50px;border-radius:20px;width:400px;box-shadow:0 15px 50px rgba(0,0,0,0.4);text-align:center;}
h2{margin-bottom:20px;color:#333;}
input,select{width:100%;padding:15px;margin:10px 0;border-radius:10px;border:1px solid #ddd;font-size:16px;}
button{width:100%;padding:15px;margin-top:10px;background:#f5576c;border:none;border-radius:10px;color:white;font-size:16px;cursor:pointer;}
button:hover{background:#f093fb;}
.link{margin-top:15px;display:inline-block;cursor:pointer;color:#f5576c;}
.password-box{position:relative;}
.password-box span{position:absolute;left:10px;top:50%;transform:translateY(-50%);cursor:pointer;font-size:18px;}
.error{color:red;margin:10px 0;font-weight:bold;}
.msg{color:green;margin:10px 0;font-weight:bold;}
#register{display:none;}
</style>
</head>
<body>

<div class="container">

<form method="POST" id="login">
<h2>تسجيل الدخول</h2>
<input type="text" name="l_username" placeholder="اسم المستخدم" required>
<div class="password-box">
<input type="password" id="login_pass" name="l_password" placeholder="كلمة المرور" required>
<span onclick="togglePassword('login_pass')">👁</span>
</div>
<button name="login">دخول</button>
<p class="error"><?php echo $error ?></p>
<p class="msg"><?php echo $msg ?></p>
<div class="link" onclick="showRegister()">إنشاء حساب جديد 🎉</div>
</form>

<form method="POST" id="register">
<h2>إنشاء حساب جديد</h2>
<input type="text" name="username" placeholder="اسم المستخدم" required>
<div class="password-box">
<input type="password" id="reg_pass" name="password" placeholder="كلمة المرور" required>
<span onclick="togglePassword('reg_pass')">👁</span>
</div>
<select name="role" id="role" onchange="toggleManagerCode()">
<option value="user">مستخدم عادي</option>
<option value="manager">مدير</option>
</select>
<input type="text" name="manager_code" id="manager_code" placeholder="كود المدير" style="display:none;">
<button name="register">تسجيل</button>
<p class="error"><?php echo $error ?></p>
<p class="msg"><?php echo $msg ?></p>
<div class="link" onclick="showLogin()">رجوع لتسجيل الدخول 🔙</div>
</form>

</div>

<script>
function showRegister(){document.getElementById("login").style.display="none";document.getElementById("register").style.display="block";}
function showLogin(){document.getElementById("register").style.display="none";document.getElementById("login").style.display="block";}
function toggleManagerCode(){let role=document.getElementById("role").value;let code=document.getElementById("manager_code");if(role==="manager"){code.style.display="block";}else{code.style.display="none";code.value="";}}
function togglePassword(id){let input=document.getElementById(id);if(input.type==="password"){input.type="text";}else{input.type="password";}}
</script>

</body>
</html>
