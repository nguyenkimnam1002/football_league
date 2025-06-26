<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = DB::getInstance();
    $currentDate = getCurrentDate();
    
    // Get current registration info
    $matchInfo = getNextMatchInfo();
    $registrationStatus = getRegistrationStatus();
    
    // Check if there are any fresh registrations (new match available)
    $isFreshRegistration = $matchInfo['is_fresh_registration'] ?? false;
    
    // Get current registered count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM daily_registrations 
        WHERE registration_date = ?
    ");
    $stmt->execute([$matchInfo['next_match_date']]);
    $currentRegistered = $stmt->fetch()['count'];
    
    // Get session stored count to compare
    session_start();
    $lastKnownCount = $_SESSION['last_registered_count'] ?? -1;
    $lastKnownStatus = $_SESSION['last_registration_status'] ?? '';
    
    // Determine if reload is needed
    $shouldReload = false;
    
    // Case 1: Registration status changed (from locked to open or vice versa)
    if ($lastKnownStatus !== $registrationStatus['status_text']) {
        $shouldReload = true;
    }
    
    // Case 2: New registration period started (count reset to 0)
    if ($lastKnownCount > 0 && $currentRegistered === 0) {
        $shouldReload = true;
    }
    
    // Case 3: Significant change in registered players count
    if (abs($currentRegistered - $lastKnownCount) >= 5) {
        $shouldReload = true;
    }
    
    // Update session with current values
    $_SESSION['last_registered_count'] = $currentRegistered;
    $_SESSION['last_registration_status'] = $registrationStatus['status_text'];
    
    echo json_encode([
        'should_reload' => $shouldReload,
        'current_registered' => $currentRegistered,
        'can_register' => $registrationStatus['can_register'],
        'match_number' => $matchInfo['next_match_number'] ?? 1,
        'is_fresh' => $isFreshRegistration
    ]);

} catch (Exception $e) {
    // On error, suggest reload
    echo json_encode([
        'should_reload' => true,
        'error' => $e->getMessage()
    ]);
}
?>