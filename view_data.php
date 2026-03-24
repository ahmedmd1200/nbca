<?php
$names = ["هاجر","عمر","ليلى","يوسف","مريم","علي","سارة","كريم","ندى","خالد","ريم","أحمد","فاطمة","سامي","ياسمين"];

// الشهر والسنة من GET
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$selectedDay = isset($_GET['day']) ? intval($_GET['day']) : 0; // اليوم المختار

// إعداد قاعدة البيانات
$host = "localhost";
$db = "daily_tracker";
$user = "root";
$pass = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e){
    die("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
}

// قراءة البيانات للشهر والسنة المختارين
$monthData = [];
$stmt = $pdo->prepare("SELECT * FROM daily_data WHERE month=:month AND year=:year");
$stmt->execute([':month'=>$month, ':year'=>$year]);
while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
    $monthData[$row['day']][$row['name']] = $row['value'];
}

// حساب عدد أيام الشهر
$totalDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
?>

<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>عرض البيانات</title>
<style>
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; background: #f5f5f5; }
h1 { text-align: center; color: #2c3e50; }
table { border-collapse: collapse; width: 100%; background: white; margin-bottom: 20px;}
th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
th { background-color: #3498db; color: white; position: sticky; top: 0; }
tr:nth-child(even){background-color: #f2f2f2;}
form { margin-bottom: 20px; text-align: center; }
select { padding: 5px; font-size: 16px; }
button { padding: 5px 10px; font-size: 16px; }
</style>
</head>
<body>

<h1>بيانات شهر <?php echo $month; ?> / <?php echo $year; ?></h1>
<br>
<!-- زر في منتصف الصفحة -->
<div style="text-align:center; margin:20px 0;">
    <a href="das.php" 
       style="padding:10px 20px; background:#3498db; color:white; text-decoration:none; border-radius:5px; font-size:16px;">
       رجوع 
    </a>
</div><br><br>
<!-- نموذج اختيار اليوم -->
<form method="get">
    <input type="hidden" name="month" value="<?php echo $month; ?>">
    <input type="hidden" name="year" value="<?php echo $year; ?>">
    اختر اليوم:
    <select name="day">
        <option value="0">كل الأيام</option>
        <?php for($i=1; $i<=$totalDays; $i++): ?>
            <option value="<?php echo $i; ?>" <?php if($selectedDay==$i) echo 'selected'; ?>>
                <?php echo $i; ?>
            </option>
        <?php endfor; ?>
    </select>
    <button type="submit">عرض</button>
</form>

<table>
<tr>
    <th>اليوم</th>
    <?php foreach($names as $name): ?>
        <th><?php echo $name; ?></th>
    <?php endforeach; ?>
</tr>

<?php 
$startDay = $selectedDay > 0 ? $selectedDay : 1;
$endDay = $selectedDay > 0 ? $selectedDay : $totalDays;

for($d=$startDay; $d<=$endDay; $d++): ?>
<tr>
    <td><?php echo $d; ?></td>
    <?php foreach($names as $name): ?>
        <td><?php echo isset($monthData[$d][$name]) ? $monthData[$d][$name] : ''; ?></td>
    <?php endforeach; ?>
</tr>
<?php endfor; ?>
</table>

</body>
</html>