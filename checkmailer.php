<?php
// ── Place this file in SAM_system/ then visit: localhost/SAM_system/checkmailer.php
// ── DELETE this file after fixing the issue

$base = dirname(__FILE__);

$paths = [
    $base . '/phpmailer/PHPMailer.php',
    $base . '/phpmailer/SMTP.php',
    $base . '/phpmailer/Exception.php',
];

echo "<h2>SAM_system base folder:</h2>";
echo "<code>" . $base . "</code>";
echo "<hr>";
echo "<h2>PHPMailer file check:</h2>";

foreach ($paths as $path) {
    if (file_exists($path)) {
        echo "✅ FOUND: <code>$path</code><br><br>";
    } else {
        echo "❌ NOT FOUND: <code>$path</code><br><br>";
    }
}

echo "<hr>";
echo "<h2>Contents of SAM_system folder:</h2><pre>";
foreach (scandir($base) as $item) {
    if ($item === '.' || $item === '..') continue;
    $type = is_dir($base . '/' . $item) ? '[FOLDER]' : '[FILE]  ';
    echo "$type $item\n";
}
echo "</pre>";

echo "<h2>Contents of phpmailer/ folder (if exists):</h2><pre>";
$pm = $base . '/phpmailer';
if (is_dir($pm)) {
    foreach (scandir($pm) as $item) {
        if ($item === '.' || $item === '..') continue;
        echo $item . "\n";
    }
} else {
    echo "❌ phpmailer/ folder does NOT exist inside " . $base;
}
echo "</pre>";
?>