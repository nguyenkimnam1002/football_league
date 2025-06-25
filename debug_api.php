<?php
/**
 * Debug API endpoints
 * Truy cập file này để test các API endpoints
 */

require_once 'config.php';

echo "<h2>🔧 API Debug Tool</h2>";

// Test database connection
try {
    $pdo = DB::getInstance();
    echo "<p style='color: green;'>✅ Database connection: OK</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}

// Get current status
$currentDate = getCurrentDate();
echo "<p><strong>Current Date:</strong> {$currentDate}</p>";
echo "<p><strong>Test Mode:</strong> " . (defined('TEST_MODE') && TEST_MODE ? 'Enabled' : 'Disabled') . "</p>";
echo "<p><strong>Registration Locked:</strong> " . (isRegistrationLocked() ? 'Yes' : 'No') . "</p>";

// Get players count
$stmt = $pdo->query("SELECT COUNT(*) FROM players");
$playersCount = $stmt->fetchColumn();
echo "<p><strong>Total Players:</strong> {$playersCount}</p>";

// Get today's registrations
$stmt = $pdo->prepare("SELECT COUNT(*) FROM daily_registrations WHERE registration_date = ?");
$stmt->execute([$currentDate]);
$regCount = $stmt->fetchColumn();
echo "<p><strong>Today's Registrations:</strong> {$regCount}</p>";

if (!isset($_POST['test_api'])) {
?>
<hr>
<h3>Test API Endpoints</h3>

<form method="post">
    <h4>1. Test Register Player</h4>
    <select name="player_id">
        <option value="">-- Chọn cầu thủ --</option>
        <?php
        $stmt = $pdo->query("SELECT id, name FROM players LIMIT 10");
        $players = $stmt->fetchAll();
        foreach ($players as $player) {
            echo "<option value='{$player['id']}'>{$player['name']}</option>";
        }
        ?>
    </select>
    <br><br>
    
    <button type="submit" name="test_api" value="register">Test Register Player</button>
    <button type="submit" name="test_api" value="unregister">Test Unregister Player</button>
    <button type="submit" name="test_api" value="divide">Test Divide Teams</button>
    <button type="submit" name="test_api" value="check_json">Test JSON Response</button>
</form>

<hr>
<h3>Manual API Test</h3>
<p>You can also test API manually using these URLs:</p>
<ul>
    <li><a href="api.php?action=get_player_stats" target="_blank">GET Player Stats</a></li>
    <li><a href="api.php?action=get_match_details&match_id=1" target="_blank">GET Match Details</a></li>
</ul>

<?php
} else {
    $testType = $_POST['test_api'];
    $playerId = $_POST['player_id'] ?? 1;
    
    echo "<hr><h3>API Test Results</h3>";
    
    switch ($testType) {
        case 'register':
            testRegisterPlayer($playerId, $currentDate);
            break;
            
        case 'unregister':
            testUnregisterPlayer($playerId, $currentDate);
            break;
            
        case 'divide':
            testDivideTeams($currentDate);
            break;
            
        case 'check_json':
            testJsonResponse();
            break;
    }
    
    echo "<p><a href='debug_api.php'>← Back to debug menu</a></p>";
}

function testRegisterPlayer($playerId, $date) {
    $data = [
        'action' => 'register_player',
        'player_id' => $playerId,
        'date' => $date
    ];
    
    $result = callAPI('POST', 'api.php', $data);
    echo "<h4>Register Player Result:</h4>";
    echo "<pre>" . htmlspecialchars($result) . "</pre>";
}

function testUnregisterPlayer($playerId, $date) {
    $data = [
        'action' => 'unregister_player',
        'player_id' => $playerId,
        'date' => $date
    ];
    
    $result = callAPI('POST', 'api.php', $data);
    echo "<h4>Unregister Player Result:</h4>";
    echo "<pre>" . htmlspecialchars($result) . "</pre>";
}

function testDivideTeams($date) {
    // First register some players
    global $pdo;
    $stmt = $pdo->query("SELECT id FROM players LIMIT 8");
    $players = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Clear existing registrations
    $pdo->prepare("DELETE FROM daily_registrations WHERE registration_date = ?")->execute([$date]);
    
    // Register players
    $regStmt = $pdo->prepare("INSERT INTO daily_registrations (player_id, registration_date) VALUES (?, ?)");
    foreach ($players as $playerId) {
        $regStmt->execute([$playerId, $date]);
    }
    
    echo "<p>Registered " . count($players) . " players for testing</p>";
    
    $data = [
        'action' => 'divide_teams',
        'date' => $date,
        'preview' => true
    ];
    
    $result = callAPI('POST', 'api.php', $data);
    echo "<h4>Divide Teams Result:</h4>";
    echo "<pre>" . htmlspecialchars($result) . "</pre>";
}

function testJsonResponse() {
    echo "<h4>Testing JSON Response Format:</h4>";
    
    // Test simple JSON
    $testData = ['test' => 'success', 'message' => 'JSON working'];
    $json = json_encode($testData, JSON_UNESCAPED_UNICODE);
    echo "<p><strong>JSON Encode Test:</strong></p>";
    echo "<pre>" . htmlspecialchars($json) . "</pre>";
    
    // Test API endpoint
    $result = callAPI('GET', 'api.php?action=get_player_stats');
    echo "<p><strong>API GET Test:</strong></p>";
    echo "<pre>" . htmlspecialchars($result) . "</pre>";
}

function callAPI($method, $url, $data = null) {
    $curl = curl_init();
    
    switch ($method) {
        case "POST":
            curl_setopt($curl, CURLOPT_POST, 1);
            if ($data) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($curl, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen(json_encode($data))
                ]);
            }
            break;
        case "PUT":
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
            if ($data) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            }
            break;
        default:
            if ($data) {
                $url = sprintf("%s?%s", $url, http_build_query($data));
            }
    }
    
    // Get the current domain
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['REQUEST_URI']);
    $baseUrl = $protocol . $host . $path . '/';
    
    curl_setopt($curl, CURLOPT_URL, $baseUrl . $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    if (curl_errno($curl)) {
        $result = 'Curl error: ' . curl_error($curl);
    } else {
        $result = "HTTP {$httpCode}: " . $result;
    }
    
    curl_close($curl);
    return $result;
}

echo "<hr>";
echo "<h3>📊 Database Tables Status</h3>";

$tables = ['players', 'daily_registrations', 'daily_matches', 'match_participants', 'player_stats'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM {$table}");
        $count = $stmt->fetchColumn();
        echo "<p><strong>{$table}:</strong> {$count} records</p>";
    } catch (Exception $e) {
        echo "<p><strong>{$table}:</strong> <span style='color: red;'>Error - {$e->getMessage()}</span></p>";
    }
}

echo "<hr>";
echo "<p><a href='index.php'>🏠 Back to Main</a> | <a href='test_data.php'>🧪 Test Data Generator</a></p>";
?>