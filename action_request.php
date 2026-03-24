<?php
session_start();
$pdo = new PDO("mysql:host=localhost;dbname=survey_db;charset=utf8","root","");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$user_id = $_SESSION['user_id'] ?? 0;
$username = $_SESSION['username'] ?? '';
$can_edit = $_SESSION['can_edit'] ?? 0;

// إذا المستخدم يحاول تعديل أو حذف
if(isset($_POST['action_type'], $_POST['target_table'], $_POST['target_id'])){

    $action_type = $_POST['action_type'];       // تعديل أو حذف
    $target_table = $_POST['target_table'];     // جدول الهدف
    $target_id = (int)$_POST['target_id'];      // صف الهدف
    $new_value = $_POST['new_value'] ?? null;   // القيمة الجديدة لو تعديل

    if(!$can_edit){
        // المستخدم عادي → نرسل طلب للمدير
        $stmt = $pdo->prepare("INSERT INTO action_requests
            (user_id, username, action_type, target_table, target_id, new_value)
            VALUES (?,?,?,?,?,?)");
        $stmt->execute([$user_id,$username,$action_type,$target_table,$target_id,$new_value]);
        echo "❌ لا يمكنك تنفيذ $action_type بدون موافقة المدير. تم إرسال الطلب.";
        exit();
    } else {
        // المستخدم عنده صلاحية → ننفذ مباشرة
        if($action_type==='تعديل'){
            $stmt = $pdo->prepare("UPDATE $target_table SET data=? WHERE id=?");
            $stmt->execute([$new_value,$target_id]);
            echo "✅ تم التعديل بنجاح!";
        } elseif($action_type==='حذف'){
            $stmt = $pdo->prepare("DELETE FROM $target_table WHERE id=?");
            $stmt->execute([$target_id]);
            echo "✅ تم الحذف بنجاح!";
        }
        exit();
    }
}
?>