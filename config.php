<?php
// config.php - Cấu hình cơ sở dữ liệu và constants (FIXED)

class Database {
    private $host = 'localhost';
    private $dbname = 'football_league';
    private $username = 'root';
    private $password = '';
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Lỗi kết nối database: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}

// Singleton pattern cho database
class DB {
    private static $instance = null;
    private static $pdo = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
            self::$pdo = self::$instance->getConnection();
        }
        return self::$pdo;
    }
}

// Constants
define('LOCK_TIME', '23:59:59');
define('MATCH_START_TIME', '00:00:01');
define('MIN_PLAYERS', 4);

// ĐIỂM SỐ CHO CẦU THỦ THƯỜNG - SỬ DỤNG FLOAT ĐỂ TÍNH TOÁN CHÍNH XÁC
define('POINTS_WIN', 3.0);
define('POINTS_DRAW', 1.0);
define('POINTS_LOSE', 0.0);

// ĐIỂM SỐ CHO CẦU THỦ ĐẶC BIỆT - SỬ DỤNG FLOAT
define('SPECIAL_POINTS_WIN', 4.5);   // 3.0 x 1.5 = 4.5
define('SPECIAL_POINTS_DRAW', 1.5);  // 1.0 x 1.5 = 1.5
define('SPECIAL_POINTS_LOSE', 0.0);  // 0.0 x 1.5 = 0.0

// Test mode
define('TEST_MODE', true);
define('EDIT_MODE', true);

// Timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Helper functions
function getCurrentDate() {
    return date('Y-m-d');
}

function getCurrentTime() {
    return date('H:i:s');
}

function getCurrentDateTime() {
    return date('Y-m-d H:i:s');
}

function isRegistrationLocked($date = null) {
    return false;
}

function canUpdateMatchResult($matchDate) {
    if (defined('TEST_MODE') && TEST_MODE) {
        return true;
    }
    
    $currentDate = getCurrentDate();
    $currentTime = getCurrentTime();
    
    if ($currentDate > $matchDate) {
        return true;
    }
    
    if ($currentDate === $matchDate && $currentTime >= MATCH_START_TIME) {
        return true;
    }
    
    return false;
}

function formatPosition($position) {
    $positions = [
        'Thủ môn' => '🥅',
        'Trung vệ' => '🛡️', 
        'Hậu vệ cánh' => '⚡',
        'Tiền vệ' => '⚽',
        'Tiền đạo' => '🎯'
    ];
    
    return ($positions[$position] ?? '') . ' ' . $position;
}

function formatSkill($skill) {
    $colors = [
        'Tốt' => 'success',
        'Trung bình' => 'warning', 
        'Yếu' => 'secondary'
    ];
    
    return [
        'text' => $skill,
        'color' => $colors[$skill] ?? 'secondary'
    ];
}

// Response helper
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function errorResponse($message, $status = 400) {
    jsonResponse(['error' => $message], $status);
}

function successResponse($message, $data = null) {
    $response = ['success' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    jsonResponse($response);
}

/**
 * Tính điểm số dựa trên loại cầu thủ và kết quả trận đấu - FIXED
 * Trả về số thập phân chính xác
 */
function calculatePoints($isWin, $isDraw, $isSpecialPlayer) {
    // Đảm bảo tính toán với float để tránh làm tròn
    if ($isSpecialPlayer) {
        if ($isWin) return (float) SPECIAL_POINTS_WIN;      // 4.5
        if ($isDraw) return (float) SPECIAL_POINTS_DRAW;    // 1.5
        return (float) SPECIAL_POINTS_LOSE;                 // 0.0
    } else {
        if ($isWin) return (float) POINTS_WIN;              // 3.0
        if ($isDraw) return (float) POINTS_DRAW;            // 1.0
        return (float) POINTS_LOSE;                         // 0.0
    }
}

/**
 * Tính số trận thua
 */
function calculateLosses($totalMatches, $wins, $draws) {
    return max(0, $totalMatches - $wins - $draws);
}

/**
 * Format thống kê thắng-hòa-thua
 */
function formatWinDrawLoss($wins, $draws, $losses) {
    return "{$wins}-{$draws}-{$losses}";
}

/**
 * Tính tỷ lệ thắng phần trăm
 */
function calculateWinRate($wins, $totalMatches) {
    if ($totalMatches == 0) return 0;
    return round(($wins / $totalMatches) * 100, 1);
}

/**
 * Format hiển thị cầu thủ đặc biệt
 */
function formatSpecialPlayer($isSpecial) {
    return $isSpecial ? '⭐ Đặc biệt' : 'Thường';
}

/**
 * Get special player badge class
 */
function getSpecialPlayerBadgeClass($isSpecial) {
    return $isSpecial ? 'bg-warning text-dark' : 'bg-secondary';
}

/**
 * Format điểm số hiển thị - THÊM MỚI
 * Hiển thị số thập phân nếu cần, nguyên nếu không cần
 */
function formatPoints($points) {
    $points = (float) $points;
    
    // Nếu là số nguyên, hiển thị không có thập phân
    if ($points == intval($points)) {
        return number_format($points, 0);
    }
    
    // Nếu có thập phân, hiển thị 1 chữ số thập phân
    return number_format($points, 1);
}

/**
 * Validate điểm số - THÊM MỚI
 * Đảm bảo điểm được tính chính xác
 */
function validatePoints($points, $isSpecialPlayer, $matchResult) {
    $expectedPoints = calculatePoints(
        $matchResult['isWin'], 
        $matchResult['isDraw'], 
        $isSpecialPlayer
    );
    
    return abs($points - $expectedPoints) < 0.01; // Cho phép sai số nhỏ
}
?>