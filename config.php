<?php
// config.php - Cấu hình cơ sở dữ liệu và constants

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
define('LOCK_TIME', '23:59:59'); // Tạm thời đổi thành 23:59 để test
define('MATCH_START_TIME', '00:00:01'); // Tạm thời đổi thành 00:00 để test
define('MIN_PLAYERS', 4); // Giảm xuống 4 để test dễ hơn
define('POINTS_WIN', 3);
define('POINTS_LOSE', 0);

// Test mode - Bỏ comment dòng dưới để enable test mode
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
    // Luôn cho phép đăng ký - không bao giờ khóa
    return false;
    
    // Code cũ bị comment để tham khảo:
    // if (defined('TEST_MODE') && TEST_MODE) {
    //     return false;
    // }
    // 
    // if ($date === null) {
    //     $date = getCurrentDate();
    // }
    // 
    // $currentTime = getCurrentTime();
    // $lockTime = LOCK_TIME;
    // 
    // return $currentTime >= $lockTime;
}

function canUpdateMatchResult($matchDate) {
    // Nếu đang ở test mode, luôn cho phép update
    if (defined('TEST_MODE') && TEST_MODE) {
        return true;
    }
    
    $currentDate = getCurrentDate();
    $currentTime = getCurrentTime();
    
    // Chỉ có thể cập nhật kết quả sau 7h sáng ngày hôm sau
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
?>