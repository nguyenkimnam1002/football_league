<?php
require_once 'config.php';

$pdo = DB::getInstance();

// Get filters
$month = $_GET['month'] ?? date('Y-m');
$status = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query conditions
$whereConditions = ['1=1'];
$params = [];

if (!empty($month)) {
    $whereConditions[] = "DATE_FORMAT(match_date, '%Y-%m') = ?";
    $params[] = $month;
}

if (!empty($status)) {
    $whereConditions[] = "status = ?";
    $params[] = $status;
}

$whereClause = implode(' AND ', $whereConditions);

// Get total count for pagination
$countQuery = "SELECT COUNT(*) FROM daily_matches WHERE $whereClause";
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$totalMatches = $stmt->fetchColumn();
$totalPages = ceil($totalMatches / $limit);

// Get matches with participants count
$query = "
    SELECT dm.*, 
           COUNT(mp.id) as total_players,
           SUM(CASE WHEN mp.team = 'A' THEN 1 ELSE 0 END) as team_a_players,
           SUM(CASE WHEN mp.team = 'B' THEN 1 ELSE 0 END) as team_b_players,
           SUM(mp.goals) as total_goals,
           MAX(mp.goals) as highest_goals
    FROM daily_matches dm 
    LEFT JOIN match_participants mp ON dm.id = mp.match_id
    WHERE $whereClause
    GROUP BY dm.id
    ORDER BY dm.match_date DESC 
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$matches = $stmt->fetchAll();

// Get available months
$stmt = $pdo->query("
    SELECT DISTINCT DATE_FORMAT(match_date, '%Y-%m') as month,
           DATE_FORMAT(match_date, '%m/%Y') as month_display
    FROM daily_matches 
    ORDER BY month DESC
");
$availableMonths = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_matches,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_matches,
        SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_matches,
        AVG(CASE WHEN status = 'completed' THEN team_a_score + team_b_score END) as avg_goals_per_match
    FROM daily_matches 
    WHERE $whereClause
");
$stmt->execute($params);
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📅 Lịch sử trận đấu - Football League</title>
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
        .match-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            margin-bottom: 15px;
            background: white;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        .match-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        .match-header {
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
        }
        .match-score {
            font-size: 2rem;
            font-weight: bold;
            text-align: center;
        }
        .team-score-a {
            color: #dc3545;
        }
        .team-score-b {
            color: #007bff;
        }
        .match-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        .status-scheduled {
            background: #cce7ff;
            color: #004085;
        }
        .status-locked {
            background: #fff3cd;
            color: #856404;
        }
        .filter-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stats-card {
            background: linear-gradient(45deg, #4CAF50, #45a049);
            color: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .player-badge {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 15px;
            padding: 3px 8px;
            font-size: 0.8em;
            margin: 2px;
            display: inline-block;
        }
        .top-scorer {
            background: #fff3e0;
            border: 1px solid #ffcc02;
            color: #f57c00;
        }
        .match-details {
            padding: 15px 20px;
        }
        .formation-preview {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
        }
        .formation-team {
            padding: 8px;
            margin: 5px 0;
            border-radius: 5px;
        }
        .formation-team-a {
            background: #ffebee;
            border-left: 3px solid #f44336;
        }
        .formation-team-b {
            background: #e3f2fd;
            border-left: 3px solid #2196f3;
        }
        .navbar-brand {
            font-weight: bold;
        }
        .nav-link {
            font-weight: 500;
        }
        .pagination {
            justify-content: center;
        }
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #6c757d;
        }
        .match-actions {
            text-align: right;
        }
        .btn-action {
            margin-left: 5px;
            font-size: 0.85em;
        }
        .winner-highlight {
            position: relative;
        }
        .winner-highlight::after {
            content: "🏆";
            position: absolute;
            top: -15px;
            right: -10px;
            font-size: 1.5em;
            text-shadow: 0 0 3px rgba(255, 215, 0, 0.8);
        }
        .winner-highlight-B {
            position: relative;
        }
        .winner-highlight-B::after {
            content: "🏆";
            position: absolute;
            top: -15px;
            right: -60px;
            font-size: 1.5em;
            text-shadow: 0 0 3px rgba(255, 215, 0, 0.8);
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
                        <a class="nav-link active" href="history.php">
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
                            <li><a class="dropdown-item active" href="history.php"><i class="fas fa-history"></i> Lịch sử trận đấu</a></li>
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
                        <h1 class="mb-2">📅 Lịch sử trận đấu</h1>
                        <p class="lead mb-0">Xem lại kết quả và thống kê các trận đấu đã diễn ra</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="index.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-home"></i> Trang chủ
                        </a>
                        <a href="leaderboard.php" class="btn btn-outline-success">
                            <i class="fas fa-trophy"></i> Bảng xếp hạng
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column: Filters & Stats -->
            <div class="col-lg-4">
                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-6">
                        <div class="stats-card">
                            <h3><?= $stats['total_matches'] ?></h3>
                            <small>Tổng trận đấu</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stats-card">
                            <h3><?= $stats['completed_matches'] ?></h3>
                            <small>Đã hoàn thành</small>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card card-custom mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-filter"></i> Bộ lọc
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="history.php" id="filterForm">
                            <div class="mb-3">
                                <label class="form-label">Tháng:</label>
                                <select class="form-select" name="month" onchange="submitFilter()">
                                    <option value="">Tất cả tháng</option>
                                    <?php foreach ($availableMonths as $monthData): ?>
                                        <option value="<?= $monthData['month'] ?>" <?= $month === $monthData['month'] ? 'selected' : '' ?>>
                                            <?= $monthData['month_display'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Trạng thái:</label>
                                <select class="form-select" name="status" onchange="submitFilter()">
                                    <option value="">Tất cả trạng thái</option>
                                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Đã hoàn thành</option>
                                    <option value="scheduled" <?= $status === 'scheduled' ? 'selected' : '' ?>>Đã lên lịch</option>
                                    <option value="locked" <?= $status === 'locked' ? 'selected' : '' ?>>Đã khóa</option>
                                </select>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Lọc
                                </button>
                                <a href="history.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="card card-custom">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-chart-bar"></i> Thống kê nhanh
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="small">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Trận chưa hoàn thành:</span>
                                <strong><?= $stats['scheduled_matches'] ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>TB bàn thắng/trận:</span>
                                <strong><?= number_format($stats['avg_goals_per_match'] ?? 0, 1) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Tỷ lệ hoàn thành:</span>
                                <strong>
                                    <?= $stats['total_matches'] > 0 ? round(($stats['completed_matches'] / $stats['total_matches']) * 100, 1) : 0 ?>%
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Match List -->
            <div class="col-lg-8">
                <div class="card card-custom">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list"></i> 
                            Lịch sử trận đấu 
                            <?php if ($totalMatches > 0): ?>
                                (<?= number_format($totalMatches) ?> trận)
                            <?php endif; ?>
                        </h5>
                        <?php if ($totalMatches > 0): ?>
                            <button class="btn btn-sm btn-outline-info" onclick="exportHistory()">
                                <i class="fas fa-download"></i> Xuất Excel
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($matches)): ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times fa-3x mb-3"></i>
                                <h5>Không tìm thấy trận đấu nào</h5>
                                <p>Thử thay đổi bộ lọc hoặc kiểm tra lại thời gian</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($matches as $match): ?>
                                <?php
                                // Determine winner
                                $winner = '';
                                $winnerClass = '';
                                if ($match['status'] === 'completed') {
                                    if ($match['team_a_score'] > $match['team_b_score']) {
                                        $winner = 'Đội A thắng';
                                        $winnerClass = 'team-a';
                                    } elseif ($match['team_b_score'] > $match['team_a_score']) {
                                        $winner = 'Đội B thắng';
                                        $winnerClass = 'team-b';
                                    } else {
                                        $winner = 'Hòa';
                                    }
                                }
                                ?>
                                <div class="match-card" data-match-id="<?= $match['id'] ?>">
                                    <div class="match-header">
                                        <div class="row align-items-center">
                                            <div class="col-md-3">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-calendar"></i>
                                                    <?= date('d/m/Y', strtotime($match['match_date'])) ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <?= date('l', strtotime($match['match_date'])) ?>
                                                </small>
                                            </div>
                                            <div class="col-md-6 text-center">
                                                <?php if ($match['status'] === 'completed'): ?>
                                                    <div class="match-score">
                                                        <span class="team-score-a <?= $winnerClass === 'team-a' ? 'winner-highlight' : '' ?>">
                                                        </span>
                                                        <span class="team-score-a">
                                                            <?= $match['team_a_score'] ?>
                                                        </span>
                                                        <span class="text-muted mx-2">-</span>
                                                        <span class="team-score-b">
                                                            <?= $match['team_b_score'] ?>
                                                        </span>
                                                        <span class="team-score-b <?= $winnerClass === 'team-b' ? 'winner-highlight-B' : '' ?>">
                                                        </span>
                                                    </div>
                                                    <small class="text-muted"><?= $winner ?></small>
                                                <?php else: ?>
                                                    <div class="text-muted">
                                                        <i class="fas fa-clock"></i> Chưa thi đấu
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-3 text-end">
                                                <span class="match-status status-<?= $match['status'] ?>">
                                                    <?php
                                                    switch($match['status']) {
                                                        case 'completed': echo '✅ Hoàn thành'; break;
                                                        case 'scheduled': echo '📅 Đã lên lịch'; break;
                                                        case 'locked': echo '🔒 Đã khóa'; break;
                                                        default: echo $match['status'];
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="match-details">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <h6 class="text-danger">🔴 Đội A (<?= $match['team_a_players'] ?> người)</h6>
                                                <div id="teamA_<?= $match['id'] ?>" class="formation-team formation-team-a">
                                                    <small class="text-muted">Click để xem đội hình</small>
                                                </div>
                                            </div>
                                            <div class="col-md-4 text-center">
                                                <div class="formation-preview">
                                                    <div class="small mb-2">
                                                        <strong><?= $match['total_players'] ?></strong> cầu thủ tham gia
                                                    </div>
                                                    <?php if ($match['status'] === 'completed'): ?>
                                                        <div class="small">
                                                            <span class="badge bg-success">
                                                                <?= $match['total_goals'] ?> bàn thắng
                                                            </span>
                                                            <?php if ($match['highest_goals'] > 0): ?>
                                                                <span class="badge bg-warning">
                                                                    Hat-trick: <?= $match['highest_goals'] ?> bàn
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <h6 class="text-primary">🔵 Đội B (<?= $match['team_b_players'] ?> người)</h6>
                                                <div id="teamB_<?= $match['id'] ?>" class="formation-team formation-team-b">
                                                    <small class="text-muted">Click để xem đội hình</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="match-actions mt-3">
                                            <button class="btn btn-sm btn-outline-info btn-action" onclick="viewFormation(<?= $match['id'] ?>)">
                                                <i class="fas fa-users"></i> Xem đội hình
                                            </button>
                                            <a href="match_result.php?id=<?= $match['id'] ?>" class="btn btn-sm btn-outline-primary btn-action">
                                                <i class="fas fa-edit"></i> Chi tiết
                                            </a>
                                            <?php if ($match['status'] === 'completed'): ?>
                                                <button class="btn btn-sm btn-outline-success btn-action" onclick="viewStats(<?= $match['id'] ?>)">
                                                    <i class="fas fa-chart-bar"></i> Thống kê
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination mt-4">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                                    <i class="fas fa-chevron-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                                    <i class="fas fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Formation Modal -->
    <div class="modal fade" id="formationModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-users"></i> Đội hình trận đấu
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="formationContent">
                    <div class="text-center">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <p class="mt-2">Đang tải đội hình...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Modal -->
    <div class="modal fade" id="statsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-chart-bar"></i> Thống kê trận đấu
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="statsContent">
                    <div class="text-center">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <p class="mt-2">Đang tải thống kê...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Submit filter form automatically
        function submitFilter() {
            document.getElementById('filterForm').submit();
        }

        // View formation - use real API
        function viewFormation(matchId) {
            const modal = new bootstrap.Modal(document.getElementById('formationModal'));
            const content = document.getElementById('formationContent');
            
            content.innerHTML = `
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-2">Đang tải đội hình...</p>
                </div>
            `;
            
            modal.show();
            
            // Fetch formation data
            fetch(`api.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_match_formation',
                    match_id: matchId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    content.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ${data.error}</div>`;
                } else {
                    content.innerHTML = generateFormationHTML(data.data);
                }
            })
            .catch(error => {
                console.error('API Error:', error);
                content.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i> 
                        Không thể tải đội hình. 
                        <a href="match_result.php?id=${matchId}" target="_blank" class="btn btn-sm btn-primary ms-2">
                            Xem chi tiết
                        </a>
                    </div>
                `;
            });
        }

        // View match statistics - use real API
        function viewStats(matchId) {
            const modal = new bootstrap.Modal(document.getElementById('statsModal'));
            const content = document.getElementById('statsContent');
            
            content.innerHTML = `
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-2">Đang tải thống kê...</p>
                </div>
            `;
            
            modal.show();
            
            // Fetch stats data
            fetch(`api.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_match_stats',
                    match_id: matchId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    content.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ${data.error}</div>`;
                } else {
                    content.innerHTML = generateStatsHTML(data.data);
                }
            })
            .catch(error => {
                console.error('API Error:', error);
                content.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i> 
                        Không thể tải thống kê. 
                        <a href="match_result.php?id=${matchId}" target="_blank" class="btn btn-sm btn-primary ms-2">
                            Xem chi tiết
                        </a>
                    </div>
                `;
            });
        }

        // Generate formation HTML
        function generateFormationHTML(data) {
            const { match, teamA, teamB } = data;
            
            let html = `
                <div class="row mb-3">
                    <div class="col-12 text-center">
                        <h4>📅 ${new Date(match.match_date).toLocaleDateString('vi-VN')}</h4>
                        ${match.status === 'completed' ? `
                            <div class="h3 mt-2">
                                <span class="text-danger">${match.team_a_score}</span>
                                <span class="text-muted mx-3">-</span>
                                <span class="text-primary">${match.team_b_score}</span>
                            </div>
                        ` : `
                            <span class="badge bg-secondary">Chưa thi đấu</span>
                        `}
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card border-danger">
                            <div class="card-header bg-danger text-white text-center">
                                <h5 class="mb-0">🔴 Đội A</h5>
                            </div>
                            <div class="card-body">
                                ${generateTeamFormationHTML(teamA)}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white text-center">
                                <h5 class="mb-0">🔵 Đội B</h5>
                            </div>
                            <div class="card-body">
                                ${generateTeamFormationHTML(teamB)}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            return html;
        }

        function generateTeamFormationHTML(team) {
            const positions = ['Thủ môn', 'Trung vệ', 'Hậu vệ cánh', 'Tiền vệ', 'Tiền đạo'];
            let html = '';
            
            positions.forEach(position => {
                if (team[position] && team[position].length > 0) {
                    html += `
                        <div class="mb-3">
                            <h6 class="fw-bold text-success border-bottom pb-1">
                                ${getPositionIcon(position)} ${position} (${team[position].length})
                            </h6>
                    `;
                    
                    team[position].forEach(player => {
                        const skillColor = getSkillColor(player.skill_level);
                        const isMainPosition = player.main_position === position;
                        
                        html += `
                            <div class="border rounded p-2 mb-2 ${isMainPosition ? 'bg-light' : 'bg-white'}">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>${player.name}</strong>
                                        <div class="small mt-1">
                                            <span class="badge bg-info">${player.position_type}</span>
                                            <span class="badge bg-${skillColor}">${player.skill_level}</span>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        ${player.goals ? `<span class="badge bg-success">${player.goals} ⚽</span>` : ''}
                                        ${player.assists ? `<span class="badge bg-primary">${player.assists} 🎯</span>` : ''}
                                        ${player.points_earned ? `<div class="small text-success">+${player.points_earned} điểm</div>` : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    html += '</div>';
                }
            });
            
            return html || '<p class="text-muted">Không có cầu thủ</p>';
        }

        function generateStatsHTML(data) {
            const { match, teamStats, topPerformers } = data;
            
            let html = `
                <div class="row mb-4">
                    <div class="col-12 text-center">
                        <h4>📊 Thống kê trận đấu ${new Date(match.match_date).toLocaleDateString('vi-VN')}</h4>
                        <div class="h3 mt-2">
                            <span class="text-danger">${match.team_a_score}</span>
                            <span class="text-muted mx-3">-</span>
                            <span class="text-primary">${match.team_b_score}</span>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card border-danger">
                            <div class="card-header bg-danger text-white">
                                <h6 class="mb-0">🔴 Đội A - Thống kê</h6>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="h4">${teamStats.teamA.players}</div>
                                        <small>Cầu thủ</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="h4">${teamStats.teamA.goals}</div>
                                        <small>Bàn thắng</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="h4">${teamStats.teamA.assists}</div>
                                        <small>Kiến tạo</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">🔵 Đội B - Thống kê</h6>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="h4">${teamStats.teamB.players}</div>
                                        <small>Cầu thủ</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="h4">${teamStats.teamB.goals}</div>
                                        <small>Bàn thắng</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="h4">${teamStats.teamB.assists}</div>
                                        <small>Kiến tạo</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">🌟 Cầu thủ xuất sắc</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
            `;
            
            if (topPerformers.topScorer) {
                html += `
                    <div class="col-md-4 text-center">
                        <div class="bg-warning bg-opacity-10 p-3 rounded">
                            <i class="fas fa-trophy text-warning fa-2x mb-2"></i>
                            <h6>⚽ Vua phá lưới</h6>
                            <strong>${topPerformers.topScorer.name}</strong>
                            <div class="badge bg-success">${topPerformers.topScorer.goals} bàn</div>
                        </div>
                    </div>
                `;
            }
            
            if (topPerformers.topAssist) {
                html += `
                    <div class="col-md-4 text-center">
                        <div class="bg-info bg-opacity-10 p-3 rounded">
                            <i class="fas fa-hand-point-right text-info fa-2x mb-2"></i>
                            <h6>🎯 Vua kiến tạo</h6>
                            <strong>${topPerformers.topAssist.name}</strong>
                            <div class="badge bg-primary">${topPerformers.topAssist.assists} kiến tạo</div>
                        </div>
                    </div>
                `;
            }
            
            if (topPerformers.mvp) {
                html += `
                    <div class="col-md-4 text-center">
                        <div class="bg-success bg-opacity-10 p-3 rounded">
                            <i class="fas fa-crown text-warning fa-2x mb-2"></i>
                            <h6>👑 MVP</h6>
                            <strong>${topPerformers.mvp.name}</strong>
                            <div class="badge bg-warning">${topPerformers.mvp.points_earned} điểm</div>
                        </div>
                    </div>
                `;
            }
            
            html += `
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            return html;
        }

        // Helper functions
        function getPositionIcon(position) {
            const icons = {
                'Thủ môn': '🥅',
                'Trung vệ': '🛡️',
                'Hậu vệ cánh': '⚡',
                'Tiền vệ': '⚽',
                'Tiền đạo': '🎯'
            };
            return icons[position] || '';
        }

        function getSkillColor(skill) {
            const colors = {
                'Tốt': 'success',
                'Trung bình': 'warning',
                'Yếu': 'secondary'
            };
            return colors[skill] || 'secondary';
        }

        // Export history to Excel
        function exportHistory() {
            const matches = <?= json_encode($matches) ?>;
            
            // Create CSV content
            let csv = '\uFEFFNgày,Trạng thái,Đội A,Đội B,Tỷ số,Tổng cầu thủ,Tổng bàn thắng\n';
            
            matches.forEach(match => {
                const date = new Date(match.match_date).toLocaleDateString('vi-VN');
                const status = getStatusText(match.status);
                const score = match.status === 'completed' ? 
                    `${match.team_a_score}-${match.team_b_score}` : 'Chưa thi đấu';
                
                csv += `"${date}","${status}","${match.team_a_players}","${match.team_b_players}","${score}","${match.total_players}","${match.total_goals || 0}"\n`;
            });

            // Download CSV
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `lich_su_tran_dau_${new Date().toISOString().split('T')[0]}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function getStatusText(status) {
            const statusTexts = {
                'completed': 'Hoàn thành',
                'scheduled': 'Đã lên lịch',
                'locked': 'Đã khóa'
            };
            return statusTexts[status] || status;
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + F to focus filter
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                document.querySelector('select[name="month"]').focus();
            }
            
            // Ctrl/Cmd + H to go home
            if ((e.ctrlKey || e.metaKey) && e.key === 'h') {
                e.preventDefault();
                window.location.href = 'index.php';
            }
            
            // Ctrl/Cmd + E to export
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                if (<?= count($matches) ?> > 0) {
                    exportHistory();
                }
            }
        });

        // Auto-refresh for current month
        <?php if ($month === date('Y-m')): ?>
            setInterval(function() {
                if (document.visibilityState === 'visible') {
                    location.reload();
                }
            }, 300000); // 5 minutes
        <?php endif; ?>

        // Smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animate match cards on load
            const matchCards = document.querySelectorAll('.match-card');
            matchCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.3s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Add loading states to buttons
            document.querySelectorAll('.btn-action').forEach(btn => {
                btn.addEventListener('click', function() {
                    const originalText = this.innerHTML;
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang tải...';
                    
                    setTimeout(() => {
                        this.disabled = false;
                        this.innerHTML = originalText;
                    }, 3000);
                });
            });
        });

        // Touch/swipe support for mobile
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
            
            if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
                if (diffX > 0) {
                    // Swipe left - next page
                    <?php if ($page < $totalPages): ?>
                        window.location.href = '?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>';
                    <?php endif; ?>
                } else {
                    // Swipe right - previous page
                    <?php if ($page > 1): ?>
                        window.location.href = '?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>';
                    <?php endif; ?>
                }
            }
            
            startX = 0;
            startY = 0;
        }, { passive: true });
    </script>
</body>
</html>