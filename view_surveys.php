
<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$servername="localhost";
$username="root";
$password="";
$dbname="survey_db";

$conn=new mysqli($servername,$username,$password,$dbname);

if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات");
}

/* حذف سجل - للمدير فقط */

if(isset($_GET['delete']) && $_SESSION['role']=="admin"){

    $id=intval($_GET['delete']);

    $stmt=$conn->prepare("DELETE FROM surveys WHERE id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $stmt->close();

    header("Location:view_surveys.php");
    exit();
}

/* البحث */

$search="";

if(isset($_POST['search'])){
    $search=$_POST['search'];
}

$stmt=$conn->prepare("SELECT * FROM surveys
WHERE client_name LIKE ?
OR client_phone LIKE ?
OR guarantor_phone LIKE ?
ORDER BY id DESC");

$like="%$search%";

$stmt->bind_param("sss",$like,$like,$like);
$stmt->execute();

$result=$stmt->get_result();

/* لو الطلب ajax يرجع الجدول فقط */

if(isset($_POST['search'])){

if($result->num_rows>0){

while($row=$result->fetch_assoc()){

echo "<tr>";

echo "<td class='client-name'>".htmlspecialchars($row['client_name'])."</td>";
echo "<td>".htmlspecialchars($row['client_address'])."</td>";
echo "<td class='phone'>".htmlspecialchars($row['client_phone'])."</td>";
echo "<td class='job'>".htmlspecialchars($row['client_job'])."</td>";
echo "<td class='guarantor-name'>".htmlspecialchars($row['guarantor_name'])."</td>";
echo "<td>".htmlspecialchars($row['guarantor_address'])."</td>";
echo "<td class='phone'>".htmlspecialchars($row['guarantor_phone'])."</td>";
echo "<td class='job'>".htmlspecialchars($row['guarantor_job'])."</td>";

echo "<td>".nl2br(htmlspecialchars($row['notes']))."</td>";

echo "<td>";

if($_SESSION['role']=="admin"){
echo "<a class='delete' href='?delete=".$row['id']."' onclick=\"return confirm('هل أنت متأكد؟');\">حذف</a>";
}else{
echo "<span style='color:#999;'>غير مسموح</span>";
}

echo "</td>";

echo "</tr>";

}

}else{

echo "<tr><td colspan='10'>لا توجد نتائج</td></tr>";

}

exit();
}

?>

<!DOCTYPE html>
<html lang="ar">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>عرض العملاء</title>

<style>

body{
font-family:Arial;
direction:rtl;
background:#f3e5f5;
padding:20px;
}

h2{
text-align:center;
color:#6a1b9a;
}

.table-container{
width:100%;
overflow-x:auto;
}

table{
width:100%;
min-width:900px;
border-collapse:collapse;
background:white;
}

th,td{
border:1px solid #ccc;
padding:10px;
text-align:center;
}

th{
background:#6a1b9a;
color:white;
}

.client-name,.guarantor-name{
font-size:18px;
font-weight:bold;
}

.phone{
direction:ltr;
font-weight:bold;
}

.job{
font-size:14px;
}

.delete{
background:red;
color:white;
padding:5px 12px;
border-radius:50px;
text-decoration:none;
}

.search-box{
text-align:center;
margin-bottom:20px;
}

input{
padding:8px;
width:250px;
}

</style>

</head>

<body>

<h2>قائمة العملاء</h2>

<div style="text-align:center;margin:20px 0;">
<a href="das.php"
style="padding:10px 20px;background:#3498db;color:white;text-decoration:none;border-radius:5px;">
رجوع
</a>
</div>

<div class="search-box">

<input type="text" id="searchInput" placeholder="بحث بالاسم او الرقم">

</div>

<div class="table-container">

<table>

<thead>

<tr>
<th>اسم العميل</th>
<th>عنوان العميل</th>
<th>رقم العميل</th>
<th>وظيفة العميل</th>
<th>اسم الضامن</th>
<th>عنوان الضامن</th>
<th>رقم الضامن</th>
<th>وظيفة الضامن</th>
<th>ملاحظات</th>
<th>حذف</th>
</tr>

</thead>

<tbody id="tableData">

<?php

if($result->num_rows>0){

while($row=$result->fetch_assoc()){

echo "<tr>";

echo "<td class='client-name'>".htmlspecialchars($row['client_name'])."</td>";
echo "<td>".htmlspecialchars($row['client_address'])."</td>";
echo "<td class='phone'>".htmlspecialchars($row['client_phone'])."</td>";
echo "<td class='job'>".htmlspecialchars($row['client_job'])."</td>";
echo "<td class='guarantor-name'>".htmlspecialchars($row['guarantor_name'])."</td>";
echo "<td>".htmlspecialchars($row['guarantor_address'])."</td>";
echo "<td class='phone'>".htmlspecialchars($row['guarantor_phone'])."</td>";
echo "<td class='job'>".htmlspecialchars($row['guarantor_job'])."</td>";

echo "<td>".nl2br(htmlspecialchars($row['notes']))."</td>";

echo "<td>";

if($_SESSION['role']=="admin"){
echo "<a class='delete' href='?delete=".$row['id']."' onclick=\"return confirm('هل أنت متأكد؟');\">حذف</a>";
}else{
echo "<span style='color:#999;'>غير مسموح</span>";
}

echo "</td>";

echo "</tr>";

}

}else{

echo "<tr><td colspan='10'>لا توجد بيانات</td></tr>";

}

?>

</tbody>

</table>

</div>

<script>

/* البحث اللايف */

document.getElementById("searchInput").addEventListener("keyup",function(){

let search=this.value;

let xhr=new XMLHttpRequest();

xhr.open("POST","view_surveys.php",true);

xhr.setRequestHeader("Content-type","application/x-www-form-urlencoded");

xhr.onload=function(){

document.getElementById("tableData").innerHTML=this.responseText;

};

xhr.send("search="+search);

});

</script>

</body>
</html>
```
