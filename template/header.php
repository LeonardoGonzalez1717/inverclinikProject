<?php
if (!defined('ROOT_PATH')) {
    $levels = 3;
    $path = __DIR__;
    for ($i = 0; $i < $levels; $i++) {
        if (file_exists($path . '/config.php')) {
            require_once $path . '/config.php';
            break;
        }
        $path = dirname($path);
    }
}
require_once ROOT_PATH . '/connection/connection.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INVERCLINIK</title>

    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/css/custom.css" rel="stylesheet" />

    <script src="assets/js/jquery-3.7.1.min.js"></script>
    
</head>