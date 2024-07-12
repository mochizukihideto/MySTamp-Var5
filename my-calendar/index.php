<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db_connection.php';
require_once '../includes/database_functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../registration/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user = get_user_by_id($conn, $user_id);
if (!$user) {
    error_log("User not found for ID: $user_id");
    header("Location: ../error.php");
    exit();
}

// カレンダー用の日付処理
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDay);
$prevMonth = date('Y-m', strtotime('-1 month', $firstDay));
$nextMonth = date('Y-m', strtotime('+1 month', $firstDay));

// スタンプ使用データの取得
$stamp_usage = get_stamp_usage_for_month($conn, $user_id, $year, $month);

function get_stamp_usage_for_month($conn, $user_id, $year, $month) {
    $start_date = "$year-$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    
    $query = "SELECT su.id, su.created_at, s.id as stamp_id, s.image_path, s.hobby,
                     su.start_date, su.frequency_type, su.frequency_count, su.duration,
                     su.intermediate_goal_type, su.intermediate_goal_count
              FROM stamp_usage su 
              JOIN stamps s ON su.stamp_id = s.id 
              WHERE su.user_id = ? AND su.start_date <= ?
              ORDER BY su.start_date, su.created_at";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $user_id, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $usage_data = [];
    while ($row = $result->fetch_assoc()) {
        $row['image_path'] = '/lesson-management-system' . $row['image_path'];
        $usage_data[] = $row;
    }
    
    return generate_stamp_data($usage_data, $year, $month);
}

function generate_stamp_data($usage_data, $year, $month) {
    $calendar_data = [];
    $start_of_month = new DateTime("$year-$month-01");
    $end_of_month = new DateTime("$year-$month-" . $start_of_month->format('t'));
    $today = new DateTime(); // 現在の日付

    foreach ($usage_data as $stamp) {
        $start_date = new DateTime($stamp['start_date']);
        $current_date = clone $start_date;
        
        while ($current_date <= $end_of_month && $current_date <= $today) { // 今日までに制限
            if ($current_date >= $start_of_month) {
                $day = $current_date->format('j');
                if (!isset($calendar_data[$day])) {
                    $calendar_data[$day] = [];
                }
                
                $stamp_info = calculate_stamp_info($stamp, $current_date);
                if (should_add_stamp($stamp, $current_date)) {
                    // 同じスタンプIDが既に存在しない場合のみ追加
                    $stamp_exists = false;
                    foreach ($calendar_data[$day] as $existing_stamp) {
                        if ($existing_stamp['stamp_id'] == $stamp_info['stamp_id']) {
                            $stamp_exists = true;
                            break;
                        }
                    }
                    if (!$stamp_exists) {
                        $calendar_data[$day][] = $stamp_info;
                    }
                }
            }
            $current_date->modify('+1 day');
        }
    }
    
    return $calendar_data;
}

function calculate_stamp_info($stamp, $current_date) {
    $start = new DateTime($stamp['start_date']);
    $today = new DateTime(); // 現在の日付
    
    // 現在の日付が今日より後の場合、今日の日付を使用
    if ($current_date > $today) {
        $current_date = $today;
    }
    
    $interval = $start->diff($current_date);
    $total_days = $interval->days + 1;
    
    $stamp_info = $stamp;
    $stamp_info['duration_period'] = $interval->y . '年' . $interval->m . 'ヶ月' . $interval->d . '日';
    
    switch($stamp['frequency_type']) {
        case 'daily':
            $stamp_info['total_count'] = $total_days * $stamp['frequency_count'];
            break;
        case 'weekly':
            $stamp_info['total_count'] = ceil($total_days / 7) * $stamp['frequency_count'];
            break;
        case 'monthly':
            $stamp_info['total_count'] = ($interval->m + ($interval->y * 12) + 1) * $stamp['frequency_count'];
            break;
        default:
            $stamp_info['total_count'] = $stamp['frequency_count'];
    }
    
    $total_minutes = $stamp_info['total_count'] * $stamp['duration'];
    $stamp_info['total_hours'] = floor($total_minutes / 60);
    $stamp_info['total_minutes'] = $total_minutes % 60;
    
    return $stamp_info;
}

function should_add_stamp($stamp, $current_date) {
    $start = new DateTime($stamp['start_date']);
    $today = new DateTime(); // 現在の日付
    $diff = $start->diff($current_date);
    
    // 現在の日付が今日より後の場合はスタンプを表示しない
    if ($current_date > $today) {
        return false;
    }
    
    switch($stamp['frequency_type']) {
        case 'daily':
            return true;
        case 'weekly':
            return $diff->days % 7 == 0;
        case 'monthly':
            return $diff->d == 0;
        default:
            return false;
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['nickname'] ?? 'ゲスト'); ?>さんのMyCalendar</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="calendar.js"></script>
</head>
<body>
    <h1><?php echo htmlspecialchars($user['nickname']); ?>さんのMyCalendar</h1>
    
    <div class="calendar-container">
        <div class="calendar-header">
            <a href="?year=<?php echo date('Y', strtotime($prevMonth)); ?>&month=<?php echo date('n', strtotime($prevMonth)); ?>">&lt;</a>
            <h2><?php echo $year; ?>年 <?php echo $month; ?>月</h2>
            <a href="?year=<?php echo date('Y', strtotime($nextMonth)); ?>&month=<?php echo date('n', strtotime($nextMonth)); ?>">&gt;</a>
        </div>
        <table class="calendar">
            <tr>
                <th>日</th>
                <th>月</th>
                <th>火</th>
                <th>水</th>
                <th>木</th>
                <th>金</th>
                <th>土</th>
            </tr>
            <?php
            $dayOfWeek = date('w', $firstDay);
            $day = 1;
            echo "<tr>";
            for ($i = 0; $i < $dayOfWeek; $i++) {
                echo "<td class='empty-day'></td>";
            }
            while ($day <= $daysInMonth) {
                if ($dayOfWeek == 7) {
                    echo "</tr><tr>";
                    $dayOfWeek = 0;
                }
                echo "<td class='calendar-day'>";
                echo "<div class='day-number'>" . $day . "</div>";
                if (isset($stamp_usage[$day])) {
                    echo "<div class='stamp-container'>";
                    foreach ($stamp_usage[$day] as $stamp) {
                        $image_path = htmlspecialchars($stamp['image_path']);
                        $stamp_data = htmlspecialchars(json_encode($stamp, JSON_HEX_APOS | JSON_HEX_QUOT));
                        echo "<img src='{$image_path}' alt='Stamp' class='calendar-stamp' data-stamp='{$stamp_data}' onerror=\"this.style.display='none'\">";
                    }
                    echo "</div>";
                }
                echo "</td>";
                $day++;
                $dayOfWeek++;
            }
            while ($dayOfWeek < 7) {
                echo "<td class='empty-day'></td>";
                $dayOfWeek++;
            }
            echo "</tr>";
            ?>
        </table>
    </div>

    <div id="stamp-info" style="display:none;position:absolute;background-color:white;border:1px solid #ccc;padding:10px;z-index:1000;"></div>

    <a href="../stamp-usage/index.php">スタンプ使用ページに戻る</a>
</body>
</html>