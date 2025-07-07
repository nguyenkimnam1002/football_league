<?php
require_once 'config.php';

$pdo = DB::getInstance();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_player':
                addPlayer($_POST);
                break;
            case 'update_player':
                updatePlayer($_POST);
                break;
            case 'delete_player':
                deletePlayer($_POST['player_id']);
                break;
        }
        // Redirect to prevent form resubmission
        header('Location: players.php');
        exit;
    }
}

function addPlayer($data) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO players (name, main_position, secondary_position, main_skill, secondary_skill, is_special_player) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $data['name'],
        $data['main_position'],
        $data['secondary_position'] ?: null,
        $data['main_skill'],
        $data['secondary_skill'] ?: null,
        isset($data['is_special_player']) ? 1 : 0
    ]);
}

function updatePlayer($data) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE players 
        SET name = ?, main_position = ?, secondary_position = ?, 
            main_skill = ?, secondary_skill = ?, is_special_player = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $data['name'],
        $data['main_position'],
        $data['secondary_position'] ?: null,
        $data['main_skill'],
        $data['secondary_skill'] ?: null,
        isset($data['is_special_player']) ? 1 : 0,
        $data['player_id']
    ]);
}

function deletePlayer($playerId) {
    global $pdo;
    
    // Check if player has matches
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM match_participants WHERE player_id = ?");
    $stmt->execute([$playerId]);
    $matchCount = $stmt->fetchColumn();
    
    if ($matchCount > 0) {
        throw new Exception("Không thể xóa cầu thủ đã tham gia trận đấu");
    }
    
    $stmt = $pdo->prepare("DELETE FROM players WHERE id = ?");
    $stmt->execute([$playerId]);
}

// Get all players with statistics
$stmt = $pdo->query("
    SELECT p.*, 
            COUNT(mp.id) as matches_played,
            COUNT(mp.id) as total_matches,
            SUM(CASE WHEN dm.team_a_score > dm.team_b_score AND mp.team = 'A' THEN 1 
                     WHEN dm.team_b_score > dm.team_a_score AND mp.team = 'B' THEN 1 
                     ELSE 0 END) as total_wins,
            SUM(CASE WHEN dm.team_a_score = dm.team_b_score THEN 1 ELSE 0 END) as total_draws,
            SUM(CASE WHEN dm.team_a_score < dm.team_b_score AND mp.team = 'A' THEN 1 
                     WHEN dm.team_b_score < dm.team_a_score AND mp.team = 'B' THEN 1 
                     ELSE 0 END) as losses,
            SUM(mp.goals) as total_goals,
            SUM(mp.assists) as total_assists,
            SUM(mp.points_earned) as total_points,
            ROUND(SUM(mp.points_earned) / GREATEST(COUNT(mp.id), 1), 2) as avg_points,
            ROUND((SUM(CASE WHEN dm.team_a_score > dm.team_b_score AND mp.team = 'A' THEN 1 
                            WHEN dm.team_b_score > dm.team_a_score AND mp.team = 'B' THEN 1 
                            ELSE 0 END) / GREATEST(COUNT(mp.id), 1)) * 100, 1) as win_rate
    FROM players p
        LEFT JOIN match_participants mp ON p.id = mp.player_id 
        LEFT JOIN daily_matches dm ON mp.match_id = dm.id 
    WHERE dm.status = 'completed'
    GROUP BY p.id,
        name,
        main_position,
        secondary_position,
        main_skill,
        secondary_skill,
        total_points,
        total_matches,
        total_wins,
        total_goals,
        total_assists,
        created_at,
        updated_at,
        is_special_player,
        total_draws
    HAVING matches_played > 0
    ORDER BY p.total_points DESC, p.name ASC
");
$players = $stmt->fetchAll();

// Get statistics
$totalPlayers = count($players);
$specialPlayers = count(array_filter($players, function($p) { return $p['is_special_player']; }));
$activePlayers = count(array_filter($players, function($p) { return $p['total_matches'] > 0; }));
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>👥 Quản lý cầu thủ - FC Gà Gáy</title>
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
        .special-player {
            background: linear-gradient(45deg, #fff3e0, #ffe0b2);
            border-left: 4px solid #ff9800;
        }
        .special-badge {
            background: linear-gradient(45deg, #ff9800, #f57c00);
            color: white;
            border: none;
            box-shadow: 0 2px 4px rgba(255, 152, 0, 0.3);
        }
        .player-row {
            transition: all 0.3s ease;
        }
        .player-row:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .stats-card {
            background: linear-gradient(45deg, #4CAF50, #45a049);
            color: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
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
        .player-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .special-avatar {
            background: linear-gradient(45deg, #ff9800, #f57c00);
            box-shadow: 0 0 10px rgba(255, 152, 0, 0.5);
        }
        .navbar-brand {
            font-weight: bold;
        }
        .nav-link {
            font-weight: 500;
        }
        .modal-content {
            border-radius: 15px;
        }
        .form-switch input:checked {
            background-color: #ff9800;
            border-color: #ff9800;
        }
        .special-indicator {
            position: absolute;
            top: -5px;
            right: -5px;
            font-size: 1.2em;
            animation: sparkle 2s infinite;
        }
        @keyframes sparkle {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.2) rotate(180deg); }
        }
    </style>
</head>
<body class="gradient-bg">
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-futbol"></i> FC Gà Gáy
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home"></i> Trang chủ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">
                            <i class="fas fa-user-plus"></i> Đăng ký
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="match_result.php">
                            <i class="fas fa-edit"></i> Quản lý kết quả
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="leaderboard.php">
                            <i class="fas fa-trophy"></i> Bảng xếp hạng
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="players.php">
                            <i class="fas fa-users"></i> Quản lý cầu thủ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="history.php">
                            <i class="fas fa-history"></i> Lịch sử trận đấu
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
                        <h1 class="mb-2">👥 Quản lý cầu thủ</h1>
                        <p class="lead mb-0">Thêm, sửa, xóa thông tin cầu thủ</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#addPlayerModal">
                            <i class="fas fa-user-plus"></i> Thêm cầu thủ
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <h3><?= $totalPlayers ?></h3>
                    <small>Tổng cầu thủ</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(45deg, #ff9800, #f57c00);">
                    <h3><?= $specialPlayers ?></h3>
                    <small>Cầu thủ đặc cách</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(45deg, #2196f3, #1976d2);">
                    <h3><?= $activePlayers ?></h3>
                    <small>Đã thi đấu</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(45deg, #9c27b0, #7b1fa2);">
                    <h3><?= $totalPlayers - $activePlayers ?></h3>
                    <small>Chưa thi đấu</small>
                </div>
            </div>
        </div>

        <!-- Players List -->
        <div class="card card-custom">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list"></i> Danh sách cầu thủ
                </h5>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-warning" onclick="filterSpecialPlayers()">
                        <i class="fas fa-star"></i> Cầu thủ đặc cách
                    </button>
                    <button class="btn btn-sm btn-outline-info" onclick="showAllPlayers()">
                        <i class="fas fa-users"></i> Tất cả
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Cầu thủ</th>
                                <th>Vị trí chính</th>
                                <th>Vị trí phụ</th>
                                <th>Kỹ năng</th>
                                <th>Loại</th>
                                <th>Thống kê</th>
                                <th>Điểm</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody id="playersTableBody">
                            <?php foreach ($players as $player): ?>
                                <tr class="player-row <?= $player['is_special_player'] ? 'special-player' : '' ?>" 
                                    data-special="<?= $player['is_special_player'] ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="player-avatar <?= $player['is_special_player'] ? 'special-avatar' : '' ?> me-3 position-relative">
                                                <?= strtoupper(substr($player['name'], 0, 2)) ?>
                                                <?php if ($player['is_special_player']): ?>
                                                    <span class="special-indicator"></span>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars($player['name']) ?></strong>
                                                <?php if ($player['is_special_player']): ?>
                                                    <span class="badge special-badge ms-2">Đặc cách</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info position-badge">
                                            <?= formatPosition($player['main_position']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($player['secondary_position']): ?>
                                            <span class="badge bg-secondary position-badge">
                                                <?= formatPosition($player['secondary_position']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">Không có</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php $mainSkill = formatSkill($player['main_skill']); ?>
                                        <span class="badge bg-<?= $mainSkill['color'] ?> skill-badge">
                                            <?= $mainSkill['text'] ?>
                                        </span>
                                        <?php if ($player['secondary_skill']): ?>
                                            <?php $secSkill = formatSkill($player['secondary_skill']); ?>
                                            <span class="badge bg-<?= $secSkill['color'] ?> skill-badge">
                                                <?= $secSkill['text'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($player['is_special_player']): ?>
                                            <span class="badge special-badge">
                                                Đặc cách
                                                <small class="d-block">x1.5 điểm</small>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Thường</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <div><strong><?= $player['total_matches'] ?></strong> trận</div>
                                            <div><?= formatWinDrawLoss($player['total_wins'], $player['total_draws'], calculateLosses($player['total_matches'], $player['total_wins'], $player['total_draws'])) ?></div>
                                            <div><?= $player['total_goals'] ?>⚽ <?= $player['total_assists'] ?>🎯</div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-center">
                                            <div class="h6 mb-0 text-success"><?= $player['total_points'] ?></div>
                                            <small class="text-muted"><?= $player['avg_points'] ?>/trận</small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="editPlayer(<?= htmlspecialchars(json_encode($player)) ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="deletePlayer(<?= $player['id'] ?>, '<?= htmlspecialchars($player['name']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Player Modal -->
    <div class="modal fade" id="addPlayerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus"></i> Thêm cầu thủ mới
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_player">
                        
                        <div class="mb-3">
                            <label class="form-label">Tên cầu thủ <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Vị trí chính <span class="text-danger">*</span></label>
                                    <select class="form-select" name="main_position" required>
                                        <option value="">Chọn vị trí</option>
                                        <option value="Thủ môn">🥅 Thủ môn</option>
                                        <option value="Trung vệ">🛡️ Trung vệ</option>
                                        <option value="Hậu vệ cánh">⚡ Hậu vệ cánh</option>
                                        <option value="Tiền vệ">⚽ Tiền vệ</option>
                                        <option value="Tiền đạo">🎯 Tiền đạo</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Kỹ năng chính <span class="text-danger">*</span></label>
                                    <select class="form-select" name="main_skill" required>
                                        <option value="">Chọn kỹ năng</option>
                                        <option value="Tốt">Tốt</option>
                                        <option value="Trung bình">Trung bình</option>
                                        <option value="Yếu">Yếu</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Kỹ năng phụ</label>
                                    <select class="form-select" name="secondary_skill">
                                        <option value="">Không có</option>
                                        <option value="Tốt">Tốt</option>
                                        <option value="Trung bình">Trung bình</option>
                                        <option value="Yếu">Yếu</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Cầu thủ đặc cách -->
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="addSpecialPlayer" name="is_special_player">
                                <label class="form-check-label" for="addSpecialPlayer">
                                    <strong>⭐ Cầu thủ đặc cách</strong>
                                    <div class="small text-muted">
                                        Nhận x1.5 điểm (Thắng: 4.5đ, Hòa: 1.5đ, Thua: 0đ)
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Thêm cầu thủ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Player Modal -->
    <div class="modal fade" id="editPlayerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit"></i> Sửa thông tin cầu thủ
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editPlayerForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_player">
                        <input type="hidden" name="player_id" id="editPlayerId">
                        
                        <div class="mb-3">
                            <label class="form-label">Tên cầu thủ <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="editPlayerName" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Vị trí chính <span class="text-danger">*</span></label>
                                    <select class="form-select" name="main_position" id="editMainPosition" required>
                                        <option value="">Chọn vị trí</option>
                                        <option value="Thủ môn">🥅 Thủ môn</option>
                                        <option value="Trung vệ">🛡️ Trung vệ</option>
                                        <option value="Hậu vệ cánh">⚡ Hậu vệ cánh</option>
                                        <option value="Tiền vệ">⚽ Tiền vệ</option>
                                        <option value="Tiền đạo">🎯 Tiền đạo</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Vị trí phụ</label>
                                    <select class="form-select" name="secondary_position" id="editSecondaryPosition">
                                        <option value="">Không có</option>
                                        <option value="Thủ môn">🥅 Thủ môn</option>
                                        <option value="Trung vệ">🛡️ Trung vệ</option>
                                        <option value="Hậu vệ cánh">⚡ Hậu vệ cánh</option>
                                        <option value="Tiền vệ">⚽ Tiền vệ</option>
                                        <option value="Tiền đạo">🎯 Tiền đạo</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Kỹ năng chính <span class="text-danger">*</span></label>
                                    <select class="form-select" name="main_skill" id="editMainSkill" required>
                                        <option value="">Chọn kỹ năng</option>
                                        <option value="Tốt">Tốt</option>
                                        <option value="Trung bình">Trung bình</option>
                                        <option value="Yếu">Yếu</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Kỹ năng phụ</label>
                                    <select class="form-select" name="secondary_skill" id="editSecondarySkill">
                                        <option value="">Không có</option>
                                        <option value="Tốt">Tốt</option>
                                        <option value="Trung bình">Trung bình</option>
                                        <option value="Yếu">Yếu</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Cầu thủ đặc cách -->
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="editSpecialPlayer" name="is_special_player">
                                <label class="form-check-label" for="editSpecialPlayer">
                                    <strong>⭐ Cầu thủ đặc cách</strong>
                                    <div class="small text-muted">
                                        Nhận x1.5 điểm (Thắng: 4.5đ, Hòa: 1.5đ, Thua: 0đ)
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Cập nhật
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deletePlayerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <i class="fas fa-exclamation-triangle"></i> Xác nhận xóa
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Bạn có chắc chắn muốn xóa cầu thủ <strong id="deletePlayerName"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-warning"></i>
                        Không thể hoàn tác hành động này!
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete_player">
                        <input type="hidden" name="player_id" id="deletePlayerId">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Xóa
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Edit player function
        function editPlayer(player) {
            document.getElementById('editPlayerId').value = player.id;
            document.getElementById('editPlayerName').value = player.name;
            document.getElementById('editMainPosition').value = player.main_position;
            document.getElementById('editSecondaryPosition').value = player.secondary_position || '';
            document.getElementById('editMainSkill').value = player.main_skill;
            document.getElementById('editSecondarySkill').value = player.secondary_skill || '';
            document.getElementById('editSpecialPlayer').checked = player.is_special_player == 1;
            
            const modal = new bootstrap.Modal(document.getElementById('editPlayerModal'));
            modal.show();
        }

        // Delete player function
        function deletePlayer(playerId, playerName) {
            document.getElementById('deletePlayerId').value = playerId;
            document.getElementById('deletePlayerName').textContent = playerName;
            
            const modal = new bootstrap.Modal(document.getElementById('deletePlayerModal'));
            modal.show();
        }

        // Filter special players
        function filterSpecialPlayers() {
            const rows = document.querySelectorAll('.player-row');
            rows.forEach(row => {
                if (row.dataset.special === '1') {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Show all players
        function showAllPlayers() {
            const rows = document.querySelectorAll('.player-row');
            rows.forEach(row => {
                row.style.display = '';
            });
        }

        // Add animations on page load
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.player-row');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 50);
            });

            // Highlight special players on load
            const specialRows = document.querySelectorAll('[data-special="1"]');
            specialRows.forEach(row => {
                row.style.animation = 'pulse 2s infinite';
            });
        });

        // Add search functionality
        function searchPlayers(searchTerm) {
            const rows = document.querySelectorAll('.player-row');
            rows.forEach(row => {
                const name = row.querySelector('strong').textContent.toLowerCase();
                if (name.includes(searchTerm.toLowerCase()) || searchTerm === '') {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + N to add new player
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                const modal = new bootstrap.Modal(document.getElementById('addPlayerModal'));
                modal.show();
            }
            
            // Ctrl/Cmd + F to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                // Add search input if not exists
                if (!document.getElementById('searchInput')) {
                    const searchInput = document.createElement('input');
                    searchInput.type = 'text';
                    searchInput.id = 'searchInput';
                    searchInput.className = 'form-control mb-3';
                    searchInput.placeholder = 'Tìm kiếm cầu thủ...';
                    searchInput.addEventListener('input', function() {
                        searchPlayers(this.value);
                    });
                    
                    const cardBody = document.querySelector('.card-custom .card-body');
                    cardBody.insertBefore(searchInput, cardBody.firstChild);
                }
                document.getElementById('searchInput').focus();
            }
        });

        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { box-shadow: 0 0 0 0 rgba(255, 152, 0, 0.7); }
                70% { box-shadow: 0 0 0 10px rgba(255, 152, 0, 0); }
                100% { box-shadow: 0 0 0 0 rgba(255, 152, 0, 0); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>