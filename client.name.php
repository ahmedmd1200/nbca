<?php
session_start();

// ========================
// صلاحيات المستخدم
// ========================
$allowed_roles = ['admin', 'manager', 'sub-admin', 'user']; // أدوار
$username = $_SESSION['username'] ?? '';
$role = strtolower(trim($_SESSION['role'] ?? ''));
$user_id = $_SESSION['user_id'] ?? 0;

if (empty($username)) {
    die("<h2 style='color:red;text-align:center;margin-top:50px;'>يجب تسجيل الدخول</h2>");
}

// ========================
// اتصال قاعدة البيانات
// ========================
$conn = new mysqli("localhost", "root", "", "survey_db");
if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

// ========================
// التحقق من معرف العميل
// ========================
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("معرف العميل غير صالح");
}
$id = intval($_GET['id']);

// ========================
// جلب بيانات العميل
// ========================
$stmt = $conn->prepare("SELECT * FROM surveys WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$client = $result->fetch_assoc();

// ========================
// جلب قائمة الموظفين
// ========================
$staff_options = [];
$result_staff = $conn->query("SELECT staff_name FROM staff_list ORDER BY staff_name ASC");
while ($row = $result_staff->fetch_assoc()) {
    $staff_options[] = $row;
}

// ========================
// حفظ البيانات عند POST
// ========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_code = $_POST['client_code'] ?? '';
    $client_name = $_POST['client_name'] ?? '';
    $client_address = $_POST['client_address'] ?? '';
    $client_phone = $_POST['client_phone'] ?? '';
    $guarantor_name = $_POST['guarantor_name'] ?? '';
    $guarantor_address = $_POST['guarantor_address'] ?? '';
    $guarantor_phone = $_POST['guarantor_phone'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $staff_name = $_POST['staff_name'] ?? '';

    // تحقق من الكود المكرر
    $stmt_check = $conn->prepare("SELECT client_name FROM surveys WHERE client_code=? AND id != ?");
    $stmt_check->bind_param("si", $client_code, $id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $existing = $result_check->fetch_assoc();

    if ($existing) {
        $_SESSION['msg'] = "الكود موجود بالفعل! العميل: " . htmlspecialchars($existing['client_name']);
    } else {
        // مدير أو sub-admin يمكنه الحفظ مباشرة
        if (in_array($role, ['admin','sub-admin','manager'])) {
            $stmt = $conn->prepare("
                UPDATE surveys 
                SET client_code=?, client_name=?, client_address=?, client_phone=?, 
                    guarantor_name=?, guarantor_address=?, guarantor_phone=?, notes=?, staff_name=? 
                WHERE id=?
            ");
            $stmt->bind_param(
                "sssssssssi",
                $client_code, $client_name, $client_address, $client_phone,
                $guarantor_name, $guarantor_address, $guarantor_phone, $notes, $staff_name, $id
            );
            if ($stmt->execute()) {
                $_SESSION['msg'] = "تم حفظ التعديلات بنجاح!";
            } else {
                $_SESSION['msg'] = "حدث خطأ أثناء الحفظ: " . $stmt->error;
            }
        } else {
            // المستخدم العادي: إنشاء طلب تعديل يحتاج موافقة مدير
            $new_value = json_encode([
                'client_code'=>$client_code,
                'client_name'=>$client_name,
                'client_address'=>$client_address,
                'client_phone'=>$client_phone,
                'guarantor_name'=>$guarantor_name,
                'guarantor_address'=>$guarantor_address,
                'guarantor_phone'=>$guarantor_phone,
                'notes'=>$notes,
                'staff_name'=>$staff_name
            ], JSON_UNESCAPED_UNICODE);
            $old_value = json_encode($client, JSON_UNESCAPED_UNICODE);

            $stmt = $conn->prepare("
                INSERT INTO action_requests(user_id,target_id,old_value,new_value,status,created_at)
                VALUES (?,?,?,?, 'pending', NOW())
            ");
            $stmt->bind_param("iiss", $user_id, $id, $old_value, $new_value);
            if ($stmt->execute()) {
                $_SESSION['msg'] = "تم إرسال طلب تعديل لمراجعة المدير.";
            } else {
                $_SESSION['msg'] = "حدث خطأ أثناء إرسال الطلب: " . $stmt->error;
            }
        }
    }
}

// ========================
// التحقق من كود العميل عند AJAX
// ========================
if (isset($_GET['ajax_check']) && $_GET['ajax_check']=='1') {
    $check_code = $_GET['code'] ?? '';
    $stmt_ajax = $conn->prepare("SELECT client_name FROM surveys WHERE client_code=? AND id != ?");
    $stmt_ajax->bind_param("si", $check_code, $id);
    $stmt_ajax->execute();
    $res_ajax = $stmt_ajax->get_result();
    $row_ajax = $res_ajax->fetch_assoc();
    if ($row_ajax) {
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
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin:20px; direction:rtl; font-size:20px; font-weight:bold; background: linear-gradient(rgba(195,236,241,0.7), rgba(252,246,189,0.7)); }
.container { max-width:900px; margin:0 auto; padding:25px 35px; border-radius:25px; background-color:rgba(95,158,160,0.9); box-shadow:0 10px 25px rgba(0,0,0,0.4); color:#333;}
.data-item, input[type="text"], textarea, select { padding:10px 20px; margin-bottom:10px; font-size:18px; color:#333; background-color:#f3e5f5; border-radius:12px; width:100%; box-sizing:border-box; border:none; text-align:center; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; box-shadow:0 4px 10px rgba(0,0,0,0.2); }
input[type="text"], textarea, select { display:none; }
textarea { min-height:200px; max-height:200px; overflow-y:auto; white-space:pre-wrap; background-color:#fff3b0; }
.section-title { text-align:center; margin:10px 0; font-weight:bold; font-size:34px; color:#6a1b9a; border-bottom:2px solid #ce93d8; padding-bottom:4px;}
.edit-btns { display:flex; justify-content:center; gap:10px; margin-bottom:15px;}
.edit-btns button { padding:12px 35px; background:linear-gradient(135deg,#6a1b9a,#8e24aa); color:white; border-radius:15px; font-size:20px; font-weight:bold; border:none; cursor:pointer; }
.edit-btns button:hover { transform:translateY(-2px); }
input.editing, textarea.editing, select.editing { display:block; background-color:#fff3b0; font-weight:bold; font-size:18px; text-align:center; }
.msg { text-align:center; margin-bottom:15px; font-weight:bold; color:green; }
#error { color:red; font-size:16px; margin-top:4px; }
</style>
</head>
<body>
<div class="container">
<a href="staff.php" style="padding:10px 20px; background:#3498db; color:white; text-decoration:none; border-radius:5px; font-size:26px; display: inline-block; width: 100%; text-align:center;">رجوع</a>

<?php if(isset($_SESSION['msg'])): ?>
    <div class="msg"><?= $_SESSION['msg']; unset($_SESSION['msg']); ?></div>
<?php endif; ?>

<form method="post" action="">
    <div class="edit-btns">
        <?php if(in_array($role,['admin','sub-admin','manager','user'])): ?>
            <button type="button" onclick="enableEdit()">تعديل البيانات</button>
            <button type="submit" id="save-btn" style="display:none;">حفظ</button>
            <button type="button" id="cancel-btn" style="display:none;" onclick="cancelEdit()">إلغاء</button>
        <?php endif; ?>
    </div>

    <div class="section-title">كود العميل</div>
    <div class="data-item"><?= htmlspecialchars($client['client_code'] ?? '-') ?></div>
    <input type="text" name="client_code" 
           data-value="<?= htmlspecialchars($client['client_code'] ?? '') ?>" 
           placeholder="أدخل كود العميل" required>
    <div id="code-error"></div>

    <div class="section-title">بيانات العميل</div>
    <div class="data-item"><?= htmlspecialchars($client['client_name'] ?? '-') ?></div>
    <input type="text" name="client_name" 
           data-value="<?= htmlspecialchars($client['client_name'] ?? '') ?>" 
           placeholder="أدخل اسم العميل" required>
    <div class="data-item"><?= htmlspecialchars($client['client_address'] ?? '-') ?></div>
    <input type="text" name="client_address" 
           data-value="<?= htmlspecialchars($client['client_address'] ?? '') ?>" 
           placeholder="أدخل عنوان العميل">
    <div class="data-item"><?= htmlspecialchars($client['client_phone'] ?? '-') ?></div>
    <input type="text" name="client_phone" 
           data-value="<?= htmlspecialchars($client['client_phone'] ?? '') ?>" 
           placeholder="أدخل رقم الهاتف">

    <div class="section-title">بيانات الضامن</div>
    <div class="data-item"><?= htmlspecialchars($client['guarantor_name'] ?? '-') ?></div>
    <input type="text" name="guarantor_name" 
           data-value="<?= htmlspecialchars($client['guarantor_name'] ?? '') ?>" 
           placeholder="أدخل اسم الضامن">
    <div class="data-item"><?= htmlspecialchars($client['guarantor_address'] ?? '-') ?></div>
    <input type="text" name="guarantor_address" 
           data-value="<?= htmlspecialchars($client['guarantor_address'] ?? '') ?>" 
           placeholder="أدخل عنوان الضامن">
    <div class="data-item"><?= htmlspecialchars($client['guarantor_phone'] ?? '-') ?></div>
    <input type="text" name="guarantor_phone" 
           data-value="<?= htmlspecialchars($client['guarantor_phone'] ?? '') ?>" 
           placeholder="أدخل رقم هاتف الضامن">

    <div class="section-title">المسؤول</div>
    <div class="data-item"><?= htmlspecialchars($client['staff_name'] ?? '-') ?></div>
    <select name="staff_name">
        <?php foreach($staff_options as $staff): 
            $staff_name_var = $staff['staff_name']; // لتجنب تمرير دالة مباشرة
        ?>
            <option value="<?= htmlspecialchars($staff_name_var) ?>" <?= ($staff_name_var == ($client['staff_name'] ?? '')) ? 'selected' : '' ?>>
                <?= htmlspecialchars($staff_name_var) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <div class="section-title">ملاحظات</div>
    <div class="data-item"><?= nl2br(htmlspecialchars($client['notes'] ?? '-')) ?></div>
    <textarea name="notes" 
              data-value="<?= htmlspecialchars($client['notes'] ?? '') ?>" 
              placeholder="أدخل ملاحظات"></textarea>
</form>
</div>

<script>
function enableEdit() {
    document.querySelector('button[onclick="enableEdit()"]').style.display='none';
    document.getElementById('save-btn').style.display='inline-block';
    document.getElementById('cancel-btn').style.display='inline-block';

    document.querySelectorAll('.data-item').forEach(el=>el.style.display='none');
    document.querySelectorAll('input, textarea, select').forEach(el=>{
        el.style.display='block';
        el.classList.add('editing');
        el.value = el.getAttribute('data-value') || el.value;
    });
}

function cancelEdit() {
    document.querySelector('button[onclick="enableEdit()"]').style.display='inline-block';
    document.getElementById('save-btn').style.display='none';
    document.getElementById('cancel-btn').style.display='none';

    document.querySelectorAll('.data-item').forEach(el=>el.style.display='block');
    document.querySelectorAll('input, textarea, select').forEach(el=>{
        el.style.display='none';
        el.classList.remove('editing');
        el.value = el.getAttribute('data-value') || el.value;
        el.style.border = '';
        if(el.name === 'client_code') document.getElementById('code-error').textContent='';
    });
}

const codeInput = document.querySelector('input[name="client_code"]');
const codeError = document.getElementById('code-error');

codeInput.addEventListener('input', function() {
    codeError.textContent = '';
    codeInput.style.border = '';
});

codeInput.addEventListener('blur', function(){
    let code = this.value.trim();
    if(code==='') return;

    fetch('?ajax_check=1&code='+encodeURIComponent(code))
    .then(r => r.json())
    .then(data => {
        if(data.exists){
            codeError.textContent = 'الكود موجود بالفعل! العميل: ' + data.client_name;
            codeInput.style.border = '2px solid red';
            codeInput.focus();
        }
    })
    .catch(err => console.error('حدث خطأ في التحقق من الكود:', err));
});

document.querySelector('form').addEventListener('submit', function(e){
    let inputs = document.querySelectorAll('input.editing, textarea.editing, select.editing');
    let valid = true;
    inputs.forEach(input=>{
        if(input.value.trim()===''){ valid=false; input.style.border='2px solid red'; } 
        else { input.style.border=''; }
    });
    if(codeError.textContent !== '') { valid=false; codeInput.focus(); }
    if(!valid){ alert('يرجى تعبئة جميع الحقول بشكل صحيح قبل الحفظ!'); e.preventDefault(); return; }
    if(!confirm('هل تريد حفظ التعديلات؟')) { e.preventDefault(); return; }
});
</script>
</body>
</html>