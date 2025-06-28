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

// Debug: Log team data
error_log("Team A count: " . count($teamA));
error_log("Team B count: " . count($teamB));
error_log("Total participants: " . count($participants));

// Get unregistered players for adding to teams
$stmt = $pdo->prepare("
    SELECT p.* FROM players p 
    WHERE p.id NOT IN (
        SELECT mp.player_id FROM match_participants mp WHERE mp.match_id = ?
    )
    ORDER BY p.name
");
$stmt->execute([$matchId]);
$unregisteredPlayers = $stmt->fetchAll();

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

// Lấy status hiện tại từ DB
$current_status = '';
$result = $pdo->prepare("SELECT status FROM daily_matches WHERE id = ?");
$result->execute([$matchId]);
if ($row = $result->fetch()) {
    $current_status = $row["status"];
}
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
            position: relative;
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
            position: relative;
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
        .edit-mode .remove-btn {
            display: inline-block !important;
        }
        .edit-mode .add-player-section {
            display: block !important;
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
        
        /* New styles for add/remove features */
        .remove-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            padding: 0;
            font-size: 12px;
            display: none;
            z-index: 10;
        }
        .add-player-section {
            display: none;
            border: 2px dashed #28a745;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            background: #f8fff9;
        }
        .player-select-dropdown {
            max-height: 200px;
            overflow-y: auto;
        }
        .add-player-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            transition: all 0.2s;
        }
        .add-player-item:hover {
            background-color: #f0f0f0;
            transform: translateX(3px);
        }
        .add-player-item:last-child {
            border-bottom: none;
        }
        
        /* Animations */
        @keyframes swapAnimation {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); background-color: #e3f2fd; }
            100% { transform: scale(1); }
        }
        @keyframes fadeOutScale {
            0% { opacity: 1; transform: scale(1); }
            100% { opacity: 0; transform: scale(0.8); }
        }
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(20px); }
            100% { opacity: 1; transform: translateY(0); }
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
        .new-player {
            animation: fadeInUp 0.5s ease-out;
        }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }

        /* ===== CSS MẶC ĐỊNH COMPACT MODE VỚI TÊN TO RÕ RÀNG ===== */

/* Formation Preview - Always Compact with Large Names */
.formation-preview {
    background: linear-gradient(45deg, #1e3c72, #2a5298);
    border-radius: 15px;
    padding: 25px 15px 15px 15px;
    position: relative;
    overflow: hidden;
    min-height: 700px;
    max-height: 85vh;
    overflow-y: auto;
}

.formation-field {
    background: 
        radial-gradient(circle at center, rgba(255,255,255,0.1) 2px, transparent 2px),
        linear-gradient(90deg, rgba(255,255,255,0.1) 1px, transparent 1px),
        linear-gradient(0deg, rgba(255,255,255,0.1) 1px, transparent 1px);
    background-size: 20px 20px, 20px 20px, 20px 20px;
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 10px;
    position: relative;
    height: 100%;
    min-height: 600px;
    margin: 0 auto;
    max-width: 900px;
    display: flex;
}

.center-circle {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 50px;
    height: 50px;
    border: 2px solid rgba(255,255,255,0.5);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 5;
}

.center-circle::before {
    content: '⚽';
    font-size: 1rem;
}

.team-half {
    position: relative;
    width: 50%;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: center;
    padding: 35px 8px 15px 8px;
    box-sizing: border-box;
}

.team-half.team-a {
    background: linear-gradient(to right, rgba(220, 53, 69, 0.1), transparent);
    border-right: 1px solid rgba(255,255,255,0.2);
}

.team-half.team-b {
    background: linear-gradient(to left, rgba(0, 123, 255, 0.1), transparent);
    border-left: 1px solid rgba(255,255,255,0.2);
}

.team-title {
    position: absolute;
    top: 6px;
    font-weight: bold;
    color: white;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.7);
    z-index: 100;
    font-size: 0.95rem;
    left: 50%;
    transform: translateX(-50%);
    text-align: center;
}

.team-half.team-a .team-title {
    color: #ff6b6b;
}

.team-half.team-b .team-title {
    color: #4dabf7;
}

/* Position Lines - Compact Layout */
.position-line {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;
    gap: 8px; /* Compact spacing */
    width: 100%;
    margin-bottom: 10px; /* Compact vertical spacing */
    flex-wrap: wrap;
    min-height: 55px; /* Compact height */
}

/* Player Container - Compact but with Large Names */
.player-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    margin: 2px;
    transition: all 0.3s ease;
    flex: 0 0 auto;
    max-width: 85px; /* Slightly wider for names */
}

.player-container:hover {
    transform: scale(1.1);
    z-index: 15;
}

/* Compact Player Circle */
.formation-player {
    background: rgba(255, 255, 255, 0.95);
    border: 2px solid;
    border-radius: 50%;
    width: 38px; /* Compact size */
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.65rem;
    font-weight: bold;
    text-align: center;
    position: relative;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 3px 6px rgba(0,0,0,0.3);
    margin-bottom: 5px;
}

.formation-player.team-a {
    border-color: #dc3545;
    color: #dc3545;
}

.formation-player.team-b {
    border-color: #007bff;
    color: #007bff;
}

/* LARGE FULL NAME - ALWAYS VISIBLE AND CLEAR */
.player-full-name {
    color: white;
    font-size: 0.85rem !important; /* Large, clear font */
    font-weight: 700 !important; /* Bold for clarity */
    text-align: center;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.9); /* Strong shadow for readability */
    background: rgba(0,0,0,0.6); /* Darker background for contrast */
    padding: 4px 8px;
    border-radius: 12px;
    white-space: nowrap;
    max-width: 120px; /* Wide enough for full names */
    overflow: hidden;
    text-overflow: ellipsis;
    border: 1px solid rgba(255,255,255,0.3);
    transition: all 0.3s ease;
    line-height: 1.2;
    min-height: 20px; /* Ensure consistent height */
}

.team-a .player-full-name {
    background: rgba(220, 53, 69, 0.4); /* Stronger background */
    border-color: rgba(220, 53, 69, 0.6);
}

.team-b .player-full-name {
    background: rgba(0, 123, 255, 0.4); /* Stronger background */
    border-color: rgba(0, 123, 255, 0.6);
}

/* Compact Position Badge */
.player-position {
    position: absolute;
    top: -3px;
    right: -3px;
    background: rgba(0,0,0,0.9);
    color: white;
    border-radius: 50%;
    width: 15px;
    height: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.5rem;
    font-weight: bold;
    border: 1px solid rgba(255,255,255,0.3);
}

/* Position Colors - Same as before */
.goalkeeper { 
    background: #ffd93d !important; 
    color: #000 !important; 
}
.defender { 
    background: #6bcf7f !important; 
    color: #000 !important; 
}
.midfielder { 
    background: #4dabf7 !important; 
    color: #000 !important; 
}
.forward { 
    background: #ff6b6b !important; 
    color: #fff !important; 
}

.formation-info {
    position: absolute;
    top: 6px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0,0,0,0.8);
    color: white;
    padding: 6px 14px;
    border-radius: 12px;
    font-weight: bold;
    z-index: 100;
    font-size: 0.85rem;
}

.export-options {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 12px;
}

/* Enhanced hover effects for large names */
.player-container:hover .formation-player {
    transform: scale(1.15);
    box-shadow: 0 6px 12px rgba(0,0,0,0.4), 0 0 20px rgba(255,255,255,0.3);
}

.player-container:hover .player-full-name {
    transform: scale(1.08);
    box-shadow: 0 3px 10px rgba(0,0,0,0.6);
    background: rgba(255,255,255,0.15) !important;
    color: #fff !important;
    font-weight: 800 !important;
}

/* Responsive - Keep names large even on mobile */
@media (max-width: 768px) {
    .formation-preview {
        min-height: 550px;
        padding: 20px 8px 12px 8px;
    }
    
    .formation-field {
        min-height: 450px;
    }
    
    .team-half {
        padding: 30px 5px 12px 5px;
    }
    
    .formation-player {
        width: 35px;
        height: 35px;
        font-size: 0.6rem;
    }
    
    .player-full-name {
        font-size: 0.8rem !important; /* Still large on mobile */
        font-weight: 700 !important;
        max-width: 100px;
        padding: 3px 6px;
    }
    
    .position-line {
        gap: 6px;
        margin-bottom: 8px;
        min-height: 50px;
    }
}

@media (max-width: 576px) {
    .formation-preview {
        min-height: 480px;
        padding: 15px 6px 10px 6px;
    }
    
    .formation-field {
        min-height: 400px;
        max-width: 100%;
    }
    
    .formation-player {
        width: 32px;
        height: 32px;
        font-size: 0.55rem;
    }
    
    .player-full-name {
        font-size: 0.75rem !important; /* Large even on small screens */
        font-weight: 700 !important;
        max-width: 85px;
        padding: 2px 5px;
    }
    
    .position-line {
        gap: 5px;
        margin-bottom: 7px;
    }
    
    .center-circle {
        width: 45px;
        height: 45px;
    }
    
    .team-title {
        font-size: 0.85rem;
    }
}

/* Ensure names are always readable */
@media (max-width: 480px) {
    .player-full-name {
        font-size: 0.7rem !important; /* Still readable */
        font-weight: 700 !important;
        background: rgba(0,0,0,0.8) !important; /* Strong contrast */
        text-shadow: 1px 1px 3px rgba(0,0,0,0.9) !important;
    }
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
                        <a class="nav-link active" href="match_result.php">
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
                            <!-- NÚT MỚI - XUẤT ĐỘI HÌNH -->
                            <button class="btn btn-outline-info" onclick="showFormationPreview()" title="Xuất đội hình">
                                <i class="fas fa-download"></i> Xuất đội hình
                            </button>
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
                
                <!-- Cho phép admin mở trạng thái   -->
                <div hidden>
                    <label>Chọn trạng thái:</label>
                    <select name="statusNew" id="statusNew">
                        <option value="scheduled" <?= $current_status == 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                        <option value="locked" <?= $current_status == 'locked' ? 'selected' : '' ?>>Locked</option>
                        <option value="completed" <?= $current_status == 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                    <button onclick="updateStatus()" class="btn btn-outline-secondary btn-lg ms-2">Lưu trạng thái</button>
                </div>

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

                        <!-- Debug info (temporary) -->
                        <?php if (defined('TEST_MODE') && TEST_MODE): ?>
                            <div class="alert alert-info small">
                                <strong>Debug:</strong> Team A: <?= count($teamA) ?> players, Team B: <?= count($teamB) ?> players, Total: <?= count($participants) ?> participants
                            </div>
                        <?php endif; ?>

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
                                                
                                                <!-- Remove button (X) - only visible in edit mode -->
                                                <button class="btn btn-danger btn-sm remove-btn" 
                                                        onclick="removePlayerFromMatch(<?= $player['player_id'] ?>)"
                                                        title="Loại khỏi trận đấu">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                
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
                                    
                                    <!-- Add Player Section for Team A -->
                                    <div class="add-player-section" id="addPlayerSectionA">
                                        <h6 class="text-success mb-2">➕ Thêm cầu thủ vào Đội A</h6>
                                        <div class="dropdown">
                                            <button class="btn btn-outline-success dropdown-toggle w-100" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-plus"></i> Chọn cầu thủ
                                            </button>
                                            <ul class="dropdown-menu w-100 player-select-dropdown" id="dropdownA">
                                                <?php foreach ($unregisteredPlayers as $player): ?>
                                                    <li>
                                                        <div class="add-player-item" onclick="addPlayerToTeam(<?= $player['id'] ?>, 'A')" data-player-id="<?= $player['id'] ?>">
                                                            <strong><?= htmlspecialchars($player['name']) ?></strong>
                                                            <div class="small text-muted">
                                                                <?= $player['main_position'] ?> 
                                                                <span class="badge bg-<?= $player['main_skill'] === 'Tốt' ? 'success' : ($player['main_skill'] === 'Trung bình' ? 'warning' : 'secondary') ?> skill-badge">
                                                                    <?= $player['main_skill'] ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
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
                                                
                                                <!-- Remove button (X) - only visible in edit mode -->
                                                <button class="btn btn-danger btn-sm remove-btn" 
                                                        onclick="removePlayerFromMatch(<?= $player['player_id'] ?>)"
                                                        title="Loại khỏi trận đấu">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                
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
                                    
                                    
                                    <!-- Add Player Section for Team B -->
                                    <div class="add-player-section" id="addPlayerSectionB">
                                        <h6 class="text-primary mb-2">➕ Thêm cầu thủ vào Đội B</h6>
                                        <div class="dropdown">
                                            <button class="btn btn-outline-primary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-plus"></i> Chọn cầu thủ
                                            </button>
                                            <ul class="dropdown-menu w-100 player-select-dropdown" id="dropdownB">
                                                <?php foreach ($unregisteredPlayers as $player): ?>
                                                    <li>
                                                        <div class="add-player-item" onclick="addPlayerToTeam(<?= $player['id'] ?>, 'B')" data-player-id="<?= $player['id'] ?>">
                                                            <strong><?= htmlspecialchars($player['name']) ?></strong>
                                                            <div class="small text-muted">
                                                                <?= $player['main_position'] ?> 
                                                                <span class="badge bg-<?= $player['main_skill'] === 'Tốt' ? 'success' : ($player['main_skill'] === 'Trung bình' ? 'warning' : 'secondary') ?> skill-badge">
                                                                    <?= $player['main_skill'] ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
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
        const matchId = <?= $matchId ?>;

        // Toggle edit mode for team swapping
        function toggleEditMode() {
            editMode = !editMode;
            const swapButtons = document.querySelectorAll('.swap-btn');
            const removeButtons = document.querySelectorAll('.remove-btn');
            const addPlayerSections = document.querySelectorAll('.add-player-section');
            const editModeText = document.getElementById('editModeText');
            const saveFormationBtn = document.getElementById('saveFormationBtn');
            const teamsContainer = document.getElementById('teamsContainer');
            const instructionText = document.getElementById('instructionText');
            
            if (editMode) {
                // Enable edit mode
                teamsContainer.classList.add('edit-mode');
                swapButtons.forEach(btn => btn.style.display = 'inline-block');
                removeButtons.forEach(btn => btn.style.display = 'inline-block');
                addPlayerSections.forEach(section => section.style.display = 'block');
                editModeText.textContent = 'Tắt chế độ đổi đội';
                saveFormationBtn.style.display = 'inline-block';
                instructionText.innerHTML = '<i class="fas fa-exchange-alt"></i> Đang ở chế độ chỉnh sửa đội hình. Có thể: đổi đội, thêm/bớt cầu thủ. Nhớ lưu đội hình mới sau khi chỉnh sửa.';
                
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
                removeButtons.forEach(btn => btn.style.display = 'none');
                addPlayerSections.forEach(section => section.style.display = 'none');
                editModeText.textContent = 'Bật chế độ đổi đội';
                saveFormationBtn.style.display = 'none';
                instructionText.innerHTML = '<i class="fas fa-info-circle"></i> Nhập tỷ số và thống kê cầu thủ, sau đó click "Lưu kết quả" để hoàn tất.';
                
                // Disable drag and drop
                disableDragAndDrop();
            }
        }

        // update Status
        function updateStatus() {
            if (!confirm(`Bạn có chắc muốn update trạng thái này không?`)) {
                return;
            }

            const statusNew = document.getElementById('statusNew').value;
            
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_status',
                    match_id: matchId,
                    newStatus: statusNew
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Lỗi: ' + data.error);
                    restorePlayerItems(playerItems);
                } else {
                    showNotification('Update trạng thái thành công!', 'success');
                    
                    // Reload page to update team lists
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi update');
            });
        }

        // Add player to team
        function addPlayerToTeam(playerId, team) {
            if (!confirm(`Bạn có chắc muốn thêm cầu thủ này vào Đội ${team}?`)) {
                return;
            }
            
            // Show loading state
            const playerItems = document.querySelectorAll(`[data-player-id="${playerId}"]`);
            playerItems.forEach(item => {
                if (item.classList.contains('add-player-item')) {
                    item.style.opacity = '0.5';
                    item.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang thêm...';
                }
            });
            
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'add_player_to_match',
                    match_id: matchId,
                    player_id: playerId,
                    team: team
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Lỗi: ' + data.error);
                    restorePlayerItems(playerItems);
                } else {
                    showNotification('Thêm cầu thủ thành công!', 'success');
                    
                    // Remove from dropdown menus
                    playerItems.forEach(item => {
                        if (item.classList.contains('add-player-item')) {
                            item.closest('li').remove();
                        }
                    });
                    
                    // Reload page to update team lists
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi thêm cầu thủ');
                restorePlayerItems(playerItems);
            });
        }

        // Remove player from match
        function removePlayerFromMatch(playerId) {
            if (!confirm('Bạn có chắc muốn loại cầu thủ này khỏi trận đấu?')) {
                return;
            }
            
            // Show loading state
            const playerElement = document.querySelector(`[data-player-id="${playerId}"]`);
            const removeBtn = playerElement.querySelector('.remove-btn');
            removeBtn.disabled = true;
            removeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'remove_player_from_match',
                    match_id: matchId,
                    player_id: playerId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Lỗi: ' + data.error);
                    removeBtn.disabled = false;
                    removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                } else {
                    showNotification('Loại cầu thủ thành công!', 'success');
                    
                    // Remove from UI with animation
                    playerElement.style.animation = 'fadeOutScale 0.3s ease-out forwards';
                    setTimeout(() => {
                        playerElement.remove();
                        updateTeamCounts();
                        updateTeamBalance();
                    }, 300);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi loại cầu thủ');
                removeBtn.disabled = false;
                removeBtn.innerHTML = '<i class="fas fa-times"></i>';
            });
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
                updateTeamCounts();
                updateTeamBalance();
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
            
            const teamACountElement = document.getElementById('teamACount');
            const teamBCountElement = document.getElementById('teamBCount');
            
            if (teamACountElement) teamACountElement.textContent = teamACount;
            if (teamBCountElement) teamBCountElement.textContent = teamBCount;
        }

        // Calculate and display team balance
        function updateTeamBalance() {
            const teamABalance = calculateTeamStrength('A');
            const teamBBalance = calculateTeamStrength('B');
            
            const balanceA = document.getElementById('teamABalance');
            const balanceB = document.getElementById('teamBBalance');
            
            if (balanceA) {
                balanceA.innerHTML = `Sức mạnh: ${teamABalance.total} | Tốt: ${teamABalance.good} | TB: ${teamABalance.average} | Yếu: ${teamABalance.weak}`;
                
                // Color coding based on balance
                const difference = Math.abs(teamABalance.total - teamBBalance.total);
                const balanceClass = difference <= 2 ? 'balance-good' : difference <= 5 ? 'balance-warning' : 'balance-danger';
                balanceA.className = `balance-indicator ${balanceClass}`;
            }
            
            if (balanceB) {
                balanceB.innerHTML = `Sức mạnh: ${teamBBalance.total} | Tốt: ${teamBBalance.good} | TB: ${teamBBalance.average} | Yếu: ${teamBBalance.weak}`;
                
                // Color coding based on balance
                const difference = Math.abs(teamABalance.total - teamBBalance.total);
                const balanceClass = difference <= 2 ? 'balance-good' : difference <= 5 ? 'balance-warning' : 'balance-danger';
                balanceB.className = `balance-indicator ${balanceClass}`;
            }
        }

        function calculateTeamStrength(team) {
            const players = document.querySelectorAll(`[data-team="${team}"]`);
            let total = 0;
            let good = 0;
            let average = 0;
            let weak = 0;
            
            players.forEach(player => {
                const skillBadge = player.querySelector('.skill-badge');
                if (skillBadge && skillBadge.textContent) {
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
                        default:
                            // Handle unexpected values
                            total += 1;
                            weak++;
                            break;
                    }
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
            
            const saveBtn = document.getElementById('saveFormationBtn');
            const originalText = saveBtn.innerHTML;
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang lưu...';
            
            const currentFormation = getCurrentFormation();
            
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_formation',
                    match_id: matchId,
                    team_a_players: currentFormation.teamA,
                    team_b_players: currentFormation.teamB
                })
            })
            .then(response => response.json())
            .then(data => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalText;
                
                if (data.error) {
                    alert('Lỗi: ' + data.error);
                } else {
                    showNotification('Lưu đội hình mới thành công!', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi lưu đội hình');
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalText;
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
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang lưu...';
            
            // Save match result
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_match_result',
                    match_id: matchId,
                    team_a_score: teamAScore,
                    team_b_score: teamBScore,
                    player_stats: playerStats
                })
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                
                if (data.error) {
                    alert('Lỗi: ' + data.error);
                } else {
                    showNotification('Cập nhật kết quả thành công!', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi lưu kết quả');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
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
                
                showNotification('Đã reset tất cả dữ liệu', 'info');
            }
        }

        // Show notification
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} position-fixed`;
            notification.style.cssText = `
                top: 20px;
                right: 20px;
                z-index: 9999;
                min-width: 300px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                animation: slideInRight 0.3s ease-out;
            `;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check' : 'info'}-circle me-2"></i>
                ${message}
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.style.animation = 'slideOutRight 0.3s ease-out forwards';
                    setTimeout(() => notification.remove(), 300);
                }
            }, 3000);
        }

        // Helper function to restore player items on error
        function restorePlayerItems(playerItems) {
            playerItems.forEach(item => {
                if (item.classList.contains('add-player-item')) {
                    item.style.opacity = '1';
                    location.reload(); // Reload to restore original state
                }
            });
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
            
            // Ctrl/Cmd + E to toggle edit mode
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                const editBtn = document.getElementById('editModeBtn');
                if (editBtn) {
                    toggleEditMode();
                }
            }
        });

        
        // Real-time validation for score inputs - moved inside DOMContentLoaded
        document.addEventListener('DOMContentLoaded', function() {
            // Real-time validation for score inputs
            ['teamAScore', 'teamBScore'].forEach(id => {
                const input = document.getElementById(id);
                if (input) {
                    input.addEventListener('input', function() {
                        if (this.value < 0) this.value = 0;
                        if (this.value > 20) this.value = 20;
                    });
                }
            });
            
            // Wait for DOM to be fully loaded
            setTimeout(() => {
                const dropdowns = document.querySelectorAll('.dropdown-menu');
                dropdowns.forEach(dropdown => {
                    dropdown.addEventListener('click', function(e) {
                        e.stopPropagation();
                    });
                });
                
                const firstInput = document.getElementById('teamAScore');
                if (firstInput && <?= $canUpdate && $match['status'] !== 'completed' ? 'true' : 'false' ?>) {
                    firstInput.focus();
                    firstInput.select();
                }
                
                // Initialize team balance display with error handling
                try {
                    updateTeamBalance();
                    updateTeamCounts();
                } catch (error) {
                    console.log('Initial balance calculation skipped:', error.message);
                }
            }, 100);
        });
    </script>

    <!-- ===== MODAL HTML ĐƠN GIẢN - ẨN COMPACT TOGGLE ===== -->

<!-- Formation Preview Modal - Always Compact with Large Names -->
<div class="modal fade" id="formationModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-xl">
        <div class="modal-content" style="border-radius: 15px; overflow: hidden; max-height: 95vh;">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none;">
                <h5 class="modal-title">
                    <i class="fas fa-eye me-2"></i>Đội hình FC Gà Gáy - <?= date('d/m/Y', strtotime($match['match_date'])) ?>
                </h5>
                <button type="button" class="btn-close" onclick="closeFormationModal()" style="filter: invert(1);"></button>
            </div>
            <div class="modal-body" style="max-height: calc(95vh - 120px); overflow-y: auto;">
                <!-- Export Options - Simplified -->
                <div class="export-options">
                    <h6 class="mb-3">Tùy chọn xuất</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">Chất lượng ảnh:</label>
                            <select class="form-select" id="imageQuality">
                                <option value="1">Thường (800x600)</option>
                                <option value="2" selected>Cao (1600x1200)</option>
                                <option value="3">Siêu cao (2400x1800)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Định dạng:</label>
                            <select class="form-select" id="imageFormat">
                                <option value="png">PNG (chất lượng tốt nhất)</option>
                                <option value="jpeg">JPEG (kích thước nhỏ)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="showMatchInfo" checked>
                                <label class="form-check-label" for="showMatchInfo">
                                    Thông tin trận đấu
                                </label>
                            </div>
                        </div>
                        <!-- HIDDEN: Compact Mode Toggle - Always Enabled -->
                        <input type="checkbox" id="compactMode" checked style="display: none;">
                    </div>
                </div>

                <!-- Formation Preview Container - Always Compact -->
                <div class="formation-container compact-mode" id="formationContainer">
                    <div class="formation-preview" id="formationPreview">
                        <div class="formation-info" id="matchInfo">
                            📅 <?= date('d/m/Y', strtotime($match['match_date'])) ?> - ⚽ FC Gà Gáy
                            <?php if ($match['status'] === 'completed'): ?>
                                - Kết quả: <?= $match['team_a_score'] ?>-<?= $match['team_b_score'] ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="formation-field" id="formationField">
                            <div class="center-circle"></div>
                            
                            <!-- Team A -->
                            <div class="team-half team-a" id="teamHalfA">
                                <div class="team-title">🔴 Đội A (<?= count($teamA) ?> người)</div>
                                
                                <!-- PHP Generated Formation for Team A -->
                                <?php
                                $positionGroups = [
                                    'Thủ môn' => ['class' => 'goalkeeper', 'abbr' => 'GK'],
                                    'Trung vệ' => ['class' => 'defender', 'abbr' => 'CB'],
                                    'Hậu vệ cánh' => ['class' => 'defender', 'abbr' => 'WB'],
                                    'Tiền vệ' => ['class' => 'midfielder', 'abbr' => 'MF'],
                                    'Tiền đạo' => ['class' => 'forward', 'abbr' => 'FW']
                                ];
                                
                                foreach ($positionGroups as $position => $config):
                                    $playersInPosition = array_filter($teamA, function($p) use ($position) {
                                        return $p['assigned_position'] === $position;
                                    });
                                    
                                    if (!empty($playersInPosition)):
                                ?>
                                    <div class="position-line" 
                                         data-position="<?= $position ?>" 
                                         data-player-count="<?= count($playersInPosition) ?>">
                                        <?php foreach ($playersInPosition as $player): ?>
                                            <div class="player-container" data-player-id="<?= $player['player_id'] ?>">
                                                <div class="formation-player team-a <?= $config['class'] ?>" 
                                                     data-skill="<?= $player['skill_level'] ?>">
                                                    <div class="player-position"><?= $config['abbr'] ?></div>
                                                    <?php
                                                    // Generate smart initials
                                                    $nameParts = explode(' ', trim($player['name']));
                                                    if (count($nameParts) >= 2) {
                                                        echo strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[count($nameParts)-1], 0, 1));
                                                    } else {
                                                        echo strtoupper(substr($nameParts[0], 0, 2));
                                                    }
                                                    ?>
                                                </div>
                                                <!-- LARGE, CLEAR FULL NAME -->
                                                <div class="player-full-name" 
                                                     data-full-name="<?= htmlspecialchars($player['name']) ?>"
                                                     title="<?= htmlspecialchars($player['name']) ?>">
                                                    <?= htmlspecialchars($player['name']) ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                            
                            <!-- Team B -->
                            <div class="team-half team-b" id="teamHalfB">
                                <div class="team-title">🔵 Đội B (<?= count($teamB) ?> người)</div>
                                
                                <!-- PHP Generated Formation for Team B -->
                                <?php
                                foreach ($positionGroups as $position => $config):
                                    $playersInPosition = array_filter($teamB, function($p) use ($position) {
                                        return $p['assigned_position'] === $position;
                                    });
                                    
                                    if (!empty($playersInPosition)):
                                ?>
                                    <div class="position-line" 
                                         data-position="<?= $position ?>" 
                                         data-player-count="<?= count($playersInPosition) ?>">
                                        <?php foreach ($playersInPosition as $player): ?>
                                            <div class="player-container" data-player-id="<?= $player['player_id'] ?>">
                                                <div class="formation-player team-b <?= $config['class'] ?>" 
                                                     data-skill="<?= $player['skill_level'] ?>">
                                                    <div class="player-position"><?= $config['abbr'] ?></div>
                                                    <?php
                                                    // Generate smart initials
                                                    $nameParts = explode(' ', trim($player['name']));
                                                    if (count($nameParts) >= 2) {
                                                        echo strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[count($nameParts)-1], 0, 1));
                                                    } else {
                                                        echo strtoupper(substr($nameParts[0], 0, 2));
                                                    }
                                                    ?>
                                                </div>
                                                <!-- LARGE, CLEAR FULL NAME -->
                                                <div class="player-full-name" 
                                                     data-full-name="<?= htmlspecialchars($player['name']) ?>"
                                                     title="<?= htmlspecialchars($player['name']) ?>">
                                                    <?= htmlspecialchars($player['name']) ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <div class="d-flex justify-content-between w-100 align-items-center">
                    <div class="text-muted small" id="formationStats">
                        <i class="fas fa-users me-1"></i>
                        Tổng: <?= count($teamA) + count($teamB) ?> | 
                        Đội A: <?= count($teamA) ?> | 
                        Đội B: <?= count($teamB) ?>
                        <span class="badge bg-success ms-2">Chế độ tối ưu</span>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary" onclick="closeFormationModal()">
                            <i class="fas fa-times me-2"></i>Đóng
                        </button>
                        <button type="button" class="btn btn-success" onclick="exportFormation()">
                            <i class="fas fa-download me-2"></i>Xuất ảnh đội hình
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Modal - Simple -->
<div id="loadingModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 40px; border-radius: 15px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
        <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <h5 class="mb-2">Đang tạo ảnh đội hình...</h5>
        <p class="text-muted mb-0">Layout tối ưu - tên rõ ràng</p>
        <div class="progress mt-3" style="height: 6px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: 100%"></div>
        </div>
    </div>
</div>

<style>
/* Additional CSS for Always-Compact Mode */
.formation-container.compact-mode {
    /* Container is always in compact mode */
}

/* Force large, clear names even in compact mode */
.compact-mode .player-full-name {
    font-size: 0.85rem !important;
    font-weight: 700 !important;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.9) !important;
    background: rgba(0,0,0,0.6) !important;
    border: 1px solid rgba(255,255,255,0.3) !important;
    max-width: 120px !important;
    padding: 4px 8px !important;
    min-height: 20px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

/* Compact player circles */
.compact-mode .formation-player {
    width: 38px !important;
    height: 38px !important;
    font-size: 0.65rem !important;
    font-weight: bold !important;
}

/* Compact position badges */
.compact-mode .player-position {
    width: 15px !important;
    height: 15px !important;
    font-size: 0.5rem !important;
    top: -3px !important;
    right: -3px !important;
}

/* Compact spacing */
.compact-mode .position-line {
    gap: 8px !important;
    margin-bottom: 10px !important;
    min-height: 55px !important;
}

/* Enhanced hover for large names */
.compact-mode .player-container:hover .player-full-name {
    transform: scale(1.1) !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.6) !important;
    background: rgba(255,255,255,0.2) !important;
    color: #fff !important;
    font-weight: 800 !important;
    z-index: 100 !important;
    max-width: none !important;
    white-space: normal !important;
}

/* Mobile optimizations - keep names large */
@media (max-width: 768px) {
    .compact-mode .player-full-name {
        font-size: 0.8rem !important;
        font-weight: 700 !important;
        max-width: 100px !important;
    }
    
    .compact-mode .formation-player {
        width: 35px !important;
        height: 35px !important;
        font-size: 0.6rem !important;
    }
}

@media (max-width: 576px) {
    .compact-mode .player-full-name {
        font-size: 0.75rem !important;
        font-weight: 700 !important;
        max-width: 85px !important;
        text-shadow: 1px 1px 3px rgba(0,0,0,0.9) !important;
        background: rgba(0,0,0,0.8) !important;
    }
    
    .compact-mode .formation-player {
        width: 32px !important;
        height: 32px !important;
        font-size: 0.55rem !important;
    }
    
    .compact-mode .position-line {
        gap: 6px !important;
        margin-bottom: 8px !important;
    }
}

/* Success indicator for optimized layout */
.badge.bg-success {
    background-color: #28a745 !important;
}

/* Hide any remaining compact toggle elements */
label[for="compactMode"],
#compactMode {
    display: none !important;
}
</style>

<script>
// Enhanced JavaScript for responsive formation layout
document.addEventListener('DOMContentLoaded', function() {
    // Initialize responsive formation
    initializeResponsiveFormation();
    
    // Add event listeners for new features
    const compactModeCheckbox = document.getElementById('compactMode');
    if (compactModeCheckbox) {
        compactModeCheckbox.addEventListener('change', toggleCompactMode);
    }
    
    // Auto-detect high density and suggest compact mode
    detectFormationDensity();
});

function initializeResponsiveFormation() {
    // Calculate total players
    const totalPlayers = calculateTotalPlayers();
    
    // Update stats display
    updateFormationStats(totalPlayers);
    
    // Show alert for high-density formations
    if (totalPlayers > 14) {
        showPlayerCountAlert(totalPlayers);
    }
    
    // Auto-apply optimizations
    applyAutoOptimizations(totalPlayers);
}

function calculateTotalPlayers() {
    const teamAPlayers = document.querySelectorAll('.team-half.team-a .player-container').length;
    const teamBPlayers = document.querySelectorAll('.team-half.team-b .player-container').length;
    return teamAPlayers + teamBPlayers;
}

function updateFormationStats(totalPlayers) {
    const statsEl = document.getElementById('formationStats');
    if (statsEl) {
        const teamACount = document.querySelectorAll('.team-half.team-a .player-container').length;
        const teamBCount = document.querySelectorAll('.team-half.team-b .player-container').length;
        
        statsEl.innerHTML = `
            <i class="fas fa-users me-1"></i>
            Tổng: ${totalPlayers} | Đội A: ${teamACount} | Đội B: ${teamBCount}
        `;
    }
}

function showPlayerCountAlert(totalPlayers) {
    const alertEl = document.getElementById('playerCountAlert');
    const textEl = document.getElementById('playerCountText');
    
    if (alertEl && textEl) {
        textEl.textContent = `${totalPlayers} cầu thủ tham gia`;
        alertEl.classList.remove('d-none');
        
        if (totalPlayers > 18) {
            alertEl.className = 'alert alert-warning';
            textEl.innerHTML += ' <span class="badge bg-warning">Rất đông</span>';
        } else if (totalPlayers > 16) {
            alertEl.className = 'alert alert-info';
            textEl.innerHTML += ' <span class="badge bg-info">Đông</span>';
        }
    }
}

function detectFormationDensity() {
    const totalPlayers = calculateTotalPlayers();
    const compactCheckbox = document.getElementById('compactMode');
    
    // Auto-suggest compact mode for dense formations
    if (totalPlayers > 16 && compactCheckbox && !compactCheckbox.checked) {
        // Show suggestion notification
        setTimeout(() => {
            showAutoSuggestion(totalPlayers);
        }, 1000);
    }
}

function showAutoSuggestion(totalPlayers) {
    const suggestion = document.createElement('div');
    suggestion.className = 'alert alert-success alert-dismissible fade show';
    suggestion.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        max-width: 350px;
        animation: slideInRight 0.3s ease-out;
    `;
    
    suggestion.innerHTML = `
        <div class="d-flex align-items-start">
            <i class="fas fa-lightbulb text-warning me-2 mt-1"></i>
            <div class="flex-grow-1">
                <strong>Gợi ý:</strong> Với ${totalPlayers} cầu thủ, bạn có thể bật "Chế độ thu gọn" để hiển thị tốt hơn.
                <div class="mt-2">
                    <button class="btn btn-sm btn-success me-2" onclick="applyCompactMode(); this.closest('.alert').remove();">
                        <i class="fas fa-check"></i> Áp dụng
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="this.closest('.alert').remove();">
                        Bỏ qua
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(suggestion);
    
    // Auto remove after 10 seconds
    setTimeout(() => {
        if (suggestion.parentElement) {
            suggestion.remove();
        }
    }, 10000);
}

function applyCompactMode() {
    const compactCheckbox = document.getElementById('compactMode');
    if (compactCheckbox) {
        compactCheckbox.checked = true;
        toggleCompactMode();
    }
}

function toggleCompactMode() {
    const compactCheckbox = document.getElementById('compactMode');
    const formationContainer = document.getElementById('formationContainer');
    
    if (compactCheckbox && formationContainer) {
        if (compactCheckbox.checked) {
            formationContainer.classList.add('compact-mode');
            showSimpleNotification('Đã bật chế độ thu gọn', 'success');
        } else {
            formationContainer.classList.remove('compact-mode');
            showSimpleNotification('Đã tắt chế độ thu gọn', 'info');
        }
    }
}

function applyAutoOptimizations(totalPlayers) {
    const formationField = document.getElementById('formationField');
    
    if (!formationField) return;
    
    // Adjust field height based on player count
    if (totalPlayers > 20) {
        formationField.style.minHeight = '900px';
    } else if (totalPlayers > 18) {
        formationField.style.minHeight = '850px';
    } else if (totalPlayers > 16) {
        formationField.style.minHeight = '800px';
    } else if (totalPlayers > 14) {
        formationField.style.minHeight = '750px';
    }
    
    // Adjust player spacing for dense formations
    const positionLines = document.querySelectorAll('.position-line');
    positionLines.forEach(line => {
        const playerCount = parseInt(line.dataset.playerCount) || 0;
        
        if (playerCount >= 5) {
            line.style.flexWrap = 'wrap';
            line.style.gap = '6px';
            line.style.alignContent = 'center';
            line.style.minHeight = '90px';
        } else if (playerCount >= 4) {
            line.style.gap = '8px';
        }
    });
}

// Enhanced export function with density awareness
async function exportFormationEnhanced() {
    const totalPlayers = calculateTotalPlayers();
    
    // Warn user about very dense formations
    if (totalPlayers > 20) {
        if (!confirm(`Đội hình có ${totalPlayers} cầu thủ rất đông. Bạn có muốn tiếp tục xuất ảnh không?\n\nGợi ý: Bật "Chế độ thu gọn" để có kết quả tốt hơn.`)) {
            return;
        }
    }
    
    // Call the original export function
    await exportFormation();
}

// Update the export button to use enhanced function
document.addEventListener('DOMContentLoaded', function() {
    const exportBtn = document.querySelector('button[onclick="exportFormation()"]');
    if (exportBtn) {
        exportBtn.setAttribute('onclick', 'exportFormationEnhanced()');
    }
});


// ===== JAVASCRIPT THÔNG MINH CHO LAYOUT NHIỀU CẦU THỦ =====

// Enhanced formation layout for many players
function generateFormationLayout(teamClass, teamData) {
    try {
        const teamHalf = document.querySelector(`.team-half.${teamClass}`);
        if (!teamHalf) {
            console.error(`Team half element not found: ${teamClass}`);
            return;
        }
        
        const existingPositionLines = teamHalf.querySelectorAll('.position-line');
        existingPositionLines.forEach(line => line.remove());
        
        const positionConfig = {
            'Thủ môn': { class: 'goalkeeper', abbr: 'GK', priority: 1 },
            'Trung vệ': { class: 'defender', abbr: 'CB', priority: 2 },
            'Hậu vệ cánh': { class: 'defender', abbr: 'WB', priority: 3 },
            'Tiền vệ': { class: 'midfielder', abbr: 'MF', priority: 4 },
            'Tiền đạo': { class: 'forward', abbr: 'FW', priority: 5 }
        };
        
        // Count total players for this team
        const totalPlayers = Object.values(teamData).reduce((sum, players) => sum + (players?.length || 0), 0);
        
        // Sort positions by priority
        const sortedPositions = Object.keys(teamData)
            .filter(pos => teamData[pos] && teamData[pos].length > 0)
            .sort((a, b) => (positionConfig[a]?.priority || 99) - (positionConfig[b]?.priority || 99));
        
        // Calculate available space and adjust layout
        const availableHeight = teamHalf.clientHeight - 80; // Account for title and padding
        const lineHeight = Math.min(80, Math.max(60, availableHeight / sortedPositions.length));
        
        sortedPositions.forEach((position, index) => {
            const players = teamData[position];
            if (!players || players.length === 0) return;
            
            const positionLine = document.createElement('div');
            positionLine.className = 'position-line';
            
            // Add class for many players detection
            if (players.length >= 4) {
                positionLine.classList.add('many-players');
            }
            
            // Smart arrangement based on player count
            arrangePlayersInLine(positionLine, players, position, config = positionConfig[position], totalPlayers);
            
            // Adjust spacing based on total players
            if (totalPlayers > 8) {
                positionLine.style.marginBottom = '10px';
            } else if (totalPlayers > 6) {
                positionLine.style.marginBottom = '15px';
            }
            
            teamHalf.appendChild(positionLine);
        });
        
        // Apply final adjustments
        adjustFormationDensity(teamHalf, totalPlayers);
        
    } catch (error) {
        console.error('Error generating formation layout:', error);
    }
}

// Smart player arrangement within position line
function arrangePlayersInLine(positionLine, players, position, config, totalTeamPlayers) {
    const playerCount = players.length;
    
    // Determine arrangement strategy
    if (playerCount === 1) {
        positionLine.style.justifyContent = 'center';
    } else if (playerCount === 2) {
        positionLine.style.justifyContent = 'space-evenly';
        positionLine.style.paddingLeft = '15px';
        positionLine.style.paddingRight = '15px';
    } else if (playerCount === 3) {
        positionLine.style.justifyContent = 'space-around';
        positionLine.style.paddingLeft = '10px';
        positionLine.style.paddingRight = '10px';
    } else if (playerCount >= 4) {
        positionLine.style.justifyContent = 'center';
        positionLine.style.flexWrap = 'wrap';
        positionLine.style.gap = '6px';
        
        // For 5+ players, create two rows
        if (playerCount >= 5) {
            positionLine.style.alignContent = 'center';
            positionLine.style.minHeight = '90px';
        }
    }
    
    // Create player elements
    players.forEach((player, index) => {
        if (!player || !player.name) return;
        
        const playerContainer = createPlayerElement(player, config, totalTeamPlayers, playerCount);
        
        // Special positioning for 5+ players (two rows)
        if (playerCount >= 5) {
            if (playerCount === 5) {
                // 3 top, 2 bottom or 2 top, 3 bottom
                const isTopRow = index < Math.ceil(playerCount / 2);
                playerContainer.style.order = isTopRow ? '1' : '2';
            } else if (playerCount === 6) {
                // 3 top, 3 bottom
                const isTopRow = index < 3;
                playerContainer.style.order = isTopRow ? '1' : '2';
            }
        }
        
        positionLine.appendChild(playerContainer);
    });
}

// Create individual player element
function createPlayerElement(player, config, totalTeamPlayers, positionPlayerCount) {
    const playerContainer = document.createElement('div');
    playerContainer.className = 'player-container';
    
    // Apply scaling based on team density
    if (totalTeamPlayers > 10) {
        playerContainer.style.transform = 'scale(0.85)';
    } else if (totalTeamPlayers > 8) {
        playerContainer.style.transform = 'scale(0.9)';
    }
    
    const playerEl = document.createElement('div');
    playerEl.className = `formation-player ${playerContainer.closest('.team-half').classList.contains('team-a') ? 'team-a' : 'team-b'} ${config.class}`;
    
    // Generate smart initials
    const initials = generateSmartInitials(player.name);
    
    // Create player name display with smart truncation
    const playerNameEl = document.createElement('div');
    playerNameEl.className = 'player-full-name';
    playerNameEl.textContent = truncatePlayerName(player.name, totalTeamPlayers);
    
    playerEl.innerHTML = `
        <div class="player-position">${config.abbr}</div>
        ${initials}
    `;
    
    // Add skill level indicator
    applySkillStyling(playerEl, player);
    
    // Add comprehensive tooltip
    playerContainer.title = createPlayerTooltip(player, config);
    
    // Assemble container
    playerContainer.appendChild(playerEl);
    playerContainer.appendChild(playerNameEl);
    
    return playerContainer;
}

// Generate smart initials from name
function generateSmartInitials(name) {
    const nameParts = name.trim().split(' ').filter(part => part.length > 0);
    
    if (nameParts.length >= 2) {
        // Take first letter of first and last name
        const firstInitial = nameParts[0].charAt(0).toUpperCase();
        const lastInitial = nameParts[nameParts.length - 1].charAt(0).toUpperCase();
        return firstInitial + lastInitial;
    } else if (nameParts.length === 1) {
        // Take first two letters
        return nameParts[0].substring(0, 2).toUpperCase();
    } else {
        return 'XX';
    }
}

// Smart name truncation based on team density
function truncatePlayerName(name, totalPlayers) {
    let maxLength;
    
    if (totalPlayers > 12) {
        maxLength = 8; // Very dense
    } else if (totalPlayers > 10) {
        maxLength = 10; // Dense
    } else if (totalPlayers > 8) {
        maxLength = 12; // Medium
    } else {
        maxLength = 15; // Normal
    }
    
    if (name.length <= maxLength) {
        return name;
    }
    
    // Try to truncate at word boundary
    const words = name.split(' ');
    if (words.length > 1) {
        let truncated = words[0];
        for (let i = 1; i < words.length; i++) {
            if ((truncated + ' ' + words[i]).length <= maxLength) {
                truncated += ' ' + words[i];
            } else {
                break;
            }
        }
        return truncated;
    }
    
    // Hard truncation
    return name.substring(0, maxLength - 1) + '…';
}

// Apply skill-based styling
function applySkillStyling(playerEl, player) {
    const skillColors = {
        'Tốt': '#28a745',
        'Trung bình': '#ffc107', 
        'Yếu': '#6c757d'
    };
    
    if (player.skill_level && skillColors[player.skill_level]) {
        playerEl.style.borderColor = skillColors[player.skill_level];
        playerEl.style.borderWidth = '2px';
        
        // Add subtle glow for high skill
        if (player.skill_level === 'Tốt') {
            playerEl.style.boxShadow = `0 3px 6px rgba(0,0,0,0.3), 0 0 8px ${skillColors[player.skill_level]}40`;
        }
    }
}

// Create comprehensive tooltip
function createPlayerTooltip(player, config) {
    const parts = [
        player.name,
        `Vị trí: ${player.assigned_position || config.position}`,
        `Kỹ năng: ${player.skill_level || 'Không rõ'}`,
        `Loại: ${player.position_type || 'Không rõ'}`
    ];
    
    if (player.goals > 0) parts.push(`Bàn thắng: ${player.goals}`);
    if (player.assists > 0) parts.push(`Kiến tạo: ${player.assists}`);
    
    return parts.join('\n');
}

// Adjust overall formation density
function adjustFormationDensity(teamHalf, totalPlayers) {
    if (totalPlayers > 12) {
        teamHalf.style.fontSize = '0.85em';
        teamHalf.classList.add('very-dense');
    } else if (totalPlayers > 10) {
        teamHalf.style.fontSize = '0.9em';
        teamHalf.classList.add('dense');
    } else if (totalPlayers > 8) {
        teamHalf.style.fontSize = '0.95em';
        teamHalf.classList.add('medium-dense');
    }
}

// Enhanced update function with density awareness
function updateFormationPreview() {
    if (!formationData) {
        console.warn('No formation data available');
        return;
    }
    
    try {
        const { match, teamA, teamB, teamAStats, teamBStats } = formationData;
        
        // Update match info safely
        const matchInfo = document.getElementById('matchInfo');
        if (matchInfo) {
            let infoText = `📅 ${formationData.formation_date} - ⚽ FC Gà Gáy`;
            
            if (formationData.is_completed && match.team_a_score !== null && match.team_b_score !== null) {
                infoText += ` - Kết quả: ${match.team_a_score}-${match.team_b_score}`;
            }
            
            matchInfo.textContent = infoText;
        }
        
        // Update team titles with stats safely
        const teamATitleEl = document.querySelector('.team-half.team-a .team-title');
        const teamBTitleEl = document.querySelector('.team-half.team-b .team-title');
        
        if (teamATitleEl && teamAStats) {
            teamATitleEl.textContent = `🔴 Đội A (${teamAStats.total_players} người)`;
        }
        if (teamBTitleEl && teamBStats) {
            teamBTitleEl.textContent = `🔵 Đội B (${teamBStats.total_players} người)`;
        }
        
        // Check for high-density formations
        const totalPlayersA = teamAStats?.total_players || 0;
        const totalPlayersB = teamBStats?.total_players || 0;
        const totalPlayers = totalPlayersA + totalPlayersB;
        
        // Adjust formation field for very dense formations
        const formationField = document.querySelector('.formation-field');
        if (formationField && totalPlayers > 16) {
            formationField.style.minHeight = '800px';
        } else if (formationField && totalPlayers > 14) {
            formationField.style.minHeight = '750px';
        }
        
        // Generate layouts with density awareness
        if (teamA) generateFormationLayout('team-a', teamA);
        if (teamB) generateFormationLayout('team-b', teamB);
        
        // Initialize interactions
        setTimeout(() => {
            initializePlayerInteractions();
        }, 100);
        
        // Show density warning if needed
        if (totalPlayers > 18) {
            showDensityWarning(totalPlayers);
        }
        
    } catch (error) {
        console.error('Error updating formation preview:', error);
        showSimpleNotification('Lỗi khi cập nhật preview: ' + error.message, 'warning');
    }
}

// Show warning for very dense formations
function showDensityWarning(totalPlayers) {
    const warning = document.createElement('div');
    warning.id = 'density-warning';
    warning.style.cssText = `
        position: absolute;
        bottom: 10px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(255, 193, 7, 0.9);
        color: #000;
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
        z-index: 200;
        animation: fadeInUp 0.3s ease-out;
    `;
    warning.innerHTML = `⚠️ Đội hình dày đặc (${totalPlayers} người) - có thể khó nhìn trên mobile`;
    
    const formationPreview = document.getElementById('formationPreview');
    if (formationPreview) {
        // Remove existing warning
        const existingWarning = document.getElementById('density-warning');
        if (existingWarning) existingWarning.remove();
        
        formationPreview.appendChild(warning);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (warning.parentElement) {
                warning.style.animation = 'fadeOut 0.3s ease-out forwards';
                setTimeout(() => warning.remove(), 300);
            }
        }, 5000);
    }
}

// Add required animations
function addDensityStyles() {
    if (document.getElementById('density-styles')) return;
    
    const style = document.createElement('style');
    style.id = 'density-styles';
    style.textContent = `
        @keyframes fadeInUp {
            from { opacity: 0; transform: translate(-50%, 20px); }
            to { opacity: 1; transform: translate(-50%, 0); }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        
        .team-half.very-dense .formation-player {
            width: 36px !important;
            height: 36px !important;
            font-size: 0.6rem !important;
        }
        
        .team-half.very-dense .player-full-name {
            font-size: 0.65rem !important;
            max-width: 60px !important;
        }
        
        .team-half.dense .formation-player {
            width: 40px !important;
            height: 40px !important;
            font-size: 0.65rem !important;
        }
        
        .team-half.dense .player-full-name {
            font-size: 0.7rem !important;
            max-width: 65px !important;
        }
    `;
    
    document.head.appendChild(style);
}

// Initialize density styles when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    addDensityStyles();
});
</script>

    <!-- Thêm CDN html2canvas trước tag </body> -->
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>

    <script>
    // ===== JAVASCRIPT ĐỖN GIẢN - THAY THẾ TOÀN BỘ PHẦN FORMATION =====

    // Simple Formation Export Functions - No Bootstrap Dependencies
    function showFormationPreview() {
        try {
            // Show modal manually
            const modal = document.getElementById('formationModal');
            if (modal) {
                modal.style.display = 'block';
                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
                
                // Add backdrop
                const backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show';
                backdrop.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0,0,0,0.5);
                    z-index: 1040;
                `;
                backdrop.id = 'formation-backdrop';
                document.body.appendChild(backdrop);
                
                // Click backdrop to close
                backdrop.addEventListener('click', closeFormationModal);
            }
            
            // Initialize interactions
            setTimeout(() => {
                initializePlayerInteractions();
            }, 100);
            
        } catch (error) {
            console.error('Error showing formation:', error);
            alert('Có lỗi khi hiển thị đội hình: ' + error.message);
        }
    }

    function closeFormationModal() {
        try {
            const modal = document.getElementById('formationModal');
            const backdrop = document.getElementById('formation-backdrop');
            
            if (modal) {
                modal.style.display = 'none';
                modal.classList.remove('show');
                document.body.style.overflow = '';
            }
            
            if (backdrop) {
                backdrop.remove();
            }
        } catch (error) {
            console.error('Error closing modal:', error);
        }
    }

    function initializePlayerInteractions() {
        try {
            const containers = document.querySelectorAll('.player-container');
            containers.forEach(container => {
                container.addEventListener('mouseenter', function() {
                    this.style.zIndex = '10';
                    const nameEl = this.querySelector('.player-full-name');
                    if (nameEl) {
                        nameEl.style.transform = 'scale(1.05)';
                        nameEl.style.boxShadow = '0 2px 8px rgba(0,0,0,0.5)';
                    }
                });
                
                container.addEventListener('mouseleave', function() {
                    this.style.zIndex = '1';
                    const nameEl = this.querySelector('.player-full-name');
                    if (nameEl) {
                        nameEl.style.transform = 'scale(1)';
                        nameEl.style.boxShadow = 'none';
                    }
                });
                
                // Mobile tap highlight
                container.addEventListener('click', function() {
                    const nameEl = this.querySelector('.player-full-name');
                    const playerEl = this.querySelector('.formation-player');
                    
                    if (nameEl && playerEl) {
                        // Toggle highlight
                        if (nameEl.style.backgroundColor === 'rgba(255, 255, 255, 0.9)') {
                            nameEl.style.backgroundColor = '';
                            nameEl.style.color = '';
                            playerEl.style.transform = '';
                        } else {
                            nameEl.style.backgroundColor = 'rgba(255, 255, 255, 0.9)';
                            nameEl.style.color = '#000';
                            playerEl.style.transform = 'scale(1.1)';
                            
                            setTimeout(() => {
                                nameEl.style.backgroundColor = '';
                                nameEl.style.color = '';
                                playerEl.style.transform = '';
                            }, 2000);
                        }
                    }
                });
            });
        } catch (error) {
            console.error('Error initializing interactions:', error);
        }
    }

    function updatePreview() {
        try {
            const showMatchInfo = document.getElementById('showMatchInfo');
            const matchInfo = document.getElementById('matchInfo');
            
            if (showMatchInfo && matchInfo) {
                matchInfo.style.display = showMatchInfo.checked ? 'block' : 'none';
            }
        } catch (error) {
            console.error('Error updating preview:', error);
        }
    }

    // Simple export function without API calls
    function exportFormation() {
        // Check if html2canvas is available
        if (typeof html2canvas === 'undefined') {
            // Try to load html2canvas dynamically
            const script = document.createElement('script');
            script.src = 'https://html2canvas.hertzen.com/dist/html2canvas.min.js';
            script.onload = () => {
                setTimeout(exportFormation, 100);
            };
            script.onerror = () => {
                showSimpleNotification('Không thể tải thư viện html2canvas. Vui lòng refresh trang và thử lại.', 'error');
            };
            document.head.appendChild(script);
            return;
        }
        
        try {
            // Show loading
            const loadingModal = document.getElementById('loadingModal');
            if (loadingModal) {
                loadingModal.style.display = 'block';
            }
            
            const quality = parseInt(document.getElementById('imageQuality')?.value || '2');
            const format = document.getElementById('imageFormat')?.value || 'png';
            const element = document.getElementById('formationPreview');
            
            if (!element) {
                throw new Error('Không tìm thấy element để xuất');
            }
            
            // Calculate dimensions
            const baseWidth = 1000;
            const baseHeight = 750;
            const scale = quality;
            
            const options = {
                width: baseWidth * scale,
                height: baseHeight * scale,
                scale: scale,
                useCORS: true,
                allowTaint: true,
                backgroundColor: null,
                logging: false,
                imageTimeout: 30000,
                onclone: function(clonedDoc) {
                    // Ensure styles are properly applied in cloned document
                    const clonedElement = clonedDoc.querySelector('#formationPreview');
                    if (clonedElement) {
                        clonedElement.style.transform = 'scale(1)';
                        clonedElement.style.transformOrigin = 'top left';
                    }
                }
            };
            
            // Create canvas
            html2canvas(element, options).then(canvas => {
                try {
                    // Hide loading
                    if (loadingModal) {
                        loadingModal.style.display = 'none';
                    }
                    
                    // Create download
                    const link = document.createElement('a');
                    const today = new Date();
                    const dateStr = today.getDate().toString().padStart(2, '0') + '-' + 
                                (today.getMonth() + 1).toString().padStart(2, '0') + '-' + 
                                today.getFullYear();
                    
                    // Count players
                    const teamACount = document.querySelectorAll('.team-half.team-a .player-container').length;
                    const teamBCount = document.querySelectorAll('.team-half.team-b .player-container').length;
                    
                    let fileName = `doi-hinh-fc-ga-gay-${dateStr}-${teamACount}v${teamBCount}.${format}`;
                    
                    if (format === 'png') {
                        link.href = canvas.toDataURL('image/png', 1.0);
                    } else {
                        link.href = canvas.toDataURL('image/jpeg', 0.95);
                    }
                    
                    link.download = fileName;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    // Show success
                    const fileSize = Math.round((canvas.toDataURL().length * 0.75) / 1024);
                    showSimpleNotification(
                        `Xuất đội hình thành công!\nFile: ${fileName}\nKích thước: ${canvas.width}x${canvas.height}px (~${fileSize}KB)`, 
                        'success'
                    );
                    
                    // Close modal after delay
                    setTimeout(() => {
                        closeFormationModal();
                    }, 2000);
                    
                } catch (error) {
                    if (loadingModal) {
                        loadingModal.style.display = 'none';
                    }
                    throw error;
                }
            }).catch(error => {
                // Hide loading
                if (loadingModal) {
                    loadingModal.style.display = 'none';
                }
                
                console.error('Canvas creation error:', error);
                showSimpleNotification('Có lỗi khi tạo ảnh: ' + error.message, 'error');
            });
            
        } catch (error) {
            // Hide loading
            const loadingModal = document.getElementById('loadingModal');
            if (loadingModal) {
                loadingModal.style.display = 'none';
            }
            
            console.error('Export error:', error);
            showSimpleNotification('Có lỗi khi xuất đội hình: ' + error.message, 'error');
        }
    }

    // Simple notification without Bootstrap
    function showSimpleNotification(message, type = 'info') {
        try {
            // Remove existing notifications
            const existing = document.querySelectorAll('.simple-notification');
            existing.forEach(el => el.remove());
            
            const notification = document.createElement('div');
            notification.className = 'simple-notification';
            
            let bgColor = '#17a2b8'; // info
            let icon = 'ℹ️';
            
            if (type === 'success') {
                bgColor = '#28a745';
                icon = '✅';
            } else if (type === 'error') {
                bgColor = '#dc3545';
                icon = '❌';
            } else if (type === 'warning') {
                bgColor = '#ffc107';
                icon = '⚠️';
            }
            
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                background: ${bgColor};
                color: white;
                padding: 15px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                max-width: 400px;
                font-size: 14px;
                line-height: 1.4;
                animation: slideInRight 0.3s ease-out;
            `;
            
            notification.innerHTML = `
                <div style="display: flex; align-items: flex-start; gap: 10px;">
                    <span style="font-size: 16px;">${icon}</span>
                    <div style="flex: 1;">
                        ${message.replace(/\n/g, '<br>')}
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" 
                            style="background: none; border: none; color: white; font-size: 18px; cursor: pointer; padding: 0; margin-left: 10px;">
                        ×
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.style.animation = 'slideOutRight 0.3s ease-out forwards';
                    setTimeout(() => notification.remove(), 300);
                }
            }, 5000);
            
        } catch (error) {
            console.error('Notification error:', error);
            // Fallback to alert
            alert(message);
        }
    }

    // Add required CSS animations
    function addFormationStyles() {
        if (document.getElementById('formation-styles')) return;
        
        const style = document.createElement('style');
        style.id = 'formation-styles';
        style.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            
            @keyframes slideOutRight {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            
            .modal.show {
                display: block !important;
            }
            
            .modal-backdrop.show {
                opacity: 0.5;
            }
            
            .player-container {
                transition: all 0.3s ease;
            }
            
            .formation-player {
                transition: all 0.3s ease;
            }
            
            .player-full-name {
                transition: all 0.3s ease;
            }
        `;
        
        document.head.appendChild(style);
    }

    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
        try {
            // Add required styles
            addFormationStyles();
            
            // Add event listeners
            const showMatchInfoCheckbox = document.getElementById('showMatchInfo');
            if (showMatchInfoCheckbox) {
                showMatchInfoCheckbox.addEventListener('change', updatePreview);
            }
            
            // ESC key to close modal
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeFormationModal();
                }
            });
            
            // Prevent modal from closing when clicking inside
            const modalContent = document.querySelector('#formationModal .modal-content');
            if (modalContent) {
                modalContent.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
            
        } catch (error) {
            console.error('Error initializing formation export:', error);
        }
    });

    // Prevent errors from breaking the page
    window.addEventListener('error', function(e) {
        if (e.message.includes('backdrop') || e.message.includes('Modal')) {
            console.warn('Modal error caught and ignored:', e.message);
            e.preventDefault();
            return false;
        }
    });

    // Add global click handler for formation modal
    document.addEventListener('click', function(e) {
        const modal = document.getElementById('formationModal');
        if (modal && modal.style.display === 'block' && e.target === modal) {
            closeFormationModal();
        }
    });

    // Add CSS animations for better UX
    const formationStyles = document.createElement('style');
    formationStyles.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 4px 8px rgba(0,0,0,0.3); }
            50% { box-shadow: 0 8px 16px rgba(0,0,0,0.4), 0 0 20px rgba(255,255,255,0.2); }
        }
        
        .formation-player:hover {
            animation: pulseGlow 1s ease-in-out infinite;
        }
        
        .export-options {
            transition: all 0.3s ease;
        }
        
        .export-options:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .position-line {
            transition: all 0.3s ease;
        }
        
        .formation-field:hover .position-line {
            transform: scale(1.02);
        }
        
        /* Loading states */
        .btn-loading {
            position: relative;
            pointer-events: none;
        }
        
        .btn-loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 16px;
            height: 16px;
            margin: -8px 0 0 -8px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Enhanced modal styles */
        .modal-xl .modal-content {
            border-radius: 15px;
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
        }
        
        .modal-header .btn-close {
            filter: invert(1);
        }
        
        /* Responsive enhancements */
        @media (max-width: 576px) {
            .export-options .row > div {
                margin-bottom: 15px;
            }
            
            .formation-field {
                min-height: 350px;
            }
            
            .team-half {
                padding: 10px 5px;
            }
            
            .formation-player {
                width: 40px;
                height: 40px;
                font-size: 0.5rem;
            }
            
            .player-position {
                width: 16px;
                height: 16px;
                font-size: 0.5rem;
            }
        }
    `;

    document.head.appendChild(formationStyles);
    </script>
</body>
</html>