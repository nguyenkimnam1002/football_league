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
define('LOCK_TIME', '22:30:00');
define('MATCH_START_TIME', '07:00:00');
define('MIN_PLAYERS', 14);
define('POINTS_WIN', 3);
define('POINTS_LOSE', 0);

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
    if ($date === null) {
        $date = getCurrentDate();
    }
    
    $currentTime = getCurrentTime();
    $lockTime = LOCK_TIME;
    
    // Nếu hiện tại đã qua 22:30 thì khóa đăng ký cho ngày hôm nay
    return $currentTime >= $lockTime;
}

function canUpdateMatchResult($matchDate) {
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
?>