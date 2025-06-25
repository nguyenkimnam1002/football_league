<?php
require_once 'config.php';

$pdo = DB::getInstance();
$currentDate = getCurrentDate();

// Get match ID from URL
$matchId = $_GET['id'] ?? null;
if (!$matchId) {
    // Get today's match or latest match
    $stmt = $pdo->prepare("
        SELECT * FROM daily_matches 
        WHERE match_date <= ? 
        ORDER BY match_date DESC 
        LIMIT 1
    ");
    $stmt->execute([$currentDate]);
    $match = $stmt->fetch();
    
    if ($match) {
        header("Location: match_result.php?id=" . $match['id']);
        exit;
    } else {
        die("Không tìm thấy trận đấu nào.");
    }
}

// Get match details
$stmt = $pdo->prepare("SELECT * FROM daily_matches WHERE id = ?");
$stmt->execute([$matchId]);
$match = $stmt->fetch();

if (!$match) {
    die("Trận đấu không tồn tại.");
}

// Check if can update result
$canUpdate = canUpdateMatchResult($match['match_date']);

// Get participants
$stmt = $pdo->prepare("
    SELECT mp.*, p.name, p.main_position 
    FROM match_participants mp 
    JOIN players p ON mp.player_id = p.id 
    WHERE mp.match_id = ?
    ORDER BY mp.team, mp.assigned_position, p.name
");
$stmt->execute([$matchId]);
$participants = $stmt->fetchAll();

// Group by team
$teamA = array_filter($participants, function($p) { return $p['team'] === 'A'; });
$teamB = array_filter($participants, function($p) { return $p['team'] === 'B'; });

// Get recent matches for navigation
$stmt = $pdo->prepare("
    SELECT id, match_date, team_a_score, team_b_score, status 
    FROM daily_matches 
    WHERE match_date <= ?
    ORDER BY match_date DESC 
    LIMIT 10
");
$stmt->execute([$currentDate]);
$recentMatches = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚽ Kết quả trận đấu - <?= date('d/m/Y', strtotime($match['match_date'])) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .card-custom {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .team-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            min-height: 400px;
        }
        .team-a { border-left: 4px solid #dc3545; }
        .team-b { border-left: 4px solid #007bff; }
        .player-row {
            background: white;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        .player-row:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .score-input {
            font-size: 2rem;
            text-align: center;
            font-weight: bold;
        }
        .stat-input {
            width: 60px;
            text-align: center;
        }
        .position-badge {
            font-size: 0.8em;
            padding: 4px 8px;
            border-radius: 12px;
        }
        .skill-badge {
            font-size: 0.75em;
            padding: 2px 6px;
            border-radius: 8px;
        }
        .navbar-brand {
            font-weight: bold;
        }
        .nav-link {
            font-weight: 500;
        }
        .swap-btn {
            transition: all 0.3s ease;
        }
        .swap-btn:hover {
            transform: scale(1.1);
        }
        .edit-mode .swap-btn {
            display: inline-block !important;
        }
        .player-row.dragging {
            opacity: 0.5;
            transform: rotate(5deg);
        }
        .team-section.drop-zone {
            border: 2px dashed #28a745;
            background-color: #f8fff9;
        }
        .balance-indicator {
            font-size: 0.9em;
            margin-top: 10px;
        }
        .balance-good { color: #28a745; }
        .balance-warning { color: #ffc107; }
        .balance-danger { color: #dc3545; }
    </style>
</head>
<body class="gradient-bg">
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-futbol"></i> Football League
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-user-plus"></i> Đăng ký
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="match_result.php">
                            <i class="fas fa-edit"></i> Quản lý kết quả
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="leaderboard.php">
                            <i class="fas fa-trophy"></i> Bảng xếp hạng
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Header -->
        <div class="card card-custom mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="mb-2">⚽ Quản lý kết quả trận đấu</h1>
                        <p class="lead mb-0">
                            Ngày <?= date('d/m/Y', strtotime($match['match_date'])) ?>
                            <span class="badge bg-<?= $match['status'] === 'completed' ? 'success' : ($match['status'] === 'locked' ? 'warning' : 'secondary') ?>">
                                <?= $match['status'] === 'completed' ? 'Đã hoàn thành' : ($match['status'] === 'locked' ? 'Đã khóa' : 'Đang lên lịch') ?>
                            </span>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="btn-group">
                            <a href="index.php" class="btn btn-outline-primary">
                                <i class="fas fa-home"></i> Trang chủ
                            </a>
                            <a href="leaderboard.php" class="btn btn-outline-success">
                                <i class="fas fa-trophy"></i> Bảng xếp hạng
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Match Navigation -->
            <div class="col-lg-3 mb-4">
                <div class="card card-custom">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-calendar"></i> Các trận đấu
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentMatches as $recentMatch): ?>
                                <a href="match_result.php?id=<?= $recentMatch['id'] ?>" 
                                   class="list-group-item list-group-item-action <?= $recentMatch['id'] == $matchId ? 'active' : '' ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><?= date('d/m', strtotime($recentMatch['match_date'])) ?></span>
                                        <?php if ($recentMatch['status'] === 'completed'): ?>
                                            <span class="badge bg-success">
                                                <?= $recentMatch['team_a_score'] ?>-<?= $recentMatch['team_b_score'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <?= $recentMatch['status'] === 'locked' ? 'Khóa' : 'Chờ' ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-9">
                <?php if ($match['status'] === 'completed'): ?>
                    <!-- Display completed match result -->
                    <div class="card card-custom mb-4">
                        <div class="card-body text-center">
                            <h2 class="text-success mb-4">🎉 Kết quả trận đấu</h2>
                            <div class="row">
                                <div class="col-4">
                                    <h3 class="text-danger">🔴 Đội A</h3>
                                    <div class="display-4 text-danger"><?= $match['team_a_score'] ?></div>
                                </div>
                                <div class="col-4">
                                    <h4 class="text-muted">VS</h4>
                                    <div class="text-muted">
                                        <?php
                                        $winner = $match['team_a_score'] > $match['team_b_score'] ? 'Đội A thắng' : 
                                                 ($match['team_b_score'] > $match['team_a_score'] ? 'Đội B thắng' : 'Hòa');
                                        echo $winner;
                                        ?>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <h3 class="text-primary">🔵 Đội B</h3>
                                    <div class="display-4 text-primary"><?= $match['team_b_score'] ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($canUpdate): ?>
                    <!-- Score input form -->
                    <div class="card card-custom mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-edit"></i> Nhập kết quả trận đấu
                            </h5>
                        </div>
                        <div class="card-body">
                            <form id="matchResultForm">
                                <div class="row text-center mb-4">
                                    <div class="col-4">
                                        <h4 class="text-danger">🔴 Đội A</h4>
                                        <input type="number" 
                                               class="form-control score-input text-danger" 
                                               id="teamAScore" 
                                               min="0" 
                                               max="20" 
                                               value="<?= $match['team_a_score'] ?? 0 ?>"
                                               required>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="text-muted">VS</h4>
                                        <div class="display-6 text-muted">⚽</div>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="text-primary">🔵 Đội B</h4>
                                        <input type="number" 
                                               class="form-control score-input text-primary" 
                                               id="teamBScore" 
                                               min="0" 
                                               max="20" 
                                               value="<?= $match['team_b_score'] ?? 0 ?>"
                                               required>
                                    </div>
                                </div>
                                
                                <div class="text-center">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-save"></i> Lưu kết quả
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-lg ms-2" onclick="resetScores()">
                                        <i class="fas fa-undo"></i> Reset
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-clock"></i>
                        Chỉ có thể cập nhật kết quả sau 7h sáng ngày <?= date('d/m/Y', strtotime($match['match_date'] . ' +1 day')) ?>
                        <?php if (defined('TEST_MODE') && TEST_MODE): ?>
                            <br><span class="text-info">Test Mode: Thời gian đã được bỏ khóa</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Player Statistics & Team Management -->
                <div class="card card-custom">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-users"></i> Thống kê cầu thủ
                        </h5>
                        <?php if ($canUpdate && $match['status'] !== 'completed'): ?>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-info" onclick="toggleEditMode()" id="editModeBtn">
                                    <i class="fas fa-exchange-alt"></i> <span id="editModeText">Bật chế độ đổi đội</span>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="saveFormation()" style="display: none;" id="saveFormationBtn">
                                    <i class="fas fa-save"></i> Lưu đội hình mới
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <!-- Team Balance Indicator -->
                        <div class="row mb-3" id="balanceIndicator">
                            <div class="col-md-6">
                                <div class="balance-indicator">
                                    <strong>Đội A:</strong> <span id="teamABalance"></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="balance-indicator">
                                    <strong>Đội B:</strong> <span id="teamBBalance"></span>
                                </div>
                            </div>
                        </div>

                        <div class="row" id="teamsContainer">
                            <!-- Team A -->
                            <div class="col-md-6">
                                <div class="team-section team-a" id="teamASection" ondrop="drop(event, 'A')" ondragover="allowDrop(event)">
                                    <h5 class="text-danger mb-3">🔴 Đội A (<span id="teamACount"><?= count($teamA) ?></span> người)</h5>
                                    <div id="teamAPlayers">
                                        <?php foreach ($teamA as $player): ?>
                                            <div class="player-row" 
                                                 data-player-id="<?= $player['player_id'] ?>" 
                                                 data-team="A"
                                                 draggable="false"
                                                 ondragstart="drag(event)">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex align-items-center">
                                                            <button class="btn btn-sm btn-outline-primary swap-btn me-2" 
                                                                    onclick="swapPlayer(<?= $player['player_id'] ?>, 'A')" 
                                                                    style="display: none;"
                                                                    title="Chuyển sang đội B">
                                                                <i class="fas fa-arrow-right"></i>
                                                            </button>
                                                            <div>
                                                                <strong><?= htmlspecialchars($player['name']) ?></strong>
                                                                <div class="small mt-1">
                                                                    <span class="badge bg-info position-badge">
                                                                        <?= $player['assigned_position'] ?>
                                                                    </span>
                                                                    <span class="badge bg-<?= $player['skill_level'] === 'Tốt' ? 'success' : ($player['skill_level'] === 'Trung bình' ? 'warning' : 'secondary') ?> skill-badge">
                                                                        <?= $player['skill_level'] ?>
                                                                    </span>
                                                                    <span class="text-muted small">(<?= $player['position_type'] ?>)</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="text-end">
                                                        <?php if ($canUpdate && $match['status'] !== 'completed'): ?>
                                                            <!-- Editable stats -->
                                                            <div class="d-flex gap-2 align-items-center">
                                                                <div class="text-center">
                                                                    <label class="form-label small mb-1">Bàn thắng</label>
                                                                    <input type="number" 
                                                                           class="form-control stat-input" 
                                                                           data-player="<?= $player['player_id'] ?>"
                                                                           data-stat="goals"
                                                                           min="0" 
                                                                           max="10" 
                                                                           value="<?= $player['goals'] ?? 0 ?>">
                                                                </div>
                                                                <div class="text-center">
                                                                    <label class="form-label small mb-1">Kiến tạo</label>
                                                                    <input type="number" 
                                                                           class="form-control stat-input" 
                                                                           data-player="<?= $player['player_id'] ?>"
                                                                           data-stat="assists"
                                                                           min="0" 
                                                                           max="10" 
                                                                           value="<?= $player['assists'] ?? 0 ?>">
                                                                </div>
                                                            </div>
                                                        <?php else: ?>
                                                            <!-- Display stats -->
                                                            <div class="small">
                                                                <span class="badge bg-success"><?= $player['goals'] ?? 0 ?> bàn</span>
                                                                <span class="badge bg-primary"><?= $player['assists'] ?? 0 ?> kiến tạo</span>
                                                            </div>
                                                            <?php if ($match['status'] === 'completed'): ?>
                                                                <div class="small mt-1">
                                                                    <span class="badge bg-warning">+<?= $player['points_earned'] ?? 0 ?> điểm</span>
                                                                </div>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Team B -->
                            <div class="col-md-6">
                                <div class="team-section team-b" id="teamBSection" ondrop="drop(event, 'B')" ondragover="allowDrop(event)">
                                    <h5 class="text-primary mb-3">🔵 Đội B (<span id="teamBCount"><?= count($teamB) ?></span> người)</h5>
                                    <div id="teamBPlayers">
                                        <?php foreach ($teamB as $player): ?>
                                            <div class="player-row" 
                                                 data-player-id="<?= $player['player_id'] ?>" 
                                                 data-team="B"
                                                 draggable="false"
                                                 ondragstart="drag(event)">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex align-items-center">
                                                            <button class="btn btn-sm btn-outline-danger swap-btn me-2" 
                                                                    onclick="swapPlayer(<?= $player['player_id'] ?>, 'B')" 
                                                                    style="display: none;"
                                                                    title="Chuyển sang đội A">
                                                                <i class="fas fa-arrow-left"></i>
                                                            </button>
                                                            <div>
                                                                <strong><?= htmlspecialchars($player['name']) ?></strong>
                                                                <div class="small mt-1">
                                                                    <span class="badge bg-info position-badge">
                                                                        <?= $player['assigned_position'] ?>
                                                                    </span>
                                                                    <span class="badge bg-<?= $player['skill_level'] === 'Tốt' ? 'success' : ($player['skill_level'] === 'Trung bình' ? 'warning' : 'secondary') ?> skill-badge">
                                                                        <?= $player['skill_level'] ?>
                                                                    </span>
                                                                    <span class="text-muted small">(<?= $player['position_type'] ?>)</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="text-end">
                                                        <?php if ($canUpdate && $match['status'] !== 'completed'): ?>
                                                            <!-- Editable stats -->
                                                            <div class="d-flex gap-2 align-items-center">
                                                                <div class="text-center">
                                                                    <label class="form-label small mb-1">Bàn thắng</label>
                                                                    <input type="number" 
                                                                           class="form-control stat-input" 
                                                                           data-player="<?= $player['player_id'] ?>"
                                                                           data-stat="goals"
                                                                           min="0" 
                                                                           max="10" 
                                                                           value="<?= $player['goals'] ?? 0 ?>">
                                                                </div>
                                                                <div class="text-center">
                                                                    <label class="form-label small mb-1">Kiến tạo</label>
                                                                    <input type="number" 
                                                                           class="form-control stat-input" 
                                                                           data-player="<?= $player['player_id'] ?>"
                                                                           data-stat="assists"
                                                                           min="0" 
                                                                           max="10" 
                                                                           value="<?= $player['assists'] ?? 0 ?>">
                                                                </div>
                                                            </div>
                                                        <?php else: ?>
                                                            <!-- Display stats -->
                                                            <div class="small">
                                                                <span class="badge bg-success"><?= $player['goals'] ?? 0 ?> bàn</span>
                                                                <span class="badge bg-primary"><?= $player['assists'] ?? 0 ?> kiến tạo</span>
                                                            </div>
                                                            <?php if ($match['status'] === 'completed'): ?>
                                                                <div class="small mt-1">
                                                                    <span class="badge bg-warning">+<?= $player['points_earned'] ?? 0 ?> điểm</span>
                                                                </div>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($canUpdate && $match['status'] !== 'completed'): ?>
                            <div class="text-center mt-4">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <span id="instructionText">Nhập tỷ số và thống kê cầu thủ, sau đó click "Lưu kết quả" để hoàn tất.</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let editMode = false;
        let originalFormation = null;

        // Toggle edit mode for team swapping
        function toggleEditMode() {
            editMode = !editMode;
            const swapButtons = document.querySelectorAll('.swap-btn');
            const editModeText = document.getElementById('editModeText');
            const saveFormationBtn = document.getElementById('saveFormationBtn');
            const teamsContainer = document.getElementById('teamsContainer');
            const instructionText = document.getElementById('instructionText');
            
            if (editMode) {
                // Enable edit mode
                teamsContainer.classList.add('edit-mode');
                swapButtons.forEach(btn => btn.style.display = 'inline-block');
                editModeText.textContent = 'Tắt chế độ đổi đội';
                saveFormationBtn.style.display = 'inline-block';
                instructionText.innerHTML = '<i class="fas fa-exchange-alt"></i> Đang ở chế độ đổi đội. Click các mũi tên để chuyển cầu thủ giữa 2 đội. Nhớ lưu đội hình mới sau khi chỉnh sửa.';
                
                // Store original formation
                originalFormation = getCurrentFormation();
                
                // Enable drag and drop
                enableDragAndDrop();
                
                // Update balance indicator
                updateTeamBalance();
            } else {
                // Disable edit mode
                teamsContainer.classList.remove('edit-mode');
                swapButtons.forEach(btn => btn.style.display = 'none');
                editModeText.textContent = 'Bật chế độ đổi đội';
                saveFormationBtn.style.display = 'none';
                instructionText.innerHTML = '<i class="fas fa-info-circle"></i> Nhập tỷ số và thống kê cầu thủ, sau đó click "Lưu kết quả" để hoàn tất.';
                
                // Disable drag and drop
                disableDragAndDrop();
            }
        }

        // Swap player between teams
        function swapPlayer(playerId, currentTeam) {
            const playerRow = document.querySelector(`[data-player-id="${playerId}"]`);
            const targetTeam = currentTeam === 'A' ? 'B' : 'A';
            const targetContainer = document.getElementById(`team${targetTeam}Players`);
            
            // Update data attributes
            playerRow.setAttribute('data-team', targetTeam);
            
            // Update swap button
            const swapBtn = playerRow.querySelector('.swap-btn');
            if (targetTeam === 'A') {
                swapBtn.className = 'btn btn-sm btn-outline-primary swap-btn me-2';
                swapBtn.innerHTML = '<i class="fas fa-arrow-right"></i>';
                swapBtn.setAttribute('onclick', `swapPlayer(${playerId}, 'A')`);
                swapBtn.title = 'Chuyển sang đội B';
            } else {
                swapBtn.className = 'btn btn-sm btn-outline-danger swap-btn me-2';
                swapBtn.innerHTML = '<i class="fas fa-arrow-left"></i>';
                swapBtn.setAttribute('onclick', `swapPlayer(${playerId}, 'B')`);
                swapBtn.title = 'Chuyển sang đội A';
            }
            
            // Move to target team
            targetContainer.appendChild(playerRow);
            
            // Add animation effect
            playerRow.style.animation = 'swapAnimation 0.5s ease-in-out';
            setTimeout(() => {
                playerRow.style.animation = '';
            }, 500);
        }

        // Drag and drop functions
        function enableDragAndDrop() {
            document.querySelectorAll('.player-row').forEach(row => {
                row.setAttribute('draggable', 'true');
            });
        }

        function disableDragAndDrop() {
            document.querySelectorAll('.player-row').forEach(row => {
                row.setAttribute('draggable', 'false');
            });
        }

        function allowDrop(ev) {
            ev.preventDefault();
            ev.currentTarget.classList.add('drop-zone');
        }

        function drag(ev) {
            ev.dataTransfer.setData("text", ev.target.getAttribute('data-player-id'));
            ev.target.classList.add('dragging');
        }

        function drop(ev, targetTeam) {
            ev.preventDefault();
            ev.currentTarget.classList.remove('drop-zone');
            
            const playerId = ev.dataTransfer.getData("text");
            const playerRow = document.querySelector(`[data-player-id="${playerId}"]`);
            const currentTeam = playerRow.getAttribute('data-team');
            
            if (currentTeam !== targetTeam) {
                swapPlayer(parseInt(playerId), currentTeam);
            }
            
            playerRow.classList.remove('dragging');
        }

        // Update team counts
        function updateTeamCounts() {
            const teamACount = document.querySelectorAll('[data-team="A"]').length;
            const teamBCount = document.querySelectorAll('[data-team="B"]').length;
            
            document.getElementById('teamACount').textContent = teamACount;
            document.getElementById('teamBCount').textContent = teamBCount;
        }

        // Calculate and display team balance
        function updateTeamBalance() {
            const teamABalance = calculateTeamStrength('A');
            const teamBBalance = calculateTeamStrength('B');
            
            const balanceA = document.getElementById('teamABalance');
            const balanceB = document.getElementById('teamBBalance');
            
            balanceA.innerHTML = `Sức mạnh: ${teamABalance.total} | Tốt: ${teamABalance.good} | TB: ${teamABalance.average} | Yếu: ${teamABalance.weak}`;
            balanceB.innerHTML = `Sức mạnh: ${teamBBalance.total} | Tốt: ${teamBBalance.good} | TB: ${teamBBalance.average} | Yếu: ${teamBBalance.weak}`;
            
            // Color coding based on balance
            const difference = Math.abs(teamABalance.total - teamBBalance.total);
            const balanceClass = difference <= 2 ? 'balance-good' : difference <= 5 ? 'balance-warning' : 'balance-danger';
            
            balanceA.className = `balance-indicator ${balanceClass}`;
            balanceB.className = `balance-indicator ${balanceClass}`;
        }

        function calculateTeamStrength(team) {
            const players = document.querySelectorAll(`[data-team="${team}"]`);
            let total = 0;
            let good = 0;
            let average = 0;
            let weak = 0;
            
            players.forEach(player => {
                const skillBadge = player.querySelector('.skill-badge');
                const skillLevel = skillBadge.textContent.trim();
                
                switch (skillLevel) {
                    case 'Tốt':
                        total += 3;
                        good++;
                        break;
                    case 'Trung bình':
                        total += 2;
                        average++;
                        break;
                    case 'Yếu':
                        total += 1;
                        weak++;
                        break;
                }
            });
            
            return { total, good, average, weak };
        }

        // Get current formation
        function getCurrentFormation() {
            const formation = { teamA: [], teamB: [] };
            
            document.querySelectorAll('[data-team="A"]').forEach(player => {
                formation.teamA.push(parseInt(player.getAttribute('data-player-id')));
            });
            
            document.querySelectorAll('[data-team="B"]').forEach(player => {
                formation.teamB.push(parseInt(player.getAttribute('data-player-id')));
            });
            
            return formation;
        }

        // Save new formation
        function saveFormation() {
            if (!confirm('Bạn có chắc muốn lưu đội hình mới này?')) {
                return;
            }
            
            const currentFormation = getCurrentFormation();
            
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_formation',
                    match_id: <?= $matchId ?>,
                    team_a_players: currentFormation.teamA,
                    team_b_players: currentFormation.teamB
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Lỗi: ' + data.error);
                } else {
                    alert('Lưu đội hình mới thành công!');
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi lưu đội hình');
            });
        }

        // Match result form handler
        document.getElementById('matchResultForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const teamAScore = parseInt(document.getElementById('teamAScore').value);
            const teamBScore = parseInt(document.getElementById('teamBScore').value);
            
            if (isNaN(teamAScore) || isNaN(teamBScore)) {
                alert('Vui lòng nhập tỷ số hợp lệ');
                return;
            }
            
            if (teamAScore < 0 || teamBScore < 0) {
                alert('Tỷ số không được âm');
                return;
            }
            
            // Collect player stats
            const playerStats = {};
            document.querySelectorAll('.stat-input').forEach(input => {
                const playerId = input.dataset.player;
                const stat = input.dataset.stat;
                const value = parseInt(input.value) || 0;
                
                if (!playerStats[playerId]) {
                    playerStats[playerId] = { goals: 0, assists: 0 };
                }
                playerStats[playerId][stat] = value;
            });
            
            // Confirm before saving
            if (!confirm(`Xác nhận lưu kết quả: Đội A ${teamAScore} - ${teamBScore} Đội B?`)) {
                return;
            }
            
            // Save match result
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_match_result',
                    match_id: <?= $matchId ?>,
                    team_a_score: teamAScore,
                    team_b_score: teamBScore,
                    player_stats: playerStats
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Lỗi: ' + data.error);
                } else {
                    alert('Cập nhật kết quả thành công!');
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi lưu kết quả');
            });
        });

        // Reset scores function
        function resetScores() {
            if (confirm('Bạn có chắc muốn reset tất cả dữ liệu?')) {
                document.getElementById('teamAScore').value = 0;
                document.getElementById('teamBScore').value = 0;
                
                // Reset all player stats
                document.querySelectorAll('.stat-input').forEach(input => {
                    input.value = 0;
                });
            }
        }

        // Auto-validate input
        document.querySelectorAll('.stat-input').forEach(input => {
            input.addEventListener('input', function() {
                if (this.value < 0) this.value = 0;
                if (this.value > 10) this.value = 10;
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                const form = document.getElementById('matchResultForm');
                if (form) {
                    form.dispatchEvent(new Event('submit'));
                }
            }
            
            // Escape to cancel edit mode
            if (e.key === 'Escape' && editMode) {
                toggleEditMode();
            }
        });

        // Auto-focus first score input
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.getElementById('teamAScore');
            if (firstInput && <?= $canUpdate && $match['status'] !== 'completed' ? 'true' : 'false' ?>) {
                firstInput.focus();
                firstInput.select();
            }
            
            // Initialize team balance display
            updateTeamBalance();
        });

        // Real-time validation
        ['teamAScore', 'teamBScore'].forEach(id => {
            const input = document.getElementById(id);
            if (input) {
                input.addEventListener('input', function() {
                    if (this.value < 0) this.value = 0;
                    if (this.value > 20) this.value = 20;
                });
            }
        });

        // Add CSS animation for swap effect
        const style = document.createElement('style');
        style.textContent = `
            @keyframes swapAnimation {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); background-color: #e3f2fd; }
                100% { transform: scale(1); }
            }
            
            .team-section.drop-zone::before {
                content: "Thả cầu thủ vào đây";
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: rgba(40, 167, 69, 0.9);
                color: white;
                padding: 10px 20px;
                border-radius: 5px;
                font-weight: bold;
                z-index: 1000;
                pointer-events: none;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>