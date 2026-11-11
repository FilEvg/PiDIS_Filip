<?php
require __DIR__ . '/config.php';
if (!empty($_SESSION['user_id'])) {
    log_action(db(), 'users', (int)$_SESSION['user_id'], 'logout');
}
session_destroy();
header('Location: index.php');
exit;