<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

if($_SESSION['role'] !== 'manager') die("⚠️ لا يمكنك الدخول لهذه الصفحة");

$pdo = new PDO("mysql:host=localhost;dbname=survey_db;charset=utf8","root","");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$allowed_tables = ['users','posts','comments'];

// --- معالجة طلب AJAX للموافقة/الرفض ---
if(isset($_POST['ajax_action'])){
    $id = (int)$_POST['id'];
    $action = $_POST['ajax_action'];

    $stmt = $pdo->prepare("SELECT * FROM action_requests WHERE id=?");
    $stmt->execute([$id]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$req){ echo json_encode(['status'=>'error','message'=>'⚠️ الطلب غير موجود']); exit(); }
    if(!in_array($req['target_table'], $allowed_tables)){ echo json_encode(['status'=>'error','message'=>'⚠️ جدول غير مسموح']); exit(); }

    if($action==='approve' && $req['status']=='pending'){
        if($req['action_type']==='تعديل'){
            $stmt2 = $pdo->prepare("UPDATE ".$req['target_table']." SET data=? WHERE id=?");
            $stmt2->execute([$req['new_value'],$req['target_id']]);
        } elseif($req['action_type']==='حذف'){
            $stmt2 = $pdo->prepare("DELETE FROM ".$req['target_table']." WHERE id=?");
            $stmt2->execute([$req['target_id']]);
        }
        $stmt3 = $pdo->prepare("UPDATE action_requests SET status='approved' WHERE id=?");
        $stmt3->execute([$id]);
        echo json_encode(['status'=>'success','new_status'=>'approved']); exit();
    } elseif($action==='reject' && $req['status']=='pending'){
        $stmt3 = $pdo->prepare("UPDATE action_requests SET status='rejected' WHERE id=?");
        $stmt3->execute([$id]);
        echo json_encode(['status'=>'success','new_status'=>'rejected']); exit();
    } else {
        echo json_encode(['status'=>'error','message'=>'⚠️ لا يمكن تنفيذ العملية']); exit();
    }
}

// --- فلترة وبحث ---
$filter_user = $_GET['user'] ?? '';
$filter_action = $_GET['action_type'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$export_csv = $_GET['export'] ?? false;

// Pagination
$per_page = 10;
$page = max(1,(int)($_GET['page'] ?? 1));
$start = ($page-1)*$per_page;

// بناء شروط WHERE
$where = [];
$params = [];

if($filter_user){ $where[] = "username LIKE ?"; $params[] = "%$filter_user%"; }
if($filter_action){ $where[] = "action_type=?"; $params[] = $filter_action; }
if($filter_status){ $where[] = "status=?"; $params[] = $filter_status; }
if($search){ $where[] = "(username LIKE ? OR new_value LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$where_sql = $where ? "WHERE ".implode(" AND ", $where) : "";

// تحميل CSV
if($export_csv){
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=action_requests.csv');
    $output = fopen('php://output','w');
    fputcsv($output,['ID','المستخدم','العملية','الجدول','رقم الصف','القيمة الجديدة','الحالة','تاريخ الإنشاء']);
    $stmt_csv = $pdo->prepare("SELECT * FROM action_requests $where_sql ORDER BY created_at DESC");
    $stmt_csv->execute($params);
    while($row=$stmt_csv->fetch(PDO::FETCH_ASSOC)){
        fputcsv($output,[$row['id'],$row['username'],$row['action_type'],$row['target_table'],$row['target_id'],$row['new_value'],$row['status'],$row['created_at']]);
    }
    exit();
}

// جلب العدد الكلي
$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM action_requests $where_sql");
$total_stmt->execute($params);
$total_requests = $total_stmt->fetchColumn();
$total_pages = ceil($total_requests / $per_page);

// جلب الطلبات للصفحة الحالية
$stmt = $pdo->prepare("SELECT * FROM action_requests $where_sql ORDER BY created_at DESC LIMIT $start,$per_page");
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>إدارة الطلبات المتقدمة AJAX</title>
<style>
body{font-family:sans-serif;padding:20px;background:#f5f5f5;}
table{border-collapse:collapse;width:100%;margin-top:15px;}
th,td{border:1px solid #ccc;padding:10px;text-align:center;}
th{background:#f5576c;color:white;}
button{padding:5px 10px;border-radius:5px;border:none;color:white;cursor:pointer;}
.approve{background:green;}
.reject{background:red;}
.approved{background:#d4edda;}
.rejected{background:#f8d7da;}
.message{margin-bottom:15px;padding:10px;background:#e7f3fe;border-left:5px solid #2196F3;}
.filter-form input,.filter-form select{padding:5px;margin:0 5px;}
.pagination a{margin:0 5px;text-decoration:none;color:#f5576c;font-weight:bold;}
.export-btn{background:#2196F3;color:white;padding:5px 10px;border-radius:5px;text-decoration:none;}
</style>
</head>
<body>
<h2>إدارة الطلبات المتقدمة بتقنية AJAX</h2>

<div id="message" class="message" style="display:none;"></div>

<!-- نموذج فلترة وبحث -->
<form class="filter-form" method="get">
<input type="text" name="user" placeholder="بحث بالمستخدم" value="<?php echo htmlspecialchars($filter_user); ?>">
<select name="action_type">
<option value="">كل العمليات</option>
<option value="تعديل" <?php if($filter_action==='تعديل') echo 'selected'; ?>>تعديل</option>
<option value="حذف" <?php if($filter_action==='حذف') echo 'selected'; ?>>حذف</option>
</select>
<select name="status">
<option value="">كل الحالات</option>
<option value="pending" <?php if($filter_status==='pending') echo 'selected'; ?>>معلق</option>
<option value="approved" <?php if($filter_status==='approved') echo 'selected'; ?>>موافق عليه</option>
<option value="rejected" <?php if($filter_status==='rejected') echo 'selected'; ?>>مرفوض</option>
</select>
<input type="text" name="search" placeholder="بحث عام" value="<?php echo htmlspecialchars($search); ?>">
<button type="submit">فلترة</button>
<a class="export-btn" href="<?php echo $_SERVER['PHP_SELF'].'?'.http_build_query(array_merge($_GET,['export'=>1])); ?>">تحميل CSV</a>
</form>

<table id="requests_table">
<tr>
<th>المستخدم</th>
<th>العملية</th>
<th>الجدول</th>
<th>رقم الصف</th>
<th>القيمة الجديدة</th>
<th>الحالة</th>
<th>الإجراء</th>
</tr>

<?php foreach($requests as $r): ?>
<tr id="row-<?php echo $r['id']; ?>" class="<?php echo $r['status']=='approved'?'approved':($r['status']=='rejected'?'rejected':''); ?>">
<td><?php echo htmlspecialchars($r['username']); ?></td>
<td><?php echo htmlspecialchars($r['action_type']); ?></td>
<td><?php echo htmlspecialchars($r['target_table']); ?></td>
<td><?php echo $r['target_id']; ?></td>
<td><?php echo htmlspecialchars($r['new_value']); ?></td>
<td class="status"><?php echo $r['status']; ?></td>
<td>
<?php if($r['status']=='pending'): ?>
<button class="approve" data-id="<?php echo $r['id']; ?>">✅ موافقة</button>
<button class="reject" data-id="<?php echo $r['id']; ?>">❌ رفض</button>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>

<!-- Pagination -->
<div class="pagination">
<?php
for($i=1;$i<=$total_pages;$i++){
    $params_page = $_GET;
    $params_page['page']=$i;
    $url = $_SERVER['PHP_SELF'].'?'.http_build_query($params_page);
    echo "<a href='$url'>$i</a>";
}
?>
</div>

<script>
document.querySelectorAll('.approve, .reject').forEach(btn=>{
    btn.addEventListener('click',function(){
        let id = this.dataset.id;
        let action = this.classList.contains('approve') ? 'approve' : 'reject';
        if(!confirm(action==='approve'?'هل أنت متأكد من الموافقة؟':'هل أنت متأكد من الرفض؟')) return;

        fetch('',{
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'ajax_action='+action+'&id='+id
        }).then(res=>res.json())
        .then(data=>{
            let msgDiv = document.getElementById('message');
            msgDiv.style.display='block';
            msgDiv.textContent = data.message ?? (data.status==='success'?'تم تحديث الحالة ✅':'خطأ غير معروف');

            let row = document.getElementById('row-'+id);
            if(data.status==='success'){
                row.className = data.new_status==='approved'?'approved':'rejected';
                row.querySelector('.status').textContent = data.new_status;
                row.querySelectorAll('button').forEach(b=>b.remove());
            }
        }).catch(err=>alert('حدث خطأ في العملية'));
    });
});
</script>

</body>
</html>