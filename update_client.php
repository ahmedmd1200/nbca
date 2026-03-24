<?php
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['username'])){
    echo json_encode(['success'=>false,'msg'=>'يجب تسجيل الدخول']);
    exit;
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=survey_db;charset=utf8","root","");
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

    $user_id = $_SESSION['user_id'];

    // جلب صلاحيات المستخدم
    $stmt_perm = $pdo->prepare("SELECT role, can_edit FROM users WHERE id=?");
    $stmt_perm->execute([$user_id]);
    $user = $stmt_perm->fetch(PDO::FETCH_ASSOC);

    if(!$user){
        echo json_encode(['success'=>false,'msg'=>'المستخدم غير موجود']);
        exit;
    }

    // استقبال بيانات JSON
    $data = json_decode(file_get_contents('php://input'), true);

    if(!isset($data['client_code'], $data['client_name'])){
        echo json_encode(['success'=>false,'msg'=>'بيانات العميل ناقصة']);
        exit;
    }

    $client_code = $data['client_code'];

    // تحقق من وجود العميل
    $stmt_client = $pdo->prepare("SELECT * FROM surveys WHERE client_code=?");
    $stmt_client->execute([$client_code]);
    $client = $stmt_client->fetch(PDO::FETCH_ASSOC);

    if(!$client){
        echo json_encode(['success'=>false,'msg'=>'العميل غير موجود']);
        exit;
    }

    // إذا كان المستخدم لا يملك صلاحية التعديل → أرسل طلب للمدير
    if($user['role']!=='admin' && $user['role']!=='manager' && !$user['can_edit']){
        $stmt_request = $pdo->prepare("
            INSERT INTO action_requests 
            (user_id, client_code, action_type, data, status, created_at) 
            VALUES (:user_id, :client_code, 'edit', :data, 'pending', NOW())
        ");
        $stmt_request->execute([
            ':user_id'=>$user_id,
            ':client_code'=>$client_code,
            ':data'=>json_encode($data)
        ]);

        echo json_encode(['success'=>true,'msg'=>'⚠️ لا يمكنك تعديل العميل مباشرة، تم إرسال طلب موافقة المدير']);
        exit;
    }

    // المستخدم لديه صلاحية → تحديث مباشرة
    $stmt_update = $pdo->prepare("
        UPDATE surveys 
        SET client_name=:client_name,
            client_address=:client_address,
            client_phone=:client_phone,
            notes=:notes,
            staff_name=:staff_name
        WHERE client_code=:client_code
    ");

    $stmt_update->execute([
        ':client_code'=>$client_code,
        ':client_name'=>$data['client_name'],
        ':client_address'=>$data['client_address'] ?? '',
        ':client_phone'=>$data['client_phone'] ?? '',
        ':notes'=>$data['notes'] ?? '',
        ':staff_name'=>$data['staff_name'] ?? ''
    ]);

    echo json_encode(['success'=>true,'msg'=>'تم تحديث بيانات العميل بنجاح']);

} catch(PDOException $e){
    echo json_encode(['success'=>false,'msg'=>'حدث خطأ: '.$e->getMessage()]);
}