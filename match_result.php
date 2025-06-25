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
        }
        .team-a { border-left: 4px solid #dc3545; }
        .team-b { border-left: 4px solid #007bff; }
        .player-row {
            background: white;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            border: 1px solid #e9ecef;
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

                <!-- Player Statistics -->
                <div class="card card-custom">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-users"></i> Thống kê cầu thủ
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Team A -->
                            <div class="col-md-6">
                                <div class="team-section team-a">
                                    <h5 class="text-danger mb-3">🔴 Đội A (<?= count($teamA) ?> người)</h5>
                                    <?php foreach ($teamA as $player): ?>
                                        <div class="player-row">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
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

                            <!-- Team B -->
                            <div class="col-md-6">
                                <div class="team-section team-b">
                                    <h5 class="text-primary mb-3">🔵 Đội B (<?= count($teamB) ?> người)</h5>
                                    <?php foreach ($teamB as $player): ?>
                                        <div class="player-row">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
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

                        <?php if ($canUpdate && $match['status'] !== 'completed'): ?>
                            <div class="text-center mt-4">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    Nhập tỷ số và thống kê cầu thủ, sau đó click "Lưu kết quả" để hoàn tất.
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
        });

        // Auto-focus first score input
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.getElementById('teamAScore');
            if (firstInput && <?= $canUpdate && $match['status'] !== 'completed' ? 'true' : 'false' ?>) {
                firstInput.focus();
                firstInput.select();
            }
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
    </script>
</body>
</html>