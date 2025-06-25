<?php
/**
 * Simple API Test
 */

echo "<h2>🧪 Simple API Test</h2>";

// Test 1: Check if files exist
echo "<h3>File Check:</h3>";
echo "<p>config.php: " . (file_exists('config.php') ? '✅ Exists' : '❌ Missing') . "</p>";
echo "<p>api.php: " . (file_exists('api.php') ? '✅ Exists' : '❌ Missing') . "</p>";
echo "<p>TeamDivision.php: " . (file_exists('TeamDivision.php') ? '✅ Exists' : '❌ Missing') . "</p>";

// Test 2: Check syntax
echo "<h3>Syntax Check:</h3>";
$syntaxCheck = shell_exec("php -l api.php 2>&1");
echo "<pre>" . htmlspecialchars($syntaxCheck) . "</pre>";

// Test 3: Direct API call
echo "<h3>Direct API Test:</h3>";

if (isset($_POST['test'])) {
    echo "<h4>Testing Register Player...</h4>";
    
    // Prepare POST data
    $postData = json_encode([
        'action' => 'register_player',
        'player_id' => 1,
        'date' => date('Y-m-d')
    ]);
    
    // Create context
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $postData
        ]
    ]);
    
    // Make request
    $response = file_get_contents('http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/api.php', false, $context);
    
    echo "<p><strong>Response:</strong></p>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    // Try to decode JSON
    $decoded = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<p style='color: green;'>✅ Valid JSON response</p>";
        echo "<pre>" . print_r($decoded, true) . "</pre>";
    } else {
        echo "<p style='color: red;'>❌ Invalid JSON: " . json_last_error_msg() . "</p>";
    }
} else {
    echo "<form method='post'>";
    echo "<button type='submit' name='test' value='1'>Test API Call</button>";
    echo "</form>";
}

// Test 4: Check database connection
echo "<h3>Database Test:</h3>";
try {
    require_once 'config.php';
    $pdo = DB::getInstance();
    echo "<p style='color: green;'>✅ Database connection OK</p>";
    
    // Check if we have players
    $stmt = $pdo->query("SELECT COUNT(*) FROM players");
    $playerCount = $stmt->fetchColumn();
    echo "<p>Players in database: {$playerCount}</p>";
    
    if ($playerCount > 0) {
        $stmt = $pdo->query("SELECT id, name FROM players LIMIT 3");
        $players = $stmt->fetchAll();
        echo "<p>Sample players:</p><ul>";
        foreach ($players as $player) {
            echo "<li>ID: {$player['id']} - {$player['name']}</li>";
        }
        echo "</ul>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>← Back to Main</a></p>";
?>