<?php
session_start();
// ======= التحقق من تسجيل الدخول =======
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$names = ["هاجر","عمر","ليلى","يوسف","مريم","علي","سارة","كريم","ندى","خالد","ريم","أحمد","فاطمة","سامي","ياسمين"];

// الشهر والسنة الحالي أو من GET
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m'); 
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// عدد أيام الشهر
$totalDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);

// --- إعداد قاعدة البيانات ---
$host = "localhost";
$db = "daily_tracker";
$user = "root";
$pass = ""; // ضع كلمة المرور هنا إذا كانت موجودة

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e){
    die("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
}

// حفظ البيانات عند POST
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'], $_POST['day'], $_POST['value'], $_POST['month'], $_POST['year'])){
    $name = $_POST['name'];
    $day = intval($_POST['day']);
    $monthPost = intval($_POST['month']);
    $yearPost = intval($_POST['year']);
    $value = $_POST['value'] !== '' ? intval($_POST['value']) : null;

    $stmt = $pdo->prepare("
        INSERT INTO daily_data (name, day, month, year, value) 
        VALUES (:name, :day, :month, :year, :value)
        ON DUPLICATE KEY UPDATE value=:value
    ");
    $stmt->execute([
        ':name'=>$name,
        ':day'=>$day,
        ':month'=>$monthPost,
        ':year'=>$yearPost,
        ':value'=>$value
    ]);

    echo json_encode(['success'=>true]);
    exit;
}

// قراءة البيانات للشهر الحالي
$monthData = [];
$stmt = $pdo->prepare("SELECT * FROM daily_data WHERE month=:month AND year=:year");
$stmt->execute([':month'=>$month, ':year'=>$year]);
while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
    $monthData[$row['day']][$row['name']] = $row['value'];
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>إدخال البيانات اليومية</title>
<style>
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; color: #333; padding:10px; font-size:20 }
h1 { text-align: center; color: red; }
button, select { padding: 10px 25px; margin: 0 6px; border: none; background: linear-gradient(45deg,,navy); color: blue; font-weight: bold; cursor: pointer; border-radius: 6px; transition: 0.3s; }
button:hover, select:hover { background: linear-gradient(45deg,#2980b9,#3498db); }
table { border-collapse: collapse; width: 90%; background: white; box-shadow: 0 4px 10px rgba(0,0,0,0.1); direction: rtl; margin-bottom: 20px;}
th, td { border: 1px solid #ddd; padding: 10px 8px; text-align: center; }
th { background-color: #3498db; color: white; position: sticky; top: 0; z-index: 2; }
tr.day-row.hidden { display: none; }
input { width: 70px; text-align: center; padding: 5px; border-radius: 4px; border: 1px solid #ccc; transition: 0.3s; font-size: 26px; }
input.success { border-color: #27ae60; background-color: #c8e6c9; }
input.error { border-color: #c0392b; background-color: #ffcdd2; }
.show-button { background-color: #27ae60; }
.show-button:hover { background-color: #2ecc71; }
#show-buttons-container { margin: 15px 0; }
.message { color: red; font-weight: bold; margin: 10px 0; }
</style>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<h1>إدخال البيانات اليومية</h1>
<div style="text-align:center; margin:20px 0;">
    <a href="dashboard.php" 
       style="padding:10px 20px; background:#3498db; color:white; text-decoration:none; border-radius:5px; font-size:16px;">
       رجوع
    </a>
</div>
<div id="controls">
    <label for="month-select">اختر الشهر:</label>
    <select id="month-select">
        <?php for($m=1;$m<=12;$m++): ?>
            <option value="<?php echo $m; ?>" <?php echo ($m==$month)?'selected':''; ?>><?php echo $m; ?></option>
        <?php endfor; ?>
    </select>
    <button id="show-all">عرض كل البيانات</button>
    <a id="view-separate" href="view_data.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>" target="_blank"><button>عرض البيانات في صفحة منفصلة</button></a>
    <button id="prev">السابق</button>
    <button id="next">التالي</button>
</div>

<div id="show-buttons-container"></div>
<div id="message" class="message"></div>

<table id="data-table">
<tr>
    <th>اليوم</th>
    <?php foreach($names as $name): ?>
        <th><?php echo $name; ?></th>
    <?php endforeach; ?>
    <th>إخفاء / إظهار</th>
</tr>

<?php for($d=1; $d<=$totalDays; $d++): ?>
<tr class="day-row" data-day="<?php echo $d; ?>" data-month="<?php echo $month; ?>">
    <td><?php echo $d; ?></td>
    <?php foreach($names as $name): ?>
        <td><input type="number" maxlength="3" data-name="<?php echo $name; ?>" data-day="<?php echo $d; ?>" value="<?php echo isset($monthData[$d][$name]) ? $monthData[$d][$name] : ''; ?>" /></td>
    <?php endforeach; ?>
    <td>
        <button class="toggle-row" data-day="<?php echo $d; ?>">إخفاء</button>
    </td>
</tr>
<?php endfor; ?>
</table>

<script>
let totalDays = <?php echo $totalDays; ?>;
let startDay = 1;
let daysPerPage = 7;
let month = <?php echo $month; ?>;
let year = <?php echo $year; ?>;

function showDays() {
    $(".day-row").each(function(){
        let day = parseInt($(this).attr("data-day"));
        if(day >= startDay && day < startDay+daysPerPage){
            $(this).show();
        } else {
            $(this).hide();
        }
    });
}
showDays();

$("#next").on("click", function(){
    startDay += daysPerPage;
    if(startDay > totalDays) startDay = totalDays - daysPerPage + 1;
    if(startDay < 1) startDay = 1;
    showDays();
});
$("#prev").on("click", function(){
    startDay -= daysPerPage;
    if(startDay < 1) startDay = 1;
    showDays();
});

$("#month-select").on("change", function(){
    month = parseInt($(this).val());
    window.location.href = "?month=" + month + "&year=" + year;
});
$("#month-select").on("change", function(){
    let selectedMonth = parseInt($(this).val());
    $("#view-separate").attr("href","view_data.php?month="+selectedMonth+"&year="+year);
});

$("#show-all").on("click", function(){
    $(".day-row").show();
});

$("input").on("input", function() {
    this.value = this.value.replace(/\D/g,'').slice(0,3);
});

$("input").on("change", function() {
    let input = $(this);
    let name = input.data("name");
    let day = input.data("day");
    let value = input.val();

    $.ajax({
        url: "",
        type: "POST",
        data: { name: name, day: day, value: value, month: month, year: year },
        dataType: "json",
        success: function(response){
            if(response.success){
                input.addClass("success").removeClass("error");
            } else {
                input.addClass("error").removeClass("success");
                alert(response.message);
            }
        },
        error: function() {
            input.addClass("error").removeClass("success");
            alert("خطأ في النظام أو الاتصال بالخادم");
        }
    });
});

$(document).on("click", ".toggle-row", function(){
    let row = $(this).closest("tr");
    let day = row.data("day");
    if(row.data("hidden") === true){
        row.show();
        row.data("hidden", false);
        $(this).text("إخفاء");
        $("#show-buttons-container button[data-day='"+day+"']").remove();
        $("#message").text("");
    } else {
        row.hide();
        row.data("hidden", true);
        $(this).text("إظهار");
        if($("#show-buttons-container button[data-day='"+day+"']").length === 0){
            $("#show-buttons-container").append('<button class="show-row" data-day="'+day+'">إظهار اليوم '+day+'</button>');
        }
    }
});

$(document).on("click", ".show-row", function(){
    let day = $(this).data("day");
    let row = $("tr.day-row[data-day='"+day+"'][data-month='"+month+"']");
    if(row.length > 0){
        row.show().data("hidden", false);
        row.find(".toggle-row").text("إخفاء");
        $(this).remove();
        $("#message").text("");
    } else {
        $("#message").text("لا توجد بيانات لليوم "+day+" في هذا الشهر");
    }
});
</script>

</body>
</html>