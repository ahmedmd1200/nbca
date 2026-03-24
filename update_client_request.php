<script>
const clientData = {
    "client_code":"1212",
    "client_name":"محمد يوسف رسلان",
    "client_address":"بهجورة مسجد السلام المسجد",
    "client_phone":"01272301281",
    "notes":"خخخخ",
    "staff_name":""
};

function sendUpdateRequest() {
    fetch('update_client_request.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(clientData)
    })
    .then(response => response.json())
    .then(data => {
        alert(data.msg);
        if(data.success){
            console.log("تم الإرسال بنجاح");
        } else {
            console.warn("فشل الإرسال");
        }
    })
    .catch(err => console.error("خطأ في الإرسال:", err));
}

// مثال: عند الضغط على زر تحديث
document.getElementById('updateBtn').addEventListener('click', sendUpdateRequest);
</script>

<button id="updateBtn">تحديث بيانات العميل</button>