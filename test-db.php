<?php
require 'includes/db.php';

echo "Connected DB: ";
echo $pdo->query("SELECT DATABASE()")->fetchColumn();
