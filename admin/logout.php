<?php
require_once __DIR__ . '/inc/bootstrap.php';
Auth::logout();
header('Location: login.php');
exit;
