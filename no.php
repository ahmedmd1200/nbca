<?php if(count($notifications)>0): ?>
<div style="background:#fff3b0;padding:10px;margin:10px 0;border-radius:8px;">
<h3>إشعارات الموافقة / الرفض</h3>
<?php foreach($notifications as $n):
    $old=json_decode($n['old_value'],true);
    $new=json_decode($n['new_value'],true);
?>
<div style="border-bottom:1px solid #ccc;margin-bottom:5px;padding-bottom:5px;">
<b>الحالة:</b> <?= $n['status'] ?><br>
<b>التعديلات التي طلبتها:</b><br>
<?php foreach($new as $key=>$value):
      if(isset($old[$key]) && $old[$key]!=$value): ?>
        <?= htmlspecialchars($key) ?>: <?= htmlspecialchars($old[$key]) ?> ➜ <?= htmlspecialchars($value) ?><br>
<?php endif; endforeach; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>