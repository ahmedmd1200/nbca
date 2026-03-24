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
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// ======= إعداد متغيرات البحث والصفحات =======
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$page   = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit  = 9;
$offset = ($page - 1) * $limit;

// ======= دالة جلب العملاء =======
function getClients($conn, $search, $limit = 9, $offset = 0) {
    if ($search !== "") {
        $stmt = $conn->prepare("SELECT * FROM surveys 
                                WHERE client_name LIKE ? OR client_code LIKE ? 
                                ORDER BY id DESC
                                LIMIT ? OFFSET ?");
        $like = "%$search%";
        $stmt->bind_param("ssii", $like, $like, $limit, $offset);
    } else {
        $stmt = $conn->prepare("SELECT * FROM surveys 
                                ORDER BY id DESC
                                LIMIT ? OFFSET ?");
        $stmt->bind_param("ii", $limit, $offset);
    }
    $stmt->execute();
    return $stmt->get_result();
}

// ======= جلب عدد العملاء الكلي =======
$totalResult = $conn->prepare("SELECT COUNT(*) as total FROM surveys WHERE client_name LIKE ? OR client_code LIKE ?");
$like = "%$search%";
$totalResult->bind_param("ss", $like, $like);
$totalResult->execute();
$totalCount = $totalResult->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalCount / $limit);

// ======= الرد على طلبات AJAX =======
if(isset($_GET['ajax']) && $_GET['ajax'] == 1){
    $result = getClients($conn, $search, $limit, $offset);
    if($result->num_rows > 0){
        while($row = $result->fetch_assoc()){
            $id = $row['id'];
            $name = htmlspecialchars($row['client_name']);
            $code = htmlspecialchars($row['client_code']); 
           $staff = !empty($row['staff_name']) ? htmlspecialchars($row['staff_name']) : $_SESSION['username']; // المسؤول
            echo "<tr onclick=\"window.location='client.php?id=$id'\">";
            echo "<td class='code'>$code</td>";
            echo "<td class='name'>$name</td>";
            echo "<td class='staff'>$staff</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='3'>لا توجد بيانات</td></tr>";
    }

    // أزرار pagination
    echo '<tr><td colspan="3" style="text-align:center; padding:10px;">';
    if($page > 1){
        $prev = $page - 1;
        echo "<button onclick=\"fetchClients('{$search}', {$prev})\">السابق</button> ";
    }
    if($page < $totalPages){
        $next = $page + 1;
        echo "<button onclick=\"fetchClients('{$search}', {$next})\">التالي</button>";
    }
    echo '</td></tr>';
    exit;
}

// ======= جلب البيانات للعرض عند تحميل الصفحة =======
$result = getClients($conn, $search, $limit, $offset);
?>

<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>عرض العملاء</title>
<style>
body {
    font-family: 'Cairo', Arial, sans-serif;
    direction: rtl;
    background: linear-gradient(135deg, #f5f5f5, #e0e0e0);
    padding: 10px;
    font-size: 20px;
    margin: 0;
}
.redirect-buttons { display: flex; justify-content: center; gap: 10px; margin: 0; padding: 0; }
.search-box { text-align: center; margin-bottom: 20px; }
input[type="text"] {
    padding: 10px 20px;
    width: 300px;
    font-size: 18px;
    border-radius: 25px;
    border: 2px solid #ccc;
    outline: none;
    transition: 0.3s;
}
input[type="text"]:focus { border-color: #8e24aa; box-shadow: 0 0 8px rgba(142,36,170,0.3); }
button.search-btn {
    padding: 10px 20px;
    font-size:18px;
    border-radius:25px;
    margin:5px;
    background:linear-gradient(135deg,#6a1b9a,#8e24aa);
    color:white;
    border:none;
    cursor:pointer;
    transition:0.3s;
}
button.search-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(106,27,154,0.3); }
table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 3 13px;
    margin:20px 0;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border-radius: 15px;
    overflow: hidden;
}
th {
    background: linear-gradient(135deg, #6a1b9a, #8e24aa);
    color: white;
    padding: 12px;
    font-size: 20px;
    text-align: center;
}
td {
    font-size: 18px;
    font-weight: 700;
    color: black;
    text-align: center;
    padding: 10px;
    transition: all 0.3s ease;
}
td.code { width:20px; white-space: nowrap; }
td.name, td.staff { background-color: #fff; border-radius:11px; transition: all 0.3s ease; }
td.staff{width:8px; white-space: nowrap; }

tr { cursor: pointer; transition: all 0.3s ease; }
tr:hover td.name, tr:hover td.staff { background: linear-gradient(90deg, #f3e5f5, #e1bee7); box-shadow: 0 6px 20px rgba(106,27,154,0.2); transform: translateY(-2px); }
tr:hover td.code { background-color: #e1bee7; color: #4a148c; }
button { padding:5px 15px; margin:2px; border:none; border-radius:5px; cursor:pointer; background:#8e24aa; color:white; }
button:hover { background:#6a1b9a; }
</style>
</head>
<body>

<div style="text-align:center; margin:20px 0;">
  <a href="das.php" style="padding:10px 20px; 
    background:#3498db; 
    color:white;
    text-decoration:none; 
    border-radius:5px; 
    font-size:26px;
    display: inline-block;   /* مهم لكي تعمل width مع <a> */
    width: 80%;
    text-align: center;">رجوع</a></div>

</div>
	
<div class="search-box">
    <input type="text" id="searchInput" placeholder="بحث باسم العميل أو الكود">
    <button class="search-btn" type="button" onclick="fetchClients('')">عرض الكل</button>
</div>

<table>
<tr>
    <th>الكود</th>
    <th>اسم العميل</th>
    <th>المسؤول</th>
</tr>
<tbody id="clientTable">
<?php
if($result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        $id = $row['id'];
        $name = htmlspecialchars($row['client_name']);
        $code = htmlspecialchars($row['client_code']); 
        $staff = htmlspecialchars($row['staff_name']);
        echo "<tr onclick=\"window.location='client.php?id=$id'\">";
        echo "<td class='code'>$code</td>";
        echo "<td class='name'>$name</td>";
        echo "<td class='staff'>$staff</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='3'>لا توجد بيانات</td></tr>";
}
?>
<tr>
<td colspan="3" style="text-align:center; padding:10px;">
<?php
if($page > 1){
    $prev = $page - 1;
    echo "<button onclick=\"fetchClients('{$search}', {$prev})\">السابق</button> ";
}
if($page < $totalPages){
    $next = $page + 1;
    echo "<button onclick=\"fetchClients('{$search}', {$next})\">التالي</button>";
}
?>
</td>
</tr>
</tbody>
</table>

<script>
const input = document.getElementById('searchInput');
const tableBody = document.getElementById('clientTable');

function fetchClients(query='', page=1){
    const xhr = new XMLHttpRequest();
    xhr.open('GET', 'pepol.php?ajax=1&search=' + encodeURIComponent(query) + '&page=' + page, true);
    xhr.onload = function(){
        if(this.status === 200){
            tableBody.innerHTML = this.responseText;
        }
    };
    xhr.send();
}

// البحث أثناء الكتابة مباشرة (لايف)
input.addEventListener('input', () => {
    fetchClients(input.value, 1); // إعادة البحث من الصفحة الأولى
});

// تحميل البيانات عند فتح الصفحة
fetchClients(input.value, <?php echo $page; ?>);
</script>

</body>
</html>