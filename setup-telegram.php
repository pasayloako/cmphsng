<?php
// setup_telegram.php - Test Telegram connection
// Run this once to verify your bot works

echo "🔧 Telegram Bot Setup Test\n";
echo "===========================\n\n";

// Check config
if (!file_exists('telegram_config.json')) {
    echo "❌ telegram_config.json not found!\n";
    echo "📝 Create it with:\n";
    echo '{"bot_token": "YOUR_BOT_TOKEN", "chat_id": "YOUR_CHAT_ID"}' . "\n";
    exit;
}

$config = json_decode(file_get_contents('telegram_config.json'), true);
if (empty($config['bot_token']) || empty($config['chat_id'])) {
    echo "❌ Invalid config! Both bot_token and chat_id are required.\n";
    exit;
}

echo "✅ Config loaded\n";
echo "🤖 Bot Token: " . substr($config['bot_token'], 0, 10) . "...\n";
echo "📱 Chat ID: " . $config['chat_id'] . "\n\n";

// Send test message
$message = "✅ <b>TELEGRAM BOT WORKING!</b>\n\n";
$message .= "🔧 Your bot is configured correctly.\n";
$message .= "📡 All data will be sent here.\n";
$message .= "⏰ Time: " . date('Y-m-d H:i:s') . "\n\n";
$message .= "📊 <i>Ready to capture data</i>";

$url = "https://api.telegram.org/bot" . $config['bot_token'] . "/sendMessage";
$data = [
    'chat_id' => $config['chat_id'],
    'text' => $message,
    'parse_mode' => 'HTML'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    echo "✅ Test message sent successfully!\n";
    echo "📱 Check your Telegram bot: t.me/" . $config['bot_username'] ?? 'your_bot' . "\n";
    echo "\n🎯 Your bot is ready to receive data!";
} else {
    echo "❌ Error sending test message\n";
    echo "HTTP Code: $httpCode\n";
    echo "Response: $result\n";
    echo "\nPossible issues:\n";
    echo "1. Bot token is invalid\n";
    echo "2. Chat ID is wrong\n";
    echo "3. Bot was not started (@BotFather)\n";
}
?>
