<?php
// Tắt hiển thị lỗi PHP để không ảnh hưởng JSON response
ini_set('display_errors', 0);
error_reporting(0);

require_once 'config.php';
require_once 'TeamDivision.php';

// Set headers trước khi có bất kỳ output nào
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Bắt tất cả output buffer để tránh output không mong muốn
ob_start();

// Kiểm tra method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Đọc và parse JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $input['action'] ?? '';

if (empty($action)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['error' => 'Action is required'], JSON_UNESCAPED_UNICODE);
    exit;
}

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
            
        case 'update_formation':
            updateFormation($input);
            break;
            
        case 'add_player_to_match':
            addPlayerToMatch($input);
            break;
            
        case 'remove_player_from_match':
            removePlayerFromMatch($input);
            break;
            
        default:
            ob_clean();
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action: ' . $action], JSON_UNESCAPED_UNICODE);
            exit;
    }
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

// New function: Add player to match
function addPlayerToMatch($input) {
    global $pdo;
    
    $matchId = $input['match_id'] ?? null;
    $playerId = $input['player_id'] ?? null;
    $team = $input['team'] ?? null;
    
    if (!$matchId || !$playerId || !$team) {
        throw new Exception('Match ID, Player ID và Team là bắt buộc');
    }
    
    if (!in_array($team, ['A', 'B'])) {
        throw new Exception('Team phải là A hoặc B');
    }
    
    // Get match info
    $stmt = $pdo->prepare("SELECT * FROM daily_matches WHERE id = ?");
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    
    if (!$match) {
        throw new Exception('Trận đấu không tồn tại');
    }
    
    if (!canUpdateMatchResult($match['match_date'])) {
        throw new Exception('Chỉ có thể cập nhật đội hình sau 7h sáng ngày hôm sau');
    }
    
    if ($match['status'] === 'completed') {
        throw new Exception('Không thể thay đổi đội hình của trận đấu đã hoàn thành');
    }
    
    // Get player info
    $stmt = $pdo->prepare("SELECT * FROM players WHERE id = ?");
    $stmt->execute([$playerId]);
    $player = $stmt->fetch();
    
    if (!$player) {
        throw new Exception('Cầu thủ không tồn tại');
    }
    
    // Check if player is already in this match
    $stmt = $pdo->prepare("SELECT id FROM match_participants WHERE match_id = ? AND player_id = ?");
    $stmt->execute([$matchId, $playerId]);
    if ($stmt->fetch()) {
        throw new Exception('Cầu thủ đã tham gia trận đấu này rồi');
    }
    
    try {
        $pdo->beginTransaction();
        
        // Determine assigned position and skill level
        $assignedPosition = $player['main_position'];
        $positionType = 'Sở trường';
        $skillLevel = $player['main_skill'];
        
        // Add player to match_participants
        $stmt = $pdo->prepare("
            INSERT INTO match_participants 
            (match_id, player_id, team, assigned_position, position_type, skill_level, goals, assists, points_earned) 
            VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0)
        ");
        $stmt->execute([
            $matchId,
            $playerId,
            $team,
            $assignedPosition,
            $positionType,
            $skillLevel
        ]);
        
        // Update daily_matches formations
        updateMatchFormations($matchId);
        
        $pdo->commit();
        
        ob_clean();
        echo json_encode(['success' => 'Thêm cầu thủ vào trận đấu thành công'], JSON_UNESCAPED_UNICODE);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception('Lỗi khi thêm cầu thủ: ' . $e->getMessage());
    }
}

// New function: Remove player from match
function removePlayerFromMatch($input) {
    global $pdo;
    
    $matchId = $input['match_id'] ?? null;
    $playerId = $input['player_id'] ?? null;
    
    if (!$matchId || !$playerId) {
        throw new Exception('Match ID và Player ID là bắt buộc');
    }
    
    // Get match info
    $stmt = $pdo->prepare("SELECT * FROM daily_matches WHERE id = ?");
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    
    if (!$match) {
        throw new Exception('Trận đấu không tồn tại');
    }
    
    if (!canUpdateMatchResult($match['match_date'])) {
        throw new Exception('Chỉ có thể cập nhật đội hình sau 7h sáng ngày hôm sau');
    }
    
    if ($match['status'] === 'completed') {
        throw new Exception('Không thể thay đổi đội hình của trận đấu đã hoàn thành');
    }
    
    // Check if player is in this match
    $stmt = $pdo->prepare("SELECT id FROM match_participants WHERE match_id = ? AND player_id = ?");
    $stmt->execute([$matchId, $playerId]);
    if (!$stmt->fetch()) {
        throw new Exception('Cầu thủ không có trong trận đấu này');
    }
    
    try {
        $pdo->beginTransaction();
        
        // Remove player from match_participants
        $stmt = $pdo->prepare("DELETE FROM match_participants WHERE match_id = ? AND player_id = ?");
        $stmt->execute([$matchId, $playerId]);
        
        // Update daily_matches formations
        updateMatchFormations($matchId);
        
        $pdo->commit();
        
        ob_clean();
        echo json_encode(['success' => 'Loại cầu thủ khỏi trận đấu thành công'], JSON_UNESCAPED_UNICODE);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception('Lỗi khi loại cầu thủ: ' . $e->getMessage());
    }
}

// Helper function: Update match formations in daily_matches table
function updateMatchFormations($matchId) {
    global $pdo;
    
    $teamAFormation = [];
    $teamBFormation = [];
    $positions = ['Thủ môn', 'Trung vệ', 'Hậu vệ cánh', 'Tiền vệ', 'Tiền đạo'];
    
    foreach ($positions as $pos) {
        $teamAFormation[$pos] = [];
        $teamBFormation[$pos] = [];
    }
    
    // Get updated participants data
    $stmt = $pdo->prepare("
        SELECT mp.*, p.name, p.main_position, p.secondary_position, p.main_skill, p.secondary_skill
        FROM match_participants mp 
        JOIN players p ON mp.player_id = p.id 
        WHERE mp.match_id = ?
    ");
    $stmt->execute([$matchId]);
    $participants = $stmt->fetchAll();
    
    foreach ($participants as $participant) {
        $playerData = [
            'id' => $participant['player_id'],
            'name' => $participant['name'],
            'main_position' => $participant['main_position'],
            'secondary_position' => $participant['secondary_position'],
            'main_skill' => $participant['main_skill'],
            'secondary_skill' => $participant['secondary_skill'],
            'assigned_position' => $participant['assigned_position'],
            'position_type' => $participant['position_type'],
            'skill_level' => $participant['skill_level']
        ];
        
        if ($participant['team'] === 'A') {
            $teamAFormation[$participant['assigned_position']][] = $playerData;
        } else {
            $teamBFormation[$participant['assigned_position']][] = $playerData;
        }
    }
    
    // Update formations in daily_matches
    $stmt = $pdo->prepare("
        UPDATE daily_matches 
        SET team_a_formation = ?, team_b_formation = ?
        WHERE id = ?
    ");
    $stmt->execute([
        json_encode($teamAFormation, JSON_UNESCAPED_UNICODE),
        json_encode($teamBFormation, JSON_UNESCAPED_UNICODE),
        $matchId
    ]);
}

function updateFormation($input) {
    global $pdo;
    
    $matchId = $input['match_id'] ?? null;
    $teamAPlayers = $input['team_a_players'] ?? [];
    $teamBPlayers = $input['team_b_players'] ?? [];
    
    if (!$matchId || empty($teamAPlayers) || empty($teamBPlayers)) {
        throw new Exception('Match ID và danh sách cầu thủ là bắt buộc');
    }
    
    // Get match info
    $stmt = $pdo->prepare("SELECT * FROM daily_matches WHERE id = ?");
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    
    if (!$match) {
        throw new Exception('Trận đấu không tồn tại');
    }
    
    if (!canUpdateMatchResult($match['match_date'])) {
        throw new Exception('Chỉ có thể cập nhật đội hình sau 7h sáng ngày hôm sau');
    }
    
    if ($match['status'] === 'completed') {
        throw new Exception('Không thể thay đổi đội hình của trận đấu đã hoàn thành');
    }
    
    try {
        $pdo->beginTransaction();
        
        // Update team assignments for all players
        $updateTeamStmt = $pdo->prepare("UPDATE match_participants SET team = ? WHERE match_id = ? AND player_id = ?");
        
        // Update team A players
        foreach ($teamAPlayers as $playerId) {
            $updateTeamStmt->execute(['A', $matchId, $playerId]);
        }
        
        // Update team B players
        foreach ($teamBPlayers as $playerId) {
            $updateTeamStmt->execute(['B', $matchId, $playerId]);
        }
        
        // Update match formations
        updateMatchFormations($matchId);
        
        $pdo->commit();
        
        ob_clean();
        echo json_encode(['success' => 'Cập nhật đội hình thành công'], JSON_UNESCAPED_UNICODE);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception('Lỗi khi cập nhật đội hình: ' . $e->getMessage());
    }
}

function registerPlayer($input) {
    global $pdo;
    
    $playerId = $input['player_id'] ?? null;
    $date = $input['date'] ?? getCurrentDate();
    
    if (!$playerId) {
        throw new Exception('Player ID is required');
    }
    
    // Check if player exists
    $stmt = $pdo->prepare("SELECT * FROM players WHERE id = ?");
    $stmt->execute([$playerId]);
    $player = $stmt->fetch();
    
    if (!$player) {
        throw new Exception('Cầu thủ không tồn tại');
    }
    
    // Check if already registered
    $stmt = $pdo->prepare("
        SELECT * FROM daily_registrations 
        WHERE player_id = ? AND registration_date = ?
    ");
    $stmt->execute([$playerId, $date]);
    
    if ($stmt->fetch()) {
        throw new Exception('Cầu thủ đã đăng ký rồi');
    }
    
    // Register player
    $stmt = $pdo->prepare("
        INSERT INTO daily_registrations (player_id, registration_date) 
        VALUES (?, ?)
    ");
    $stmt->execute([$playerId, $date]);
    
    ob_clean();
    echo json_encode(['success' => 'Đăng ký thành công'], JSON_UNESCAPED_UNICODE);
    exit;
}

function unregisterPlayer($input) {
    global $pdo;
    
    $playerId = $input['player_id'] ?? null;
    $date = $input['date'] ?? getCurrentDate();
    
    if (!$playerId) {
        throw new Exception('Player ID is required');
    }
    
    $stmt = $pdo->prepare("
        DELETE FROM daily_registrations 
        WHERE player_id = ? AND registration_date = ?
    ");
    $stmt->execute([$playerId, $date]);
    
    ob_clean();
    echo json_encode(['success' => 'Hủy đăng ký thành công'], JSON_UNESCAPED_UNICODE);
    exit;
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
        throw new Exception('Cần ít nhất ' . MIN_PLAYERS . ' cầu thủ để chia đội');
    }
    
    // Divide teams using the algorithm
    $teamDivision = new TeamDivision();
    $result = $teamDivision->divideTeams($players);
    
    // If not preview, save the formation
    if (!$preview) {
        try {
            $matchId = $teamDivision->saveMatchFormation($date, $result['teamA'], $result['teamB']);
            
            $stmt = $pdo->prepare("
                UPDATE daily_matches 
                SET status = 'scheduled'
                WHERE id = ?
            ");
            $stmt->execute([$matchId]);
            
            ob_clean();
            echo json_encode(['success' => 'Đội hình đã được lưu', 'data' => $result], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Exception $e) {
            throw new Exception('Lỗi khi lưu đội hình: ' . $e->getMessage());
        }
    } else {
        ob_clean();
        echo json_encode(['success' => 'Xem trước đội hình', 'data' => $result], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function updateMatchResult($input) {
    global $pdo;
    
    $matchId = $input['match_id'] ?? null;
    $teamAScore = $input['team_a_score'] ?? null;
    $teamBScore = $input['team_b_score'] ?? null;
    $playerStats = $input['player_stats'] ?? [];
    
    if (!$matchId || $teamAScore === null || $teamBScore === null) {
        throw new Exception('Match ID và tỷ số là bắt buộc');
    }
    
    // Get match info
    $stmt = $pdo->prepare("SELECT * FROM daily_matches WHERE id = ?");
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    
    if (!$match) {
        throw new Exception('Trận đấu không tồn tại');
    }
    
    if (!canUpdateMatchResult($match['match_date'])) {
        throw new Exception('Chỉ có thể cập nhật kết quả sau 7h sáng ngày hôm sau');
    }
    
    if ($match['status'] === 'completed') {
        throw new Exception('Trận đấu đã được cập nhật kết quả rồi');
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
            $updateParticipantStmt->execute([$goals, $assists, $points, $participant['id']]);
            
            // Update players total stats
            $updatePlayerStmt->execute([$points, $isWin, $goals, $assists, $playerId]);
        }
        
        $pdo->commit();
        
        ob_clean();
        echo json_encode(['success' => 'Cập nhật kết quả thành công'], JSON_UNESCAPED_UNICODE);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception('Lỗi khi cập nhật kết quả: ' . $e->getMessage());
    }
}

// Clean any remaining output
ob_end_clean();
?>