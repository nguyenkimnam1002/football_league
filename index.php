<?php
require_once 'config.php';

// Get current match info
$pdo = DB::getInstance();
$currentDate = getCurrentDate();
$isLocked = isRegistrationLocked();

// Get today's registrations
$stmt = $pdo->prepare("
    SELECT p.*, dr.registered_at 
    FROM players p 
    JOIN daily_registrations dr ON p.id = dr.player_id 
    WHERE dr.registration_date = ?
    ORDER BY dr.registered_at ASC
");
$stmt->execute([$currentDate]);
$registeredPlayers = $stmt->fetchAll();

// Get today's match if exists
$stmt = $pdo->prepare("SELECT * FROM daily_matches WHERE match_date = ?");
$stmt->execute([$currentDate]);
$todayMatch = $stmt->fetch();

// Get recent matches for history
$stmt = $pdo->prepare("
    SELECT dm.*, 
           COUNT(mp.id) as total_players
    FROM daily_matches dm 
    LEFT JOIN match_participants mp ON dm.id = mp.match_id
    WHERE dm.match_date < ?
    GROUP BY dm.id
    ORDER BY dm.match_date DESC 
    LIMIT 5
");
$stmt->execute([$currentDate]);
$recentMatches = $stmt->fetchAll();

// Get all players for selection
$stmt = $pdo->query("SELECT * FROM players ORDER BY name");
$allPlayers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚽ Football League Manager</title>
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
        .player-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .player-card:hover {
            border-color: #28a745;
            transform: translateY(-2px);
        }
        .player-card.selected {
            border-color: #28a745;
            background-color: #f8fff9;
        }
        .skill-badge {
            font-size: 0.75em;
            padding: 2px 8px;
            border-radius: 12px;
        }
        .position-header {
            color: #28a745;
            font-weight: bold;
            border-bottom: 2px solid #28a745;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        .team-formation {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
        }
        .team-a { border-left: 4px solid #dc3545; }
        .team-b { border-left: 4px solid #007bff; }
        .status-locked {
            background: linear-gradient(45deg, #f44336, #d32f2f);
        }
        .status-open {
            background: linear-gradient(45deg, #4CAF50, #45a049);
        }
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        .nav-link {
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .nav-link:hover {
            transform: translateY(-1px);
        }
        .quick-access-btn {
            transition: all 0.3s ease;
            margin-bottom: 8px;
        }
        .quick-access-btn:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        @media (max-width: 768px) {
            .btn-group .btn {
                font-size: 0.85rem;
                padding: 0.5rem;
            }
        }
        .player-select-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .player-select-card:hover {
            background-color: #f8f9fa;
            border-color: #28a745 !important;
        }
        .form-check-input:checked ~ .form-check-label .player-select-card {
            background-color: #e8f5e8;
            border-color: #28a745 !important;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
        }
        .form-check {
            margin-bottom: 0;
        }
        .form-check-label {
            cursor: pointer;
        }
        #selectedCount {
            font-weight: bold;
            color: #fff;
        }
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
                        <a class="nav-link active" href="index.php">
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
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cog"></i> Tiện ích
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="players.php"><i class="fas fa-users"></i> Quản lý cầu thủ</a></li>
                            <li><a class="dropdown-item" href="history.php"><i class="fas fa-history"></i> Lịch sử trận đấu</a></li>
                            <li><a class="dropdown-item" href="statistics.php"><i class="fas fa-chart-bar"></i> Thống kê chi tiết</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-settings"></i> Cài đặt</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Header -->
        <div class="card card-custom mb-4">
            <div class="card-body text-center">
                <h1 class="mb-3">⚽ Football League Manager</h1>
                <p class="lead">Đăng ký và chia đội tự động hàng ngày</p>
                
                <!-- Status Bar -->
                <div class="alert <?= $isLocked ? 'status-locked' : 'status-open' ?> text-white">
                    <i class="fas fa-clock me-2"></i>
                    <strong>Ngày <?= date('d/m/Y', strtotime($currentDate)) ?></strong> - 
                    <?php if ($isLocked): ?>
                        🔒 Đã khóa đăng ký (sau 22h30)
                    <?php else: ?>
                        ✅ Đang mở đăng ký (khóa lúc 22h30)
                    <?php endif; ?>
                    - Đã đăng ký: <strong><?= count($registeredPlayers) ?></strong> người
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column: Registration -->
            <div class="col-lg-8">
                <div class="card card-custom mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user-plus me-2"></i>
                            Đăng ký tham gia (<?= count($registeredPlayers) ?>/32)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!$isLocked): ?>
                            <!-- Mass Registration -->
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Chọn cầu thủ tham gia:</h6>
                                    <div>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll()">
                                            <i class="fas fa-check-square"></i> Chọn tất cả
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="unselectAll()">
                                            <i class="fas fa-square"></i> Bỏ chọn tất cả
                                        </button>
                                    </div>
                                </div>

                                <!-- Players Grid -->
                                <div class="row" id="playersGrid">
                                    <?php
                                    // Group unregistered players by position for display
                                    $unregisteredPlayers = [];
                                    foreach ($allPlayers as $player) {
                                        $isRegistered = array_filter($registeredPlayers, function($rp) use ($player) {
                                            return $rp['id'] == $player['id'];
                                        });
                                        if (!$isRegistered) {
                                            $unregisteredPlayers[$player['main_position']][] = $player;
                                        }
                                    }
                                    ?>

                                    <?php foreach (['Thủ môn', 'Trung vệ', 'Hậu vệ cánh', 'Tiền vệ', 'Tiền đạo'] as $position): ?>
                                        <?php if (isset($unregisteredPlayers[$position])): ?>
                                            <div class="col-12 mb-3">
                                                <h6 class="text-primary border-bottom pb-2">
                                                    <?= formatPosition($position) ?> (<?= count($unregisteredPlayers[$position]) ?> người)
                                                </h6>
                                                <div class="row">
                                                    <?php foreach ($unregisteredPlayers[$position] as $player): ?>
                                                        <div class="col-md-6 col-lg-4 mb-2">
                                                            <div class="form-check">
                                                                <input class="form-check-input player-checkbox" 
                                                                       type="checkbox" 
                                                                       value="<?= $player['id'] ?>" 
                                                                       id="player_<?= $player['id'] ?>">
                                                                <label class="form-check-label w-100" for="player_<?= $player['id'] ?>">
                                                                    <div class="player-select-card p-2 border rounded">
                                                                        <div class="fw-bold"><?= htmlspecialchars($player['name']) ?></div>
                                                                        <div class="small text-muted">
                                                                            <?= $player['secondary_position'] ? formatPosition($player['secondary_position']) : 'Không có vị trí phụ' ?>
                                                                        </div>
                                                                        <div class="mt-1">
                                                                            <?php $skill = formatSkill($player['main_skill']); ?>
                                                                            <span class="badge bg-<?= $skill['color'] ?> skill-badge">
                                                                                <?= $skill['text'] ?>
                                                                            </span>
                                                                            <?php if ($player['secondary_skill']): ?>
                                                                                <?php $secSkill = formatSkill($player['secondary_skill']); ?>
                                                                                <span class="badge bg-<?= $secSkill['color'] ?> skill-badge">
                                                                                    <?= $secSkill['text'] ?>
                                                                                </span>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Registration Actions -->
                                <div class="text-center mt-3">
                                    <button type="button" id="registerSelectedBtn" class="btn btn-success btn-lg" disabled>
                                        <i class="fas fa-user-plus"></i> Đăng ký các cầu thủ đã chọn (<span id="selectedCount">0</span>)
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Registered Players by Position -->
                        <?php
                        $playersByPosition = [];
                        foreach ($registeredPlayers as $player) {
                            $playersByPosition[$player['main_position']][] = $player;
                        }
                        ?>

                        <?php foreach (['Thủ môn', 'Trung vệ', 'Hậu vệ cánh', 'Tiền vệ', 'Tiền đạo'] as $position): ?>
                            <?php if (isset($playersByPosition[$position])): ?>
                                <div class="mb-3">
                                    <div class="position-header">
                                        <?= formatPosition($position) ?> (<?= count($playersByPosition[$position]) ?>)
                                    </div>
                                    <div class="row">
                                        <?php foreach ($playersByPosition[$position] as $player): ?>
                                            <div class="col-md-6 col-lg-4 mb-2">
                                                <div class="player-card p-3">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <strong><?= htmlspecialchars($player['name']) ?></strong>
                                                            <div class="small text-muted">
                                                                <?= $player['secondary_position'] ? formatPosition($player['secondary_position']) : '' ?>
                                                            </div>
                                                        </div>
                                                        <?php if (!$isLocked): ?>
                                                            <button class="btn btn-sm btn-outline-danger remove-player" 
                                                                    data-player-id="<?= $player['id'] ?>">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="mt-2">
                                                        <?php $skill = formatSkill($player['main_skill']); ?>
                                                        <span class="badge bg-<?= $skill['color'] ?> skill-badge">
                                                            <?= $skill['text'] ?>
                                                        </span>
                                                        <?php if ($player['secondary_skill']): ?>
                                                            <?php $secSkill = formatSkill($player['secondary_skill']); ?>
                                                            <span class="badge bg-<?= $secSkill['color'] ?> skill-badge">
                                                                <?= $secSkill['text'] ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <!-- Team Division Actions -->
                        <?php if (count($registeredPlayers) >= MIN_PLAYERS): ?>
                            <div class="text-center mt-4">
                                <button id="divideTeamsBtn" class="btn btn-success btn-lg me-2">
                                    <i class="fas fa-random"></i> Chia đội tự động
                                </button>
                                <button id="previewBtn" class="btn btn-outline-success btn-lg">
                                    <i class="fas fa-eye"></i> Xem trước
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning text-center">
                                <i class="fas fa-users"></i>
                                Cần thêm <?= MIN_PLAYERS - count($registeredPlayers) ?> cầu thủ nữa để có thể chia đội
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Today's Match Result -->
                <?php if ($todayMatch): ?>
                    <div class="card card-custom">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-trophy me-2"></i>
                                Đội hình hôm nay
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($todayMatch['status'] === 'completed'): ?>
                                <div class="alert alert-success">
                                    <h6>🎉 Kết quả trận đấu:</h6>
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <strong>Đội A</strong><br>
                                            <span class="h3"><?= $todayMatch['team_a_score'] ?></span>
                                        </div>
                                        <div class="col-4">
                                            <span class="h5">VS</span>
                                        </div>
                                        <div class="col-4">
                                            <strong>Đội B</strong><br>
                                            <span class="h3"><?= $todayMatch['team_b_score'] ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif ($todayMatch['status'] === 'locked'): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-lock"></i> Đội hình đã được khóa, chờ thi đấu...
                                </div>
                            <?php endif; ?>
                            
                            <div id="todayFormation"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right Column: Stats & History -->
            <div class="col-lg-4">
                <!-- Quick Access Panel -->
                <div class="card card-custom mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-bolt me-2"></i>
                            Truy cập nhanh
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="match_result.php" class="btn btn-warning quick-access-btn">
                                <i class="fas fa-edit me-2"></i> Nhập kết quả trận đấu
                            </a>
                            <a href="leaderboard.php" class="btn btn-success quick-access-btn">
                                <i class="fas fa-trophy me-2"></i> Xem bảng xếp hạng
                            </a>
                            <a href="players.php" class="btn btn-info quick-access-btn">
                                <i class="fas fa-users me-2"></i> Quản lý cầu thủ
                            </a>
                            <a href="history.php" class="btn btn-secondary quick-access-btn">
                                <i class="fas fa-history me-2"></i> Lịch sử trận đấu
                            </a>
                        </div>
                        
                        <!-- Quick Stats -->
                        <hr class="my-3">
                        <div class="small text-muted">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Hôm nay:</span>
                                <span class="fw-bold"><?= count($registeredPlayers) ?>/32 người</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Trạng thái:</span>
                                <span class="<?= $isLocked ? 'text-danger' : 'text-success' ?> fw-bold">
                                    <?= $isLocked ? 'Đã khóa' : 'Đang mở' ?>
                                </span>
                            </div>
                            <?php if ($todayMatch): ?>
                                <div class="d-flex justify-content-between">
                                    <span>Đội hình:</span>
                                    <span class="text-info fw-bold">Đã tạo</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="card card-custom mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>
                            Thống kê nhanh
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get top players stats
                        $stmt = $pdo->query("
                            SELECT name, total_points, total_matches, total_wins, total_goals 
                            FROM players 
                            WHERE total_matches > 0
                            ORDER BY total_points DESC 
                            LIMIT 5
                        ");
                        $topPlayers = $stmt->fetchAll();
                        ?>
                        
                        <h6>🏆 Top 5 Điểm số:</h6>
                        <?php foreach ($topPlayers as $index => $player): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>
                                    <span class="badge bg-secondary"><?= $index + 1 ?></span>
                                    <?= htmlspecialchars($player['name']) ?>
                                </span>
                                <strong><?= $player['total_points'] ?> điểm</strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Recent Matches -->
                <div class="card card-custom">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-history me-2"></i>
                            Lịch sử gần đây
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($recentMatches as $match): ?>
                            <div class="mb-3 p-2 border rounded">
                                <div class="d-flex justify-content-between">
                                    <strong><?= date('d/m', strtotime($match['match_date'])) ?></strong>
                                    <span class="badge bg-primary"><?= $match['total_players'] ?> người</span>
                                </div>
                                <?php if ($match['status'] === 'completed'): ?>
                                    <div class="text-center mt-1">
                                        <span class="badge bg-danger"><?= $match['team_a_score'] ?></span>
                                        -
                                        <span class="badge bg-primary"><?= $match['team_b_score'] ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted small">Chưa có kết quả</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <a href="history.php" class="btn btn-outline-primary btn-sm w-100">
                            <i class="fas fa-list"></i> Xem tất cả
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Result Modal -->
    <div class="modal fade" id="formationModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">⚽ Đội hình được chia</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="formationContent"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="button" id="confirmFormation" class="btn btn-success">
                        <i class="fas fa-check"></i> Xác nhận đội hình
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Player checkbox selection
        const playerCheckboxes = document.querySelectorAll('.player-checkbox');
        const registerBtn = document.getElementById('registerSelectedBtn');
        const selectedCountSpan = document.getElementById('selectedCount');

        // Update selected count and button state
        function updateSelectedCount() {
            const selectedCount = document.querySelectorAll('.player-checkbox:checked').length;
            selectedCountSpan.textContent = selectedCount;
            registerBtn.disabled = selectedCount === 0;
        }

        // Add event listeners to checkboxes
        playerCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedCount);
        });

        // Select all function
        function selectAll() {
            playerCheckboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            updateSelectedCount();
        }

        // Unselect all function
        function unselectAll() {
            playerCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelectedCount();
        }

        // Register selected players
        document.getElementById('registerSelectedBtn')?.addEventListener('click', function() {
            const selectedPlayers = Array.from(document.querySelectorAll('.player-checkbox:checked'))
                .map(checkbox => checkbox.value);
            
            if (selectedPlayers.length === 0) {
                alert('Vui lòng chọn ít nhất 1 cầu thủ');
                return;
            }

            // Disable button to prevent multiple clicks
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang đăng ký...';

            // Register players one by one
            registerPlayersSequentially(selectedPlayers, 0);
        });

        function registerPlayersSequentially(playerIds, index) {
            if (index >= playerIds.length) {
                // All players registered, reload page
                alert(`Đã đăng ký thành công ${playerIds.length} cầu thủ!`);
                location.reload();
                return;
            }

            const playerId = playerIds[index];
            
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'register_player',
                    player_id: playerId,
                    date: '<?= $currentDate ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error(`Lỗi đăng ký cầu thủ ${playerId}:`, data.error);
                }
                // Continue with next player regardless of success/failure
                registerPlayersSequentially(playerIds, index + 1);
            })
            .catch(error => {
                console.error(`Network error for player ${playerId}:`, error);
                // Continue with next player even on network error
                registerPlayersSequentially(playerIds, index + 1);
            });
        }

        // Remove player (existing functionality)
        document.querySelectorAll('.remove-player').forEach(btn => {
            btn.addEventListener('click', function() {
                const playerId = this.dataset.playerId;
                
                if (!confirm('Bạn có chắc muốn hủy đăng ký cầu thủ này?')) {
                    return;
                }
                
                fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'unregister_player',
                        player_id: playerId,
                        date: '<?= $currentDate ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                    } else {
                        location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Có lỗi xảy ra khi hủy đăng ký');
                });
            });
        });

        // Divide teams
        document.getElementById('divideTeamsBtn')?.addEventListener('click', function() {
            divideTeams(false);
        });

        document.getElementById('previewBtn')?.addEventListener('click', function() {
            divideTeams(true);
        });

        function divideTeams(preview = false) {
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'divide_teams',
                    date: '<?= $currentDate ?>',
                    preview: preview
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                } else {
                    showFormation(data.data, preview);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi chia đội');
            });
        }

        function showFormation(data, preview) {
            const modal = new bootstrap.Modal(document.getElementById('formationModal'));
            const content = document.getElementById('formationContent');
            const confirmBtn = document.getElementById('confirmFormation');
            
            content.innerHTML = generateFormationHTML(data);
            confirmBtn.style.display = preview ? 'block' : 'none';
            
            if (preview) {
                confirmBtn.onclick = function() {
                    divideTeams(false);
                    modal.hide();
                };
            }
            
            modal.show();
        }

        function generateFormationHTML(data) {
            let html = `
                <div class="row mb-3">
                    <div class="col-md-4 text-center">
                        <div class="h4">🔴 Đội A</div>
                        <div class="h5">${data.stats.totalA} người</div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="h5">VS</div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="h4">🔵 Đội B</div>
                        <div class="h5">${data.stats.totalB} người</div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="team-formation team-a">
                            ${generateTeamHTML(data.teamA, 'A')}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="team-formation team-b">
                            ${generateTeamHTML(data.teamB, 'B')}
                        </div>
                    </div>
                </div>
            `;
            return html;
        }

        function generateTeamHTML(team, teamName) {
            const positions = ['Thủ môn', 'Trung vệ', 'Hậu vệ cánh', 'Tiền vệ', 'Tiền đạo'];
            let html = '<h5 class="text-center mb-3">Đội ' + teamName + '</h5>';
            
            positions.forEach(position => {
                if (team[position] && team[position].length > 0) {
                    html += `<div class="mb-3">
                        <h6 class="position-header">${position} (${team[position].length})</h6>`;
                    
                    team[position].forEach(player => {
                        const skillColor = player.skill_level === 'Tốt' ? 'success' : 
                                         player.skill_level === 'Trung bình' ? 'warning' : 'secondary';
                        
                        html += `
                            <div class="border rounded p-2 mb-2">
                                <strong>${player.name}</strong>
                                <div class="small">
                                    <span class="badge bg-info">${player.position_type}</span>
                                    <span class="badge bg-${skillColor}">${player.skill_level}</span>
                                </div>
                            </div>
                        `;
                    });
                    
                    html += '</div>';
                }
            });
            
            return html;
        }

        // Load today's formation if exists
        <?php if ($todayMatch && $todayMatch['team_a_formation']): ?>
            const todayFormation = {
                teamA: <?= $todayMatch['team_a_formation'] ?>,
                teamB: <?= $todayMatch['team_b_formation'] ?>,
                stats: {
                    totalA: <?= array_sum(array_map('count', json_decode($todayMatch['team_a_formation'], true))) ?>,
                    totalB: <?= array_sum(array_map('count', json_decode($todayMatch['team_b_formation'], true))) ?>
                }
            };
            document.getElementById('todayFormation').innerHTML = generateFormationHTML(todayFormation);
        <?php endif; ?>

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateSelectedCount();
        });
    </script>
</body>
</html>