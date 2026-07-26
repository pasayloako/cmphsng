<?php
// setup.php - Create folders and test
// Run once: php setup.php

$folders = ['data', 'data/captures'];
foreach ($folders as $folder) {
    if (!file_exists($folder)) {
        mkdir($folder, 0777, true);
        echo "✅ Created: $folder\n";
    }
}

echo "✅ Setup complete!\n";
echo "📁 Data will be saved in the 'data' folder\n";
echo "🚀 Visit index.html to test\n";
?>
