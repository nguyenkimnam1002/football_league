<?php
require_once 'config.php';

// Simple CSV generation
function generateCSV($matchData) {
    $filename = 'doi-hinh-fc-ga-gay-' . date('d-m-Y', strtotime($matchData['match']['match_date'])) . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header
    fputcsv($output, ['ĐỘI HÌNH FC GÀ GÁY - ' . date('d/m/Y', strtotime($matchData['match']['match_date']))]);
    fputcsv($output, []);
    
    if ($matchData['match']['status'] === 'completed') {
        fputcsv($output, ['Kết quả: ' . $matchData['match']['team_a_score'] . ' - ' . $matchData['match']['team_b_score']]);
        fputcsv($output, []);
    }
    
    // Team headers
    fputcsv($output, ['ĐỘI A (' . count($matchData['teamA_flat']) . ' người)', '', '', '', 'ĐỘI B (' . count($matchData['teamB_flat']) . ' người)']);
    fputcsv($output, ['Tên', 'Vị trí', 'Kỹ năng', 'Số trận', 'Tên', 'Vị trí', 'Kỹ năng', 'Số trận']);
    
    // Players data
    $maxRows = max(count($matchData['teamA_flat']), count($matchData['teamB_flat']));
    
    for ($i = 0; $i < $maxRows; $i++) {
        $row = [];
        
        // Team A player
        if (isset($matchData['teamA_flat'][$i])) {
            $player = $matchData['teamA_flat'][$i];
            $row[] = $player['name'];
            $row[] = $player['assigned_position'];
            $row[] = $player['skill_level'];
            $row[] = $player['total_matches'] ?? 0;
        } else {
            $row[] = $row[] = $row[] = $row[] = '';
        }
        
        // Team B player
        if (isset($matchData['teamB_flat'][$i])) {
            $player = $matchData['teamB_flat'][$i];
            $row[] = $player['name'];
            $row[] = $player['assigned_position'];
            $row[] = $player['skill_level'];
            $row[] = $player['total_matches'] ?? 0;
        } else {
            $row[] = $row[] = $row[] = $row[] = '';
        }
        
        fputcsv($output, $row);
    }
    
    // Statistics
    fputcsv($output, []);
    fputcsv($output, ['THỐNG KÊ']);
    
    $teamAStats = calculateTeamStats($matchData['teamA_flat']);
    $teamBStats = calculateTeamStats($matchData['teamB_flat']);
    
    fputcsv($output, ['Sức mạnh:', $teamAStats['strength'], '', '', 'Sức mạnh:', $teamBStats['strength']]);
    fputcsv($output, ['Tốt:', $teamAStats['good'], '', '', 'Tốt:', $teamBStats['good']]);
    fputcsv($output, ['Trung bình:', $teamAStats['average'], '', '', 'Trung bình:', $teamBStats['average']]);
    fputcsv($output, ['Yếu:', $teamAStats['weak'], '', '', 'Yếu:', $teamBStats['weak']]);
    
    fclose($output);
    exit;
}

// Generate simple HTML table for PDF printing
function generatePrintableHTML($matchData) {
    $teamAStats = calculateTeamStats($matchData['teamA_flat']);
    $teamBStats = calculateTeamStats($matchData['teamB_flat']);
    
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đội hình FC Gà Gáy</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 15px; 
            background: white;
            color: black;
        }
        .header { 
            text-align: center; 
            margin-bottom: 20px; 
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .match-info { 
            text-align: center; 
            margin-bottom: 20px; 
            font-size: 14px;
        }
        .teams-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .teams-table th, .teams-table td {
            border: 1px solid #333;
            padding: 8px;
            text-align: left;
        }
        .team-header {
            font-weight: bold;
            text-align: center;
        }
        /* Header đội A - đồng màu với nội dung */
        .team-a-header { 
            background-color: #ffebee !important; 
            color: #b71c1c !important;
        }
        /* Header đội B - đồng màu với nội dung */
        .team-b-header { 
            background-color: #e3f2fd !important; 
            color: #0d47a1 !important;
        }
        .player-name { font-weight: bold; }
        
        /* Highlight team rows */
        .teams-table tbody tr:hover {
            background-color: #f5f5f5;
        }
        
        /* Team A cells - màu giống bảng thống kê */
        .teams-table tbody td:nth-child(1),
        .teams-table tbody td:nth-child(2),
        .teams-table tbody td:nth-child(3),
        .teams-table tbody td:nth-child(4) {
            background-color: #ffebee !important;
            color: #b71c1c !important;
        }
        
        /* Team B cells - màu giống bảng thống kê */
        .teams-table tbody td:nth-child(5),
        .teams-table tbody td:nth-child(6),
        .teams-table tbody td:nth-child(7),
        .teams-table tbody td:nth-child(8) {
            background-color: #e3f2fd !important;
            color: #0d47a1 !important;
        }
        
        /* Làm đậm tên cầu thủ */
        .teams-table tbody td:nth-child(1),
        .teams-table tbody td:nth-child(5) {
            font-weight: bold;
        }
        .stats-section { 
            margin-top: 20px; 
            border-top: 2px solid #333;
            padding-top: 15px;
        }
        .stats-table {
            width: 100%;
            border-collapse: collapse;
        }
        .stats-table th, .stats-table td {
            border: 1px solid #333;
            padding: 6px;
            text-align: center;
        }
        .stats-table th {
            background-color: #e0e0e0;
            font-weight: bold;
        }
        /* Màu đội A trong bảng thống kê */
        .stats-table td:nth-child(2) {
            background-color: #ffebee !important;
            color: #b71c1c !important;
            font-weight: bold;
        }
        /* Màu đội B trong bảng thống kê */
        .stats-table td:nth-child(3) {
            background-color: #e3f2fd !important;
            color: #0d47a1 !important;
            font-weight: bold;
        }
        .print-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 10px;
        }
        .no-print { 
            display: block; 
        }
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
            .header { border-bottom: 2px solid #000; }
            .stats-section { border-top: 2px solid #000; }
        }
        @media (max-width: 768px) {
            body { margin: 5px; font-size: 11px; }
            .teams-table th, .teams-table td { padding: 4px; font-size: 11px; }
            .stats-table th, .stats-table td { padding: 3px; font-size: 10px; }
            .header h1 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center; margin-bottom: 15px;">
        <button class="print-btn" onclick="window.print()">🖨️ In đội hình</button>
        <button class="print-btn" onclick="window.close()">❌ Đóng</button>
    </div>

    <div class="header">
        <h1>⚽ ĐỘI HÌNH FC GÀ GÁY</h1>
    </div>
    
    <div class="match-info">
        <p><strong>📅 Ngày thi đấu:</strong> ' . date('d/m/Y', strtotime($matchData['match']['match_date'])) . '</p>';
        
    if ($matchData['match']['status'] === 'completed') {
        $winner = $matchData['match']['team_a_score'] > $matchData['match']['team_b_score'] ? 'Đội A thắng' : 
                 ($matchData['match']['team_b_score'] > $matchData['match']['team_a_score'] ? 'Đội B thắng' : 'Hòa');
        $html .= '<p><strong>🏆 Kết quả:</strong> ' . $matchData['match']['team_a_score'] . ' - ' . $matchData['match']['team_b_score'] . ' (' . $winner . ')</p>';
    }
    
    $html .= '</div>
    
    <table class="teams-table">
        <thead>
            <tr>
                <th class="team-header team-a-header" colspan="4">🔴 ĐỘI A (' . count($matchData['teamA_flat']) . ' người)</th>
                <th class="team-header team-b-header" colspan="4">🔵 ĐỘI B (' . count($matchData['teamB_flat']) . ' người)</th>
            </tr>
            <tr>
                <th>Tên cầu thủ</th>
                <th>Vị trí</th>
                <th>Kỹ năng</th>
                <th>Số trận</th>
                <th>Tên cầu thủ</th>
                <th>Vị trí</th>
                <th>Kỹ năng</th>
                <th>Số trận</th>
            </tr>
        </thead>
        <tbody>';
    
    // Add players data
    $maxRows = max(count($matchData['teamA_flat']), count($matchData['teamB_flat']));
    
    for ($i = 0; $i < $maxRows; $i++) {
        $html .= '<tr>';
        
        // Team A player
        if (isset($matchData['teamA_flat'][$i])) {
            $player = $matchData['teamA_flat'][$i];
            $html .= '<td class="player-name">' . htmlspecialchars($player['name']) . '</td>';
            $html .= '<td>' . $player['assigned_position'] . '</td>';
            $html .= '<td>' . $player['skill_level'] . '</td>';
            $html .= '<td>' . ($player['total_matches'] ?? 0) . '</td>';
        } else {
            $html .= '<td></td><td></td><td></td><td></td>';
        }
        
        // Team B player
        if (isset($matchData['teamB_flat'][$i])) {
            $player = $matchData['teamB_flat'][$i];
            $html .= '<td class="player-name">' . htmlspecialchars($player['name']) . '</td>';
            $html .= '<td>' . $player['assigned_position'] . '</td>';
            $html .= '<td>' . $player['skill_level'] . '</td>';
            $html .= '<td>' . ($player['total_matches'] ?? 0) . '</td>';
        } else {
            $html .= '<td></td><td></td><td></td><td></td>';
        }
        
        $html .= '</tr>';
    }
    
    $html .= '</tbody>
    </table>
    
    <div class="stats-section">
        <h3>📊 Thống kê đội hình</h3>
        <table class="stats-table">
            <thead>
                <tr>
                    <th>Chỉ số</th>
                    <th>Đội A</th>
                    <th>Đội B</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Sức mạnh tổng</strong></td>
                    <td>' . $teamAStats['strength'] . '</td>
                    <td>' . $teamBStats['strength'] . '</td>
                </tr>
                <tr>
                    <td>Kỹ năng Tốt</td>
                    <td>' . $teamAStats['good'] . '</td>
                    <td>' . $teamBStats['good'] . '</td>
                </tr>
                <tr>
                    <td>Kỹ năng Trung bình</td>
                    <td>' . $teamAStats['average'] . '</td>
                    <td>' . $teamBStats['average'] . '</td>
                </tr>
                <tr>
                    <td>Kỹ năng Yếu</td>
                    <td>' . $teamAStats['weak'] . '</td>
                    <td>' . $teamBStats['weak'] . '</td>
                </tr>
            </tbody>
        </table>
        
        <div style="margin-top: 15px; font-size: 12px; text-align: center; color: #666;">
            📱 Gợi ý: Để in tốt nhất, hãy chọn "Landscape" (ngang) trong tùy chọn in
        </div>
    </div>
    
    <script>
        // Auto focus for better mobile experience
        window.onload = function() {
            if (window.innerWidth <= 768) {
                document.body.style.fontSize = "11px";
            }
        }
        
        // Print shortcut
        document.addEventListener("keydown", function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === "p") {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>';
    
    return $html;
}

// Calculate team statistics
function calculateTeamStats($players) {
    $stats = ['strength' => 0, 'good' => 0, 'average' => 0, 'weak' => 0];
    
    foreach ($players as $player) {
        switch ($player['skill_level']) {
            case 'Tốt':
                $stats['strength'] += 3;
                $stats['good']++;
                break;
            case 'Trung bình':
                $stats['strength'] += 2;
                $stats['average']++;
                break;
            case 'Yếu':
                $stats['strength'] += 1;
                $stats['weak']++;
                break;
        }
    }
    
    return $stats;
}

// Main execution
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $matchId = $_GET['match_id'] ?? null;
    $format = $_GET['format'] ?? 'html'; // html, csv
    
    if (!$matchId) {
        die('<h1>Lỗi</h1><p>Thiếu Match ID. Vui lòng quay lại trang quản lý kết quả.</p>');
    }
    
    try {
        $pdo = DB::getInstance();
        
        // Get match data
        $stmt = $pdo->prepare("SELECT * FROM daily_matches WHERE id = ?");
        $stmt->execute([$matchId]);
        $match = $stmt->fetch();
        
        if (!$match) {
            die('<h1>Lỗi</h1><p>Không tìm thấy trận đấu. Match ID: ' . htmlspecialchars($matchId) . '</p>');
        }
        
        // Get participants
        $stmt = $pdo->prepare("
            SELECT mp.*, p.name, p.main_position, p.total_matches
            FROM match_participants mp 
            JOIN players p ON mp.player_id = p.id 
            WHERE mp.match_id = ?
            ORDER BY mp.team, mp.assigned_position, p.name
        ");
        $stmt->execute([$matchId]);
        $participants = $stmt->fetchAll();
        
        if (empty($participants)) {
            die('<h1>Lỗi</h1><p>Trận đấu này chưa có cầu thủ nào tham gia.</p>');
        }
        
        // Separate teams into flat arrays
        $teamA_flat = [];
        $teamB_flat = [];
        
        foreach ($participants as $participant) {
            if ($participant['team'] === 'A') {
                $teamA_flat[] = $participant;
            } else {
                $teamB_flat[] = $participant;
            }
        }
        
        $matchData = [
            'match' => $match,
            'teamA_flat' => $teamA_flat,
            'teamB_flat' => $teamB_flat
        ];
        
        // Generate based on format
        switch ($format) {
            case 'csv':
                generateCSV($matchData);
                break;
            case 'html':
            default:
                echo generatePrintableHTML($matchData);
                break;
        }
        
    } catch (Exception $e) {
        die('<h1>Lỗi hệ thống</h1><p>Chi tiết: ' . htmlspecialchars($e->getMessage()) . '</p>');
    }
    
} else {
    http_response_code(405);
    echo '<h1>Method Not Allowed</h1><p>Chỉ chấp nhận GET request.</p>';
}
?>