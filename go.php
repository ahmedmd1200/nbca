<?php
session_start();

// ======= التحقق من تسجيل الدخول =======
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// ======= إعداد قاعدة البيانات =======
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "survey_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("فشل الاتصال: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// ======= جلب الأخصائيين =======
$staff_list = [];
$result = $conn->query("SELECT id, staff_name FROM staff_list ORDER BY staff_name ASC");
if($result){
    while($row = $result->fetch_assoc()){
        $staff_list[] = $row;
    }
}

// ======= متغيرات تخزين البيانات =======
$client_code = $client_name = $client_address = $client_phone = "";
$guarantor_name = $guarantor_address = $guarantor_phone = "";
$notes = "";
$user_id = ""; // الأخصائي
$errors = [];

// ======= معالجة POST =======
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $client_code       = htmlspecialchars(trim($_POST['client_code']));
    $client_name       = htmlspecialchars(trim($_POST['client_name']));
    $client_address    = htmlspecialchars(trim($_POST['client_address']));
    $client_phone      = htmlspecialchars(trim($_POST['client_phone']));
    $guarantor_name    = htmlspecialchars(trim($_POST['guarantor_name']));
    $guarantor_address = htmlspecialchars(trim($_POST['guarantor_address']));
    $guarantor_phone   = htmlspecialchars(trim($_POST['guarantor_phone']));
    $notes             = htmlspecialchars(trim($_POST['notes']));
    $user_id           = htmlspecialchars(trim($_POST['user_id']));

    $fields = [
        'client_code' => 'كود العميل',
        'client_name' => 'اسم العميل',
        'client_address' => 'عنوان العميل',
        'client_phone' => 'رقم العميل',
        'guarantor_name' => 'اسم الضامن',
        'guarantor_address' => 'عنوان الضامن',
        'guarantor_phone' => 'رقم الضامن',
        'user_id' => 'الأخصائي'
    ];

    foreach ($fields as $field => $label) {
        if (empty($$field)) {
            $errors[$field] = "الرجاء إدخال {$label}";
        }
    }

    if (!empty($client_code) && (!ctype_digit($client_code) || strlen($client_code) > 8)) {
        $errors['client_code'] = "الكود يجب أن يكون أرقام فقط وبحد أقصى 8 أرقام";
    }
    if (!empty($client_phone) && (!ctype_digit($client_phone) || strlen($client_phone) > 12)) {
        $errors['client_phone'] = "رقم العميل يجب أن يكون أرقام فقط وبحد أقصى 12 رقم";
    }
    if (!empty($guarantor_phone) && (!ctype_digit($guarantor_phone) || strlen($guarantor_phone) > 12)) {
        $errors['guarantor_phone'] = "رقم الضامن يجب أن يكون أرقام فقط وبحد أقصى 12 رقم";
    }

    if (empty($errors)) {
        $sql = "INSERT INTO surveys
                (client_code, client_name, client_address, client_phone,
                 guarantor_name, guarantor_address, guarantor_phone, notes, user_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("خطأ في الاستعلام: " . $conn->error);
        }

        $stmt->bind_param(
            "sssssssss",
            $client_code, $client_name, $client_address, $client_phone,
            $guarantor_name, $guarantor_address, $guarantor_phone, $notes, $user_id
        );

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "تم حفظ الاستبيان بنجاح!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            error_log("DB Error: " . $stmt->error);
            echo "<script>alert('حدث خطأ أثناء الحفظ.');</script>";
        }

        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>استبيان متكامل</title>
<style>
*{margin:0; padding:0;}
body {font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; direction:rtl;
      background: linear-gradient(135deg, #c3ecf1, #fcf6bd);}
form {max-width:700px; margin:20px auto; padding:30px 25px; border-radius:25px;
       background-color:#ffffffdd; box-shadow:0 15px 40px rgba(0,0,0,0.2);}
.section-title {text-align:center; margin:20px 0 10px; font-weight:bold; font-size:27px;
                color:#6a1b9a; border-bottom:3px solid #ce93d8; padding-bottom:3px;}
.form-group {display:flex; flex-direction:column; margin-bottom:15px;}
.double-row {display:flex; gap:10px; flex-wrap:wrap;}
.double-row .form-group {flex:1; min-width:280px;}
label {font-size:22px; font-weight:bold; color:#4a148c; margin-bottom:5px;}
input, textarea, select {padding:12px 15px; font-size:18px; border:2px solid #ce93d8;
                  border-radius:10px; transition:0.3s; background-color:#f3e5f5;}
input:focus, textarea:focus, select:focus {border-color:#6a1b9a; box-shadow:0 0 8px rgba(106,27,154,0.4); outline:none;}
textarea {resize:vertical; min-height:50px;}
button {display:block; width:230px; margin:20px auto 0; padding:14px; font-size:20px;
       font-weight:bold; color:#fff; background: linear-gradient(135deg, #6a1b9a, #ab47bc);
       border:none; border-radius:15px; cursor:pointer; transition:0.3s;}
button:hover {background: linear-gradient(135deg, #4a148c, #8e24aa); transform: scale(1.05);}
.success-message {text-align:center; font-size:22px; color:green; margin-bottom:15px;}
.error-message {color:red; font-size:16px; margin-top:4px;}
</style>
</head>
<body>

<?php
if(isset($_SESSION['success_message'])){
    echo "<div class='success-message'>" . htmlspecialchars($_SESSION['success_message']) . "</div>";
    unset($_SESSION['success_message']);
}
?>
<div style="text-align:center; margin:20px 0;">
  <a href="das.php" style="padding:10px 20px; 
    background:#3498db; 
    color:white;
    text-decoration:none; 
    border-radius:5px; 
    font-size:26px;
    display: inline-block;
    width: 80%;
    text-align: center;">رجوع</a>
</div>

<form method="POST" action="" autocomplete="off">

    <div class="form-group">
        <label>الأخصائي:</label>
        <select name="user_id" required>
            <option value="">اختر الأخصائي</option>
            <?php foreach($staff_list as $staff): ?>
                <option value="<?php echo $staff['id']; ?>" <?php if($staff['id'] == $user_id) echo "selected"; ?>>
                    <?php echo htmlspecialchars($staff['staff_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if(isset($errors['user_id'])) echo "<div class='error-message'>{$errors['user_id']}</div>"; ?>
    </div>

    <div class="form-group">
        <label>كود العميل:</label>
        <input type="text" name="client_code" value="<?php echo $client_code; ?>" placeholder="أدخل كود العميل" required>
        <?php if(isset($errors['client_code'])) echo "<div class='error-message'>{$errors['client_code']}</div>"; ?>
    </div>

    <div class="section-title">بيانات العميل</div>
    <div class="double-row">
        <div class="form-group">
            <label>اسم العميل:</label>
            <input type="text" name="client_name" value="<?php echo $client_name; ?>" placeholder="أدخل اسم العميل" required>
            <?php if(isset($errors['client_name'])) echo "<div class='error-message'>{$errors['client_name']}</div>"; ?>
        </div>
        <div class="form-group">
            <label>العنوان:</label>
            <input type="text" name="client_address" value="<?php echo $client_address; ?>" placeholder="أدخل عنوان العميل" required>
            <?php if(isset($errors['client_address'])) echo "<div class='error-message'>{$errors['client_address']}</div>"; ?>
        </div>
    </div>

    <div class="double-row">
        <div class="form-group">
            <label>رقم العميل:</label>
            <input type="tel" name="client_phone" value="<?php echo $client_phone; ?>" placeholder="أدخل رقم العميل" required>
            <?php if(isset($errors['client_phone'])) echo "<div class='error-message'>{$errors['client_phone']}</div>"; ?>
        </div>
    </div>

    <div class="section-title">بيانات الضامن</div>
    <div class="double-row">
        <div class="form-group">
            <label>اسم الضامن:</label>
            <input type="text" name="guarantor_name" value="<?php echo $guarantor_name; ?>" placeholder="أدخل اسم الضامن" required>
            <?php if(isset($errors['guarantor_name'])) echo "<div class='error-message'>{$errors['guarantor_name']}</div>"; ?>
        </div>
        <div class="form-group">
            <label>العنوان:</label>
            <input type="text" name="guarantor_address" value="<?php echo $guarantor_address; ?>" placeholder="أدخل عنوان الضامن" required>
            <?php if(isset($errors['guarantor_address'])) echo "<div class='error-message'>{$errors['guarantor_address']}</div>"; ?>
        </div>
    </div>

    <div class="double-row">
        <div class="form-group">
            <label>رقم الضامن:</label>
            <input type="tel" name="guarantor_phone" value="<?php echo $guarantor_phone; ?>" placeholder="أدخل رقم الضامن" required>
            <?php if(isset($errors['guarantor_phone'])) echo "<div class='error-message'>{$errors['guarantor_phone']}</div>"; ?>
        </div>
    </div>

    <div class="form-group">
        <label>ملاحظات:</label>
        <textarea name="notes" placeholder="أدخل أي ملاحظات إضافية"><?php echo $notes; ?></textarea>
    </div>

    <button type="submit">إرسال البيانات</button>
</form>

</body>
</html>