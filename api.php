<?php
require_once 'config.php';
require_once 'TeamDivision.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

$pdo = DB::getInstance();

try {
    switch ($action) {
        case 'register_player':
            registerPlayer($input);
            break;
            
        case 'unregister_player':
            unregisterPlayer($input);
            break;
            
        case 'divide_teams':
            divideTeams($input);
            break;
            
        case 'update_match_result':
            updateMatchResult($input);
            break;
            
        case 'update_player_stats':
            updatePlayerStats($input);
            break;
            
        default:
            errorResponse('Invalid action');
    }
} catch (Exception $e) {
    errorResponse($e->getMessage());
}

function registerPlayer($input) {
    global $pdo;
    
    $playerId = $input['player_id'] ?? null;
    $date = $input['date'] ?? getCurrentDate();
    
    if (!$playerId) {
        errorResponse('Player ID is required');
    }
    
    if (isRegistrationLocked()) {
        errorResponse('Đăng ký đã được khóa (sau 22h30)');
    }
    
    // Check if player exists
    $stmt = $pdo->prepare("SELECT * FROM players WHERE id = ?");
    $stmt->execute([$playerId]);
    $player = $stmt->fetch();
    
    if (!$player) {
        errorResponse('Cầu thủ không tồn tại');
    }
    
    // Check if already registered
    $stmt = $pdo->prepare("
        SELECT * FROM daily_registrations 
        WHERE player_id = ? AND registration_date = ?
    ");
    $stmt->execute([$playerId, $date]);
    
    if ($stmt->fetch()) {
        errorResponse('Cầu thủ đã đăng ký rồi');
    }
    
    // Register player
    $stmt = $pdo->prepare("
        INSERT INTO daily_registrations (player_id, registration_date) 
        VALUES (?, ?)
    ");
    $stmt->execute([$playerId, $date]);
    
    successResponse('Đăng ký thành công');
}

function unregisterPlayer($input) {
    global $pdo;
    
    $playerId = $input['player_id'] ?? null;
    $date = $input['date'] ?? getCurrentDate();
    
    if (!$playerId) {
        errorResponse('Player ID is required');
    }
    
    if (isRegistrationLocked()) {
        errorResponse('Đăng ký đã được khóa (sau 22h30)');
    }
    
    $stmt = $pdo->prepare("
        DELETE FROM daily_registrations 
        WHERE player_id = ? AND registration_date = ?
    ");
    $stmt->execute([$playerId, $date]);
    
    successResponse('Hủy đăng ký thành công');
}

function divideTeams($input) {
    global $pdo;
    
    $date = $input['date'] ?? getCurrentDate();
    $preview = $input['preview'] ?? false;
    
    // Get registered players for the date
    $stmt = $pdo->prepare("
        SELECT p.* 
        FROM players p 
        JOIN daily_registrations dr ON p.id = dr.player_id 
        WHERE dr.registration_date = ?
        ORDER BY dr.registered_at ASC
    ");
    $stmt->execute([$date]);
    $players = $stmt->fetchAll();
    
    if (count($players) < MIN_PLAYERS) {
        errorResponse('Cần ít nhất ' . MIN_PLAYERS . ' cầu thủ để chia đội');
    }
    
    // Divide teams using the algorithm
    $teamDivision = new TeamDivision();
    $result = $teamDivision->divideTeams($players);
    
    // If not preview, save the formation
    if (!$preview) {
        try {
            $matchId = $teamDivision->saveMatchFormation($date, $result['teamA'], $result['teamB']);
            
            // Lock registration and set match status
            $stmt = $pdo->prepare("
                UPDATE daily_matches 
                SET status = 'locked', locked_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$matchId]);
            
            successResponse('Đội hình đã được lưu và khóa', $result);
        } catch (Exception $e) {
            errorResponse('Lỗi khi lưu đội hình: ' . $e->getMessage());
        }
    } else {
        successResponse('Xem trước đội hình', $result);
    }
}

function updateMatchResult($input) {
    global $pdo;
    
    $matchId = $input['match_id'] ?? null;
    $teamAScore = $input['team_a_score'] ?? null;
    $teamBScore = $input['team_b_score'] ?? null;
    $playerStats = $input['player_stats'] ?? []; // [player_id => [goals, assists]]
    
    if (!$matchId || $teamAScore === null || $teamBScore === null) {
        errorResponse('Match ID và tỷ số là bắt buộc');
    }
    
    // Get match info
    $stmt = $pdo->prepare("SELECT * FROM daily_matches WHERE id = ?");
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    
    if (!$match) {
        errorResponse('Trận đấu không tồn tại');
    }
    
    if (!canUpdateMatchResult($match['match_date'])) {
        errorResponse('Chỉ có thể cập nhật kết quả sau 7h sáng ngày hôm sau');
    }
    
    if ($match['status'] === 'completed') {
        errorResponse('Trận đấu đã được cập nhật kết quả rồi');
    }
    
    try {
        $pdo->beginTransaction();
        
        // Determine winning team
        $winningTeam = $teamAScore > $teamBScore ? 'A' : ($teamBScore > $teamAScore ? 'B' : null);
        
        // Update match result
        $stmt = $pdo->prepare("
            UPDATE daily_matches 
            SET team_a_score = ?, team_b_score = ?, status = 'completed', completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$teamAScore, $teamBScore, $matchId]);
        
        // Get all participants
        $stmt = $pdo->prepare("
            SELECT mp.*, p.name 
            FROM match_participants mp 
            JOIN players p ON mp.player_id = p.id 
            WHERE mp.match_id = ?
        ");
        $stmt->execute([$matchId]);
        $participants = $stmt->fetchAll();
        
        // Update participant stats and points
        $updateParticipantStmt = $pdo->prepare("
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
            $playerId = $participant['player_id'];
            $goals = $playerStats[$playerId]['goals'] ?? 0;
            $assists = $playerStats[$playerId]['assists'] ?? 0;
            
            // Calculate points (3 for win, 0 for loss, 1 for draw)
            $points = 0;
            if ($winningTeam === null) {
                $points = 1; // Draw
            } elseif ($participant['team'] === $winningTeam) {
                $points = POINTS_WIN; // Win
            } else {
                $points = POINTS_LOSE; // Loss
            }
            
            $isWin = ($winningTeam !== null && $participant['team'] === $winningTeam) ? 1 : 0;
            
            // Update match_participants
            $updateParticipantStmt->execute([
                $goals, $assists, $points, $participant['id']
            ]);
            
            // Update players total stats
            $updatePlayerStmt->execute([
                $points, $isWin, $goals, $assists, $playerId
            ]);
        }
        
        // Update monthly stats
        updateMonthlyStats($participants, $match['match_date'], $winningTeam, $playerStats);
        
        $pdo->commit();
        successResponse('Cập nhật kết quả thành công');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        errorResponse('Lỗi khi cập nhật kết quả: ' . $e->getMessage());
    }
}

function updateMonthlyStats($participants, $matchDate, $winningTeam, $playerStats) {
    global $pdo;
    
    $month = date('Y-m', strtotime($matchDate));
    
    $stmt = $pdo->prepare("
        INSERT INTO player_stats (player_id, month, matches_played, wins, goals, assists, points)
        VALUES (?, ?, 1, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        matches_played = matches_played + 1,
        wins = wins + VALUES(wins),
        goals = goals + VALUES(goals),
        assists = assists + VALUES(assists),
        points = points + VALUES(points)
    ");
    
    foreach ($participants as $participant) {
        $playerId = $participant['player_id'];
        $goals = $playerStats[$playerId]['goals'] ?? 0;
        $assists = $playerStats[$playerId]['assists'] ?? 0;
        
        $points = 0;
        $isWin = 0;
        
        if ($winningTeam === null) {
            $points = 1; // Draw
        } elseif ($participant['team'] === $winningTeam) {
            $points = POINTS_WIN; // Win
            $isWin = 1;
        } else {
            $points = POINTS_LOSE; // Loss
        }
        
        $stmt->execute([$playerId, $month, $isWin, $goals, $assists, $points]);
    }
}

function updatePlayerStats($input) {
    global $pdo;
    
    $playerId = $input['player_id'] ?? null;
    $name = $input['name'] ?? null;
    $mainPosition = $input['main_position'] ?? null;
    $secondaryPosition = $input['secondary_position'] ?? null;
    $mainSkill = $input['main_skill'] ?? null;
    $secondarySkill = $input['secondary_skill'] ?? null;
    
    if (!$playerId) {
        errorResponse('Player ID is required');
    }
    
    $updateFields = [];
    $updateValues = [];
    
    if ($name) {
        $updateFields[] = "name = ?";
        $updateValues[] = $name;
    }
    
    if ($mainPosition) {
        $updateFields[] = "main_position = ?";
        $updateValues[] = $mainPosition;
    }
    
    if ($secondaryPosition) {
        $updateFields[] = "secondary_position = ?";
        $updateValues[] = $secondaryPosition;
    }
    
    if ($mainSkill) {
        $updateFields[] = "main_skill = ?";
        $updateValues[] = $mainSkill;
    }
    
    if ($secondarySkill) {
        $updateFields[] = "secondary_skill = ?";
        $updateValues[] = $secondarySkill;
    }
    
    if (empty($updateFields)) {
        errorResponse('No fields to update');
    }
    
    $updateValues[] = $playerId;
    
    $sql = "UPDATE players SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($updateValues);
    
    successResponse('Cập nhật thông tin cầu thủ thành công');
}

// Additional helper functions for API
function getMatchDetails($matchId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT dm.*, 
               COUNT(mp.id) as total_players,
               SUM(CASE WHEN mp.team = 'A' THEN 1 ELSE 0 END) as team_a_count,
               SUM(CASE WHEN mp.team = 'B' THEN 1 ELSE 0 END) as team_b_count
        FROM daily_matches dm 
        LEFT JOIN match_participants mp ON dm.id = mp.match_id
        WHERE dm.id = ?
        GROUP BY dm.id
    ");
    $stmt->execute([$matchId]);
    return $stmt->fetch();
}

function getMatchParticipants($matchId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT mp.*, p.name, p.main_position, p.secondary_position 
        FROM match_participants mp 
        JOIN players p ON mp.player_id = p.id 
        WHERE mp.match_id = ?
        ORDER BY mp.team, mp.assigned_position, p.name
    ");
    $stmt->execute([$matchId]);
    return $stmt->fetchAll();
}

// Handle additional API endpoints
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    try {
        switch ($action) {
            case 'get_match_details':
                $matchId = $_GET['match_id'] ?? null;
                if (!$matchId) {
                    errorResponse('Match ID is required');
                }
                
                $match = getMatchDetails($matchId);
                $participants = getMatchParticipants($matchId);
                
                successResponse('Match details retrieved', [
                    'match' => $match,
                    'participants' => $participants
                ]);
                break;
                
            case 'get_player_stats':
                $playerId = $_GET['player_id'] ?? null;
                $month = $_GET['month'] ?? date('Y-m');
                
                if ($playerId) {
                    $stmt = $pdo->prepare("
                        SELECT ps.*, p.name 
                        FROM player_stats ps 
                        JOIN players p ON ps.player_id = p.id 
                        WHERE ps.player_id = ? AND ps.month = ?
                    ");
                    $stmt->execute([$playerId, $month]);
                    $stats = $stmt->fetch();
                } else {
                    $stmt = $pdo->prepare("
                        SELECT ps.*, p.name 
                        FROM player_stats ps 
                        JOIN players p ON ps.player_id = p.id 
                        WHERE ps.month = ?
                        ORDER BY ps.points DESC, ps.wins DESC
                    ");
                    $stmt->execute([$month]);
                    $stats = $stmt->fetchAll();
                }
                
                successResponse('Player stats retrieved', $stats);
                break;
                
            case 'get_leaderboard':
                $period = $_GET['period'] ?? 'month'; // month, all_time
                $month = $_GET['month'] ?? date('Y-m');
                
                if ($period === 'month') {
                    $stmt = $pdo->prepare("
                        SELECT ps.*, p.name, p.main_position
                        FROM player_stats ps 
                        JOIN players p ON ps.player_id = p.id 
                        WHERE ps.month = ? AND ps.matches_played > 0
                        ORDER BY ps.points DESC, ps.wins DESC, ps.goals DESC
                        LIMIT 20
                    ");
                    $stmt->execute([$month]);
                } else {
                    $stmt = $pdo->query("
                        SELECT p.*, 
                               ROUND(p.total_points / GREATEST(p.total_matches, 1), 2) as avg_points
                        FROM players p 
                        WHERE p.total_matches > 0
                        ORDER BY p.total_points DESC, p.total_wins DESC, p.total_goals DESC
                        LIMIT 20
                    ");
                }
                
                $leaderboard = $stmt->fetchAll();
                successResponse('Leaderboard retrieved', $leaderboard);
                break;
                
            default:
                errorResponse('Invalid GET action');
        }
    } catch (Exception $e) {
        errorResponse($e->getMessage());
    }
}
?>