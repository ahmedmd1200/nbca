<?php
session_start();

$servername="localhost";
$username="root";
$password="";
$dbname="survey_db";

$conn=new mysqli($servername,$username,$password,$dbname);
if($conn->connect_error){ die("فشل الاتصال"); }

/* جلب الأخصائيين */
$staff_result=$conn->query("SELECT staff_name FROM staff_list ORDER BY staff_name ASC");

/* معالجة طلب البحث المباشر */
if(isset($_GET['action']) && $_GET['action']=="fetch_clients"){
    $staff = $_GET['staff'] ?? "";
    $search = $_GET['client_search'] ?? "";
    if($staff!=""){
        if($search!=""){
            $stmt = $conn->prepare("SELECT id, client_name FROM surveys WHERE staff_name=? AND client_name LIKE ? ORDER BY client_name ASC");
            $likeSearch = "%".$search."%";
            $stmt->bind_param("ss",$staff,$likeSearch);
        }else{
            $stmt = $conn->prepare("SELECT id, client_name FROM surveys WHERE staff_name=? ORDER BY client_name ASC");
            $stmt->bind_param("s",$staff);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        if($res->num_rows>0){
            while($row = $res->fetch_assoc()){
                $name = htmlspecialchars($row['client_name']);
                // كل الصندوق هو رابط
                echo "<a class='client-box' href='client.name.php?id=".$row['id']."'>{$name}</a>";
            }
        }else{
            echo "<div class='no-results'>لا توجد استبيانات مطابقة للبحث</div>";
        }
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>استبيانات الأخصائيين</title>
<style>
body{
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    direction:rtl;
    margin:0;
    background: linear-gradient(to right, #f3e5f5, #e1bee7);
}
.wrapper{ display:flex; height:100vh; box-shadow:0 0 10px rgba(0,0,0,0.1); }

/* قائمة الأخصائيين */
.staff-list{
    width:260px;
    background:#6a1b9a;
    color:white;
    padding:20px;
    overflow-y:auto;
    box-sizing:border-box;
    border-right:3px solid #8e24aa;
}
.staff-list h3{ margin-top:0; text-align:center; font-size:22px; }
.staff-list a{
    display:block;
    padding:12px;
    margin-bottom:8px;
    background:#8e24aa;
    color:white;
    text-decoration:none;
    border-radius:8px;
    text-align:center;
    transition:0.3s;
    font-weight:bold;
}
.staff-list a:hover, .staff-list a.active{ background:#ab47bc; transform:scale(1.02); }

/* العملاء */
.clients{
    flex:1;
    padding:20px;
    overflow-y:auto;
    box-sizing:border-box;
}
.clients h2{ text-align:center; color:#6a1b9a; font-size:24px; margin-bottom:20px; }

/* البحث */
.client-search{ margin-bottom:20px; text-align:center; }
.client-search input[type="text"]{
    width:60%;
    padding:12px;
    font-size:16px;
    border-radius:25px;
    border:2px solid #8e24aa;
    outline:none;
    transition:0.3s;
}
.client-search input[type="text"]:focus{ border-color:#ab47bc; box-shadow:0 0 8px rgba(171,71,188,0.4); }

/* صندوق العميل كرابط */
.client-box{
    display:block;
    background:white;
    padding:15px 20px;
    margin-bottom:12px;
    border-radius:10px;
    box-shadow:0 2px 6px rgba(0,0,0,0.15);
    transition:0.3s;
    color:#6a1b9a;
    font-weight:bold;
    font-size:18px;
    text-decoration:none;
}
.client-box:hover{
    transform:scale(1.02);
    box-shadow:0 4px 12px rgba(0,0,0,0.2);
}

/* تمييز البحث */
.highlight{ background: #ffeb3b; color:#000; border-radius:3px; padding:0 2px; }
.no-results{ text-align:center; color:#444; font-size:18px; margin-top:20px; }
</style>
<script>
// جلب العملاء وعرضهم
function fetchClients(staffName, query=""){
    const xhr = new XMLHttpRequest();
    xhr.open("GET","?action=fetch_clients&staff="+encodeURIComponent(staffName)+"&client_search="+encodeURIComponent(query),true);
    xhr.onload = function(){
        if(this.status===200){
            document.getElementById("client-list").innerHTML = this.responseText;
            highlightMatches(query);
        }
    };
    xhr.send();
}

// تمييز الكلمات المطابقة
function highlightMatches(query){
    if(!query) return;
    query = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); // escape regex
    const regex = new RegExp(query,'gi');
    document.querySelectorAll('.client-box').forEach(el=>{
        el.innerHTML = el.textContent.replace(regex, match=>`<span class="highlight">${match}</span>`);
    });
}

// التعامل مع البحث المباشر واختيار أول أخصائي تلقائيًا
document.addEventListener("DOMContentLoaded", function(){
    const searchInput = document.getElementById("search-input");
    const staffLinks = document.querySelectorAll(".staff-list a");

    // تفعيل البحث المباشر
    searchInput.addEventListener("input", function(){
        const query = this.value;
        const staffName = document.querySelector(".staff-list a.active")?.textContent;
        if(staffName) fetchClients(staffName, query);
    });

    // دالة لتفعيل أخصائي محدد
    function activateStaff(link){
        staffLinks.forEach(l=>l.classList.remove("active"));
        link.classList.add("active");
        document.querySelector(".clients h2").textContent = "عملاء الأخصائي: " + link.textContent;
        document.querySelector(".client-search").style.display = "block";
        searchInput.value = "";
        fetchClients(link.textContent);
    }

    // ربط الضغط على الأخصائي
    staffLinks.forEach(link=>{
        link.addEventListener("click", function(e){
            e.preventDefault();
            activateStaff(this);
        });
    });

    // اختيار أول أخصائي تلقائيًا عند تحميل الصفحة
    if(staffLinks.length > 0){
        activateStaff(staffLinks[0]);
    }
});
</script>
</head>
<body>

<div class="wrapper">
<div class="staff-list">
<h3>الأخصائيين</h3>
<?php
while($staff=$staff_result->fetch_assoc()){
    $name = htmlspecialchars($staff['staff_name']);
    echo "<a href='#'>{$name}</a>";
}
?>
</div>

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

<div class="clients">
<h2>اختر أخصائي</h2>
<div class="client-search" style="display:none;">
    <input type="text" id="search-input" placeholder="ابحث في الاستبيانات">
</div>
<div id="client-list"></div>
</div>
</div>

</body>
</html>