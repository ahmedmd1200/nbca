<?php
session_start();

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "survey_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("معرف العميل غير صالح");
}

$id = intval($_GET['id']);

$stmt_perm = $conn->prepare("SELECT can_edit, username FROM users WHERE id=?");
$stmt_perm->bind_param("i", $_SESSION['user_id']);
$stmt_perm->execute();
$res_perm = $stmt_perm->get_result();
$user_perm = $res_perm->fetch_assoc();

$can_edit = $user_perm['can_edit'];
$username_login = $user_perm['username'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $client_code = $_POST['client_code'] ?? '';
    $client_name = $_POST['client_name'] ?? '';
    $client_address = $_POST['client_address'] ?? '';
    $client_phone = $_POST['client_phone'] ?? '';
    $client_job = $_POST['client_job'] ?? '';
    $guarantor_name = $_POST['guarantor_name'] ?? '';
    $guarantor_address = $_POST['guarantor_address'] ?? '';
    $guarantor_phone = $_POST['guarantor_phone'] ?? '';
    $guarantor_job = $_POST['guarantor_job'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $staff_name = $_POST['staff_name'] ?? '';

    if(!$can_edit){

        $new_data = json_encode($_POST, JSON_UNESCAPED_UNICODE);

        $stmt_req = $conn->prepare("
            INSERT INTO action_requests
            (user_id, username, action_type, target_table, target_id, new_value)
            VALUES (?, ?, 'update', 'surveys', ?, ?)
        ");

        $stmt_req->bind_param("isis",
            $_SESSION['user_id'],
            $username_login,
            $id,
            $new_data
        );

        $stmt_req->execute();

        $_SESSION['msg'] = "⚠️ ليس لديك صلاحية التعديل، تم إرسال طلب للمدير";
        header("Location: ".$_SERVER['PHP_SELF']."?id=".$id);
        exit;
    }

    $stmt_check = $conn->prepare("SELECT client_name FROM surveys WHERE client_code=? AND id != ?");
    $stmt_check->bind_param("si", $client_code, $id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($row_check = $result_check->fetch_assoc()) {
        $_SESSION['msg'] = "الكود موجود بالفعل! العميل: " . $row_check['client_name'];
    } else {

        $stmt = $conn->prepare("
            UPDATE surveys 
            SET client_code=?, client_name=?, client_address=?, client_phone=?, client_job=?, 
                guarantor_name=?, guarantor_address=?, guarantor_phone=?, guarantor_job=?, notes=?, staff_name=? 
            WHERE id=?
        ");

        $stmt->bind_param(
            "sssssssssssi",
            $client_code, $client_name, $client_address, $client_phone, $client_job,
            $guarantor_name, $guarantor_address, $guarantor_phone, $guarantor_job, $notes, $staff_name, $id
        );

        if ($stmt->execute()) {
            $_SESSION['msg'] = "تم حفظ التعديلات بنجاح!";
        } else {
            $_SESSION['msg'] = "حدث خطأ أثناء الحفظ: " . $stmt->error;
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM surveys WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$client = $result->fetch_assoc();

$staff_options = [];
$result_staff = $conn->query("SELECT staff_name FROM staff_list ORDER BY staff_name ASC");
while ($row = $result_staff->fetch_assoc()) {
    $staff_options[] = $row;
}

if (isset($_GET['ajax_check']) && $_GET['ajax_check']=='1') {

    $check_code = $_GET['code'] ?? '';

    $stmt_ajax = $conn->prepare("SELECT client_name FROM surveys WHERE client_code=? AND id != ?");
    $stmt_ajax->bind_param("si", $check_code, $id);
    $stmt_ajax->execute();

    $res_ajax = $stmt_ajax->get_result();

    if ($row_ajax = $res_ajax->fetch_assoc()) {
        echo json_encode(['exists'=>true,'client_name'=>$row_ajax['client_name']]);
    } else {
        echo json_encode(['exists'=>false]);
    }

    exit;
}
?>

<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تفاصيل العميل</title>

<style>

body{
font-family:'Segoe UI';
margin:20px;
direction:rtl;
font-size:20px;
font-weight:bold;
background:#e0f7fa;
}

.container{
max-width:900px;
margin:auto;
padding:30px;
border-radius:20px;
background:#ffffff;
box-shadow:0 10px 25px rgba(0,0,0,0.2);
}

.data-item,input,textarea,select{
padding:10px;
margin-bottom:10px;
font-size:18px;
width:100%;
border-radius:10px;
border:none;
text-align:center;
background:#f3e5f5;
}

input,textarea,select{
display:none;
}

textarea{
min-height:150px;
}

.section-title{
text-align:center;
margin:10px 0;
font-size:30px;
color:#6a1b9a;
}

.edit-btns{
display:flex;
justify-content:center;
gap:10px;
margin-bottom:20px;
}

.edit-btns button{
padding:10px 30px;
background:#8e24aa;
color:white;
border:none;
border-radius:10px;
font-size:20px;
cursor:pointer;
}

input.editing,textarea.editing,select.editing{
display:block;
background:#fff3b0;
}

.msg{
text-align:center;
color:green;
margin-bottom:15px;
}

</style>
</head>

<body>

<div class="container">

<div style="text-align:center;margin:20px 0;">
<a href="das.php" style="padding:10px 20px;background:#3498db;color:white;text-decoration:none;border-radius:5px;font-size:22px;">رجوع</a>
</div>

<?php if(isset($_SESSION['msg'])): ?>
<div class="msg">
<?= $_SESSION['msg']; unset($_SESSION['msg']); ?>
</div>
<?php endif; ?>

<form method="post">

<div class="edit-btns">

<button type="button" onclick="enableEdit()">تعديل البيانات</button>

<button type="submit" id="save-btn" style="display:none;">حفظ</button>

<button type="button" id="cancel-btn" style="display:none;" onclick="cancelEdit()">إلغاء</button>

</div>

<div class="section-title">كود العميل</div>
<div class="data-item"><?= htmlspecialchars($client['client_code']) ?></div>
<input type="text" name="client_code" id="client_code" placeholder="<?= htmlspecialchars($client['client_code']) ?>">

<div class="section-title">بيانات العميل</div>

<div class="data-item"><?= htmlspecialchars($client['client_name']) ?></div>
<input type="text" name="client_name" placeholder="<?= htmlspecialchars($client['client_name']) ?>">

<div class="data-item"><?= htmlspecialchars($client['client_address'] ?? '-') ?></div>
<input type="text" name="client_address" placeholder="<?= htmlspecialchars($client['client_address']) ?>">

<div class="data-item"><?= htmlspecialchars($client['client_phone'] ?? '-') ?></div>
<input type="text" name="client_phone" placeholder="<?= htmlspecialchars($client['client_phone']) ?>">

<div class="data-item"><?= htmlspecialchars($client['client_job'] ?? '-') ?></div>
<input type="text" name="client_job" placeholder="<?= htmlspecialchars($client['client_job']) ?>">

<div class="section-title">بيانات الضامن</div>

<div class="data-item"><?= htmlspecialchars($client['guarantor_name'] ?? '-') ?></div>
<input type="text" name="guarantor_name" placeholder="<?= htmlspecialchars($client['guarantor_name']) ?>">

<div class="data-item"><?= htmlspecialchars($client['guarantor_address'] ?? '-') ?></div>
<input type="text" name="guarantor_address" placeholder="<?= htmlspecialchars($client['guarantor_address']) ?>">

<div class="data-item"><?= htmlspecialchars($client['guarantor_phone'] ?? '-') ?></div>
<input type="text" name="guarantor_phone" placeholder="<?= htmlspecialchars($client['guarantor_phone']) ?>">

<div class="data-item"><?= htmlspecialchars($client['guarantor_job'] ?? '-') ?></div>
<input type="text" name="guarantor_job" placeholder="<?= htmlspecialchars($client['guarantor_job']) ?>">

<div class="section-title">اسم الموظف</div>

<div class="data-item"><?= htmlspecialchars($client['staff_name'] ?? '-') ?></div>

<select name="staff_name">
<option value="">اختر الموظف</option>

<?php foreach($staff_options as $staff): ?>

<option value="<?= htmlspecialchars($staff['staff_name']) ?>">
<?= htmlspecialchars($staff['staff_name']) ?>
</option>

<?php endforeach; ?>

</select>

<div class="section-title">ملاحظات</div>

<div class="data-item"><?= nl2br(htmlspecialchars($client['notes'] ?? '-')) ?></div>

<textarea name="notes" placeholder="<?= htmlspecialchars($client['notes']) ?>"></textarea>

</form>

</div>

<script>

function enableEdit(){

document.querySelector('button[onclick="enableEdit()"]').style.display='none';

document.getElementById('save-btn').style.display='inline-block';

document.getElementById('cancel-btn').style.display='inline-block';

document.querySelectorAll('.data-item').forEach(el=>el.style.display='none');

document.querySelectorAll('input,textarea,select').forEach(el=>{

el.style.display='block';
el.classList.add('editing');
el.value='';

});

}

function cancelEdit(){

document.querySelector('button[onclick="enableEdit()"]').style.display='inline-block';

document.getElementById('save-btn').style.display='none';

document.getElementById('cancel-btn').style.display='none';

document.querySelectorAll('.data-item').forEach(el=>el.style.display='block');

document.querySelectorAll('input,textarea,select').forEach(el=>{

el.style.display='none';
el.classList.remove('editing');
el.value='';

});

}

</script>

</body>
</html>