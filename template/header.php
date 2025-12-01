<?php
if (empty($sin_sidebar)) {
    include 'navbar.php';
}
require_once "../connection/connection.php";

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
    
    <link rel="icon" type="image/png" href="../assets/img/inverclinik_3.png">
    <link rel="shortcut icon" type="image/png" href="../assets/img/inverclinik_3.png">
    <link rel="apple-touch-icon" href="../assets/img/inverclinik_3.png">
    
    <link rel="stylesheet" href="../assets/css/bootstrap.css">
    <link rel="stylesheet" href="../assets/css/font-awesome.css">
    <link rel="stylesheet" href="../assets/css/custom.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/reportes.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/navbar.css">
    
    <script src="../assets/js/jquery-3.7.1.min.js"></script>

</head>