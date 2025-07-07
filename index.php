<?php
require_once 'config.php';

$pdo = DB::getInstance();

// Get latest match
$stmt = $pdo->prepare("
    SELECT dm.*, 
           COUNT(mp.id) as total_players
    FROM daily_matches dm 
    LEFT JOIN match_participants mp ON dm.id = mp.match_id
    GROUP BY dm.id
    ORDER BY dm.match_date DESC 
    LIMIT 1
");
$latestMatch = $stmt->fetch();

// Get total statistics
$currentYear = date('Y');

// Basic stats
$stmt = $pdo->query("
    SELECT 
        COUNT(mp.player_id) as total_players,
        SUM(mp.goals) as total_goals,
        SUM(mp.assists) as total_assists,
        COUNT(CASE WHEN total_matches > 0 THEN 1 END) as active_players
    FROM players p
        LEFT JOIN match_participants mp ON p.id = mp.player_id 
        LEFT JOIN daily_matches dm ON mp.match_id = dm.id 
    Where dm.status = 'completed'
");
$basicStats = $stmt->fetch();

// Matches this year
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_players
    FROM players p
");
$stmt->execute();
$playerOfTeam = $stmt->fetchColumn();

// Matches this year
$stmt = $pdo->prepare("
    SELECT COUNT(*) as matches_this_year
    FROM daily_matches 
    WHERE YEAR(match_date) = ? AND status = 'completed'
");
$stmt->execute([$currentYear]);
$yearMatches = $stmt->fetchColumn();

// Most goals in a single match
$stmt = $pdo->query("
    SELECT MAX(goals) as highest_goals
    FROM match_participants
    WHERE goals > 0
");
$highestGoals = $stmt->fetchColumn() ?: 0;

// Most active player (most matches this year)
$stmt = $pdo->prepare("
    SELECT p.name, COUNT(mp.id) as matches_count
    FROM players p
    JOIN match_participants mp ON p.id = mp.player_id
    JOIN daily_matches dm ON mp.match_id = dm.id
    WHERE YEAR(dm.match_date) = ? AND dm.status = 'completed'
    GROUP BY p.id, p.name
    ORDER BY matches_count DESC, p.name
    LIMIT 1
");
$stmt->execute([$currentYear]);
$mostActivePlayer = $stmt->fetch();

// Average goals per match this year
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(AVG(team_a_score + team_b_score), 0) as avg_goals_per_match
    FROM daily_matches 
    WHERE YEAR(match_date) = ? AND status = 'completed'
");
$stmt->execute([$currentYear]);
$avgGoalsPerMatch = $stmt->fetchColumn();

$clubStats = [
    'total_players' => $playerOfTeam,
    'matches_this_year' => $yearMatches,
    'total_goals' => $basicStats['total_goals'],
    'total_assists' => $basicStats['total_assists'],
    'highest_goals' => $highestGoals,
    'most_active_player' => $mostActivePlayer ? $mostActivePlayer['name'] : 'Chưa có',
    'most_active_matches' => $mostActivePlayer ? $mostActivePlayer['matches_count'] : 0,
    'avg_goals_per_match' => round($avgGoalsPerMatch, 1)
];

// Get recent completed matches
$stmt = $pdo->prepare("
    SELECT dm.*, 
           COUNT(mp.id) as total_players
    FROM daily_matches dm 
    LEFT JOIN match_participants mp ON dm.id = mp.match_id
    WHERE dm.status = 'completed'
    GROUP BY dm.id
    ORDER BY dm.match_date DESC 
    LIMIT 3
");
$recentMatches = $stmt->fetchAll();

// Get top players
$stmt = $pdo->query("
    SELECT p.name,
        SUM(mp.points_earned) total_points, 
        SUM(mp.goals) total_goals, 
        COUNT(mp.id) total_matches, 
        p.main_position
    FROM players p
        LEFT JOIN match_participants mp ON p.id = mp.player_id 
        LEFT JOIN daily_matches dm ON mp.match_id = dm.id 
    WHERE 1=1 
        AND dm.status = 'completed'
        AND p.total_matches > 0
    GROUP BY p.name, p.main_position
    ORDER BY total_points DESC, total_goals DESC
    LIMIT 6
");
$topPlayers = $stmt->fetchAll();

// Check today's registration
$currentDate = getCurrentDate();
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM daily_registrations 
    WHERE registration_date = ?
");
$stmt->execute([$currentDate]);
$todayRegistrations = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚽ FC Gà Gáy - Football League</title>
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
        .stats-card {
            background: linear-gradient(45deg, #4CAF50, #45a049);
            color: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .team-photo-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            margin-bottom: 30px;
        }
        .team-photo {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .hero-section {
            padding: 40px 0;
        }
        .club-title {
            color: white;
            font-size: 3rem;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            margin-bottom: 20px;
        }
        .club-subtitle {
            color: #e3f2fd;
            font-size: 1.3rem;
            margin-bottom: 30px;
        }
        .quick-actions {
            margin-top: 30px;
        }
        .btn-primary-custom {
            background: linear-gradient(45deg, #ff6b35, #f7931e);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
            margin: 5px;
        }
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 53, 0.4);
            color: white;
        }
        .btn-outline-custom {
            border: 2px solid white;
            color: white;
            background: transparent;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
            margin: 5px;
        }
        .btn-outline-custom:hover {
            background: white;
            color: #667eea;
            transform: translateY(-2px);
        }
        .recent-match-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .recent-match-card:hover {
            transform: translateY(-3px);
        }
        .match-score {
            font-size: 1.5rem;
            font-weight: bold;
            text-align: center;
        }
        .team-score-a { color: #dc3545; }
        .team-score-b { color: #007bff; }
        .player-item {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            color: white;
            transition: background 0.3s ease;
        }
        .player-item:hover {
            background: rgba(255,255,255,0.2);
        }
        .player-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, #ff6b35, #f7931e);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 15px;
        }
        .navbar-brand {
            font-weight: bold;
        }
        .nav-link {
            font-weight: 500;
        }
        .gallery-item {
            position: relative;
            overflow: hidden;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        .gallery-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        .gallery-img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .gallery-item:hover .gallery-img {
            transform: scale(1.1);
        }
        .gallery-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            color: white;
            font-size: 1.5rem;
        }
        .gallery-item:hover .gallery-overlay {
            opacity: 1;
        }
        .modal-body img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
        }
        /* Quick Actions Section */
        .quick-actions-section {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            padding: 30px;
            margin-top: 20px;
        }
        @media (max-width: 768px) {
            .club-title {
                font-size: 2.2rem;
            }
            .club-subtitle {
                font-size: 1.1rem;
            }
            .btn-primary-custom, .btn-outline-custom {
                display: block;
                width: 100%;
                max-width: 250px;
                margin: 10px auto;
            }
            .gallery-img {
                height: 100px;
            }
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
                        <a class="nav-link" href="players.php">
                            <i class="fas fa-users"></i> Quản lý cầu thủ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="history.php">
                            <i class="fas fa-history"></i> Lịch sử
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
        <!-- Hero Section -->
        <div class="hero-section text-center">
            <h1 class="club-title">⚽ FC Gà Gáy</h1>
            <p class="club-subtitle">Giao Hữu Nội Bộ - Đam Mê Bóng Đá</p>
            
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <div class="team-photo-container">
                        <img src="images/team-main.jpg" 
                             alt="Đội bóng FC Gà Gáy" 
                             class="team-photo"
                             onerror="this.src='data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 600 400\'><rect width=\'600\' height=\'400\' fill=\'%23f8f9fa\'/><text x=\'300\' y=\'180\' text-anchor=\'middle\' font-size=\'18\' fill=\'%236c757d\'>Ảnh đội bóng FC Gà Gáy</text><text x=\'300\' y=\'220\' text-anchor=\'middle\' font-size=\'14\' fill=\'%236c757d\'>Giao Hữu Nội Bộ</text><rect x=\'20\' y=\'20\' width=\'560\' height=\'360\' fill=\'none\' stroke=\'%23dee2e6\' stroke-width=\'2\' rx=\'10\'/></svg>'">
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-users me-1"></i>
                                <?= $clubStats['total_players'] ?> cầu thủ |
                                <i class="fas fa-calendar me-1"></i>
                                <?= $clubStats['matches_this_year'] ?> trận năm <?= $currentYear ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="quick-actions">
                <a href="register.php" class="btn btn-primary-custom btn-lg">
                    <i class="fas fa-user-plus me-2"></i>Đăng ký thi đấu
                </a>
                <a href="leaderboard.php" class="btn btn-outline-custom btn-lg">
                    <i class="fas fa-trophy me-2"></i>Xem bảng xếp hạng
                </a>
            </div>
            
            <?php if ($todayRegistrations > 0): ?>
                <div class="alert alert-success mt-4 d-inline-block">
                    <i class="fas fa-users"></i>
                    Hôm nay đã có <strong><?= $todayRegistrations ?></strong> người đăng ký
                </div>
            <?php endif; ?>
        </div>

        <div class="row">
            <!-- Left Column: Stats & Latest Match -->
            <div class="col-lg-4">
                <!-- Statistics -->
                <div class="card card-custom mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>Thống kê câu lạc bộ
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="row g-0">
                            <div class="col-6">
                                <div class="stats-card">
                                    <div class="h3"><?= $clubStats['total_players'] ?></div>
                                    <small>Tổng cầu thủ</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stats-card">
                                    <div class="h3"><?= $clubStats['matches_this_year'] ?></div>
                                    <small>Trận năm <?= $currentYear ?></small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stats-card">
                                    <div class="h3"><?= $clubStats['total_goals'] ?></div>
                                    <small>Tổng bàn thắng</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stats-card">
                                    <div class="h3"><?= $clubStats['avg_goals_per_match'] ?></div>
                                    <small>TB bàn/trận</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stats-card">
                                    <div class="h3"><?= $clubStats['highest_goals'] ?></div>
                                    <small>Nhiều bàn nhất</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stats-card">
                                    <div class="h3"><?= $clubStats['total_assists'] ?></div>
                                    <small>Tổng kiến tạo</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Most Active Player -->
                        <?php if ($clubStats['most_active_player'] !== 'Chưa có'): ?>
                            <div class="alert alert-info mx-3 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-fire text-warning fa-2x me-3"></i>
                                    <div>
                                        <strong>Cầu thủ tích cực nhất năm <?= $currentYear ?>:</strong><br>
                                        <span class="text-primary fw-bold"><?= htmlspecialchars($clubStats['most_active_player']) ?></span>
                                        <span class="badge bg-success ms-2"><?= $clubStats['most_active_matches'] ?> trận</span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Latest Match -->
                <?php if ($latestMatch): ?>
                    <div class="card card-custom mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-calendar-day me-2"></i>Trận đấu gần nhất
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <h6><?= date('d/m/Y', strtotime($latestMatch['match_date'])) ?></h6>
                                <?php if ($latestMatch['status'] === 'completed'): ?>
                                    <div class="match-score mb-2">
                                        <span class="team-score-a"><?= $latestMatch['team_a_score'] ?></span>
                                        <span class="text-muted mx-2">-</span>
                                        <span class="team-score-b"><?= $latestMatch['team_b_score'] ?></span>
                                    </div>
                                    <small class="text-muted">
                                        <?php
                                        $winner = $latestMatch['team_a_score'] > $latestMatch['team_b_score'] ? 'Đội A thắng' : 
                                                 ($latestMatch['team_b_score'] > $latestMatch['team_a_score'] ? 'Đội B thắng' : 'Hòa');
                                        echo $winner;
                                        ?>
                                    </small>
                                <?php else: ?>
                                    <div class="text-muted">
                                        <i class="fas fa-clock"></i> 
                                        <?= $latestMatch['status'] === 'scheduled' ? 'Đã lên lịch' : 'Chưa hoàn thành' ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="text-center">
                                <span class="badge bg-primary">
                                    <?= $latestMatch['total_players'] ?> cầu thủ tham gia
                                </span>
                            </div>
                            <div class="text-center mt-3">
                                <a href="match_result.php?id=<?= $latestMatch['id'] ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye"></i> Chi tiết
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Middle Column: Recent Matches -->
            <div class="col-lg-4">
                <div class="card card-custom mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>Kết quả gần đây
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recentMatches)): ?>
                            <?php foreach ($recentMatches as $match): ?>
                                <div class="recent-match-card">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <strong><?= date('d/m', strtotime($match['match_date'])) ?></strong>
                                        <span class="badge bg-success">Hoàn thành</span>
                                    </div>
                                    <div class="match-score mb-2">
                                        <span class="team-score-a"><?= $match['team_a_score'] ?></span>
                                        <span class="text-muted mx-2">-</span>
                                        <span class="team-score-b"><?= $match['team_b_score'] ?></span>
                                    </div>
                                    <div class="text-center">
                                        <small class="text-muted">
                                            <?= $match['total_players'] ?> cầu thủ tham gia
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-calendar-times fa-2x mb-2"></i>
                                <p>Chưa có trận đấu nào hoàn thành</p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-center mt-3">
                            <a href="history.php" class="btn btn-outline-primary">
                                <i class="fas fa-list"></i> Xem tất cả
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Top Players -->
            <div class="col-lg-4">
                <div class="card card-custom" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                    <div class="card-header border-0" style="background: transparent;">
                        <h5 class="mb-0 text-white">
                            <i class="fas fa-star me-2"></i>Cầu thủ xuất sắc
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($topPlayers)): ?>
                            <?php foreach (array_slice($topPlayers, 0, 6) as $index => $player): ?>
                                <div class="player-item">
                                    <div class="d-flex align-items-center">
                                        <div class="player-avatar">
                                            <?= strtoupper(substr($player['name'], 0, 2)) ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold">
                                                <?php if ($index < 3): ?>
                                                    <?= $index === 0 ? '🥇' : ($index === 1 ? '🥈' : '🥉') ?>
                                                <?php endif; ?>
                                                <?= htmlspecialchars($player['name']) ?>
                                            </div>
                                            <small class="opacity-75">
                                                <?= formatPosition($player['main_position']) ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold"><?= $player['total_points'] ?></div>
                                            <small class="opacity-75">điểm</small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-white-50 py-4">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <p>Chưa có dữ liệu cầu thủ</p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-center mt-3">
                            <a href="leaderboard.php" class="btn btn-outline-light">
                                <i class="fas fa-trophy"></i> Bảng xếp hạng
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Photo Gallery Showcase -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card card-custom">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-camera me-2"></i>Khoảnh khắc đáng nhớ
                        </h5>
                        <button class="btn btn-outline-primary btn-sm" onclick="showAllPhotos()">
                            <i class="fas fa-images"></i> Xem tất cả
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row g-2" id="photoGallery">
                            <?php
                            // Get list of images from images folder
                            $imageDir = 'images/';
                            $images = [];
                            
                            // Check if images directory exists
                            if (is_dir($imageDir)) {
                                $imageFiles = glob($imageDir . "*.{jpg,jpeg,png,gif,webp}", GLOB_BRACE);
                                
                                // Filter out main team photo and limit to 6 for homepage
                                foreach ($imageFiles as $image) {
                                    $filename = basename($image);
                                    if ($filename !== 'team-main.jpg' && count($images) < 6) {
                                        $images[] = $image;
                                    }
                                }
                            }
                            
                            // If no images found, show placeholder
                            if (empty($images)) {
                                for ($i = 1; $i <= 6; $i++) {
                                    echo '<div class="col-lg-2 col-md-3 col-4">
                                        <div class="gallery-item">
                                            <img src="data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 300 200\'><rect width=\'300\' height=\'200\' fill=\'%23f8f9fa\'/><text x=\'150\' y=\'90\' text-anchor=\'middle\' font-size=\'14\' fill=\'%236c757d\'>Ảnh ' . $i . '</text><text x=\'150\' y=\'110\' text-anchor=\'middle\' font-size=\'12\' fill=\'%236c757d\'>FC Gà Gáy</text><rect x=\'10\' y=\'10\' width=\'280\' height=\'180\' fill=\'none\' stroke=\'%23dee2e6\' stroke-width=\'2\' rx=\'10\'/></svg>" 
                                                 alt="Khoảnh khắc FC Gà Gáy" 
                                                 class="gallery-img">
                                            <div class="gallery-overlay">
                                                <i class="fas fa-search-plus"></i>
                                            </div>
                                        </div>
                                    </div>';
                                }
                            } else {
                                // Display actual images
                                foreach ($images as $image) {
                                    $filename = basename($image);
                                    $alt = "Khoảnh khắc FC Gà Gáy - " . pathinfo($filename, PATHINFO_FILENAME);
                                    
                                    echo '<div class="col-lg-2 col-md-3 col-4">
                                        <div class="gallery-item" onclick="viewImage(\'' . $image . '\', \'' . htmlspecialchars($alt) . '\')">
                                            <img src="' . $image . '" 
                                                 alt="' . htmlspecialchars($alt) . '" 
                                                 class="gallery-img"
                                                 loading="lazy">
                                            <div class="gallery-overlay">
                                                <i class="fas fa-search-plus"></i>
                                            </div>
                                        </div>
                                    </div>';
                                }
                            }
                            ?>
                        </div>
                        
                        <?php if (count($images) >= 6): ?>
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i>
                                    Hiển thị 6 ảnh gần đây nhất. Click "Xem tất cả" để xem thêm.
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="quick-actions-section">
                    <h5 class="text-center mb-4">Truy cập nhanh</h5>
                    <div class="row">
                        <div class="col-md-3 col-6 mb-3">
                            <a href="register.php" class="btn btn-outline-success w-100">
                                <i class="fas fa-user-plus fa-2x d-block mb-2"></i>
                                Đăng ký thi đấu
                            </a>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <a href="match_result.php" class="btn btn-outline-warning w-100">
                                <i class="fas fa-edit fa-2x d-block mb-2"></i>
                                Nhập kết quả
                            </a>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <a href="leaderboard.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-trophy fa-2x d-block mb-2"></i>
                                Bảng xếp hạng
                            </a>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <a href="players.php" class="btn btn-outline-info w-100">
                                <i class="fas fa-users fa-2x d-block mb-2"></i>
                                Quản lý cầu thủ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Photo Viewer Modal -->
    <div class="modal fade" id="photoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="photoModalTitle">Khoảnh khắc FC Gà Gáy</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="photoModalImage" src="" alt="" class="img-fluid">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="button" class="btn btn-primary" onclick="downloadImage()">
                        <i class="fas fa-download"></i> Tải về
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- All Photos Modal -->
    <div class="modal fade" id="allPhotosModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-images me-2"></i>Thư viện ảnh FC Gà Gáy
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3" id="allPhotosGrid">
                        <!-- Photos will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple animations
        document.addEventListener('DOMContentLoaded', function() {
            // Fade in cards
            const cards = document.querySelectorAll('.card-custom, .recent-match-card, .player-item');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'all 0.5s ease';
                
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Auto-refresh registration count
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'get_today_registrations'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.count !== undefined) {
                        const alertElement = document.querySelector('.alert-success');
                        if (data.count > 0) {
                            if (alertElement) {
                                alertElement.innerHTML = `
                                    <i class="fas fa-users"></i>
                                    Hôm nay đã có <strong>${data.count}</strong> người đăng ký
                                `;
                            }
                        } else if (alertElement) {
                            alertElement.style.display = 'none';
                        }
                    }
                })
                .catch(error => console.log('Auto-refresh error:', error));
            }
        }, 30000);

        // View single image
        function viewImage(src, alt) {
            const modal = new bootstrap.Modal(document.getElementById('photoModal'));
            document.getElementById('photoModalImage').src = src;
            document.getElementById('photoModalImage').alt = alt;
            document.getElementById('photoModalTitle').textContent = alt;
            modal.show();
        }

        // Show all photos
        function showAllPhotos() {
            const modal = new bootstrap.Modal(document.getElementById('allPhotosModal'));
            const grid = document.getElementById('allPhotosGrid');
            
            // Show loading
            grid.innerHTML = '<div class="col-12 text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Đang tải ảnh...</p></div>';
            
            modal.show();
            
            // Load all photos
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_all_photos'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.photos && data.photos.length > 0) {
                    let html = '';
                    data.photos.forEach(photo => {
                        html += `
                            <div class="col-lg-3 col-md-4 col-6">
                                <div class="gallery-item" onclick="viewImage('${photo.src}', '${photo.alt}')">
                                    <img src="${photo.src}" alt="${photo.alt}" class="gallery-img" loading="lazy">
                                    <div class="gallery-overlay">
                                        <i class="fas fa-search-plus"></i>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    grid.innerHTML = html;
                } else {
                    grid.innerHTML = '<div class="col-12 text-center text-muted"><i class="fas fa-images fa-3x mb-3"></i><p>Chưa có ảnh nào được tải lên</p></div>';
                }
            })
            .catch(error => {
                console.error('Error loading photos:', error);
                grid.innerHTML = '<div class="col-12 text-center text-danger"><i class="fas fa-exclamation-triangle fa-3x mb-3"></i><p>Lỗi khi tải ảnh</p></div>';
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                window.location.href = 'register.php';
            }
            if ((e.ctrlKey || e.metaKey) && e.key === 'l') {
                e.preventDefault();
                window.location.href = 'leaderboard.php';
            }
            if ((e.ctrlKey || e.metaKey) && e.key === 'g') {
                e.preventDefault();
                showAllPhotos();
            }
        });

        // Lazy loading for images
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.classList.add('loaded');
                        imageObserver.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('.gallery-img').forEach(img => {
                imageObserver.observe(img);
            });
        }
    </script>
</body>
</html>