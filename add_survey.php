<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost","root","","survey_db");

if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات");
}

$conn->set_charset("utf8mb4");

$staff_list = [];

$result = $conn->query("SELECT id, staff_name FROM staff_list ORDER BY staff_name ASC");

while($row = $result->fetch_assoc()){
$staff_list[] = $row;
}

$client_code = "";
$client_name = "";
$client_address = "";
$client_phone = "";

$guarantor_name = "";
$guarantor_address = "";
$guarantor_phone = "";

$notes = "";
$user_id = "";

$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

$client_code = trim($_POST['client_code']);
$client_name = trim($_POST['client_name']);
$client_address = trim($_POST['client_address']);
$client_phone = trim($_POST['client_phone']);

$guarantor_name = trim($_POST['guarantor_name']);
$guarantor_address = trim($_POST['guarantor_address']);
$guarantor_phone = trim($_POST['guarantor_phone']);

$notes = trim($_POST['notes']);
$user_id = trim($_POST['user_id']);

if(empty($client_code)) $errors[]="أدخل كود العميل";
if(empty($client_name)) $errors[]="أدخل اسم العميل";
if(empty($client_phone)) $errors[]="أدخل رقم العميل";
if(empty($guarantor_name)) $errors[]="أدخل اسم الضامن";
if(empty($user_id)) $errors[]="اختر الأخصائي";

if(empty($errors)){

$sql="INSERT INTO surveys
(client_code,client_name,client_address,client_phone,
guarantor_name,guarantor_address,guarantor_phone,
notes,user_id,created_at)
VALUES (?,?,?,?,?,?,?,?,?,NOW())";

$stmt=$conn->prepare($sql);

$stmt->bind_param("ssssssssi",
$client_code,
$client_name,
$client_address,
$client_phone,
$guarantor_name,
$guarantor_address,
$guarantor_phone,
$notes,
$user_id
);

$stmt->execute();

$_SESSION['success']="تم حفظ الاستبيان";

header("Location:add_survey.php");
exit();
}

}

?>

<!DOCTYPE html>
<html lang="ar">
<head>

<meta charset="UTF-8">
<title>إضافة استبيان</title>

<style>

body{
font-family:tahoma;
direction:rtl;
background:linear-gradient(135deg,#667eea,#764ba2);
padding:20px;
}

form{
max-width:700px;
margin:auto;
background:white;
padding:30px;
border-radius:15px;
box-shadow:0 0 15px rgba(0,0,0,0.2);
}

input,select,textarea{

width:100%;
padding:12px;
margin-bottom:12px;
border-radius:8px;
border:1px solid #ccc;
font-size:16px;

}

button{

width:100%;
padding:14px;
background:#6a1b9a;
color:white;
border:none;
border-radius:10px;
font-size:18px;
cursor:pointer;

}

button:hover{
background:#4a148c;
}

.section{

font-size:22px;
margin:20px 0 10px;
text-align:center;
color:#6a1b9a;

}

.success{

background:#e8f5e9;
padding:10px;
margin-bottom:15px;
border-radius:8px;
text-align:center;

}

a{
display:block;
text-align:center;
margin-bottom:15px;
font-size:20px;
}

</style>

</head>

<body>

<a href="surveys_list.php">📊 عرض كل الاستبيانات</a>

<?php
if(isset($_SESSION['success'])){
echo "<div class='success'>".$_SESSION['success']."</div>";
unset($_SESSION['success']);
}
?>

<form method="POST">

<select name="user_id" required>

<option value="">اختر الأخصائي</option>

<?php foreach($staff_list as $staff): ?>

<option value="<?php echo $staff['id']; ?>">

<?php echo $staff['staff_name']; ?>

</option>

<?php endforeach; ?>

</select>

<input type="text" name="client_code" placeholder="كود العميل" required>

<div class="section">بيانات العميل</div>

<input type="text" name="client_name" placeholder="اسم العميل" required>

<input type="text" name="client_address" placeholder="عنوان العميل">

<input type="text" name="client_phone" placeholder="رقم العميل" required>

<div class="section">بيانات الضامن</div>

<input type="text" name="guarantor_name" placeholder="اسم الضامن" required>

<input type="text" name="guarantor_address" placeholder="عنوان الضامن">

<input type="text" name="guarantor_phone" placeholder="رقم الضامن">

<textarea name="notes" placeholder="ملاحظات"></textarea>

<button type="submit">حفظ الاستبيان</button>

</form>

</body>
</html>
