<?php
// fix_points.php - Script để fix lại tất cả điểm số

require_once 'config.php';

echo "<h1>🔧 Fix Điểm Số Cầu Thủ Đặc Biệt</h1>\n";
echo "<p>Đang tiến hành fix lại tất cả điểm số...</p>\n";

$pdo = DB::getInstance();

try {
    $pdo->beginTransaction();
    
    echo "<p>✅ Bước 1: Reset tất cả điểm về 0...</p>\n";
    
    // Reset tất cả điểm về 0
    $pdo->query("UPDATE players SET total_points = 0.00");
    $pdo->query("UPDATE match_participants SET points_earned = 0.00");
    
    echo "<p>✅ Bước 2: Lấy dữ liệu tất cả trận đấu đã hoàn thành...</p>\n";
    
    // Lấy tất cả trận đấu đã hoàn thành
    $stmt = $pdo->query("
        SELECT dm.id as match_id, dm.team_a_score, dm.team_b_score, dm.match_date,
               mp.id as participant_id, mp.player_id, mp.team, mp.goals, mp.assists,
               p.name, p.is_special_player
        FROM daily_matches dm
        JOIN match_participants mp ON dm.id = mp.match_id
        JOIN players p ON mp.player_id = p.id
        WHERE dm.status = 'completed'
        ORDER BY dm.match_date, dm.id
    ");
    $allData = $stmt->fetchAll();
    
    // Group by match
    $matchesGrouped = [];
    foreach ($allData as $row) {
        $matchesGrouped[$row['match_id']][] = $row;
    }
    
    echo "<p>✅ Bước 3: Xử lý " . count($matchesGrouped) . " trận đấu...</p>\n";
    
    $totalFixed = 0;
    $specialPlayersFixed = 0;
    
    foreach ($matchesGrouped as $matchId => $participants) {
        $firstParticipant = $participants[0];
        $teamAScore = (int) $firstParticipant['team_a_score'];
        $teamBScore = (int) $firstParticipant['team_b_score'];
        $matchDate = $firstParticipant['match_date'];
        
        $winningTeam = $teamAScore > $teamBScore ? 'A' : ($teamBScore > $teamAScore ? 'B' : null);
        $isDraw = ($teamAScore == $teamBScore);
        
        echo "<p>🏈 Trận {$matchDate}: {$teamAScore}-{$teamBScore} (" . ($isDraw ? 'Hòa' : "Đội {$winningTeam} thắng") . ")</p>\n";
        
        foreach ($participants as $participant) {
            $isSpecialPlayer = (bool) $participant['is_special_player'];
            
            $isWin = 0;
            $isDraw_player = 0;
            
            if ($isDraw) {
                $isDraw_player = 1;
            } elseif ($participant['team'] === $winningTeam) {
                $isWin = 1;
            }
            
            // Tính điểm chính xác
            $correctPoints = calculatePoints($isWin, $isDraw_player, $isSpecialPlayer);
            
            // Update match_participants
            $updateStmt = $pdo->prepare("
                UPDATE match_participants 
                SET points_earned = ? 
                WHERE id = ?
            ");
            $updateStmt->execute([$correctPoints, $participant['participant_id']]);
            
            // Update players total_points
            $updatePlayerStmt = $pdo->prepare("
                UPDATE players 
                SET total_points = total_points + ?
                WHERE id = ?
            ");
            $updatePlayerStmt->execute([$correctPoints, $participant['player_id']]);
            
            $totalFixed++;
            if ($isSpecialPlayer) {
                $specialPlayersFixed++;
            }
            
            $pointsDisplay = formatPoints($correctPoints);
            $specialLabel = $isSpecialPlayer ? ' ⭐' : '';
            
            echo "&nbsp;&nbsp;• {$participant['name']}{$specialLabel}: {$pointsDisplay} điểm<br>\n";
        }
        
        echo "<br>\n";
    }
    
    $pdo->commit();
    
    echo "<h2>🎉 Hoàn thành!</h2>\n";
    echo "<p>✅ Đã fix thành công {$totalFixed} cầu thủ</p>\n";
    echo "<p>⭐ Trong đó có {$specialPlayersFixed} cầu thủ đặc biệt</p>\n";
    
    // Kiểm tra kết quả
    echo "<h3>📊 Kiểm tra kết quả:</h3>\n";
    
    $stmt = $pdo->query("
        SELECT name, is_special_player, total_points, total_matches
        FROM players 
        WHERE total_matches > 0
        ORDER BY total_points DESC
        LIMIT 10
    ");
    $topPlayers = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Cầu thủ</th><th>Loại</th><th>Điểm</th><th>Trận</th><th>TB/trận</th></tr>\n";
    
    foreach ($topPlayers as $player) {
        $specialLabel = $player['is_special_player'] ? '⭐ Đặc biệt' : 'Thường';
        $avgPoints = $player['total_matches'] > 0 ? round($player['total_points'] / $player['total_matches'], 2) : 0;
        $pointsDisplay = formatPoints($player['total_points']);
        
        echo "<tr>";
        echo "<td>{$player['name']}</td>";
        echo "<td>{$specialLabel}</td>";
        echo "<td style='font-weight: bold;'>{$pointsDisplay}</td>";
        echo "<td>{$player['total_matches']}</td>";
        echo "<td>{$avgPoints}</td>";
        echo "</tr>\n";
    }
    
    echo "</table>\n";
    
    echo "<p><a href='index.php'>🏠 Về trang chủ</a> | <a href='leaderboard.php'>🏆 Xem bảng xếp hạng</a></p>\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h2 style='color: red;'>❌ Lỗi: " . $e->getMessage() . "</h2>\n";
}
?>