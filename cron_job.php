<?php
/**
 * Cron Job Script - Football League
 * 
 * Chạy mỗi ngày vào 22h30 để khóa đăng ký và tạo đội hình tự động
 * 
 * Cài đặt cron job:
 * 30 22 * * * /usr/bin/php /path/to/your/project/cron_job.php
 */

require_once 'config.php';
require_once 'TeamDivision.php';

// Chỉ cho phép chạy từ command line hoặc localhost
if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== 'localhost') {
    die('Access denied. This script can only be run from command line or localhost.');
}

$pdo = DB::getInstance();
$currentDate = getCurrentDate();
$currentTime = getCurrentTime();

echo "=== FOOTBALL LEAGUE CRON JOB ===\n";
echo "Date: {$currentDate}\n";
echo "Time: {$currentTime}\n";
echo "================================\n\n";

try {
    // 1. Check if registration should be locked
    if ($currentTime >= LOCK_TIME) {
        echo "🔒 Locking registration for {$currentDate}...\n";
        
        // Get today's registrations
        $stmt = $pdo->prepare("
            SELECT p.* 
            FROM players p 
            JOIN daily_registrations dr ON p.id = dr.player_id 
            WHERE dr.registration_date = ?
            ORDER BY dr.registered_at ASC
        ");
        $stmt->execute([$currentDate]);
        $registeredPlayers = $stmt->fetchAll();
        
        echo "📊 Registered players: " . count($registeredPlayers) . "\n";
        
        if (count($registeredPlayers) >= MIN_PLAYERS) {
            // Automatically divide teams
            echo "⚽ Auto-dividing teams...\n";
            
            $teamDivision = new TeamDivision();
            $result = $teamDivision->divideTeams($registeredPlayers);
            
            // Save formation and lock
            $matchId = $teamDivision->saveMatchFormation($currentDate, $result['teamA'], $result['teamB']);
            
            // Update match status
            $stmt = $pdo->prepare("
                UPDATE daily_matches 
                SET status = 'locked', locked_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$matchId]);
            
            echo "✅ Teams divided and locked successfully!\n";
            echo "   Team A: {$result['stats']['totalA']} players\n";
            echo "   Team B: {$result['stats']['totalB']} players\n";
            
            // Send notification (if you have email/SMS service)
            sendNotification($currentDate, $result['stats']);
            
        } else {
            echo "❌ Not enough players (" . count($registeredPlayers) . "/" . MIN_PLAYERS . ") - Match cancelled\n";
            
            // Create cancelled match record
            $stmt = $pdo->prepare("
                INSERT INTO daily_matches (match_date, team_a_formation, team_b_formation, status) 
                VALUES (?, '{}', '{}', 'cancelled')
                ON DUPLICATE KEY UPDATE status = 'cancelled'
            ");
            $stmt->execute([$currentDate]);
        }
    } else {
        echo "⏰ Registration still open until " . LOCK_TIME . "\n";
    }
    
    // 2. Cleanup old registrations (optional - keep for 30 days)
    $cleanupDate = date('Y-m-d', strtotime('-30 days'));
    $stmt = $pdo->prepare("
        DELETE FROM daily_registrations 
        WHERE registration_date < ?
    ");
    $stmt->execute([$cleanupDate]);
    $deletedRegs = $stmt->rowCount();
    
    if ($deletedRegs > 0) {
        echo "🧹 Cleaned up {$deletedRegs} old registrations\n";
    }
    
    // 3. Update monthly statistics
    updateMonthlyStatistics();
    
    // 4. Check for matches that need result updates
    checkPendingMatches();
    
    echo "\n✅ Cron job completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    error_log("Football League Cron Job Error: " . $e->getMessage());
}

/**
 * Send notification about match status
 */
function sendNotification($date, $stats) {
    // This is where you'd integrate with your notification service
    // Examples: Email, SMS, Slack webhook, etc.
    
    $message = "🏆 Trận đấu ngày " . date('d/m/Y', strtotime($date)) . " đã được tạo!\n";
    $message .= "Đội A: {$stats['totalA']} người\n";
    $message .= "Đội B: {$stats['totalB']} người\n";
    $message .= "Giờ thi đấu: " . MATCH_START_TIME . " sáng\n";
    
    // Example: Log to file (replace with actual notification service)
    file_put_contents('notifications.log', date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
    
    echo "📱 Notification sent\n";
}

/**
 * Update monthly statistics for all players
 */
function updateMonthlyStatistics() {
    global $pdo;
    
    $currentMonth = date('Y-m');
    
    echo "📈 Updating monthly statistics for {$currentMonth}...\n";
    
    // Aggregate monthly stats from match participants
    $stmt = $pdo->prepare("
        INSERT INTO player_stats (player_id, month, matches_played, wins, goals, assists, points)
        SELECT 
            mp.player_id,
            ? as month,
            COUNT(*) as matches_played,
            SUM(CASE WHEN mp.points_earned > 0 THEN 1 ELSE 0 END) as wins,
            SUM(mp.goals) as goals,
            SUM(mp.assists) as assists,
            SUM(mp.points_earned) as points
        FROM match_participants mp 
        JOIN daily_matches dm ON mp.match_id = dm.id 
        WHERE dm.match_date >= ? AND dm.match_date < ? AND dm.status = 'completed'
        GROUP BY mp.player_id
        ON DUPLICATE KEY UPDATE
        matches_played = VALUES(matches_played),
        wins = VALUES(wins),
        goals = VALUES(goals),
        assists = VALUES(assists),
        points = VALUES(points)
    ");
    
    $monthStart = $currentMonth . '-01';
    $nextMonth = date('Y-m-01', strtotime($monthStart . ' +1 month'));
    
    $stmt->execute([$currentMonth, $monthStart, $nextMonth]);
    
    echo "✅ Monthly statistics updated\n";
}

/**
 * Check for matches that are ready for result input
 */
function checkPendingMatches() {
    global $pdo;
    
    $currentDate = getCurrentDate();
    $currentTime = getCurrentTime();
    
    // Find matches from yesterday that are locked but not completed
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    $stmt = $pdo->prepare("
        SELECT * FROM daily_matches 
        WHERE match_date = ? AND status = 'locked'
    ");
    $stmt->execute([$yesterday]);
    $pendingMatches = $stmt->fetchAll();
    
    if (!empty($pendingMatches)) {
        echo "⏳ Found " . count($pendingMatches) . " matches pending result input\n";
        
        foreach ($pendingMatches as $match) {
            echo "   - Match {$match['id']} ({$match['match_date']}) - waiting for results\n";
        }
        
        // Optionally send reminder notification
        if (count($pendingMatches) > 0) {
            $reminderMessage = "⚠️ Có " . count($pendingMatches) . " trận đấu chưa cập nhật kết quả!\n";
            file_put_contents('notifications.log', date('Y-m-d H:i:s') . " - " . $reminderMessage . "\n", FILE_APPEND);
        }
    }
}

/**
 * Backup database (weekly)
 */
function backupDatabase() {
    if (date('w') == 0) { // Sunday
        $backupFile = 'backup_' . date('Y-m-d') . '.sql';
        
        // This is a simple example - use proper backup tools in production
        $command = "mysqldump -u root -p football_league > backups/{$backupFile}";
        
        echo "💾 Creating weekly backup: {$backupFile}\n";
        // exec($command); // Uncomment if you want to enable backup
    }
}

/**
 * Generate weekly report (Monday)
 */
function generateWeeklyReport() {
    global $pdo;
    
    if (date('w') == 1) { // Monday
        echo "📊 Generating weekly report...\n";
        
        $weekStart = date('Y-m-d', strtotime('last monday', strtotime('-1 day')));
        $weekEnd = date('Y-m-d', strtotime('sunday', strtotime($weekStart)));
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT dm.id) as matches,
                COUNT(DISTINCT mp.player_id) as players,
                SUM(mp.goals) as total_goals,
                SUM(mp.assists) as total_assists
            FROM daily_matches dm 
            LEFT JOIN match_participants mp ON dm.id = mp.match_id
            WHERE dm.match_date BETWEEN ? AND ? AND dm.status = 'completed'
        ");
        $stmt->execute([$weekStart, $weekEnd]);
        $weekStats = $stmt->fetch();
        
        $report = "📈 WEEKLY REPORT ({$weekStart} to {$weekEnd})\n";
        $report .= "Matches played: " . ($weekStats['matches'] ?? 0) . "\n";
        $report .= "Players participated: " . ($weekStats['players'] ?? 0) . "\n";
        $report .= "Total goals: " . ($weekStats['total_goals'] ?? 0) . "\n";
        $report .= "Total assists: " . ($weekStats['total_assists'] ?? 0) . "\n";
        
        file_put_contents('weekly_reports.log', date('Y-m-d H:i:s') . "\n" . $report . "\n\n", FILE_APPEND);
        
        echo "✅ Weekly report generated\n";
    }
}

// Run additional tasks
backupDatabase();
generateWeeklyReport();

echo "\n=== CRON JOB FINISHED ===\n";
?>