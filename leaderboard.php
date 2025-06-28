<?php
require_once 'config.php';

$pdo = DB::getInstance();
$currentMonth = $_GET['month'] ?? date('Y-m'); // Mặc định là tháng hiện tại
$period = $_GET['period'] ?? 'month'; // Mặc định là theo tháng

// Generate available months - all months in current year
$availableMonths = [];
$currentYear = date('Y');

// Add all 12 months of current year
for ($i = 1; $i <= 12; $i++) {
    $month = sprintf('%04d-%02d', $currentYear, $i);
    $availableMonths[] = $month;
}

// Sort months in descending order (newest first)
rsort($availableMonths);

// Get leaderboard data
if ($period === 'month') {
    // Monthly leaderboard: aggregate from match participants for the specific month
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.name, 
            p.main_position,
            COUNT(mp.id) as matches_played,
            SUM(CASE WHEN dm.team_a_score > dm.team_b_score AND mp.team = 'A' THEN 1 
                     WHEN dm.team_b_score > dm.team_a_score AND mp.team = 'B' THEN 1 
                     ELSE 0 END) as wins,
            SUM(mp.goals) as goals,
            SUM(mp.assists) as assists,
            SUM(mp.points_earned) as points,
            ROUND(SUM(mp.points_earned) / GREATEST(COUNT(mp.id), 1), 2) as avg_points,
            ROUND((SUM(CASE WHEN dm.team_a_score > dm.team_b_score AND mp.team = 'A' THEN 1 
                            WHEN dm.team_b_score > dm.team_a_score AND mp.team = 'B' THEN 1 
                            ELSE 0 END) / GREATEST(COUNT(mp.id), 1)) * 100, 1) as win_rate
        FROM players p 
        JOIN match_participants mp ON p.id = mp.player_id 
        JOIN daily_matches dm ON mp.match_id = dm.id 
        WHERE DATE_FORMAT(dm.match_date, '%Y-%m') = ? 
          AND dm.status = 'completed'
        GROUP BY p.id, p.name, p.main_position
        HAVING matches_played > 0
        ORDER BY points DESC, wins DESC, goals DESC
    ");
    $stmt->execute([$currentMonth]);
    $leaderboard = $stmt->fetchAll();
    
    // Get month stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT mp.player_id) as total_players,
            COUNT(mp.id) as total_matches,
            SUM(mp.goals) as total_goals,
            SUM(mp.assists) as total_assists,
            AVG(mp.points_earned) as avg_points
        FROM match_participants mp
        JOIN daily_matches dm ON mp.match_id = dm.id
        WHERE DATE_FORMAT(dm.match_date, '%Y-%m') = ?
          AND dm.status = 'completed'
    ");
    $stmt->execute([$currentMonth]);
    $monthStats = $stmt->fetch();
    
} else {
    // All-time leaderboard from players table
    $stmt = $pdo->query("
        SELECT 
            id,
            name,
            main_position,
            total_matches as matches_played,
            total_wins as wins,
            total_goals as goals,
            total_assists as assists,
            total_points as points,
            ROUND(total_points / GREATEST(total_matches, 1), 2) as avg_points,
            ROUND((total_wins / GREATEST(total_matches, 1)) * 100, 1) as win_rate
        FROM players 
        WHERE total_matches > 0
        ORDER BY total_points DESC, total_wins DESC, total_goals DESC
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
    // Top goal scorer for the month
    $stmt = $pdo->prepare("
        SELECT SUM(mp.goals) as goals, p.name 
        FROM match_participants mp 
        JOIN players p ON mp.player_id = p.id 
        JOIN daily_matches dm ON mp.match_id = dm.id
        WHERE DATE_FORMAT(dm.match_date, '%Y-%m') = ?
          AND dm.status = 'completed'
        GROUP BY p.id, p.name
        ORDER BY goals DESC 
        LIMIT 1
    ");
    $stmt->execute([$currentMonth]);
    $topScorer = $stmt->fetch();
    
    // Top assist maker for the month
    $stmt = $pdo->prepare("
        SELECT SUM(mp.assists) as assists, p.name 
        FROM match_participants mp 
        JOIN players p ON mp.player_id = p.id 
        JOIN daily_matches dm ON mp.match_id = dm.id
        WHERE DATE_FORMAT(dm.match_date, '%Y-%m') = ?
          AND dm.status = 'completed'
        GROUP BY p.id, p.name
        ORDER BY assists DESC 
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

// Recent matches for this month (if monthly view) or overall
$matchesWhere = $period === 'month' ? 
    "WHERE dm.status = 'completed' AND DATE_FORMAT(dm.match_date, '%Y-%m') = ?" : 
    "WHERE dm.status = 'completed'";

$stmt = $pdo->prepare("
    SELECT dm.*, 
           COUNT(mp.id) as total_players
    FROM daily_matches dm 
    LEFT JOIN match_participants mp ON dm.id = mp.match_id
    $matchesWhere
    GROUP BY dm.id
    ORDER BY dm.match_date DESC 
    LIMIT 5
");

if ($period === 'month') {
    $stmt->execute([$currentMonth]);
} else {
    $stmt->execute();
}
$recentMatches = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏆 Bảng xếp hạng - FC Gà Gáy</title>
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
        .month-info {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 10px 15px;
            color: white;
            margin-bottom: 20px;
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
                            <?php 
                            if ($period === 'month') {
                                $monthTimestamp = strtotime($currentMonth . '-01');
                                if ($monthTimestamp === false) {
                                    echo 'Tháng ' . date('m/Y');
                                } else {
                                    echo 'Tháng ' . date('m/Y', $monthTimestamp);
                                }
                            } else {
                                echo 'Tổng kết';
                            }
                            ?>
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
                
                <!-- Month Info -->
                <?php if ($period === 'month'): ?>
                    <div class="month-info">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <small>Tháng hiện tại:</small>
                                <div><strong><?= date('m/Y', strtotime($currentMonth . '-01')) ?></strong></div>
                            </div>
                            <div class="col-md-3">
                                <small>Cầu thủ tham gia:</small>
                                <div><strong><?= $monthStats['total_players'] ?? 0 ?></strong></div>
                            </div>
                            <div class="col-md-3">
                                <small>Tổng trận đấu:</small>
                                <div><strong><?= $monthStats['total_matches'] ?? 0 ?></strong></div>
                            </div>
                            <div class="col-md-3">
                                <small>Bàn thắng:</small>
                                <div><strong><?= $monthStats['total_goals'] ?? 0 ?></strong></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
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
                            Bảng xếp hạng <?php 
                            if ($period === 'month') {
                                $monthTimestamp = strtotime($currentMonth . '-01');
                                if ($monthTimestamp !== false) {
                                    echo 'tháng ' . date('m/Y', $monthTimestamp);
                                } else {
                                    echo 'tháng ' . date('m/Y');
                                }
                            } else {
                                echo 'tổng kết';
                            }
                            ?>
                        </h5>
                        <?php if (empty($leaderboard)): ?>
                            <small class="text-muted">(Chưa có dữ liệu cho tháng này)</small>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($leaderboard)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-trophy fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Chưa có dữ liệu xếp hạng</h5>
                                <p class="text-muted">
                                    <?= $period === 'month' ? 'Chưa có trận đấu nào được hoàn thành trong tháng này' : 'Chưa có cầu thủ nào thi đấu' ?>
                                </p>
                            </div>
                        <?php else: ?>
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
                                                        <?= formatPosition($player['main_position']) ?>
                                                    </span>
                                                </td>
                                                <td><?= $player['matches_played'] ?></td>
                                                <td><?= $player['wins'] ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $player['win_rate'] >= 60 ? 'success' : ($player['win_rate'] >= 40 ? 'warning' : 'danger') ?>">
                                                        <?= $player['win_rate'] ?>%
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success">
                                                        <?= $player['goals'] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary">
                                                        <?= $player['assists'] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong class="text-success">
                                                        <?= $player['points'] ?>
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
                        <?php endif; ?>
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
                        <?php if ($topScorer && $topScorer['goals'] > 0): ?>
                            <div class="mb-3">
                                <h6 class="text-success">⚽ Vua phá lưới</h6>
                                <div class="d-flex justify-content-between">
                                    <span><?= htmlspecialchars($topScorer['name']) ?></span>
                                    <strong><?= $topScorer['goals'] ?> bàn</strong>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($topAssist && $topAssist['assists'] > 0): ?>
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
                                    <strong><?= $leaderboard[0]['points'] ?> điểm</strong>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (empty($topScorer) && empty($topAssist) && empty($leaderboard)): ?>
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-info-circle mb-2"></i>
                                <p class="mb-0">Chưa có dữ liệu thành tích</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Matches -->
                <div class="card card-custom">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-history"></i> 
                            <?= $period === 'month' ? 'Kết quả tháng này' : 'Kết quả gần đây' ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recentMatches)): ?>
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
                        <?php else: ?>
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-calendar-times fa-2x mb-2"></i>
                                <p class="mb-0">
                                    <?= $period === 'month' ? 'Chưa có trận đấu nào trong tháng này' : 'Chưa có trận đấu nào' ?>
                                </p>
                            </div>
                        <?php endif; ?>
                        
                        <a href="history.php" class="btn btn-outline-primary btn-sm w-100 mt-2">
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
                if (document.visibilityState === 'visible') {
                    location.reload();
                }
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
            
            // Arrow keys to navigate months
            if (e.key === 'ArrowLeft' && document.getElementById('monthSelect')) {
                const select = document.getElementById('monthSelect');
                if (select.selectedIndex > 0) {
                    select.selectedIndex--;
                    select.dispatchEvent(new Event('change'));
                }
            }
            if (e.key === 'ArrowRight' && document.getElementById('monthSelect')) {
                const select = document.getElementById('monthSelect');
                if (select.selectedIndex < select.options.length - 1) {
                    select.selectedIndex++;
                    select.dispatchEvent(new Event('change'));
                }
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

        // Show info for months without data (không có nút "Xem tháng hiện tại")
        document.addEventListener('DOMContentLoaded', function() {
            const currentMonth = '<?= date('Y-m') ?>';
            const selectedMonth = '<?= $currentMonth ?>';
            
            // Check if selected month has data
            const hasData = <?= !empty($leaderboard) ? 'true' : 'false' ?>;
            
            if (!hasData) {
                // Show info message for months without data
                const infoDiv = document.createElement('div');
                infoDiv.className = 'alert alert-info mt-3';
                infoDiv.innerHTML = `
                    <i class="fas fa-info-circle"></i> 
                    Tháng ${selectedMonth.split('-')[1]}/${selectedMonth.split('-')[0]} chưa có dữ liệu trận đấu hoàn thành.
                `;
                
                const cardBody = document.querySelector('.month-info');
                if (cardBody) {
                    cardBody.appendChild(infoDiv);
                }
            }
        });

        // Export functionality
        function exportLeaderboard() {
            const leaderboard = <?= json_encode($leaderboard) ?>;
            const period = '<?= $period ?>';
            const month = '<?= $currentMonth ?>';
            
            let csv = '\uFEFFHạng,Tên,Vị trí,Trận,Thắng,Tỷ lệ thắng (%),Bàn thắng,Kiến tạo,Điểm,TB/trận\n';
            
            leaderboard.forEach((player, index) => {
                csv += `${index + 1},"${player.name}","${player.main_position}",${player.matches_played},${player.wins},${player.win_rate},${player.goals},${player.assists},${player.points},${player.avg_points}\n`;
            });

            const filename = period === 'month' ? 
                `bang_xep_hang_thang_${month.replace('-', '_')}.csv` : 
                `bang_xep_hang_tong_ket.csv`;

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Add export button if there's data
        <?php if (!empty($leaderboard)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const cardHeader = document.querySelector('.card-custom .card-header h5');
            if (cardHeader) {
                const exportBtn = document.createElement('button');
                exportBtn.className = 'btn btn-sm btn-outline-info ms-2';
                exportBtn.innerHTML = '<i class="fas fa-download"></i> Xuất Excel';
                exportBtn.onclick = exportLeaderboard;
                cardHeader.parentElement.appendChild(exportBtn);
            }
        });
        <?php endif; ?>

        // Performance monitoring
        window.addEventListener('load', function() {
            const loadTime = performance.now();
            console.log(`Leaderboard loaded in ${Math.round(loadTime)}ms`);
        });

        // Mobile swipe navigation for months
        let startX = 0;
        let startY = 0;

        document.addEventListener('touchstart', function(e) {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
        }, { passive: true });

        document.addEventListener('touchend', function(e) {
            if (!startX || !startY) return;
            
            const endX = e.changedTouches[0].clientX;
            const endY = e.changedTouches[0].clientY;
            
            const diffX = startX - endX;
            const diffY = startY - endY;
            
            // Only handle horizontal swipes
            if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
                const monthSelect = document.getElementById('monthSelect');
                if (monthSelect && '<?= $period ?>' === 'month') {
                    if (diffX > 0 && monthSelect.selectedIndex < monthSelect.options.length - 1) {
                        // Swipe left - next month
                        monthSelect.selectedIndex++;
                        monthSelect.dispatchEvent(new Event('change'));
                    } else if (diffX < 0 && monthSelect.selectedIndex > 0) {
                        // Swipe right - previous month  
                        monthSelect.selectedIndex--;
                        monthSelect.dispatchEvent(new Event('change'));
                    }
                }
            }
            
            startX = 0;
            startY = 0;
        }, { passive: true });
    </script>
</body>
</html>