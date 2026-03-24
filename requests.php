<?php
session_start();
session_regenerate_id(true);

/*========================
الصلاحيات
========================*/
$allowed_roles = ['admin','sub-admin','manager'];

$username = $_SESSION['username'] ?? '';
$role     = strtolower(trim($_SESSION['role'] ?? ''));
$user_id  = $_SESSION['user_id'] ?? 0;

if(empty($username) || !in_array($role,$allowed_roles)){
    echo "<h2 style='color:red;text-align:center;margin-top:50px;'>ليس لديك صلاحية</h2>";
    exit;
}

/*========================
الاتصال بقاعدة البيانات
========================*/
try{
    $pdo = new PDO(
        "mysql:host=localhost;dbname=survey_db;charset=utf8",
        "root",
        "",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}catch(PDOException $e){
    die("Database connection failed: ".$e->getMessage());
}

/*========================
أسماء الحقول
========================*/
$field_names = [
    "client_code"=>"كود العميل",
    "client_name"=>"اسم العميل",
    "client_address"=>"عنوان العميل",
    "client_phone"=>"هاتف العميل",
    "client_job"=>"وظيفة العميل",
    "guarantor_name"=>"اسم الضامن",
    "guarantor_address"=>"عنوان الضامن",
    "guarantor_phone"=>"هاتف الضامن",
    "guarantor_job"=>"وظيفة الضامن",
    "notes"=>"ملاحظات",
    "staff_name"=>"الموظف المسؤول"
];
$allowed_fields = array_keys($field_names);

/*========================
دالة إرسال إشعار متوافقة مع الجدول الحالي
========================*/
function sendNotification(PDO $pdo, $user_id, $msg){
    // العمود الحالي في جدول notifications هو: title
    $stmt = $pdo->prepare("INSERT INTO notifications(user_id, title, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$user_id, $msg]);
}

/*========================
التعامل مع الطلبات (AJAX)
========================*/
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])){
    try {
        $action = $_POST['action'];
        $request_id = intval($_POST['request_id']);

        switch($action){
            case "approve":
                $field  = $_POST['field'] ?? '';
                $value  = $_POST['value'] ?? '';
                $target = intval($_POST['target_id']);
                $user_target = intval($_POST['user_id']);
                if(!in_array($field,$allowed_fields)) throw new Exception("حقل غير مسموح");
                $pdo->prepare("UPDATE surveys SET `$field`=? WHERE id=?")->execute([$value,$target]);
                $pdo->prepare("UPDATE action_requests SET status='approved' WHERE id=?")->execute([$request_id]);
                sendNotification($pdo, $user_target, "تمت الموافقة على تعديل ".$field_names[$field]);
                break;

            case "reject":
                $field  = $_POST['field'] ?? '';
                $reason = trim($_POST['reason'] ?? '');
                $user_target = intval($_POST['user_id']);
                if(!in_array($field,$allowed_fields)) throw new Exception("حقل غير مسموح");
                if($reason === '') throw new Exception("الرجاء إدخال سبب الرفض");
                $pdo->prepare("UPDATE action_requests SET status='rejected' WHERE id=?")->execute([$request_id]);
                sendNotification($pdo, $user_target, "تم رفض تعديل ".$field_names[$field]." | السبب: ".$reason);
                break;

            case "approve_all":
                $stmt = $pdo->prepare("SELECT * FROM action_requests WHERE id=?");
                $stmt->execute([$request_id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if(!$row) throw new Exception("الطلب غير موجود");
                $new = json_decode($row['new_value'], true);
                foreach($new as $key => $value){
                    if(!in_array($key, $allowed_fields)) continue;
                    $pdo->prepare("UPDATE surveys SET `$key`=? WHERE id=?")->execute([$value,$row['target_id']]);
                }
                $pdo->prepare("UPDATE action_requests SET status='approved' WHERE id=?")->execute([$request_id]);
                break;

            case "reject_all":
                $reason = trim($_POST['reason'] ?? '');
                $user_target = intval($_POST['user_id']);
                if($reason === '') throw new Exception("الرجاء إدخال سبب رفض الطلب");
                $pdo->prepare("UPDATE action_requests SET status='rejected' WHERE id=?")->execute([$request_id]);
                sendNotification($pdo, $user_target, "تم رفض الطلب بالكامل | السبب: ".$reason);
                break;

            default:
                throw new Exception("إجراء غير معروف");
        }
        echo json_encode(["status"=>"ok"]);
    } catch (Exception $e) {
        echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
    }
    exit;
}

/*========================
جلب الطلبات
========================*/
$status = $_GET['status'] ?? 'pending';
$stmt = $pdo->prepare("SELECT * FROM action_requests WHERE status=? ORDER BY id DESC");
$requests = [];
if($stmt->execute([$status])){
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$count = $pdo->query("SELECT COUNT(*) FROM action_requests WHERE status='pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>طلبات التعديل</title>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<style>
body{font-family:tahoma;direction:rtl;background:#f4f6f9;padding:30px;}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
.badge{background:red;color:white;padding:5px 10px;border-radius:20px;}
.filter a{margin-left:10px;text-decoration:none;padding:6px 12px;background:#3498db;color:white;border-radius:5px;}
.card{background:white;padding:20px;margin-bottom:20px;border-radius:8px;box-shadow:0 3px 8px rgba(0,0,0,0.1);border-right:5px solid #3498db;}
.request-title{font-size:18px;font-weight:bold;cursor:pointer;color:#2980b9;}
.details{display:none;margin-top:15px;}
.change{margin-top:15px;padding:10px;background:#fafafa;border-right:4px solid #3498db;}
.compare{display:flex;gap:10px;margin-top:10px;}
.box{flex:1;padding:10px;border-radius:5px;}
.old{background:#f8d7da;}
.new{background:#d4edda;}
button{padding:6px 12px;border:none;border-radius:4px;cursor:pointer;margin-top:10px;}
.approve{background:#27ae60;color:white;}
.reject{background:#e74c3c;color:white;}
.approveall{background:#2980b9;color:white;}
.rejectall{background:#c0392b;color:white;}
textarea{width:100%;padding:6px;margin-top:8px;resize:none;}
.error-msg{color:red;margin-top:5px;}
</style>
</head>
<body>

<div class="header">
<h2>طلبات التعديل <span class="badge"><?=$count?></span></h2>
<div class="filter">
<a href="?status=pending">قيد الانتظار</a>
<a href="?status=approved">موافق</a>
<a href="?status=rejected">مرفوض</a>
</div>
</div>

<p>مرحباً <b><?=htmlspecialchars($username)?></b></p>

<div id="requests-container">
<?php if(!empty($requests) && is_array($requests)):
    foreach($requests as $row):
        $old=json_decode($row['old_value'],true);
        $new=json_decode($row['new_value'],true);

        $changed_fields = [];
        foreach($new as $key=>$value){
            $old_val = $old[$key] ?? '';
            if($old_val == ""){
                $stmt=$pdo->prepare("SELECT `$key` FROM surveys WHERE id=?");
                $stmt->execute([$row['target_id']]);
                $old_val=$stmt->fetchColumn();
            }
            if(trim($old_val) != trim($value)){
                $changed_fields[$key] = ['old'=>$old_val,'new'=>$value];
            }
        }

        if(count($changed_fields) === 0) continue;

        $client_name = $new['client_name'] ?? ($old['client_name'] ?? 'بدون اسم');
?>
<div class="card" data-id="<?=$row['id']?>">
    <div class="request-title"><?=$client_name?></div>
    <div class="details">
        <div class="error-msg"></div>
        <button class="approveall" data-id="<?=$row['id']?>">موافقة على الكل</button>
        <button class="rejectall" data-id="<?=$row['id']?>" data-user="<?=$row['user_id']?>">رفض الطلب</button>

        <?php foreach($changed_fields as $key=>$vals): ?>
        <div class="change">
            <b><?=$field_names[$key] ?? $key?></b>
            <div class="compare">
                <div class="box old"><b>القديم</b><br><?=htmlspecialchars($vals['old'])?></div>
                <div class="box new"><b>الجديد</b><br><?=htmlspecialchars($vals['new'])?></div>
            </div>
            <textarea placeholder="سبب الرفض" data-request="<?=$row['id']?>" data-field="<?=$key?>"></textarea>
            <div class="error-msg"></div>
            <button class="approve" data-id="<?=$row['id']?>" data-field="<?=$key?>" data-target="<?=$row['target_id']?>" data-user="<?=$row['user_id']?>" data-value="<?=htmlspecialchars($vals['new'])?>">موافقة</button>
            <button class="reject" data-id="<?=$row['id']?>" data-field="<?=$key?>" data-target="<?=$row['target_id']?>" data-user="<?=$row['user_id']?>">رفض</button>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; endif; ?>
</div>

<script>
$(document).ready(function(){

    function showError(btn, msg){
        btn.closest(".details").find(".error-msg").first().text(msg);
    }

    function ajaxAction(data, btn, callback){
        $.post("", data, function(response){
            try{
                var res = JSON.parse(response);
                if(res.status === "ok"){
                    callback();
                } else {
                    showError(btn, res.message);
                }
            } catch(e){
                showError(btn, "حدث خطأ غير متوقع");
            }
        });
    }

    $(document).on("click", ".request-title", function(){
        $(this).closest(".card").find(".details").slideToggle();
    });

    $(document).on("click", ".approve", function(){
        var btn = $(this);
        ajaxAction({
            action: "approve",
            request_id: btn.data("id"),
            field: btn.data("field"),
            target_id: btn.data("target"),
            user_id: btn.data("user"),
            value: btn.data("value")
        }, btn, function(){
            btn.closest(".change").fadeOut();
        });
    });

    $(document).on("click", ".reject", function(){
        var btn = $(this);
        var reason = $("textarea[data-request='"+btn.data("id")+"'][data-field='"+btn.data("field")+"']").val();
        if(reason.trim() === ""){
            showError(btn,"الرجاء إدخال سبب الرفض.");
            return;
        }
        ajaxAction({
            action: "reject",
            request_id: btn.data("id"),
            field: btn.data("field"),
            target_id: btn.data("target"),
            user_id: btn.data("user"),
            reason: reason
        }, btn, function(){
            btn.closest(".change").fadeOut();
        });
    });

    $(document).on("click", ".approveall", function(){
        var btn = $(this);
        ajaxAction({action:"approve_all", request_id:btn.data("id")}, btn, function(){
            btn.closest(".card").fadeOut();
        });
    });

    $(document).on("click", ".rejectall", function(){
        var btn = $(this);
        var reason = prompt("سبب رفض الطلب:");
        if(reason === null || reason.trim() === "") return;
        ajaxAction({
            action:"reject_all",
            request_id:btn.data("id"),
            user_id:btn.data("user"),
            reason:reason
        }, btn, function(){
            btn.closest(".card").fadeOut();
        });
    });

});
</script>

</body>
</html>