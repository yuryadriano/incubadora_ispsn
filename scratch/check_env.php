<?php
echo "DB_HOST: " . (getenv('DB_HOST') ?: 'not set (default: 127.0.0.1)') . "\n";
echo "DB_USER: " . (getenv('DB_USER') ?: 'not set (default: root)') . "\n";
echo "DB_NAME: " . (getenv('DB_NAME') ?: 'not set (default: imcubadora_ispsn)') . "\n";
echo "DB_PASS: " . (getenv('DB_PASS') ? 'is set' : 'not set (default: empty)') . "\n";
?>
