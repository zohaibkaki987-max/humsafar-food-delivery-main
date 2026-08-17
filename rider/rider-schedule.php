<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['rider_id'])) {
    header('Location: rider-login.php');
    exit;
}

$riderId = (int) $_SESSION['rider_id'];
$riderStatus = strtolower(trim((string) ($_SESSION['rider_status'] ?? 'pending')));

$stmt = $conn->prepare('SELECT full_name, status FROM riders WHERE id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $riderId);
    $stmt->execute();
    $rider = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($rider) {
        $riderStatus = strtolower(trim((string) $rider['status']));
        $_SESSION['rider_name'] = $rider['full_name'];
        $_SESSION['rider_status'] = $riderStatus;
    }
}

$isApproved = in_array($riderStatus, ['approved', 'active'], true);

$conn->query("CREATE TABLE IF NOT EXISTS rider_availability (
    id INT(11) NOT NULL AUTO_INCREMENT,
    rider_id INT(11) NOT NULL,
    available_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY rider_id (rider_id),
    KEY available_date (available_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$slots = [
    ['09:00:00', '12:00:00', '9:00 AM - 12:00 PM'],
    ['12:00:00', '15:00:00', '12:00 PM - 3:00 PM'],
    ['15:00:00', '18:00:00', '3:00 PM - 6:00 PM'],
    ['18:00:00', '21:00:00', '6:00 PM - 9:00 PM'],
    ['21:00:00', '23:59:59', '9:00 PM - 12:00 AM'],
    ['00:00:00', '03:00:00', '12:00 AM - 3:00 AM'],
];

$today = new DateTimeImmutable('today');
$dates = [$today, $today->modify('+1 day')];
$now = new DateTimeImmutable('now');
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_session'])) {
    $date = trim((string) ($_POST['date'] ?? ''));
    $slot = (int) ($_POST['slot'] ?? -1);
    $action = (string) ($_POST['action'] ?? 'add');

    $validDate = in_array(
        $date,
        [$today->format('Y-m-d'), $today->modify('+1 day')->format('Y-m-d')],
        true
    );

    if (!$isApproved) {
        $error = 'Your rider account must be approved before booking a session.';
    } elseif (!$validDate || !isset($slots[$slot])) {
        $error = 'Invalid session selected.';
    } else {
        [$start, $end] = $slots[$slot];

        // A session can only be booked until 30 minutes before it starts.
        $sessionStart = new DateTimeImmutable($date . ' ' . $start);
        $bookingDeadline = $sessionStart->modify('-30 minutes');

        if ($action === 'remove') {
            $q = $conn->prepare(
                'DELETE FROM rider_availability
                 WHERE rider_id = ? AND available_date = ? AND start_time = ?
                 LIMIT 1'
            );
            if ($q) {
                $q->bind_param('iss', $riderId, $date, $start);
                $q->execute();
                $q->close();
            }
            $message = 'Session removed from your schedule.';
        } elseif ($now >= $bookingDeadline) {
            $error = 'This session can no longer be booked. Sessions must be booked at least 30 minutes before they start.';
        } else {
            $q = $conn->prepare(
                'SELECT id FROM rider_availability
                 WHERE rider_id = ? AND available_date = ? AND start_time = ?
                 LIMIT 1'
            );
            $exists = false;
            if ($q) {
                $q->bind_param('iss', $riderId, $date, $start);
                $q->execute();
                $exists = $q->get_result()->num_rows > 0;
                $q->close();
            }

            if ($exists) {
                $error = 'You have already selected this session.';
            } else {
                $q = $conn->prepare(
                    'INSERT INTO rider_availability
                     (rider_id, available_date, start_time, end_time)
                     VALUES (?, ?, ?, ?)'
                );
                if ($q) {
                    $q->bind_param('isss', $riderId, $date, $start, $end);
                    if ($q->execute()) {
                        $message = 'Session booked successfully.';
                    } else {
                        $error = 'Unable to save the session.';
                    }
                    $q->close();
                } else {
                    $error = 'Unable to prepare the session request.';
                }
            }
        }
    }
}

$selected = [];
$q = $conn->prepare(
    'SELECT available_date, start_time, end_time
     FROM rider_availability
     WHERE rider_id = ?
       AND available_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)
     ORDER BY available_date, start_time'
);
if ($q) {
    $q->bind_param('i', $riderId);
    $q->execute();
    $rs = $q->get_result();
    while ($row = $rs->fetch_assoc()) {
        $selected[$row['available_date'] . '|' . $row['start_time']] = true;
    }
    $q->close();
}

function rs_e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Book Your Session | Humsafar Rider</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
*{box-sizing:border-box}body{margin:0;background:#f7f7f9;color:#222;font-family:Segoe UI,Arial,sans-serif}.page{margin-left:223px;padding:34px;max-width:1100px}h1{margin:0;font-size:32px;font-weight:850}.subtitle{margin:8px 0 25px;color:#777}.alert{padding:12px 15px;border-radius:10px;margin-bottom:18px;font-size:13px}.success{background:#eafaf0;color:#17733b}.error{background:#fff0f2;color:#b4233c}.day{background:#fff;border:1px solid #eee;border-radius:16px;padding:20px;margin-bottom:20px}.day-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}.day-title{font-size:20px;font-weight:850}.day-date{font-size:12px;color:#888}.slots{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.slot{border:1px solid #e5e5e5;border-radius:12px;padding:16px;background:#fff}.slot.selected{border-color:#ed0038;background:#fff4f7}.slot-time{font-weight:800;font-size:15px}.slot-status{font-size:11px;color:#777;margin-top:5px}.slot form{margin-top:13px}.slot button{width:100%;border:0;border-radius:8px;padding:10px;cursor:pointer;font-weight:800}.book{background:#ed0038;color:#fff}.remove{background:#eee;color:#333}.locked{opacity:.55}.info{background:#fff;border:1px solid #eee;border-radius:12px;padding:15px;color:#666;font-size:12px;margin-bottom:20px}@media(max-width:900px){.page{margin-left:0;padding:20px}.slots{grid-template-columns:1fr 1fr}}@media(max-width:560px){.slots{grid-template-columns:1fr}.day-head{display:block}.day-date{margin-top:4px}}
</style>
</head>
<body>
<?php include __DIR__ . '/rider-sidebar.php'; ?>
<main class="page">
<h1>Book Your Session</h1>
<p class="subtitle">Choose the sessions when you want to be available for deliveries.</p>
<?php if ($message): ?><div class="alert success"><?=rs_e($message)?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?=rs_e($error)?></div><?php endif; ?>
<div class="info"><i class="fa-regular fa-bell"></i> You will receive a reminder <strong>15 minutes before</strong> your selected session starts. Sessions can be booked up to <strong>30 minutes before</strong> their start time.</div>
<?php foreach ($dates as $index => $dateObj): $date = $dateObj->format('Y-m-d'); ?>
<section class="day">
<div class="day-head"><div class="day-title"><?= $index === 0 ? 'Today' : 'Tomorrow' ?></div><div class="day-date"><?=rs_e($dateObj->format('l, d M Y'))?></div></div>
<div class="slots">
<?php foreach ($slots as $slotIndex => $slot):
    $key = $date . '|' . $slot[0];
    $isSelected = isset($selected[$key]);
    $sessionStart = new DateTimeImmutable($date . ' ' . $slot[0]);
    $bookingDeadline = $sessionStart->modify('-30 minutes');
    $canBook = $now < $bookingDeadline;
    $isLocked = !$isApproved || (!$isSelected && !$canBook);
?>
<div class="slot <?=$isSelected?'selected':''?> <?=$isLocked?'locked':''?>">
<div class="slot-time"><i class="fa-regular fa-clock"></i> <?=rs_e($slot[2])?></div>
<div class="slot-status"><?php
    if ($isSelected) echo 'Selected';
    elseif (!$isApproved) echo 'Waiting for approval';
    elseif (!$canBook) echo 'Closed - booking deadline passed';
    else echo 'Available session';
?></div>
<form method="post">
<input type="hidden" name="toggle_session" value="1">
<input type="hidden" name="date" value="<?=rs_e($date)?>">
<input type="hidden" name="slot" value="<?=$slotIndex?>">
<input type="hidden" name="action" value="<?=$isSelected?'remove':'add'?>">
<button class="<?=$isSelected?'remove':'book'?>" type="submit" <?=($isApproved && ($isSelected || $canBook))?'':'disabled'?>><?=$isSelected?'Remove Session':($canBook?'Book Session':'Closed')?></button>
</form>
</div>
<?php endforeach; ?>
</div>
</section>
<?php endforeach; ?>
</main>
<script>
(function(){
    const selectedSessions = <?=json_encode(array_keys($selected))?>;
    const reminderKey = 'humsafar_rider_session_reminders_' + new Date().toISOString().slice(0,10);
    const sent = JSON.parse(localStorage.getItem(reminderKey) || '{}');
    function checkReminders(){
        const now = new Date();
        selectedSessions.forEach(function(key){
            const parts = key.split('|');
            if(parts.length !== 2) return;
            const when = new Date(parts[0] + 'T' + parts[1]);
            const diff = when.getTime() - now.getTime();
            if(diff > 0 && diff <= 15*60*1000 && !sent[key]){
                const title = 'Your session is starting in just 15 min';
                const body = 'Your rider session starts at ' + when.toLocaleTimeString([], {hour:'numeric', minute:'2-digit'} ) + '.';
                if('Notification' in window){
                    if(Notification.permission === 'granted') new Notification(title,{body:body});
                    else if(Notification.permission !== 'denied') Notification.requestPermission();
                }
                alert(title + '\n' + body);
                sent[key] = true;
                localStorage.setItem(reminderKey, JSON.stringify(sent));
            }
        });
    }
    if('Notification' in window && Notification.permission === 'default') Notification.requestPermission();
    checkReminders(); setInterval(checkReminders, 30000);
})();
</script>
</body>
</html>
