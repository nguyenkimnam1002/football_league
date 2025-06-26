<?php
require_once 'config.php';

$pdo = DB::getInstance();
$currentMonth = $_GET['month'] ?? date('Y-m');
$period = $_GET['period'] ?? 'month'; // month, all_time

// Get available months
$stmt = $pdo->query("
    SELECT DISTINCT month 
    FROM player_stats 
    ORDER BY month DESC
");
$availableMonths = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get leaderboard data
if ($period === 'month') {
    $stmt = $pdo->prepare("
        SELECT ps.*, p.name, p.main_position,
               ROUND(ps.points / GREATEST(ps.matches_played, 1), 2) as avg_points,
               ROUND((ps.wins / GREATEST(ps.matches_played, 1)) * 100, 1) as win_rate
        FROM player_stats ps 
        JOIN players p ON ps.player_id = p.id 
        WHERE ps.month = ? AND ps.matches_played > 0
        ORDER BY ps.points DESC, ps.wins DESC, ps.goals DESC
    ");
    $stmt->execute([$currentMonth]);
    $leaderboard = $stmt->fetchAll();
    
    // Get month stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT player_id) as total_players,
            SUM(matches_played) as total_matches,
            SUM(goals) as total_goals,
            SUM(assists) as total_assists,
            AVG(points) as avg_points
        FROM player_stats 
        WHERE month = ?
    ");
    $stmt->execute([$currentMonth]);
    $monthStats = $stmt->fetch();
    
} else {
    $stmt = $pdo->query("
        SELECT p.*, 
               ROUND(p.total_points / GREATEST(p.total_matches, 1), 2) as avg_points,
               ROUND((p.total_wins / GREATEST(p.total_matches, 1)) * 100, 1) as win_rate
        FROM players p 
        WHERE p.total_matches > 0
        ORDER BY p.total_points DESC, p.total_wins DESC, p.total_goals DESC
    ");
    $leaderboard = $stmt->fetchAll();
    
    // Get overall stats
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_players,
            SUM(total_matches) as total_matches,
            SUM(total_goals) as total_goals,
            SUM(total_assists) as total_assists,
            AVG(total_points) as avg_points
        FROM players 
        WHERE total_matches > 0
    ");
    $monthStats = $stmt->fetch();
}

// Get top performers by category
if ($period === 'month') {
    // Top goal scorer
    $stmt = $pdo->prepare("
        SELECT ps.goals, p.name 
        FROM player_stats ps 
        JOIN players p ON ps.player_id = p.id 
        WHERE ps.month = ? 
        ORDER BY ps.goals DESC 
        LIMIT 1
    ");
    $stmt->execute([$currentMonth]);
    $topScorer = $stmt->fetch();
    
    // Top assist maker
    $stmt = $pdo->prepare("
        SELECT ps.assists, p.name 
        FROM player_stats ps 
        JOIN players p ON ps.player_id = p.id 
        WHERE ps.month = ? 
        ORDER BY ps.assists DESC 
        LIMIT 1
    ");
    $stmt->execute([$currentMonth]);
    $topAssist = $stmt->fetch();
    
} else {
    // All-time top scorer
    $stmt = $pdo->query("
        SELECT total_goals as goals, name 
        FROM players 
        WHERE total_matches > 0
        ORDER BY total_goals DESC 
        LIMIT 1
    ");
    $topScorer = $stmt->fetch();
    
    // All-time top assist
    $stmt = $pdo->query("
        SELECT total_assists as assists, name 
        FROM players 
        WHERE total_matches > 0
        ORDER BY total_assists DESC 
        LIMIT 1
    ");
    $topAssist = $stmt->fetch();
}

// Recent matches
$stmt = $pdo->prepare("
    SELECT dm.*, 
           COUNT(mp.id) as total_players
    FROM daily_matches dm 
    LEFT JOIN match_participants mp ON dm.id = mp.match_id
    WHERE dm.status = 'completed'
    GROUP BY dm.id
    ORDER BY dm.match_date DESC 
    LIMIT 5
");
$recentMatches = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏆 Bảng xếp hạng - Football League</title>
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
        .leaderboard-table {
            background: white;
            border-radius: 10px;
        }
        .rank-1 { background: linear-gradient(45deg, #ffd700, #ffed4e); }
        .rank-2 { background: linear-gradient(45deg, #c0c0c0, #e5e5e5); }
        .rank-3 { background: linear-gradient(45deg, #cd7f32, #d4a574); }
        .rank-medal {
            font-size: 1.2em;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .stats-card {
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            background: linear-gradient(45deg, #4CAF50, #45a049);
            color: white;
            margin-bottom: 20px;
        }
        .position-badge {
            font-size: 0.8em;
            padding: 4px 8px;
            border-radius: 12px;
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
        .period-toggle {
            border-radius: 25px;
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
                        <a class="nav-link" href="match_result.php">
                            <i class="fas fa-edit"></i> Quản lý kết quả
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="leaderboard.php">
                            <i class="fas fa-trophy"></i> Bảng xếp hạng
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="players.php">
                            <i class="fas fa-users"></i> Quản lý cầu thủ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="history.php">
                            <i class="fas fa-history"></i> Lịch sử trận đấu
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
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h1 class="mb-2">🏆 Bảng xếp hạng</h1>
                        <p class="lead mb-0">
                            <?= $period === 'month' ? 'Tháng ' . date('m/Y', strtotime($currentMonth . '-01')) : 'Tổng kết' ?>
                        </p>
                    </div>
                    <div class="col-md-6 text-end">
                        <a href="index.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-home"></i> Trang chủ
                        </a>
                        <a href="match_result.php" class="btn btn-outline-success">
                            <i class="fas fa-edit"></i> Nhập kết quả
                        </a>
                    </div>
                </div>
                
                <!-- Period and Month Selection -->
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="btn-group period-toggle" role="group">
                            <input type="radio" class="btn-check" name="period" id="period-month" value="month" <?= $period === 'month' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-primary" for="period-month">Theo tháng</label>
                            
                            <input type="radio" class="btn-check" name="period" id="period-all" value="all_time" <?= $period === 'all_time' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-primary" for="period-all">Tổng kết</label>
                        </div>
                    </div>
                    
                    <?php if ($period === 'month'): ?>
                        <div class="col-md-6">
                            <select id="monthSelect" class="form-select">
                                <?php foreach ($availableMonths as $month): ?>
                                    <option value="<?= $month ?>" <?= $month === $currentMonth ? 'selected' : '' ?>>
                                        Tháng <?= date('m/Y', strtotime($month . '-01')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Main Leaderboard -->
            <div class="col-lg-8">
                <!-- Top Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3><?= $monthStats['total_players'] ?? 0 ?></h3>
                            <small>Cầu thủ tham gia</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3><?= $monthStats['total_matches'] ?? 0 ?></h3>
                            <small>Lượt thi đấu</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3><?= $monthStats['total_goals'] ?? 0 ?></h3>
                            <small>Tổng bàn thắng</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3><?= number_format($monthStats['avg_points'] ?? 0, 1) ?></h3>
                            <small>Điểm TB/người</small>
                        </div>
                    </div>
                </div>

                <!-- Leaderboard Table -->
                <div class="card card-custom">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-medal"></i> 
                            Bảng xếp hạng <?= $period === 'month' ? 'tháng' : 'tổng kết' ?>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Hạng</th>
                                        <th>Cầu thủ</th>
                                        <th>Vị trí</th>
                                        <th>Trận</th>
                                        <th>Thắng</th>
                                        <th>Tỷ lệ thắng</th>
                                        <th>Bàn thắng</th>
                                        <th>Kiến tạo</th>
                                        <th>Điểm</th>
                                        <th>TB/trận</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leaderboard as $index => $player): ?>
                                        <tr class="<?= $index < 3 ? 'rank-' . ($index + 1) : '' ?>">
                                            <td>
                                                <div class="rank-medal <?= $index < 3 ? 'rank-' . ($index + 1) : 'bg-light' ?>">
                                                    <?php if ($index === 0): ?>
                                                        🥇
                                                    <?php elseif ($index === 1): ?>
                                                        🥈
                                                    <?php elseif ($index === 2): ?>
                                                        🥉
                                                    <?php else: ?>
                                                        <?= $index + 1 ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="player-avatar me-2">
                                                        <?= strtoupper(substr($player['name'], 0, 1)) ?>
                                                    </div>
                                                    <strong><?= htmlspecialchars($player['name']) ?></strong>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info position-badge">
                                                    <?= $player['main_position'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= $period === 'month' ? $player['matches_played'] : $player['total_matches'] ?>
                                            </td>
                                            <td>
                                                <?= $period === 'month' ? $player['wins'] : $player['total_wins'] ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $player['win_rate'] >= 60 ? 'success' : ($player['win_rate'] >= 40 ? 'warning' : 'danger') ?>">
                                                    <?= $player['win_rate'] ?>%
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success">
                                                    <?= $period === 'month' ? $player['goals'] : $player['total_goals'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?= $period === 'month' ? $player['assists'] : $player['total_assists'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong class="text-success">
                                                    <?= $period === 'month' ? $player['points'] : $player['total_points'] ?>
                                                </strong>
                                            </td>
                                            <td>
                                                <span class="text-muted">
                                                    <?= $player['avg_points'] ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Top Performers -->
                <div class="card card-custom mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-star"></i> Thành tích nổi bật
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if ($topScorer): ?>
                            <div class="mb-3">
                                <h6 class="text-success">⚽ Vua phá lưới</h6>
                                <div class="d-flex justify-content-between">
                                    <span><?= htmlspecialchars($topScorer['name']) ?></span>
                                    <strong><?= $topScorer['goals'] ?> bàn</strong>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($topAssist): ?>
                            <div class="mb-3">
                                <h6 class="text-primary">🎯 Vua kiến tạo</h6>
                                <div class="d-flex justify-content-between">
                                    <span><?= htmlspecialchars($topAssist['name']) ?></span>
                                    <strong><?= $topAssist['assists'] ?> kiến tạo</strong>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($leaderboard)): ?>
                            <div class="mb-3">
                                <h6 class="text-warning">👑 Vua điểm số</h6>
                                <div class="d-flex justify-content-between">
                                    <span><?= htmlspecialchars($leaderboard[0]['name']) ?></span>
                                    <strong>
                                        <?= $period === 'month' ? $leaderboard[0]['points'] : $leaderboard[0]['total_points'] ?> điểm
                                    </strong>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Matches -->
                <div class="card card-custom">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-history"></i> Kết quả gần đây
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($recentMatches as $match): ?>
                            <div class="mb-3 p-2 border rounded">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">
                                        <?= date('d/m', strtotime($match['match_date'])) ?>
                                    </span>
                                    <div class="text-center">
                                        <span class="badge bg-danger"><?= $match['team_a_score'] ?></span>
                                        -
                                        <span class="badge bg-primary"><?= $match['team_b_score'] ?></span>
                                    </div>
                                    <small class="text-muted">
                                        <?= $match['total_players'] ?> người
                                    </small>
                                </div>
                                <div class="text-center mt-1">
                                    <?php
                                    $winner = $match['team_a_score'] > $match['team_b_score'] ? 'Đội A thắng' : 
                                             ($match['team_b_score'] > $match['team_a_score'] ? 'Đội B thắng' : 'Hòa');
                                    ?>
                                    <small class="text-muted"><?= $winner ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <a href="match_result.php" class="btn btn-outline-primary btn-sm w-100">
                            <i class="fas fa-list"></i> Xem tất cả
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Period toggle
        document.querySelectorAll('input[name="period"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const period = this.value;
                const month = document.getElementById('monthSelect')?.value || '<?= $currentMonth ?>';
                
                if (period === 'month') {
                    window.location.href = `leaderboard.php?period=month&month=${month}`;
                } else {
                    window.location.href = `leaderboard.php?period=all_time`;
                }
            });
        });

        // Month selection
        document.getElementById('monthSelect')?.addEventListener('change', function() {
            const month = this.value;
            window.location.href = `leaderboard.php?period=month&month=${month}`;
        });

        // Auto-refresh every 5 minutes if viewing current month
        <?php if ($period === 'month' && $currentMonth === date('Y-m')): ?>
            setInterval(function() {
                location.reload();
            }, 300000); // 5 minutes
        <?php endif; ?>

        // Smooth scroll to top when changing filters
        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Add loading state when navigating
        document.querySelectorAll('a, input, select').forEach(element => {
            element.addEventListener('click', function() {
                if (this.href || this.form) {
                    document.body.style.opacity = '0.7';
                }
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Press 'M' for month view
            if (e.key === 'm' || e.key === 'M') {
                document.getElementById('period-month').checked = true;
                document.getElementById('period-month').dispatchEvent(new Event('change'));
            }
            
            // Press 'A' for all-time view
            if (e.key === 'a' || e.key === 'A') {
                document.getElementById('period-all').checked = true;
                document.getElementById('period-all').dispatchEvent(new Event('change'));
            }
            
            // Press 'H' to go home
            if (e.key === 'h' || e.key === 'H') {
                window.location.href = 'index.php';
            }
        });

        // Highlight player row on hover
        document.querySelectorAll('tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.02)';
                this.style.transition = 'transform 0.2s';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });

        // Add tooltips for badges
        document.querySelectorAll('.badge').forEach(badge => {
            badge.title = badge.textContent;
        });
    </script>
</body>
</html>