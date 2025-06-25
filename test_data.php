<?php
/**
 * Script tạo dữ liệu test
 * Chạy file này để tạo dữ liệu mẫu cho việc test
 */

require_once 'config.php';
require_once 'TeamDivision.php';

$pdo = DB::getInstance();

echo "<h2>🧪 Football League - Test Data Generator</h2>";
echo "<p>Script này sẽ tạo dữ liệu test để bạn thử nghiệm hệ thống.</p>";

// Form để chọn loại test
if (!isset($_POST['action'])) {
?>
<form method="post">
    <h3>Chọn loại test:</h3>
    
    <div>
        <button type="submit" name="action" value="register_today" style="margin: 10px; padding: 10px;">
            📅 Đăng ký 16 người cho hôm nay
        </button>
    </div>
    
    <div>
        <button type="submit" name="action" value="create_match" style="margin: 10px; padding: 10px;">
            ⚽ Tạo trận đấu với kết quả mẫu
        </button>
    </div>
    
    <div>
        <button type="submit" name="action" value="create_history" style="margin: 10px; padding: 10px;">
            📈 Tạo lịch sử 7 ngày qua
        </button>
    </div>
    
    <div>
        <button type="submit" name="action" value="clear_today" style="margin: 10px; padding: 10px; background: red; color: white;">
            🗑️ Xóa dữ liệu hôm nay
        </button>
    </div>
    
    <div>
        <button type="submit" name="action" value="reset_all" style="margin: 10px; padding: 10px; background: darkred; color: white;">
            ⚠️ Reset toàn bộ dữ liệu
        </button>
    </div>
</form>

<hr>
<h3>📊 Trạng thái hiện tại:</h3>
<?php
// Hiển thị trạng thái hiện tại
$currentDate = getCurrentDate();

// Đăng ký hôm nay
$stmt = $pdo->prepare("SELECT COUNT(*) FROM daily_registrations WHERE registration_date = ?");
$stmt->execute([$currentDate]);
$todayRegistrations = $stmt->fetchColumn();

// Trận đấu hôm nay
$stmt = $pdo->prepare("SELECT * FROM daily_matches WHERE match_date = ?");
$stmt->execute([$currentDate]);
$todayMatch = $stmt->fetch();

// Tổng số trận đấu
$stmt = $pdo->query("SELECT COUNT(*) FROM daily_matches");
$totalMatches = $stmt->fetchColumn();

echo "<ul>";
echo "<li>Đăng ký hôm nay: <strong>{$todayRegistrations}</strong> người</li>";
echo "<li>Trận đấu hôm nay: <strong>" . ($todayMatch ? $todayMatch['status'] : 'Chưa có') . "</strong></li>";
echo "<li>Tổng số trận đấu: <strong>{$totalMatches}</strong></li>";
echo "<li>Test Mode: <strong>" . (defined('TEST_MODE') && TEST_MODE ? 'BẬT' : 'TẮT') . "</strong></li>";
echo "</ul>";

exit;
}

// Xử lý các action
$action = $_POST['action'];
$currentDate = getCurrentDate();

try {
    switch ($action) {
        case 'register_today':
            registerPlayersForToday();
            break;
            
        case 'create_match':
            createMatchWithResult();
            break;
            
        case 'create_history':
            createHistoryData();
            break;
            
        case 'clear_today':
            clearTodayData();
            break;
            
        case 'reset_all':
            resetAllData();
            break;
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Lỗi: " . $e->getMessage() . "</p>";
}

function registerPlayersForToday() {
    global $pdo, $currentDate;
    
    // Xóa đăng ký cũ cho hôm nay
    $pdo->prepare("DELETE FROM daily_registrations WHERE registration_date = ?")->execute([$currentDate]);
    
    // Lấy 16 cầu thủ đầu tiên
    $stmt = $pdo->query("SELECT id FROM players LIMIT 16");
    $players = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $pdo->prepare("INSERT INTO daily_registrations (player_id, registration_date) VALUES (?, ?)");
    
    foreach ($players as $playerId) {
        $stmt->execute([$playerId, $currentDate]);
    }
    
    echo "<p style='color: green;'>✅ Đã đăng ký 16 cầu thủ cho ngày {$currentDate}</p>";
    echo "<p><a href='index.php'>← Quay lại trang chính</a></p>";
}

function createMatchWithResult() {
    global $pdo, $currentDate;
    
    // Đảm bảo có đăng ký
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM daily_registrations WHERE registration_date = ?");
    $stmt->execute([$currentDate]);
    $registrationCount = $stmt->fetchColumn();
    
    if ($registrationCount < 4) {
        registerPlayersForToday();
    }
    
    // Lấy danh sách cầu thủ đã đăng ký
    $stmt = $pdo->prepare("
        SELECT p.* 
        FROM players p 
        JOIN daily_registrations dr ON p.id = dr.player_id 
        WHERE dr.registration_date = ?
    ");
    $stmt->execute([$currentDate]);
    $players = $stmt->fetchAll();
    
    // Chia đội
    $teamDivision = new TeamDivision();
    $result = $teamDivision->divideTeams($players);
    
    // Lưu trận đấu
    $matchId = $teamDivision->saveMatchFormation($currentDate, $result['teamA'], $result['teamB']);
    
    // Tạo kết quả mẫu
    $teamAScore = rand(0, 5);
    $teamBScore = rand(0, 5);
    
    // Update kết quả
    $stmt = $pdo->prepare("
        UPDATE daily_matches 
        SET team_a_score = ?, team_b_score = ?, status = 'completed', completed_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$teamAScore, $teamBScore, $matchId]);
    
    // Cập nhật điểm cho cầu thủ
    $winningTeam = $teamAScore > $teamBScore ? 'A' : ($teamBScore > $teamAScore ? 'B' : null);
    
    $stmt = $pdo->prepare("SELECT * FROM match_participants WHERE match_id = ?");
    $stmt->execute([$matchId]);
    $participants = $stmt->fetchAll();
    
    $updateStmt = $pdo->prepare("
        UPDATE match_participants 
        SET goals = ?, assists = ?, points_earned = ?
        WHERE id = ?
    ");
    
    $updatePlayerStmt = $pdo->prepare("
        UPDATE players 
        SET total_points = total_points + ?, 
            total_matches = total_matches + 1,
            total_wins = total_wins + ?,
            total_goals = total_goals + ?,
            total_assists = total_assists + ?
        WHERE id = ?
    ");
    
    foreach ($participants as $participant) {
        $goals = rand(0, 2);
        $assists = rand(0, 2);
        
        // Tính điểm
        $points = 0;
        $isWin = 0;
        if ($winningTeam === null) {
            $points = 1; // Hòa
        } elseif ($participant['team'] === $winningTeam) {
            $points = 3; // Thắng
            $isWin = 1;
        }
        
        $updateStmt->execute([$goals, $assists, $points, $participant['id']]);
        $updatePlayerStmt->execute([$points, $isWin, $goals, $assists, $participant['player_id']]);
    }
    
    echo "<p style='color: green;'>✅ Đã tạo trận đấu với kết quả {$teamAScore}-{$teamBScore}</p>";
    echo "<p><a href='match_result.php?id={$matchId}'>→ Xem kết quả trận đấu</a></p>";
    echo "<p><a href='leaderboard.php'>→ Xem bảng xếp hạng</a></p>";
}

function createHistoryData() {
    global $pdo;
    
    echo "<p>🔄 Đang tạo lịch sử 7 ngày qua...</p>";
    
    for ($i = 1; $i <= 7; $i++) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        
        // Xóa dữ liệu cũ cho ngày này
        $pdo->prepare("DELETE FROM daily_registrations WHERE registration_date = ?")->execute([$date]);
        $pdo->prepare("DELETE FROM daily_matches WHERE match_date = ?")->execute([$date]);
        
        // Tạo đăng ký ngẫu nhiên
        $stmt = $pdo->query("SELECT id FROM players ORDER BY RAND() LIMIT " . rand(14, 20));
        $players = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $regStmt = $pdo->prepare("INSERT INTO daily_registrations (player_id, registration_date) VALUES (?, ?)");
        foreach ($players as $playerId) {
            $regStmt->execute([$playerId, $date]);
        }
        
        // Tạo trận đấu
        $stmt = $pdo->prepare("
            SELECT p.* 
            FROM players p 
            JOIN daily_registrations dr ON p.id = dr.player_id 
            WHERE dr.registration_date = ?
        ");
        $stmt->execute([$date]);
        $playersData = $stmt->fetchAll();
        
        if (count($playersData) >= 4) {
            $teamDivision = new TeamDivision();
            $result = $teamDivision->divideTeams($playersData);
            $matchId = $teamDivision->saveMatchFormation($date, $result['teamA'], $result['teamB']);
            
            // 80% tỷ lệ có kết quả
            if (rand(1, 100) <= 80) {
                $teamAScore = rand(0, 4);
                $teamBScore = rand(0, 4);
                
                $pdo->prepare("
                    UPDATE daily_matches 
                    SET team_a_score = ?, team_b_score = ?, status = 'completed'
                    WHERE id = ?
                ")->execute([$teamAScore, $teamBScore, $matchId]);
                
                // Cập nhật stats ngẫu nhiên cho cầu thủ
                $participants = $pdo->prepare("SELECT * FROM match_participants WHERE match_id = ?");
                $participants->execute([$matchId]);
                $participantList = $participants->fetchAll();
                
                $winningTeam = $teamAScore > $teamBScore ? 'A' : ($teamBScore > $teamAScore ? 'B' : null);
                
                foreach ($participantList as $p) {
                    $goals = rand(0, 1);
                    $assists = rand(0, 1);
                    $points = ($winningTeam === null) ? 1 : (($p['team'] === $winningTeam) ? 3 : 0);
                    $isWin = ($winningTeam !== null && $p['team'] === $winningTeam) ? 1 : 0;
                    
                    $pdo->prepare("UPDATE match_participants SET goals = ?, assists = ?, points_earned = ? WHERE id = ?")
                        ->execute([$goals, $assists, $points, $p['id']]);
                        
                    $pdo->prepare("UPDATE players SET total_points = total_points + ?, total_matches = total_matches + 1, total_wins = total_wins + ?, total_goals = total_goals + ?, total_assists = total_assists + ? WHERE id = ?")
                        ->execute([$points, $isWin, $goals, $assists, $p['player_id']]);
                }
            }
        }
        
        echo "<p>✅ Tạo dữ liệu cho ngày {$date}</p>";
    }
    
    echo "<p style='color: green;'>🎉 Đã tạo xong lịch sử 7 ngày!</p>";
    echo "<p><a href='leaderboard.php'>→ Xem bảng xếp hạng</a></p>";
}

function clearTodayData() {
    global $pdo, $currentDate;
    
    $pdo->prepare("DELETE FROM daily_registrations WHERE registration_date = ?")->execute([$currentDate]);
    $pdo->prepare("DELETE FROM daily_matches WHERE match_date = ?")->execute([$currentDate]);
    
    echo "<p style='color: orange;'>🗑️ Đã xóa dữ liệu ngày {$currentDate}</p>";
    echo "<p><a href='index.php'>← Quay lại trang chính</a></p>";
}

function resetAllData() {
    global $pdo;
    
    // Reset tất cả dữ liệu về 0
    $pdo->exec("DELETE FROM daily_registrations");
    $pdo->exec("DELETE FROM match_participants");
    $pdo->exec("DELETE FROM daily_matches");
    $pdo->exec("DELETE FROM player_stats");
    $pdo->exec("UPDATE players SET total_points = 0, total_matches = 0, total_wins = 0, total_goals = 0, total_assists = 0");
    
    echo "<p style='color: red;'>⚠️ Đã reset toàn bộ dữ liệu về 0!</p>";
    echo "<p><a href='index.php'>← Quay lại trang chính</a></p>";
}

echo "<hr>";
echo "<p><a href='test_data.php'>🔄 Quay lại menu test</a> | <a href='index.php'>🏠 Trang chính</a></p>";
?>