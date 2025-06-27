<?php
// Set UTF-8 encoding
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once 'config.php';

$pdo = DB::getInstance();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_player':
                $name = trim($_POST['name'] ?? '');
                $mainPosition = $_POST['main_position'] ?? '';
                $secondaryPosition = $_POST['secondary_position'] ?? null;
                $mainSkill = $_POST['main_skill'] ?? '';
                $secondarySkill = $_POST['secondary_skill'] ?? null;
                
                if (empty($name) || empty($mainPosition) || empty($mainSkill)) {
                    throw new Exception('Vui lòng điền đầy đủ thông tin bắt buộc');
                }
                
                // Check if name already exists
                $stmt = $pdo->prepare("SELECT id FROM players WHERE name = ?");
                $stmt->execute([$name]);
                if ($stmt->fetch()) {
                    throw new Exception('Tên cầu thủ đã tồn tại');
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO players (name, main_position, secondary_position, main_skill, secondary_skill) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $mainPosition, $secondaryPosition ?: null, $mainSkill, $secondarySkill ?: null]);
                
                $success = "Thêm cầu thủ thành công!";
                break;
                
            case 'update_player':
                $playerId = $_POST['player_id'] ?? null;
                $name = trim($_POST['name'] ?? '');
                $mainPosition = $_POST['main_position'] ?? '';
                $secondaryPosition = $_POST['secondary_position'] ?? null;
                $mainSkill = $_POST['main_skill'] ?? '';
                $secondarySkill = $_POST['secondary_skill'] ?? null;
                
                if (!$playerId || empty($name) || empty($mainPosition) || empty($mainSkill)) {
                    throw new Exception('Vui lòng điền đầy đủ thông tin bắt buộc');
                }
                
                // Check if name already exists for other players
                $stmt = $pdo->prepare("SELECT id FROM players WHERE name = ? AND id != ?");
                $stmt->execute([$name, $playerId]);
                if ($stmt->fetch()) {
                    throw new Exception('Tên cầu thủ đã tồn tại');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE players 
                    SET name = ?, main_position = ?, secondary_position = ?, main_skill = ?, secondary_skill = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $mainPosition, $secondaryPosition ?: null, $mainSkill, $secondarySkill ?: null, $playerId]);
                
                $success = "Cập nhật thông tin cầu thủ thành công!";
                break;
                
            case 'delete_player':
                $playerId = $_POST['player_id'] ?? null;
                
                if (!$playerId) {
                    throw new Exception('ID cầu thủ không hợp lệ');
                }
                
                // Check if player is in any ongoing match
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM match_participants mp
                    JOIN daily_matches dm ON mp.match_id = dm.id
                    WHERE mp.player_id = ? AND dm.status != 'completed'
                ");
                $stmt->execute([$playerId]);
                $activeMatches = $stmt->fetchColumn();
                
                if ($activeMatches > 0) {
                    throw new Exception('Không thể xóa cầu thủ đang tham gia trận đấu');
                }
                
                $stmt = $pdo->prepare("DELETE FROM players WHERE id = ?");
                $stmt->execute([$playerId]);
                
                $success = "Xóa cầu thủ thành công!";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get filters
$positionFilter = $_GET['position'] ?? '';
$skillFilter = $_GET['skill'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$whereConditions = [];
$params = [];

if (!empty($positionFilter)) {
    $whereConditions[] = "(main_position = ? OR secondary_position = ?)";
    $params[] = $positionFilter;
    $params[] = $positionFilter;
}

if (!empty($skillFilter)) {
    $whereConditions[] = "(main_skill = ? OR secondary_skill = ?)";
    $params[] = $skillFilter;
    $params[] = $skillFilter;
}

if (!empty($search)) {
    $whereConditions[] = "name LIKE ?";
    $params[] = "%$search%";
}

$whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);

// Get players
$stmt = $pdo->prepare("
    SELECT * FROM players 
    $whereClause
    ORDER BY name ASC
");
$stmt->execute($params);
$players = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_players,
        COUNT(CASE WHEN main_skill = 'Tốt' THEN 1 END) as good_players,
        COUNT(CASE WHEN main_skill = 'Trung bình' THEN 1 END) as average_players,
        COUNT(CASE WHEN main_skill = 'Yếu' THEN 1 END) as weak_players
    FROM players
");
$stats = $stmt->fetch();

// Get position distribution
$stmt = $pdo->query("
    SELECT main_position, COUNT(*) as count 
    FROM players 
    GROUP BY main_position 
    ORDER BY count DESC
");
$positionStats = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>👥 Quản lý cầu thủ - FC Gà Gáy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Fix font rendering for Vietnamese */
        * {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
        }
        
        input, select, textarea {
            font-family: 'Times New Roman', Times, serif; !important;
            font-size: 14px !important;
            line-height: 1.5 !important;
            text-transform: none;
        }
        
        .form-control, .form-select {
            font-family: 'Times New Roman', Times, serif; !important;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-transform: none;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .card-custom {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .player-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            background: white;
            transition: all 0.3s ease;
        }
        .player-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
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
        .stats-card {
            background: linear-gradient(45deg, #4CAF50, #45a049);
            color: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .player-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(45deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2em;
        }
        .modal-lg {
            max-width: 800px;
        }
        .form-label {
            font-weight: 600;
        }
        .required {
            color: #dc3545;
        }
        .filter-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .player-stats {
            background: #e3f2fd;
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
        }
        .position-stat {
            display: inline-block;
            background: white;
            border-radius: 20px;
            padding: 8px 16px;
            margin: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Fix modal input font */
        .modal input[type="text"],
        .modal select,
        .modal textarea {
            font-family: 'Times New Roman' !important;
            font-size: 14px !important;
            line-height: 1.5 !important;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* Ensure proper Vietnamese text rendering */
        .modal-body {
            font-family: 'Times New Roman';
        }
        
        .form-control:focus,
        .form-select:focus {
            font-family: 'Times New Roman' !important;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
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
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-home"></i> Trang chủ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="register.php">
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
                        <h1 class="mb-2">👥 Quản lý cầu thủ</h1>
                        <p class="lead mb-0">Thêm, sửa, xóa và quản lý thông tin cầu thủ</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#addPlayerModal">
                            <i class="fas fa-user-plus"></i> Thêm cầu thủ mới
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Left Column: Stats & Filters -->
            <div class="col-lg-4">
                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-6">
                        <div class="stats-card">
                            <h3><?= $stats['total_players'] ?></h3>
                            <small>Tổng cầu thủ</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stats-card">
                            <h3><?= $stats['good_players'] ?></h3>
                            <small>Trình độ tốt</small>
                        </div>
                    </div>
                </div>

                <!-- Position Distribution -->
                <div class="card card-custom mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-chart-pie"></i> Phân bố vị trí
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($positionStats as $stat): ?>
                            <div class="position-stat">
                                <strong><?= formatPosition($stat['main_position']) ?></strong>
                                <span class="badge bg-primary"><?= $stat['count'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card card-custom">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-filter"></i> Bộ lọc
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="players.php">
                            <div class="mb-3">
                                <label class="form-label">Tìm kiếm tên:</label>
                                <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nhập tên cầu thủ...">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Vị trí:</label>
                                <select class="form-select" name="position">
                                    <option value="">Tất cả vị trí</option>
                                    <option value="Thủ môn" <?= $positionFilter === 'Thủ môn' ? 'selected' : '' ?>>🥅 Thủ môn</option>
                                    <option value="Trung vệ" <?= $positionFilter === 'Trung vệ' ? 'selected' : '' ?>>🛡️ Trung vệ</option>
                                    <option value="Hậu vệ cánh" <?= $positionFilter === 'Hậu vệ cánh' ? 'selected' : '' ?>>⚡ Hậu vệ cánh</option>
                                    <option value="Tiền vệ" <?= $positionFilter === 'Tiền vệ' ? 'selected' : '' ?>>⚽ Tiền vệ</option>
                                    <option value="Tiền đạo" <?= $positionFilter === 'Tiền đạo' ? 'selected' : '' ?>>🎯 Tiền đạo</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Trình độ:</label>
                                <select class="form-select" name="skill">
                                    <option value="">Tất cả trình độ</option>
                                    <option value="Tốt" <?= $skillFilter === 'Tốt' ? 'selected' : '' ?>>Tốt</option>
                                    <option value="Trung bình" <?= $skillFilter === 'Trung bình' ? 'selected' : '' ?>>Trung bình</option>
                                    <option value="Yếu" <?= $skillFilter === 'Yếu' ? 'selected' : '' ?>>Yếu</option>
                                </select>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Lọc
                                </button>
                                <a href="players.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Column: Player List -->
            <div class="col-lg-8">
                <div class="card card-custom">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list"></i> Danh sách cầu thủ (<?= count($players) ?>)
                        </h5>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary" onclick="toggleView('grid')" id="gridViewBtn">
                                <i class="fas fa-th"></i> Lưới
                            </button>
                            <button class="btn btn-sm btn-outline-primary active" onclick="toggleView('list')" id="listViewBtn">
                                <i class="fas fa-list"></i> Danh sách
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($players)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Không tìm thấy cầu thủ nào</h5>
                                <p class="text-muted">Thử thay đổi bộ lọc hoặc thêm cầu thủ mới</p>
                            </div>
                        <?php else: ?>
                            <div id="playersList">
                                <?php foreach ($players as $player): ?>
                                    <div class="player-card">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <div class="player-avatar">
                                                    <?= strtoupper(substr($player['name'], 0, 2)) ?>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <h6 class="mb-1"><?= htmlspecialchars($player['name']) ?></h6>
                                                <div class="small text-muted">
                                                    <span class="badge bg-info position-badge">
                                                        <?= formatPosition($player['main_position']) ?>
                                                    </span>
                                                    <?php if ($player['secondary_position']): ?>
                                                        <span class="badge bg-outline-info position-badge">
                                                            <?= formatPosition($player['secondary_position']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <?php $skill = formatSkill($player['main_skill']); ?>
                                                <span class="badge bg-<?= $skill['color'] ?> skill-badge">
                                                    Chính: <?= $skill['text'] ?>
                                                </span>
                                                <?php if ($player['secondary_skill']): ?>
                                                    <?php $secSkill = formatSkill($player['secondary_skill']); ?>
                                                    <br><span class="badge bg-<?= $secSkill['color'] ?> skill-badge mt-1">
                                                        Phụ: <?= $secSkill['text'] ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="player-stats small">
                                                    <div><strong><?= $player['total_points'] ?></strong> điểm</div>
                                                    <div><?= $player['total_matches'] ?> trận</div>
                                                    <div><?= $player['total_goals'] ?> bàn thắng</div>
                                                </div>
                                            </div>
                                            <div class="col-md-1">
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item" href="#" onclick="editPlayer(<?= htmlspecialchars(json_encode($player)) ?>)">
                                                                <i class="fas fa-edit"></i> Sửa
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="#" onclick="deletePlayer(<?= $player['id'] ?>, '<?= htmlspecialchars($player['name']) ?>')">
                                                                <i class="fas fa-trash"></i> Xóa
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Player Modal -->
    <div class="modal fade" id="addPlayerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus"></i> Thêm cầu thủ mới
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="players.php">
                    <input type="hidden" name="action" value="add_player">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tên cầu thủ <span class="required">*</span></label>
                                    <input type="text" class="form-control" name="name" required maxlength="100" placeholder="Nhập tên cầu thủ">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Vị trí chính <span class="required">*</span></label>
                                    <select class="form-select" name="main_position" required>
                                        <option value="">Chọn vị trí chính</option>
                                        <option value="Thủ môn">🥅 Thủ môn</option>
                                        <option value="Trung vệ">🛡️ Trung vệ</option>
                                        <option value="Hậu vệ cánh">⚡ Hậu vệ cánh</option>
                                        <option value="Tiền vệ">⚽ Tiền vệ</option>
                                        <option value="Tiền đạo">🎯 Tiền đạo</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Trình độ chính <span class="required">*</span></label>
                                    <select class="form-select" name="main_skill" required>
                                        <option value="">Chọn trình độ chính</option>
                                        <option value="Tốt">Tốt</option>
                                        <option value="Trung bình">Trung bình</option>
                                        <option value="Yếu">Yếu</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Vị trí phụ</label>
                                    <select class="form-select" name="secondary_position">
                                        <option value="">Không có vị trí phụ</option>
                                        <option value="Thủ môn">🥅 Thủ môn</option>
                                        <option value="Trung vệ">🛡️ Trung vệ</option>
                                        <option value="Hậu vệ cánh">⚡ Hậu vệ cánh</option>
                                        <option value="Tiền vệ">⚽ Tiền vệ</option>
                                        <option value="Tiền đạo">🎯 Tiền đạo</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Trình độ phụ</label>
                                    <select class="form-select" name="secondary_skill">
                                        <option value="">Không có trình độ phụ</option>
                                        <option value="Tốt">Tốt</option>
                                        <option value="Trung bình">Trung bình</option>
                                        <option value="Yếu">Yếu</option>
                                    </select>
                                </div>
                                
                                <div class="alert alert-info">
                                    <small>
                                        <i class="fas fa-info-circle"></i>
                                        Vị trí và trình độ phụ là tùy chọn. Chúng sẽ được sử dụng khi chia đội.
                                    </small>
                                </div>
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
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-edit"></i> Chỉnh sửa thông tin cầu thủ
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="players.php" id="editPlayerForm">
                    <input type="hidden" name="action" value="update_player">
                    <input type="hidden" name="player_id" id="edit_player_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tên cầu thủ <span class="required">*</span></label>
                                    <input name="name" id="edit_name" required maxlength="100">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Vị trí chính <span class="required">*</span></label>
                                    <select class="form-select" name="main_position" id="edit_main_position" required>
                                        <option value="Thủ môn">🥅 Thủ môn</option>
                                        <option value="Trung vệ">🛡️ Trung vệ</option>
                                        <option value="Hậu vệ cánh">⚡ Hậu vệ cánh</option>
                                        <option value="Tiền vệ">⚽ Tiền vệ</option>
                                        <option value="Tiền đạo">🎯 Tiền đạo</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Trình độ chính <span class="required">*</span></label>
                                    <select class="form-select" name="main_skill" id="edit_main_skill" required>
                                        <option value="Tốt">Tốt</option>
                                        <option value="Trung bình">Trung bình</option>
                                        <option value="Yếu">Yếu</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Vị trí phụ</label>
                                    <select class="form-select" name="secondary_position" id="edit_secondary_position">
                                        <option value="">Không có vị trí phụ</option>
                                        <option value="Thủ môn">🥅 Thủ môn</option>
                                        <option value="Trung vệ">🛡️ Trung vệ</option>
                                        <option value="Hậu vệ cánh">⚡ Hậu vệ cánh</option>
                                        <option value="Tiền vệ">⚽ Tiền vệ</option>
                                        <option value="Tiền đạo">🎯 Tiền đạo</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Trình độ phụ</label>
                                    <select class="form-select" name="secondary_skill" id="edit_secondary_skill">
                                        <option value="">Không có trình độ phụ</option>
                                        <option value="Tốt">Tốt</option>
                                        <option value="Trung bình">Trung bình</option>
                                        <option value="Yếu">Yếu</option>
                                    </select>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <small>
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Thay đổi thông tin cầu thủ sẽ ảnh hưởng đến các trận đấu tương lai.
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Player Statistics Display -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6>Thống kê hiện tại:</h6>
                                <div class="row text-center">
                                    <div class="col-3">
                                        <div class="bg-light p-2 rounded">
                                            <div class="h5 mb-0" id="edit_total_points">0</div>
                                            <small class="text-muted">Điểm</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="bg-light p-2 rounded">
                                            <div class="h5 mb-0" id="edit_total_matches">0</div>
                                            <small class="text-muted">Trận</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="bg-light p-2 rounded">
                                            <div class="h5 mb-0" id="edit_total_goals">0</div>
                                            <small class="text-muted">Bàn thắng</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="bg-light p-2 rounded">
                                            <div class="h5 mb-0" id="edit_total_assists">0</div>
                                            <small class="text-muted">Kiến tạo</small>
                                        </div>
                                    </div>
                                </div>
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
                    <p>Bạn có chắc chắn muốn xóa cầu thủ <strong id="delete_player_name"></strong>?</p>
                    <div class="alert alert-warning">
                        <small>
                            <i class="fas fa-info-circle"></i>
                            Hành động này không thể hoàn tác. Tất cả dữ liệu liên quan sẽ bị xóa.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <form method="POST" action="players.php" style="display: inline;">
                        <input type="hidden" name="action" value="delete_player">
                        <input type="hidden" name="player_id" id="delete_player_id">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Xóa cầu thủ
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
            document.getElementById('edit_player_id').value = player.id;
            document.getElementById('edit_name').value = player.name;
            document.getElementById('edit_main_position').value = player.main_position;
            document.getElementById('edit_main_skill').value = player.main_skill;
            document.getElementById('edit_secondary_position').value = player.secondary_position || '';
            document.getElementById('edit_secondary_skill').value = player.secondary_skill || '';
            
            // Update statistics display
            document.getElementById('edit_total_points').textContent = player.total_points;
            document.getElementById('edit_total_matches').textContent = player.total_matches;
            document.getElementById('edit_total_goals').textContent = player.total_goals;
            document.getElementById('edit_total_assists').textContent = player.total_assists;
            
            const modal = new bootstrap.Modal(document.getElementById('editPlayerModal'));
            modal.show();
        }

        // Delete player function
        function deletePlayer(playerId, playerName) {
            document.getElementById('delete_player_id').value = playerId;
            document.getElementById('delete_player_name').textContent = playerName;
            
            const modal = new bootstrap.Modal(document.getElementById('deletePlayerModal'));
            modal.show();
        }

        // Toggle view function
        function toggleView(viewType) {
            const gridBtn = document.getElementById('gridViewBtn');
            const listBtn = document.getElementById('listViewBtn');
            const playersList = document.getElementById('playersList');
            
            if (viewType === 'grid') {
                gridBtn.classList.add('active');
                listBtn.classList.remove('active');
                playersList.className = 'row';
                
                // Convert to grid view
                const playerCards = playersList.querySelectorAll('.player-card');
                playerCards.forEach(card => {
                    card.parentElement.className = 'col-md-6 col-lg-4 mb-3';
                    card.querySelector('.row').className = 'text-center';
                });
            } else {
                listBtn.classList.add('active');
                gridBtn.classList.remove('active');
                playersList.className = '';
                
                // Convert to list view
                const playerCards = playersList.querySelectorAll('.player-card');
                playerCards.forEach(card => {
                    if (card.parentElement.className.includes('col-')) {
                        const wrapper = card.parentElement;
                        wrapper.className = '';
                        card.querySelector('.text-center').className = 'row align-items-center';
                    }
                });
            }
        }

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            // Fix Vietnamese font rendering
            const inputs = document.querySelectorAll('input[type="text"], textarea');
            // inputs.forEach(input => {
            //     input.style.fontFamily = 'Times New Roman';
            //     input.style.fontSize = '14px';
            //     input.style.lineHeight = '1.5';
            // });
            
            // Validate name input - Allow Vietnamese characters
            const nameInputs = document.querySelectorAll('input[name="name"]');
            // nameInputs.forEach(input => {
            //     input.addEventListener('input', function() {
            //         // Allow Vietnamese characters, letters, spaces, parentheses
            //         this.value = this.value.replace(/[^a-zA-ZÀ-ỹ\s\(\)\-\.]/g, '');
                    
            //         // Capitalize first letter of each word
            //         this.value = this.value.replace(/\b\w/g, l => l.toUpperCase());
                    
            //         // Remove multiple spaces
            //         this.value = this.value.replace(/\s+/g, ' ');
            //     });
                
            //     // Fix font on focus
            //     input.addEventListener('focus', function() {
            //         this.style.fontFamily = 'Times New Roman';
            //         this.style.fontSize = '14px';
            //     });
            // });

            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('alert-success') || alert.classList.contains('alert-danger')) {
                    setTimeout(() => {
                        alert.style.opacity = '0';
                        setTimeout(() => alert.remove(), 300);
                    }, 5000);
                }
            });

            // Real-time search
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        if (this.value.length >= 2 || this.value.length === 0) {
                            this.form.submit();
                        }
                    }, 500);
                });
            }

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl + N to add new player
                if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                    e.preventDefault();
                    const modal = new bootstrap.Modal(document.getElementById('addPlayerModal'));
                    modal.show();
                }
                
                // Escape to close modals
                if (e.key === 'Escape') {
                    const modals = document.querySelectorAll('.modal.show');
                    modals.forEach(modal => {
                        const bsModal = bootstrap.Modal.getInstance(modal);
                        if (bsModal) bsModal.hide();
                    });
                }
            });

            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // Advanced filtering
        function advancedFilter() {
            const position = document.querySelector('select[name="position"]').value;
            const skill = document.querySelector('select[name="skill"]').value;
            const search = document.querySelector('input[name="search"]').value.toLowerCase();
            
            const playerCards = document.querySelectorAll('.player-card');
            let visibleCount = 0;
            
            playerCards.forEach(card => {
                const playerData = {
                    name: card.querySelector('h6').textContent.toLowerCase(),
                    position: card.querySelector('.position-badge').textContent,
                    skill: card.querySelector('.skill-badge').textContent
                };
                
                let visible = true;
                
                if (search && !playerData.name.includes(search)) {
                    visible = false;
                }
                
                if (position && !playerData.position.includes(position)) {
                    visible = false;
                }
                
                if (skill && !playerData.skill.includes(skill)) {
                    visible = false;
                }
                
                card.style.display = visible ? 'block' : 'none';
                if (visible) visibleCount++;
            });
            
            // Update count
            const countElement = document.querySelector('.card-header h5');
            if (countElement) {
                countElement.innerHTML = `<i class="fas fa-list"></i> Danh sách cầu thủ (${visibleCount})`;
            }
        }

        // Export functionality
        function exportPlayers() {
            const players = <?= json_encode($players) ?>;

            // Sắp xếp giảm dần theo tổng điểm
            players.sort((a, b) => b.total_points - a.total_points);
            
            // BOM để Excel hiểu UTF-8
            let csv = '\uFEFFTên,Vị trí chính,Vị trí phụ,Trình độ chính,Trình độ phụ,Tổng điểm,Tổng trận,Tổng bàn thắng,Tổng kiến tạo\n';
            
            players.forEach(player => {
                csv += `"${player.name}","${player.main_position}","${player.secondary_position || ''}","${player.main_skill}","${player.secondary_skill || ''}",${player.total_points},${player.total_matches},${player.total_goals},${player.total_assists}\n`;
            });

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `danh_sach_cau_thu_${new Date().toISOString().split('T')[0]}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Add export button to header if needed
        document.addEventListener('DOMContentLoaded', function() {
            const headerActions = document.querySelector('.col-md-4.text-end');
            if (headerActions && <?= count($players) ?> > 0) {
                const exportBtn = document.createElement('button');
                exportBtn.className = 'btn btn-outline-info me-2';
                exportBtn.innerHTML = '<i class="fas fa-download"></i> Xuất Excel';
                exportBtn.onclick = exportPlayers;
                headerActions.insertBefore(exportBtn, headerActions.firstChild);
            }
        });

        // Drag and drop functionality for future enhancement
        function enableDragDrop() {
            const playerCards = document.querySelectorAll('.player-card');
            
            playerCards.forEach(card => {
                card.draggable = true;
                
                card.addEventListener('dragstart', function(e) {
                    e.dataTransfer.setData('text/plain', card.dataset.playerId);
                    card.classList.add('dragging');
                });
                
                card.addEventListener('dragend', function() {
                    card.classList.remove('dragging');
                });
            });
        }

        // Performance optimization for large lists
        function virtualizeList() {
            const container = document.getElementById('playersList');
            const items = container.children;
            const itemHeight = 100; // Approximate height of each player card
            const containerHeight = container.clientHeight;
            const visibleItems = Math.ceil(containerHeight / itemHeight);
            
            let scrollTop = container.scrollTop;
            let startIndex = Math.floor(scrollTop / itemHeight);
            let endIndex = Math.min(startIndex + visibleItems + 1, items.length);
            
            // Hide items outside visible range for better performance
            for (let i = 0; i < items.length; i++) {
                if (i < startIndex || i > endIndex) {
                    items[i].style.display = 'none';
                } else {
                    items[i].style.display = 'block';
                }
            }
        }

        // Auto-save draft functionality
        let draftTimeout;
        function saveDraft() {
            const formData = new FormData(document.querySelector('#addPlayerModal form'));
            const draft = {};
            for (let [key, value] of formData.entries()) {
                if (value.trim()) draft[key] = value;
            }
            
            if (Object.keys(draft).length > 1) { // More than just action
                localStorage.setItem('player_draft', JSON.stringify(draft));
            }
        }

        function loadDraft() {
            const draft = localStorage.getItem('player_draft');
            if (draft) {
                const data = JSON.parse(draft);
                Object.keys(data).forEach(key => {
                    const input = document.querySelector(`#addPlayerModal [name="${key}"]`);
                    if (input && key !== 'action') {
                        input.value = data[key];
                    }
                });
            }
        }

        // Initialize draft functionality
        document.addEventListener('DOMContentLoaded', function() {
            const addPlayerInputs = document.querySelectorAll('#addPlayerModal input, #addPlayerModal select');
            addPlayerInputs.forEach(input => {
                input.addEventListener('input', function() {
                    clearTimeout(draftTimeout);
                    draftTimeout = setTimeout(saveDraft, 1000);
                });
            });

            // Load draft when modal opens
            document.getElementById('addPlayerModal').addEventListener('show.bs.modal', loadDraft);

            // Clear draft when successfully submitted
            const form = document.querySelector('#addPlayerModal form');
            form.addEventListener('submit', function() {
                localStorage.removeItem('player_draft');
            });
        });
    </script>
</body>
</html>