<?php
require_once __DIR__ . '/connect.php';
echo "APP_URL: " . APP_URL . "<br>";
echo "Form action should be: " . APP_URL . "/workspace-setup.php<br>";
echo "<br>";
echo "File exists: " . (file_exists(__DIR__ . '/workspace-setup.php') ? 'YES' : 'NO');
?>
