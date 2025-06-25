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

function registerPlayer($input) {
    global $pdo;
    
    $playerId = $input['player_id'] ?? null;
    $date = $input['date'] ?? getCurrentDate();
    
    if (!$playerId) {
        throw new Exception('Player ID is required');
    }
    
    if (isRegistrationLocked()) {
        throw new Exception('Đăng ký đã được khóa (sau 22h30)');
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
    
    if (isRegistrationLocked()) {
        throw new Exception('Đăng ký đã được khóa (sau 22h30)');
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
            
            // Lock registration and set match status
            $stmt = $pdo->prepare("
                UPDATE daily_matches 
                SET status = 'locked', locked_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$matchId]);
            
            ob_clean();
            echo json_encode(['success' => 'Đội hình đã được lưu và khóa', 'data' => $result], JSON_UNESCAPED_UNICODE);
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