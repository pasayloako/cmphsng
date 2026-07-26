<?php
// ============================================
// LOCATION.PHP - Handle location data
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    $timestamp = date('Y-m-d H:i:s');
    
    // Get JSON data
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        // Also check for form data
        $input = $_POST;
    }
    
    // Log location
    $logEntry = "[$timestamp] IP: $ip | ";
    $logEntry .= "Lat: " . ($input['lat'] ?? 'N/A') . " | ";
    $logEntry .= "Lng: " . ($input['lng'] ?? 'N/A') . " | ";
    $logEntry .= "Accuracy: " . ($input['accuracy'] ?? 'N/A') . " | ";
    $logEntry .= "UA: $userAgent\n";
    
    file_put_contents('locations.log', $logEntry, FILE_APPEND);
    
    // Also save as JSON
    $locationData = [
        'timestamp' => $timestamp,
        'ip' => $ip,
        'user_agent' => $userAgent,
        'location' => $input
    ];
    
    $jsonFile = 'locations/' . date('Y-m-d') . '.json';
    if (!file_exists('locations')) {
        mkdir('locations', 0777, true);
    }
    
    $existing = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];
    $existing[] = $locationData;
    file_put_contents($jsonFile, json_encode($existing, JSON_PRETTY_PRINT));
    
    // Response
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok']);
} else {
    http_response_code(405);
    echo 'Method not allowed';
}
?>
